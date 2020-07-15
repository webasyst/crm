<?php

class crmContactColumnsActions extends waJsonActions
{
    public function saveAction()
    {
        $data = (array)$this->getRequest()->post('column');

        $columns = crmContact::getCurrentContactColumns();
        foreach ($columns as $full_column_id => &$column) {
            if (!isset($data[$full_column_id])) {
                $column['off'] = 1;
            } else {
                $column['sort'] = $data[$full_column_id];
                $column['off'] = 0;
                unset($data[$full_column_id]);
            }
        }
        unset($column);

        foreach ($data as $full_column_id => $sort) {
            $columns[$full_column_id] = array('sort' => $sort);
        }

        crmContact::setCurrentContactColumns($columns);
    }

    public function saveWidthAction()
    {
        $width = (string) $this->getRequest()->post('width');
        $full_column_id = $this->getRequest()->post('full_column_id');

        $columns = crmContact::getCurrentContactColumns();
        if (isset($columns[$full_column_id])) {
            $columns[$full_column_id]['width'] = $width;
        } else {
            $columns[$full_column_id] = array('width' => $width, 'off' => 0);
        }

        crmContact::setCurrentContactColumns($columns);
    }
}
