<?php

/**
 * Empty editor to create new invoice.
 */
class crmInvoiceNewAction extends crmInvoiceViewAction
{
    public function execute()
    {
        $contact_id = waRequest::get('contact', null, waRequest::TYPE_INT);
        $deal_id = waRequest::request('deal_id', null, waRequest::TYPE_INT);
        $iframe =  waRequest::get('iframe', 0, waRequest::TYPE_INT);
        if (!empty($iframe) && wa('crm')->whichUI('crm') !== '1.3') {
            $this->setLayout();
        }

        if (!wa()->getUser()->getRights('crm', 'manage_invoices')) {
            throw new waRightsException();
        }

        $im = new crmInvoiceModel();
        $invoice = $im->getEmptyInvoice();
        $invoice['number'] = 1 + (int)$im->select('MAX(id) mid')->fetchField('mid');

        $cm = new crmCompanyModel();
        $companies = $cm->getAll('id');

        $company = reset($companies);
        if (!empty($company['tax_options'])) {
            $company['tax_options'] = json_decode($company['tax_options'], true);
        }

        $curm = new crmCurrencyModel();
        $currencies = $curm->getAll('code');
        $contact = null;
        $deal = null;
        if ($deal_id) {
            $dm = new crmDealModel();
            $deal = $dm->getById($deal_id);
            if (!$deal || !$this->getCrmRights()->deal($deal)) {
                $this->accessDenied();
            }
            if ($deal['contact_id']) {
                $contact = new waContact($deal['contact_id']);
                if ($contact['company_contact_id']) {
                    $contact = new waContact($contact['company_contact_id']);
                }
            }
            $invoice['deal_id'] = $deal_id;

            if (!empty($deal['external_id']) && preg_match('/^shop:(\d+)$/', $deal['external_id'], $m)) {
                try {
                    wa('shop');
                    $som = new shopOrderModel();
                    $shop_order = $som->getOrder($m[1]);

                    $customer_delivery = shopHelper::getOrderCustomerDeliveryTime(ifset($shop_order['params']));
                    $shop_order['customer_delivery_date'] = $customer_delivery[0];

                    shopHelper::workupOrders($shop_order, true);

                    $invoice['items'] = array();

                    foreach ($shop_order['items'] as $item) {
                        if ($item['quantity'] > 0) {
                            $invoice['items'][] = array(
                                'name' => $item['name'],
                                'price' => $item['price'] - ($item['total_discount'] / $item['quantity']),
                                'quantity' => $item['quantity'],
                                'product_id' => 'shop:' . $item['product_id'] . ':' . $item['sku_id'],
                            );
                        }
                    }
                    if (floatval($shop_order['shipping'])) {
                        $invoice['items'][] = array(
                            'name'       => _w('Shipping'),
                            'price'      => $shop_order['shipping'],
                            'quantity'   => 1,
                            'product_id' => 'shop:shipping',
                        );
                    }
                    $invoice['amount'] = $shop_order['total'];
                    $invoice['currency_id'] = $shop_order['currency'];
                } catch (waException $e) {
                }
            }

        } elseif ($contact_id) {
            $contact = new waContact($contact_id);
        }
        $invoice['currency_id'] = $invoice['currency_id'] ? $invoice['currency_id'] : $this->getConfig()->getCurrency();

        $this->view->assign(array(
            'iframe'                => $iframe,
            'invoice'               => $invoice,
            'companies'             => $companies,
            'company'               => $company,
            'currencies'            => $currencies,
            'contact'               => $contact,
            'deal'                  => $deal,
            'funnel'                => empty($deal['funnel_id']) ? null : $this->getFunnelModel()->getById($deal['funnel_id']),
            'shop_supported'        => crmConfig::isShopSupported() && crmShop::hasRights(),
            'shop_autocomplete_url' => wa()->getAppUrl('shop').'?action=autocomplete&with_counts=1',
            'shop_get_product_url'  => wa()->getAppUrl('shop').'?module=orders&action=getProduct',
            'has_shop_rights'       => crmShop::hasRights(),
            'site_url'             => wa()->getRootUrl(true),
        ));

        wa('crm')->getConfig()->setLastVisitedUrl('invoice/');
    }
}
