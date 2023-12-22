<?php

class crmContactDeleteMethod extends crmApiAbstractMethod
{
    protected $method = self::METHOD_DELETE;

    public function execute()
    {
        $contact_ids = (array) $this->get('id', true);

        $contact_ids = crmHelper::dropNotPositive($contact_ids);
        if (empty($contact_ids)) {
            throw new waAPIException('empty_id', sprintf_wp('Missing required parameter: “%s”.', 'id'), 400);
        }

        $operation = new crmContactOperationDelete(['contacts' => $contact_ids]);
        $result = $operation->execute();

        if (!class_exists('waLogModel')) {
            wa('webasyst');
        }
        $log_model = new waLogModel();
        $log_model->add('contact_delete', $result['log_params']);

        $this->response = [
            'count'   => ifset($result, 'count', null),
            'message' => sprintf(
                _w("%d contact has been deleted", "%d contacts have been deleted", $result['count']),
                $result['count']
            )
        ];
    }
}
