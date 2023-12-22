<?php

class crmFileListMethod extends crmApiAbstractMethod
{
    protected $contact_id;
    protected $userpic_size;

    public function execute()
    {
        $this->validateParams();
        $file_list = $this->getFiles();
        if (empty($file_list)) {
            $this->response = [];
            return;
        }

        $contact_ids = array_unique(array_column($file_list, 'creator_contact_id'));
        $contact_list = $this->getContacts($contact_ids);

        $this->response = $this->prepareFileList($file_list, $contact_list);
    }

    protected function validateParams()
    {
        $contact_id = (int) $this->get('contact_id');
        $deal_id = (int) $this->get('deal_id');
        $userpic_size = (int) $this->get('userpic_size');
        $this->userpic_size = ifempty($userpic_size, self::USERPIC_SIZE);

        if (empty($contact_id) && empty($deal_id)) {
            throw new waAPIException('empty_id', sprintf_wp('Missing required parameter: “%s”.', sprintf_wp('“%s” or “%s”', 'contact_id', 'deal_id')), 400);
        } elseif (!empty($contact_id) && !empty($deal_id)) {
            throw new waAPIException('error', sprintf_wp('Only one of the parameters is required: %s.', sprintf_wp('“%s” or “%s”', 'contact_id', 'deal_id')), 400);
        } elseif (!$this->getCrmRights()->contactOrDeal($this->getUser()->getId())) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        } elseif (!empty($deal_id)) {
            $contact_id = ($deal_id < 0 ?: $deal_id * -1);
        }
        $this->contact_id = $contact_id;
    }

    protected function getFiles()
    {
        return $this->getFileModel()
            ->select('*')
            ->where('contact_id = ?', $this->contact_id)
            ->order('create_datetime DESC')
            ->fetchAll();
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

    protected function prepareFileList($file_list, $contact_list)
    {
        if (empty($file_list)) {
            return [];
        }
        $host_backend = rtrim(wa()->getConfig()->getHostUrl(), '/').wa()->getConfig()->getBackendUrl(true);
        $file_list = array_map(function ($_file) use ($contact_list, $host_backend) {
            $_file['url'] = $host_backend.'crm/?module=file&action=download&id='.$_file['id'];
            $_file['creator'] = ifset($contact_list, $_file['creator_contact_id'], []);
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
                'creator'
            ], [
                'id' => 'integer',
                'create_datetime' => 'datetime',
                'size' => 'integer'
            ]
        );
    }
}
