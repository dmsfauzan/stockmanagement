<?php

return [
    'enabled' => (bool) env('APPROVAL_ENABLED', false),

    'maker_checker' => (bool) env('APPROVAL_MAKER_CHECKER', true),

    'flows' => [
        'purchase_order' => [
            ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
            ['level' => 2, 'role' => 'manager', 'min_total' => 10_000_000],
            ['level' => 3, 'role' => 'admin', 'min_total' => 100_000_000],
        ],
        'sales_order' => [
            ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
            ['level' => 2, 'role' => 'manager', 'min_total' => 10_000_000],
            ['level' => 3, 'role' => 'admin', 'min_total' => 100_000_000],
        ],
        'goods_receipt' => [
            ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
            ['level' => 2, 'role' => 'manager', 'min_total' => 50_000_000],
            ['level' => 3, 'role' => 'admin', 'min_total' => 200_000_000],
        ],
        'goods_issue' => [
            ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
            ['level' => 2, 'role' => 'manager', 'min_total' => 50_000_000],
            ['level' => 3, 'role' => 'admin', 'min_total' => 200_000_000],
        ],
        'stock_adjustment' => [
            ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
            ['level' => 2, 'role' => 'manager', 'min_total' => 10_000_000],
            ['level' => 3, 'role' => 'admin', 'min_total' => 50_000_000],
        ],
        'stock_opname' => [
            ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
            ['level' => 2, 'role' => 'manager', 'min_total' => 50_000_000],
            ['level' => 3, 'role' => 'admin', 'min_total' => 200_000_000],
        ],
        'stock_transfer' => [
            ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
            ['level' => 2, 'role' => 'manager', 'min_total' => 10_000_000],
            ['level' => 3, 'role' => 'admin', 'min_total' => 50_000_000],
        ],
    ],
];
