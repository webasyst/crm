<?php

class crmDealParticipantsModel extends crmModel
{
    const ROLE_USER = 'USER';
    const ROLE_CLIENT = 'CLIENT';

    protected $table = 'crm_deal_participants';

    public function getRoles()
    {
        return array(self::ROLE_CLIENT, self::ROLE_USER);
    }

    public function exists($deal_id, $contact_id, $role_id = self::ROLE_CLIENT)
    {
        return !!$this->getByField(array('deal_id' => $deal_id, 'contact_id' => $contact_id, 'role_id' => $role_id));
    }

    /**
     * Get deal(s) participants
     * @param int|int[] $deal_id
     * @param bool $grouped - group by deal id, default is FALSE
     * @return array
     * @throws waException
     */
    public function getParticipants($deal_id, $grouped = false)
    {
        $result = $this->getByField('deal_id', $deal_id, true);
        if (!$grouped) {
            return $result;
        }
        $participants = [];
        foreach ($result as $item) {
            $id = $item['deal_id'];
            $participants[$id][] = $item;
        }
        return $participants;
    }

    public function insert($data, $type = 0)
    {
        $data['create_datetime'] = !empty($data['create_datetime']) ? $data['create_datetime'] : date('Y-m-d H:i:s');
        return parent::insert($data, $type);
    }

    public function multipleInsert($data)
    {
        if (!$data) {
            return true;
        }

        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $item) {
            if (!is_array($item)) {
                return false;
            }
        }

        foreach ($data as &$item) {
            $item['create_datetime'] = !empty($item['create_datetime']) ? $item['create_datetime'] : date('Y-m-d H:i:s');
        }
        unset($item);

        return parent::multipleInsert($data);
    }

    public function replace($data)
    {
        if (!$data) {
            return true;
        }
        if (!is_array($data)) {
            return false;
        }
        $data['create_datetime'] = !empty($data['create_datetime']) ? $data['create_datetime'] : date('Y-m-d H:i:s');
        return parent::replace($data);
    }

    /**
     * @param $deal_id
     * @return array
     * @throws waException
     */
    public function getDealClients($deal_id)
    {
        $deal_ids = crmHelper::toIntArray($deal_id);
        $deal_ids = crmHelper::dropNotPositive($deal_ids);
        if (!$deal_ids) {
            return array();
        }

        $deals_clients = $this->getByField(array(
            'deal_id' => $deal_ids,
            'role_id' => crmDealParticipantsModel::ROLE_CLIENT
        ), true);

        // groups by deal_id and contact_id
        $clients = array_fill_keys($deal_ids, array());
        foreach ($deals_clients as $client) {
            $clients[$client['deal_id']][$client['contact_id']] = $client;
        }

        if (is_scalar($deal_id)) {
            return $clients[$deal_id];
        } else {
            return $clients;
        }
    }
}
