<?php

return array(
    array(
        'event'     => 'invoice.issue',
        'name'      => 'Invoice issued',     // _w('Invoice issued')
        'transport' => 'email',
        'status'    => 1,
        'subject'   => 'New invoice No. {$invoice.number} issued for you by {$company.name}',
        'sms'       => 'Invoice #{$invoice.number} has been issued for you by {$company.name} for {wa_currency($invoice.amount, $invoice.currency_id)}.',
    ),
    array(
        'event'     => 'invoice.payment',
        'name'      => 'Invoice payment',   // _w('Invoice payment')
        'transport' => 'email',
        'status'    => 1,
        'subject'   => 'Received payment for invoice No. {$invoice.number} from {$company.name}',
        'sms'       => 'Your payment for invoice #{$invoice.number} from {$company.name} has been received. Thank you!',
    ),
    array(
        'event'     => 'invoice.cancel',
        'name'      => 'Invoice canceled',    // _w('Invoice canceled')
        'transport' => 'email',
        'status'    => 1,
        'subject'   => 'Canceled invoice No. {$invoice.number} from {$company.name}',
        'sms'       => 'Invoice #{$invoice.number} from {$company.name} for {wa_currency($invoice.amount, $invoice.currency_id)} has been canceled',
    ),
    array(
        'event'     => 'invoice.expire',
        'name'      => 'Invoice expired',    // _w('Invoice expired')
        'transport' => 'email',
        'status'    => 1,
        'subject'   => 'Invoice {$invoice.number} has expired',
        'sms'       => 'Invoice {$invoice.number} has expired',
    ),
    array(
        'event'     => 'customer.birthday',
        'name'      => 'Customer birthday',   // _w('Customer birthday')
        'transport' => 'email',
        'status'    => 1,
        'subject'   => 'Happy birthday to you from {$wa->accountName()}',
        'sms'       => '{$customer.name}, happy birthday! From {$wa->accountName()}',
    ),
    array(
        'event'     => 'deal.stage_overdue',
        'name'      => 'Stage time limit expired for deal',   // _w('Deal stage overdue')
        'transport' => 'email',
        'status'    => 1,
        'recipient' => 'responsible',
        'subject'   => 'Stage time limit expired for deal',
        'sms'       => 'Deal {$deal.name} stage {$deal.stage_name} overdue',
    ),
    array(
        'event'     => 'deal.create',
        'name'      => 'Deal created',   // _w('Deal created')
        'transport' => 'email',
        'status'    => 1,
        'recipient' => 'responsible',
        'subject'   => 'Deal created',
        'sms'       => 'Deal {$deal.name} create',
    ),
    array(
        'event'     => 'deal.move',
        'name'      => 'Deal moved to another stage',   // _w('Deal moved to another stage')
        'transport' => 'email',
        'status'    => 1,
        'recipient' => 'responsible',
        'subject'   => 'Deal moved to another stage',
        'sms'       => 'Deal {$deal.name} move',
    ),
    array(
        'event'     => 'deal.won',
        'name'      => 'Deal won',   // _w('Deal won')
        'transport' => 'email',
        'status'    => 1,
        'recipient' => 'responsible',
        'subject'   => 'Deal won',
        'sms'       => 'Deal {$deal.name} won',
    ),
    array(
        'event'     => 'deal.lost',
        'name'      => 'Deal lost',   // _w('Deal lost')
        'transport' => 'email',
        'status'    => 1,
        'recipient' => 'responsible',
        'subject'   => 'Deal lost',
        'sms'       => 'Deal {$deal.name} lost',
    ),
    array(
        'event'     => 'deal.restore',
        'name'      => 'Deal reopened',   // _w('Deal reopened')
        'transport' => 'email',
        'status'    => 1,
        'recipient' => 'responsible',
        'subject'   => 'Deal reopened',
        'sms'       => 'Deal {$deal.name} restore',
    ),
);
