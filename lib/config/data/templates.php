<?php

return array(
    array(
        'name'            => _w('Variant A'),     // _w('Variant A')
        'path'            => '/templates/invoice.template_a.html',
        'template_params' => array(
            array(
                'code'        => 'color',
                'name'        => _w('Color'),
                'placeholder' => '#cc5252',
                'type'        => 'COLOR',
                'sort'        => '1',
            )
        )
    ),
    array(
        'name'            => _w('Variant B'),     // _w('Variant B')
        'path'            => '/templates/invoice.template_b.html',
        'template_params' => array(
            array(
                'code'        => 'color',
                'name'        => _w('Color'),
                'placeholder' => '#cc5252',
                'type'        => 'COLOR',
                'sort'        => '1',
            )
        )
    ),
);
