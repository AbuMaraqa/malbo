<?php

// The first admin account, created by Modules\Core\database\seeders\AdminSeeder.
return [
    'name' => env('ADMIN_NAME', 'Admin'),
    'email' => env('ADMIN_EMAIL', 'admin@admin.com'),
    'password' => env('ADMIN_PASSWORD', '123456789'),
];
