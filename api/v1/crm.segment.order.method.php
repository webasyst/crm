<?php

class crmSegmentOrderMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_POST;

    private $scope;
    private $ids;

    public function execute()
    {
        $this->getData();
        $this->validateData();
        $this->checkRights();

        $sm = $this->getSegmentModel();
        $segments = $sm->getById($this->ids);
        $ids = array_filter($this->ids, function ($id) use ($segments) {
            $segment = ifset($segments[$id], null);
            return
                !empty($segment) &&
                boolval($segment['shared']) == ($this->scope == 'shared') &&
                (boolval($segment['shared']) || $segment['contact_id'] == wa()->getUser()->getId());
        });

        $sort = 0;
        try {
            foreach ($ids as $id) {
                $sm->updateById($id, ['sort' => $sort++]);
            }
        } catch (waDbException $db_exception) {
            throw new waAPIException('error_db', $db_exception->getMessage(), 500);
        }

        $this->http_status_code = 204;
        $this->response = null;
    }

    private function getData()
    {
        $body_json  = $this->readBodyAsJson();
        $this->scope = trim(ifset($body_json, 'scope', null));
        $this->ids = ifset($body_json, 'ids', null);
    }

    private function validateData()
    {
        if (empty($this->scope)) {
            throw new waAPIException('empty_param', sprintf_wp('Missing required parameter: “%s”.', 'scope'), 400);
        }
        if (!in_array($this->scope, ['shared', 'my'])) {
            throw new waAPIException('invalid_param', sprintf_wp('Invalid “%s” value.', 'scope'), 400);
        }
        if (empty($this->ids)) {
            throw new waAPIException('empty_param', sprintf_wp('Missing required parameter: “%s”.', 'ids'), 400);
        }
        if (!is_array($this->ids)) {
            throw new waAPIException('invalid_param', _w('Invalid “ids” value — must be an array.'), 400);
        }
        $this->ids = array_map('intval', $this->ids);
        if (empty($this->ids)) {
            throw new waAPIException('invalid_param', _w('Invalid “ids” value — must be an array of integers.'), 400);
        }
    }

    private function checkRights()
    {
        $is_admin = wa()->getUser()->isAdmin($this->getAppId());
        if ($this->scope == 'shared' && !$is_admin) {
            throw new waAPIException('forbidden', _w('Access denied'), 403);
        }
    }
}
