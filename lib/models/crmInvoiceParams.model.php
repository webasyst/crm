<?php

class crmInvoiceParamsModel extends crmParamsModel
{
    protected $table = 'crm_invoice_params';
    protected $external_id = 'invoice_id';

    /**
     * @deprecated
     * This method not-deprecated up to version 1.1.0
     * use $this->get() instead
     * @param $id
     * @return array|mixed
     */
    public function getParams($id)
    {
        return $this->get($id);
    }
}
