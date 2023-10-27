<?php
/**
 * This enables receive of telephony-related WebPush on all backend pages
 * by adding a <script> at the end of $wa->header() HTML.
 */
class crmWebasystBackend_headerHandler extends waEventHandler
{
    public function execute(&$params)
    {
        // User has access to CRM app?
        if (!wa()->getUser()->getRights('crm', 'backend')) {
            return;
        }

        if (wa()->whichUI('crm') === '2.0') {
            return array (
                'header_bottom' => self::notificationsPopup()
            );
        }

        return array (
            'header_bottom' =>
                self::fixShopLayout() .
                self::notificationsPopup() .
                self::checkTelephony(),
        );
    }

    protected static function notificationsPopup()
    {   $url_prefix = wa()->whichUI('crm') === '2.0' ? '' : '-legacy';
        $script_src = wa()->getRootUrl().'wa-apps/crm/js'.$url_prefix.'/popup.js?v'.wa('crm')->getVersion();
        $css_href = wa()->getRootUrl().'wa-apps/crm/css'.$url_prefix.'/popup.css?v'.wa('crm')->getVersion();
        return '
            <link type="text/css" rel="stylesheet" href="'.htmlspecialchars($css_href).'">
            <div id="crm-popup-area" style="display:none;"></div>
            <script type="text/javascript">$(function() {
                $.ajax({
                    url: '.json_encode($script_src).',
                    dataType: "script",
                    method: "GET",
                    cache: true
                }).then(function() {
                    try {
                        window.checkPopup('.json_encode(wa()->getAppUrl('crm')).');
                    } catch (e) {
                        console.log("Error starting CRM notification popup");
                        console.log(e);
                    }
                }, function(e) {
                    console.log("Error loading CRM notification popup");
                    console.log(e);
                });
            });</script>
        ';
    }

    protected static function checkTelephony()
    {
        // User has phone numbers assigned?
        $pbx_users_model = new crmPbxUsersModel();
        $numbers = $pbx_users_model->getByContact(wa()->getUser()->getId());
        if (!$numbers) {
            return '';
        }

        // See crmPbxActions->initJs()
        $src = wa()->getAppUrl('crm').'pbx/init.djs?_='.time();

        return '<script src="'.$src.'" defer></script>';
    }

    protected static function fixShopLayout()
    {
        // In some cases CRM allows to access Shop Orders tab
        // even though user normally does not have such access rights.
        // (See shop.backend_rights handler.)
        // In this case we change layout slightly, hiding inaccessable links.
        if (!waRequest::param('crm_should_fix_shop_layout')) {
            return '';
        }

        return '<style>
#s-sidebar, #s-orders-mobile-notice, .s-search-form { display: none !important; }
#s-content.content.left200px { margin-left: 0; }
</style>';
    }
}