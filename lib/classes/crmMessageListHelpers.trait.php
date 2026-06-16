<?php

trait crmMessageListHelpersTrait
{
    use crmBaseHelpersTrait;

    protected $active_sources = null;

    protected function getDeals(array $deal_ids)
    {
        if (!$deal_ids) {
            return array();
        }
        return $this->getDealModel()->getByField(['id' => array_unique($deal_ids)], 'id');
    }

    protected function getFunnels(array $deals)
    {
        $funnels = array();
        foreach ($deals as $d) {
            if ($d['funnel_id'] && empty($funnels[$d['funnel_id']])) {
                $funnel = $this->getFunnelModel()->getById($d['funnel_id']);
                $funnel['stages'] = $this->getFunnelStageModel()->getStagesByFunnel($funnel);
                $funnels[$d['funnel_id']] = $funnel;
            }
        }
        return $funnels;
    }

    protected function getActiveSources()
    {
        if ($this->active_sources !== null) {
            return $this->active_sources;
        }

        $this->active_sources = (new crmSourceModel)->getByField([
            'type' => [crmSourceModel::TYPE_EMAIL, crmSourceModel::TYPE_IM],
            'disabled' => 0
        ], 'id');

        $this->active_sources = array_map(function ($el) {
            $el['source'] = crmSource::factory($el);

            $el['icon_color'] = '#BB64FF';
            if ($el['type'] === crmSourceModel::TYPE_IM) {
                $el['icon_url'] = $el['source']->getIcon();
                $fa_icon = $el['source']->getFontAwesomeBrandIcon();
                if (ifset($fa_icon['icon_fab'])) {
                    $el['icon_fab'] = $fa_icon['icon_fab'];
                    $el['icon_color'] = $fa_icon['icon_color'];
                }
            } elseif ($el['type'] === crmSourceModel::TYPE_EMAIL) {
                $el['icon_fa'] = 'envelope';
                $fa_icon = $el['source']->getFontAwesomeIcon();
                if (ifset($fa_icon['icon_fa'])) {
                    $el['icon_fa'] = $fa_icon['icon_fa'];
                    $el['icon_color'] = $fa_icon['icon_color'];
                }
            }

            return $el;
        }, $this->active_sources);

        return $this->active_sources;
    }

    /**
     * @return array
     * @throws waException
     */
    protected function getContacts(array $ids)
    {
        $contacts = array();
        if ($ids) {
            $ids = array_unique($ids);
            $collection = new waContactsCollection('/id/'.join(',', $ids));
            $col = $collection->getContacts(wa('crm')->getConfig()->getContactFields(), 0, count($ids));
            foreach ($col as $id => $c) {
                $contacts[$id] = new waContact($c);
                $contacts[$id]['is_visible'] = $this->getCrmRights()->contact($c);
                if ($id == wa()->getUser()->getId()) {
                    $me = $contacts[$id];
                }
            }
        }
        if (isset($me)) {
            unset($contacts[wa()->getUser()->getId()]);
            $contacts = array(wa()->getUser()->getId() => $me) + $contacts;
        }
        return $contacts;
    }

    /**
     * Has access to file by users
     * @return bool
     */
    protected function hasAccessToUsersFilter()
    {
        $crm_rights = $this->getCrmRights();
        return $crm_rights->getConversationsRights() >= crmRightConfig::RIGHT_CONVERSATION_ALL;
    }

    protected function getFilterItemsByTransport()
    {
        return array(
            "all"   => array(
                "id"   => "all",
                "name" => _w("Any transports")
            ),
            "email" => array(
                "id"   => "email",
                "name" => _w("Email")
            ),
            "im"    => array(
                "id"   => "im",
                "name" => _w("Messengers")
            ),
        );
    }

}