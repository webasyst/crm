<?php

return array(
    // backend URL after `/webasyst/crm/`             => 'module/action' (empty action means 'default')
    'deal/'                                           => 'spaLayout/',
    'deal/<id>/edit/?'                                => 'spaLayout/',
    'deal/new/?'                                      => 'spaLayout/',
    'deal/merge/?'                                    => 'spaLayout/',
    'deal/<id>/?'                                     => 'spaLayout/',
    'frame/deal-new/?'                                => 'spaFrame/',
    'frame/deal/?'                                    => 'spaFrame/',
    'frame/deal/<id>/?'                               => 'spaFrame/',
    'reminder/show/<reminder_id>/?'                   => 'reminderShow/',
    'reminder/<user_id>/?'                            => 'reminder/',
    'invoice/'                                        => 'invoice/id',
    'invoice/new/?'                                   => 'invoice/new',
    'invoice/new/<id>/?'                              => 'invoice/copy',
    'invoice/<id>/edit/?'                             => 'invoice/edit',
    'invoice/<id>/?'                                  => 'invoice/id',
    'contact/?'                                       => 'spaLayout/',
    'contact/search/?'                                => 'spaLayout/',
    'contact/search/result/<hash>/?'                  => 'spaLayout/',
    'contact/search/segment/<segment_id>/<hash>/?'    => 'spaLayout/',
    'contact/search/<hash>/?'                         => 'spaLayout/',
    'contact/merge/duplicates/?'                      => 'spaLayout/',
    'contact/selected/?'                              => 'spaLayout/',
    'contact/merge/?'                                 => 'spaLayout/',
    'contact/import/upload/?'                         => 'contact/importUpload',
    'contact/import/result/<date>/?'                  => 'spaLayout/',
    'contact/import/?'                                => 'contact/import',
    'contact/segment/<id>/?'                          => 'spaLayout/',
    'contact/vault/<id>/?'                            => 'spaLayout/',
    'contact/responsible/<id>/?'                      => 'spaLayout/',
    'contact/tag/<id>/?'                              => 'spaLayout/',
    'contact/new/?'                                   => 'spaLayout/',
    'contact/<id>/<tab>/?'                            => 'spaLayout/',
    'contact/<id>/?'                                  => 'spaLayout/',
    'frame/contact/<id>/?'                            => 'spaFrame/',
    'frame/contact-new/?'                             => 'spaFrame/',
    'frame/contact-edit/<id>/?'                       => 'spaFrame/',
    'frame/note/?'                                    => 'spaFrame/',
    'frame/file/?'                                    => 'spaFrame/',
    'frame/invoice/?'                                 => 'spaFrame/',
    'frame/history/?'                                 => 'spaFrame/',
    'report/'                                         => 'report/',
    'report/invoices/'                                => 'report/invoices',
    'report/stages/'                                  => 'report/stages',
    'call/'                                           => 'call/',
    'message/'                                        => 'message/conversations',
    'message/conversation/<id>/?'                     => 'message/conversations',
    'settings/field/?'                                => 'settings/field',
    'settings/form/?'                                 => 'settings/form',
    'settings/form/<id>/?'                            => 'settings/formId',
    'settings/regions/?'                              => 'settings/regions',
    'settings/funnels/?'                              => 'settings/funnels',
    'settings/funnels/<id>/?'                         => 'settings/funnels',
    'settings/currencies/?'                           => 'settings/currencies',
    'settings/companies/?'                            => 'settings/companies',
    'settings/companies/<id>/?'                       => 'settings/companies',
    'settings/payment/?'                              => 'settings/payment',
    'settings/payment/add/<company_id>/<plugin_id>/?' => 'settings/paymentEdit',
    'settings/payment/<instance_id>/?'                => 'settings/paymentEdit',
    'settings/lostreasons/?'                          => 'settings/lostReasons',
    'settings/vaults/?'                               => 'settings/vaults',
    'settings/notifications/?'                        => 'settings/notifications',
    'settings/notifications/edit/<id>/?'              => 'settings/notificationsEdit',
    'settings/sources/?'                              => 'settings/source',   /** @deprecated */
    'settings/sources/<id>/?'                         => 'settings/sourceId', /** @deprecated */
    'settings/message-sources/<type>/?'               => 'settings/messageSource',
    'settings/message-source/<id>/?'                  => 'settings/sourceId',
    'settings/sms/?'                                  => 'settings/sms',
    'settings/templates/?'                            => 'settings/templates',
    'settings/templates/<id>/?'                       => 'settings/templates',
    'settings/pbx/?'                                  => 'settingsPBX/',
    'settings/shop/?'                                 => 'settings/shop',
    'settings/shop/workflow/?'                        => 'settings/shopWorkflow',
    'settings/cron/?'                                 => 'settings/cron',
    'settings/general/?'                              => 'settings/general',
    'settings/'                                       => 'settings/personal',
    'pbx/<file>'                                      => 'pbx/sdk',
    'live/?'                                          => 'log/live',
    'plugins/?'                                       => 'plugins/',
    ''                                                => 'backend/',
);
