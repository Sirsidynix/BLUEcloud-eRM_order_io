<?php

define('CORAL_INSTALL_DIR', '/coral/');
define('ORDER_UPLOADS_DIR', '/uploads/orders/');
define('INVOICES_UPLOADS_DIR', '/uploads/invoices/');
define('BASE_DIR', CORAL_INSTALL_DIR . 'resources/');

spl_autoload_register(function ($class_name) {
    $search_dirs = [
        BASE_DIR.'admin/classes/common/',
        BASE_DIR.'admin/classes/domain/',
        CORAL_INSTALL_DIR.'common/',
        __DIR__ . '/'
    ];

    foreach ($search_dirs as $dir) {
        $file = $dir . $class_name . '.php';
        if (file_exists($file)) {
            break;
        }
    }

    include $file;
});