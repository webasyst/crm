<?php

class crmFileListMethod extends crmApiAbstractMethod
{
    protected $contact_id;
    protected $userpic_size;

    const PREVIEW_SIZE = 512;

    public function execute()
    {
        $this->validateParams();

        $deal_ids = [];
        if ($this->contact_id > 0) {
            $deal_ids = array_keys($this->getDealParticipantsModel()->getByField([
                'contact_id' => $this->contact_id,
                'role_id' => crmDealParticipantsModel::ROLE_CLIENT
            ], 'deal_id'));
        }

        $file_list = $this->getFiles($deal_ids);
        if (empty($file_list)) {
            $this->response = [];
            return;
        }

        $contact_ids = array_unique(array_column($file_list, 'creator_contact_id'));
        $contact_list = $this->getContacts($contact_ids);
        $deals = $this->getDeals($deal_ids);

        $this->response = $this->prepareFileList($file_list, $contact_list, $deals);
    }

    protected function validateParams()
    {
        $contact_id = (int) $this->get('contact_id');
        $deal_id = (int) $this->get('deal_id');
        $userpic_size = (int) $this->get('userpic_size');
        $this->userpic_size = ifempty($userpic_size, self::USERPIC_SIZE);

        if (empty($contact_id) && empty($deal_id)) {
            throw new waAPIException('empty_id', sprintf_wp('Missing required parameter: %s.', sprintf_wp('“%s” or “%s”', 'contact_id', 'deal_id')), 400);
        } elseif (!empty($contact_id) && !empty($deal_id)) {
            throw new waAPIException('error', sprintf_wp('Only one of the parameters is required: %s.', sprintf_wp('“%s” or “%s”', 'contact_id', 'deal_id')), 400);
        } elseif (!$this->getCrmRights()->contactOrDeal($this->getUser()->getId())) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } elseif (!empty($deal_id)) {
            $contact_id = ($deal_id < 0 ?: $deal_id * -1);
        }
        $this->contact_id = $contact_id;
    }

    protected function getFiles($deal_ids)
    {
        $condition_ids = array_map(function($deal_id) {
            return $deal_id * -1;
        }, $deal_ids);
        $condition_ids[] = $this->contact_id;

        $files = $this->getFileModel()->getByField(['contact_id' => $condition_ids], true);
        array_multisort(array_column($files, 'id'), SORT_NUMERIC, SORT_DESC, $files);
        return $files;
    }

    protected function getContacts($contact_ids)
    {
        if (empty($contact_ids)) {
            return [];
        }
        $contact_list = [];
        $contacts = $this->getContactsMicrolist($contact_ids, ['id', 'name', 'userpic'], $this->userpic_size);
        foreach ($contacts as $_contact) {
            $contact_list[$_contact['id']] = $_contact;
        }
        return $contact_list;
    }

    private function getDeals($deal_ids)
    {
        if (empty($deal_ids)) {
            return [];
        }

        $deals = $this->getDealModel()->getList([
            'id' => $deal_ids,
            'check_rights' => true,
        ]);

        if (empty($deals)) {
            return [];
        }

        $funnels = $this->getFunnelModel()->getById(array_column($deals, 'funnel_id'));
        $funnels_with_stages = $this->getFunnelStageModel()->withStages($funnels);

        $result = [];
        foreach ($deals as $deal) {
            $result[$deal['id']] = [
                'id'          => (int) $deal['id'],
                'name'        => $deal['name'],
                'status_id'   => $deal['status_id'],
                'amount'      => (float) $deal['amount'],
                'currency_id' => $deal['currency_id'],
            ];
            foreach ($funnels_with_stages as $_funn_with_st) {
                if ($_funn_with_st['id'] == $deal['funnel_id']) {
                    $result[$deal['id']]['funnel'] = [
                        'id'    => (int) $_funn_with_st['id'],
                        'name'  => $_funn_with_st['name'],
                        'color' => $_funn_with_st['color']
                    ];
                    foreach ($_funn_with_st['stages'] as $_stage) {
                        if ($_stage['id'] == $deal['stage_id']) {
                            $result[$deal['id']]['stage'] = [
                                'id'    => (int) $_stage['id'],
                                'name'  => $_stage['name'],
                                'color' => $_stage['color']
                            ];
                            break;
                        }
                    }
                    break;
                }
            }
        }

        return $result;
    }

    protected function prepareFileList($file_list, $contact_list, $deals)
    {
        if (empty($file_list)) {
            return [];
        }
        $thumb_size = waRequest::get('thumb_size', self::THUMB_SIZE, waRequest::TYPE_INT);
        $preview_size = waRequest::get('preview_size', self::PREVIEW_SIZE, waRequest::TYPE_INT);
        $host_backend = rtrim(wa()->getConfig()->getHostUrl(), '/').wa()->getConfig()->getBackendUrl(true);
        $img_ext = ['jpg', 'jpeg', 'png', 'gif'];
        if (class_exists('Imagick')) {
            $img_ext[] = 'pdf';
        }
        $file_list = array_map(function ($_file) use ($contact_list, $deals, $host_backend, $thumb_size, $preview_size, $img_ext) {
            //$_file['url'] = $host_backend.'crm/?module=file&action=download&id='.$_file['id'];
            $_file['url'] = wa()->getConfig()->getBackendUrl(true).'crm/?module=file&action=download&id='.$_file['id'];
            if (isset($contact_list[$_file['creator_contact_id']])) {
                $_file['creator'] = $contact_list[$_file['creator_contact_id']];
            }
            if ($_file['contact_id'] < 0 && isset($deals[abs($_file['contact_id'])])) {
                $_file['deal'] = $deals[abs($_file['contact_id'])];
            }
            if (in_array($_file['ext'], $img_ext)) {
                //$_file['thumb_url'] = $host_backend.'crm/?module=file&action=download&id='.$_file['id'].'&thumb='.$thumb_size;
                $_file['thumb_url'] = wa()->getConfig()->getBackendUrl(true).'crm/?module=file&action=download&id='.$_file['id'].'&thumb='.$thumb_size;
                $_file['preview_url'] = wa()->getConfig()->getBackendUrl(true).'crm/?module=file&action=download&id='.$_file['id'].'&thumb='.$preview_size;
            }
            return $_file;
        }, $file_list);

        return $this->filterData(
            $file_list,
            [
                'id',
                'name',
                'create_datetime',
                'size',
                'ext',
                'comment',
                'url',
                'thumb_url',
                'preview_url',
                'creator',
                'source_type',
                'deal',
            ], [
                'id' => 'integer',
                'create_datetime' => 'datetime',
                'size' => 'integer'
            ]
        );
    }
}
