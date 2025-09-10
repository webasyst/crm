<?php

return [
    1 => [
        'name'            => _w('Variant A'),
        'path'            => '/templates/invoices/invoice.template_a.html',
        'origin_id'       => 1,
        'style_version'   => 2,
        'template_params' => [
            [
                'code'        => 'button_color',
                'name'        => _w('Button color'),
                'placeholder' => '#cc5252',
                'type'        => crmTemplatesModel::PARAM_TYPE_COLOR,
                'sort'        => '1',
            ],
            [
                'code'        => 'headers_color',
                'name'        => _w('Headers color'),
                'placeholder' => '#cc5252',
                'type'        => crmTemplatesModel::PARAM_TYPE_COLOR,
                'sort'        => '2',
            ],
            [
                'code'        => 'bg_color',
                'name'        => _w('Background color'),
                'placeholder' => '#f3f5fa',
                'type'        => crmTemplatesModel::PARAM_TYPE_COLOR,
                'sort'        => '3',
            ],
            [
                'code'        => 'bg_image',
                'name'        => _w('Background image'),
                'placeholder' => '',
                'type'        => crmTemplatesModel::PARAM_TYPE_IMAGE,
                'sort'        => '4',
                'default'     => '/img/invoice/bg-image-1.png',
            ],
        ]
    ],
    2 => [
        'name'            => _w('Variant B'),
        'path'            => '/templates/invoices/invoice.template_b.html',
        'origin_id'       => 2,
        'style_version'   => 2,
        'template_params' => [
            [
                'code'        => 'button_color',
                'name'        => _w('Button color'),
                'placeholder' => '#cc5252',
                'type'        => 'COLOR',
                'sort'        => '1',
            ],
            [
                'code'        => 'bg_color',
                'name'        => _w('Background color'),
                'placeholder' => '#f3f5fa',
                'type'        => crmTemplatesModel::PARAM_TYPE_COLOR,
                'sort'        => '2',
                'default'     => '#ffffff',
            ],
            [
                'code'        => 'bg_image',
                'name'        => _w('Background image'),
                'placeholder' => '',
                'type'        => crmTemplatesModel::PARAM_TYPE_IMAGE,
                'sort'        => '3',
            ],
        ]
    ],
    3 => [
        'name'            => _w('Variant C'),
        'path'            => '/templates/invoices/invoice.template_c.html',
        'origin_id'       => 3,
        'style_version'   => 2,
        'template_params' => [
            [
                'code'        => 'button_color',
                'name'        => _w('Button color'),
                'placeholder' => '#cc5252',
                'type'        => 'COLOR',
                'sort'        => '1',
            ],
            [
                'code'        => 'headers_color',
                'name'        => _w('Headers color'),
                'placeholder' => '#cc5252',
                'type'        => crmTemplatesModel::PARAM_TYPE_COLOR,
                'sort'        => '2',
            ],
            [
                'code'        => 'bg_color',
                'name'        => _w('Background color'),
                'placeholder' => '#f3f5fa',
                'type'        => crmTemplatesModel::PARAM_TYPE_COLOR,
                'sort'        => '3',
            ],
            [
                'code'        => 'bg_image',
                'name'        => _w('Background image'),
                'placeholder' => '',
                'type'        => crmTemplatesModel::PARAM_TYPE_IMAGE,
                'sort'        => '4',
                'default'     => '/img/invoice/bg-image-3.png',
            ],
        ]
    ],
];
