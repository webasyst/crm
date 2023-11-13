<?php
return array(
    'name'                => 'АТОЛ Онлайн',
    'description'         => 'Фискализация платежей через сервис «АТОЛ Онлайн»',
    'img'                 => 'img/logo.png',
    'version'             => '1.0.5',
    'vendor'              => 'webasyst',
    'frontend'            => true,
    'custom_settings_url' => '?plugin=atolonline&module=settings',
    'handlers'            => array(
        'invoice_paid'              => 'invoicePaid',
        'invoice_refund'            => 'invoiceRefund',
        'backend_invoice'           => 'backendInvoice',
        'backend_invoice_refund'    => 'backendInvoiceRefund',
        'start_source_email_worker' => 'checkReceipts',
        'start_check'               => 'checkReceipts',
        'start_do_all_sources_work' => 'checkReceipts',
        'backend_assets'            => 'backendAssetsHandler',
    ),
);
