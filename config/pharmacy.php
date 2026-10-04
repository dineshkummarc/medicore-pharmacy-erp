<?php

return [

    /*
    | First administrator created by `php artisan db:seed`. When no password is
    | given a random one is generated and printed once.
    */
    'seed' => [
        'admin_name' => env('SEED_ADMIN_NAME', 'Administrator'),
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@pharmacy.test'),
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
        'demo_data' => (bool) env('SEED_DEMO_DATA', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission catalogue
    |--------------------------------------------------------------------------
    |
    | Permissions are defined in code and synced to the database by the
    | PermissionSeeder (`php artisan db:seed --class=PermissionSeeder`).
    | Roles are editable in the UI. The "admin" system role bypasses checks.
    |
    */

    'permissions' => [
        'Dashboard' => [
            'dashboard.view' => 'View dashboard & business metrics',
        ],
        'Medicines & inventory' => [
            'medicines.view' => 'View medicines',
            'medicines.create' => 'Create medicines',
            'medicines.update' => 'Edit medicines',
            'medicines.delete' => 'Archive medicines',
            'inventory.view' => 'View batches & stock history',
            'inventory.adjust' => 'Adjust stock & write off expired',
            'catalog.manage' => 'Manage categories & manufacturers',
        ],
        'Customers' => [
            'customers.view' => 'View customers',
            'customers.manage' => 'Create, edit & archive customers',
            'customers.payments' => 'Collect customer payments',
        ],
        'Suppliers' => [
            'suppliers.view' => 'View suppliers',
            'suppliers.manage' => 'Create, edit & archive suppliers',
            'suppliers.payments' => 'Record supplier payments',
        ],
        'Purchases' => [
            'purchases.view' => 'View purchases',
            'purchases.create' => 'Create purchases',
            'purchases.cancel' => 'Edit & cancel purchases',
        ],
        'Sales' => [
            'sales.create' => 'Use POS / create sales',
            'sales.view' => 'View invoices',
            'sales.discount' => 'Apply discounts',
            'sales.credit' => 'Sell on credit (unpaid balance)',
        ],
        'Returns' => [
            'returns.view' => 'View returns',
            'returns.create' => 'Process returns & refunds',
        ],
        'Expenses' => [
            'expenses.view' => 'View expenses',
            'expenses.manage' => 'Create, edit & delete expenses',
        ],
        'Reports' => [
            'reports.view' => 'View sales & inventory reports',
            'reports.financial' => 'View profit & financial reports',
        ],
        'Administration' => [
            'staff.manage' => 'Manage staff accounts',
            'roles.manage' => 'Manage roles & permissions',
            'settings.manage' => 'Manage store settings',
            'audit.view' => 'View audit log',
        ],
    ],

    /*
    | Permissions granted to the seeded "staff" role.
    */
    'default_staff_permissions' => [
        'dashboard.view',
        'medicines.view',
        'inventory.view',
        'customers.view',
        'customers.manage',
        'sales.create',
        'sales.view',
        'returns.view',
    ],

    'units' => [
        'tablet', 'capsule', 'strip', 'box', 'bottle', 'syrup', 'vial', 'ampoule',
        'tube', 'sachet', 'injection', 'inhaler', 'drops', 'piece', 'pack',
    ],

    /*
    | Default values for store settings (overridden by the settings table).
    */
    'settings' => [
        'store_name' => env('APP_NAME', 'Pharmacy'),
        'store_tagline' => 'Your trusted neighbourhood pharmacy',
        'store_address' => '',
        'store_phone' => '',
        'store_email' => '',
        'store_logo' => null,
        'license_number' => '',
        'tax_number' => '',
        'currency_symbol' => '$',
        'currency_code' => 'USD',
        'invoice_prefix' => 'INV-',
        'purchase_prefix' => 'PUR-',
        'return_prefix' => 'RET-',
        'tax_enabled' => '1',
        'tax_label' => 'Tax',
        'default_tax_rate' => '0',
        'low_stock_threshold' => '10',
        'expiry_alert_days' => '90',
        'max_discount_percent' => '20',
        'invoice_footer' => 'Thank you for your purchase. Medicines once sold are returnable only with the original invoice.',
        'receipt_width' => 'a4',
    ],
];
