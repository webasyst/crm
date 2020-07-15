<?php

wa('crm');

$all_vars = array(
    'invoice' => array_merge(
        crmNotificationInvoice::getVarsForInvoice(),
        crmNotificationInvoice::getVarsForContact(),
        crmNotificationInvoice::getVarsForCompany()
    ),
    'deal' => array_merge(
        crmNotificationDeal::getVarsForDeal(),
        crmNotificationDeal::getVarsForContact()
    ),
);

$notification_vars = array();
foreach (crmNotificationInvoice::getAllVars() as $event_type => $event_vars) {
    $notification_vars['notification.' . $event_type] = $event_vars;
}
foreach (crmNotificationDeal::getAllVars() as $event_type => $event_vars) {
    $notification_vars['notification.' . $event_type] = $event_vars;
}

$all_vars = array_merge($all_vars, $notification_vars);

return array(
    'vars' => $all_vars
);
