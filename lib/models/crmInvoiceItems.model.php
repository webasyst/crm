<?php

class crmInvoiceItemsModel extends crmModel
{
    protected $table = 'crm_invoice_items';

    public function getItems($id)
    {
        return $this->select('*')->where("invoice_id = ".intval($id))->order('id')->fetchAll();
    }
}
