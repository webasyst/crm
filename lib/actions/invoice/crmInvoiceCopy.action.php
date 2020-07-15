<?php

/**
 * Class crmInvoiceCopyAction
 * Copying an invoice without id and date
 */
class crmInvoiceCopyAction extends crmInvoiceIdAction
{
    public function execute()
    {
        parent::execute();

        //Change id and date
        $im = new crmInvoiceModel();
        $this->invoice['number'] = 1 + (int)$im->select('MAX(id) mid')->fetchField('mid');
        $this->invoice['invoice_date'] = '';
        $this->invoice['id'] = '';

        $this->view->assign(array(
            'invoice' =>  $this->invoice,
            'shop_supported'        => crmConfig::isShopSupported() && crmShop::hasRights(),
            'shop_autocomplete_url' => wa()->getAppUrl('shop').'?action=autocomplete&with_counts=1',
            'shop_get_product_url'  => wa()->getAppUrl('shop').'?module=orders&action=getProduct',
            'has_shop_rights'       => crmShop::hasRights(),
            'invoice_template' => 'templates/actions/invoice/InvoiceEdit.html'
        ));

    }
}
