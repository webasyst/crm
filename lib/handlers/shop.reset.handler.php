<?php

class crmShopResetHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $dm = new crmDealModel();
        $dm->exec("UPDATE `crm_deal` SET `external_id` = NULL
                        WHERE `external_id` LIKE 'shop:%'");
    }
}
