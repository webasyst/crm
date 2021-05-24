<?php

wa('crm');

$all_vars = array(
    'invoice' => array_merge(
        crmNotificationInvoice::getVarsForInvoice(),
        crmHelper::getVarsForContact(),
        crmNotificationInvoice::getVarsForCompany()
    ),
    'deal' => array_merge(
        crmNotificationDeal::getVarsForDeal(),
        crmHelper::getVarsForContact()
    ),
);

$notification_vars = array();
foreach (crmNotificationInvoice::getAllVars() as $event_type => $event_vars) {
    $notification_vars['notification.' . $event_type] = $event_vars;
}
foreach (crmNotificationDeal::getAllVars() as $event_type => $event_vars) {
    $key = 'notification.' . $event_type;
    if (isset($notification_vars[$key])) {
        $notification_vars[$key] = array_merge($notification_vars[$key], $event_vars);
    } else {
        $notification_vars[$key] = $event_vars;
    }
}

$all_vars = array_merge($all_vars, $notification_vars);

$all_vars['message.email_source'] = crmEmailSource::getMessageTemplateVars();
$all_vars['message.form_source'] = crmForm::getMessageTemplateVars();

return array(
    'vars' => $all_vars
);
