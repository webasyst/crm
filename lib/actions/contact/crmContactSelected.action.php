<?php

class crmContactSelectedAction extends crmContactsAction
{
    protected $id;

    protected function afterExecute()
    {
        $method = 'get';
        if (waRequest::post()) {
            $method = 'post';
        }

        $this->view->assign(array(
            'title'  => _w('Selected contacts'),
            'method' => $method,
        ));
    }

    protected function getHash()
    {
        $selected_ids = json_decode(waRequest::post('selected_ids', null));
        $selected_ids = $selected_ids ? $selected_ids : array(-1);
        return 'id/' . implode(',', $selected_ids);
    }

    protected function getLimit()
    {
        return 100500;
    }
}