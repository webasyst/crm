<?php

/**
 * HTML for invoice editor page.
 */
class crmInvoiceEditAction extends crmInvoiceIdAction
{
    public function execute()
    {
        $iframe = waRequest::request('iframe', 0, waRequest::TYPE_INT);
        if (!empty($iframe) && wa('crm')->whichUI('crm') !== '1.3') {
            $this->setLayout();
        }
        parent::execute();
        if ($this->invoice['state_id'] != 'DRAFT' && $this->invoice['state_id'] != 'PENDING') {
            throw new waRightsException();
        }
        $shop_supported = crmConfig::isShopSupported();
        $supported_currencies = array();
        if ($shop_supported) {
            wa('shop');
            $scm = new shopCurrencyModel();
            $supported_currencies = array_keys($scm->getCurrencies());
        }

        /*
        // Prepare a clean deal, in case the user wants to create a new for this invoice.
        $deal = $this->view->getVars('deal');
        if (empty($deal)) {
            $clean_data = $this->getCleanDealData();
        } */
        $this->view->assign(array(
            'iframe'               => $iframe,
            //'clean_data'           => ifempty($clean_data),
            'shop_supported'       => $shop_supported,
            'supported_currencies' => $supported_currencies,
            'has_shop_rights'      => crmShop::hasRights(),
            'site_url'             => wa()->getRootUrl(true),
        ));
    }
/*
    protected function getCleanDealData()
    {
        $funnel = $this->getFunnelModel()->getAvailableFunnel();
        if (!$funnel) {
            return [];
        }

        $stage_id = $this->getFunnelStageModel()
            ->select('id')
            ->where('funnel_id = ?', (int) $funnel['id'])
            ->order('number')
            ->limit(1)
            ->fetchField('id');

        // Just empty deal, for new invoice
        $now  = date('Y-m-d H:i:s');
        $deal = $this->getDealModel()->getEmptyDeal();
        $deal = array_merge($deal, [
            'creator_contact_id' => wa()->getUser()->getId(),
            'create_datetime'    => $now,
            'update_datetime'    => $now,
            'funnel_id'          => $funnel['id'],
            'stage_id'           => $stage_id,
        ]);

        $funnels = $this->getFunnelModel()->getAllFunnels();
        if (empty($funnels[$deal['funnel_id']])) {
            return [];
        }

        $stages = $this->getFunnelStageModel()->getStagesByFunnel($funnels[$deal['funnel_id']]);

        return [
            'deal'    => $deal,
            'funnels' => $funnels,
            'stages'  => $stages,
        ];
    } */
}
