<?php

class crmShopBackend_customers_listHandler extends waEventHandler
{
    public function execute(&$params)
    {
        if (empty($params['hash'])) {
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
                'top_li' => '<input type="button" onclick="location.href=\'' . $url . '\'" value="' . $button_text . '">',
            );
        }
    }

}
