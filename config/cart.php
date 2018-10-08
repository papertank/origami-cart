<?php

return [

    'default' => 'default',

    'instances' => [
      
        'default' => [
            'tax' => 20,
            'currency' => 'GBP',
            'database' => [
                'connection' => null,
                'table' => 'cart',
            ],
        ],

    ],

    'default_currency' => 'GBP',

    'destroy_on_logout' => false,

];
