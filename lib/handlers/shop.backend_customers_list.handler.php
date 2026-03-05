<?php

class crmShopBackend_customers_listHandler extends waEventHandler
{
    public function execute(&$params)
    {
        if (empty($params['hash'])) {
            return null;
        }

        $single_app_mode_app_id = wa()->isSingleAppMode();
        if (!empty($single_app_mode_app_id) && $single_app_mode_app_id !== 'crm') {
            return null;
        }

        $button_text = htmlspecialchars(_wd('crm', 'Open in CRM'));

        $hash = $params['hash'];
        $url = wa()->getRootUrl(true).wa()->getConfig()->getBackendUrl()."/crm/contact/search/result/";

        if (strpos($hash, 'search/') === 0) {
            $hash = trim(trim(str_replace('search/', '', $hash)), '/');
            $hash = str_replace('/', urlencode('\/'), $hash);
            $url .= 'shop_customers/'.urlencode($hash);
        } else if (preg_match('/^([a-z_0-9]*)\//', $hash, $match)) {
            $hash = str_replace($match[1] . '/', "shop_customers\/{$match[1]}=", $hash);
            $url .= $hash;
        } else {
            $hash = 'shop_customers\/' . $hash;
            $url .= $hash;
        }

        if (wa()->getUser()->getRights('crm')) {
            return array(
                'top_li' => wa()->whichUI('shop') == '1.3' ? 
                    '<input type="button" onclick="location.href=\'' . $url . '\'" value="' . $button_text . '">' : 
                    '<button class="light-gray" onclick="location.href=\'' . $url . '\'"><i class="icon size-20" style="background-image: url(\''. wa()->getConfig()->getRootUrl().'wa-apps/crm/img/crm96.png\'); border-radius: 0; margin: -3px 0 -3px -4px;"></i> ' . $button_text . '</button>',
            );
        }
    }

}
