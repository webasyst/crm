<?php

class crmCallRedirectController extends crmJsonController
{
    const TYPE_EXTERNAL = 'external'; // external number
    const TYPE_INTERIOR = 'interior'; // interior pbx number

    public function execute()
    {
        $call_id = waRequest::post('call_id', 0, waRequest::TYPE_INT);
        if (!$call_id) {
            throw new waException(_w('No call identifier'), 404);
        }

        $number_type = waRequest::post('number_type', 0, waRequest::TYPE_STRING_TRIM);
        if (!$number_type) {
            throw new waException(_w('No redirect number type.'), 404);
        }

        $number = waRequest::post('number', 0, waRequest::TYPE_STRING_TRIM);
        if (!$number) {
            throw new waException(_w('No redirect number.'), 404);
        }

        $call = $this->getCallModel()->getById($call_id);

        if (empty($call)) {
            $this->notFound(_w('Call not found'));
        }

        $tplugin = wa('crm')->getConfig()->getTelephonyPlugins($call['plugin_id']);
        if (!$tplugin) {
            throw new waException(_w('A non-telephony plugin is used.'), 404);
        }

        $redirect_allowed = $tplugin->isRedirectAllowed($call);

        if (!$redirect_allowed) {
            throw new waException(_w('The telephony plugin does not provide the ability to redirect a call.'), 404);
        }

        if ($number_type == self::TYPE_EXTERNAL) {
            // remove everything that is not digit or +
            $number = preg_replace("~[^0-9\\+]~", '', $number);
            // Remove all + except at the begining
            $number = preg_replace("~(.)\\+~", '\\1', $number);
            // 8 and 7 at the beginning of the line, replace with +7
            $number = preg_replace('~^(8|7)~', '+7', $number);
        }

        $tplugin->redirect($call, $number);
    }
}
