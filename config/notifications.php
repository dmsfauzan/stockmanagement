<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification types
    |--------------------------------------------------------------------------
    | Single source of truth for the in-app notification types. `email_default`
    | determines whether a type is emailed when a user has no explicit
    | preference row (users can override per-type from their profile).
    | `label` is used in the preference UI.
    */
    'types' => [
        'stock.low' => ['label' => 'Stok rendah', 'group' => 'Stock', 'email_default' => true],
        'stock.out' => ['label' => 'Stok habis', 'group' => 'Stock', 'email_default' => true],
        'stock.expiring' => ['label' => 'Batch kedaluwarsa', 'group' => 'Stock', 'email_default' => true],
        'approval.request' => ['label' => 'Permintaan persetujuan', 'group' => 'Approval', 'email_default' => true],
        'approval.result' => ['label' => 'Hasil persetujuan', 'group' => 'Approval', 'email_default' => true],
        'opname.completed' => ['label' => 'Opname selesai', 'group' => 'Opname', 'email_default' => true],
        'import.completed' => ['label' => 'Import selesai', 'group' => 'Import', 'email_default' => true],
    ],

    // Digest pseudo-type preference (per user).
    'digest_type' => 'digest',

    // Roles that receive the daily digest by default.
    'digest_default_roles' => ['admin', 'supervisor'],
];
