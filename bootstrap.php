<?php

define('CORAL_INSTALL_DIR', '/coral/');
define('ORDER_UPLOADS_DIR', '/uploads/orders/');
define('INVOICE_UPLOADS_DIR', '/uploads/invoices/');
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

function import($type) {
    $log = [];
    $classname = ucfirst($type);
    /*
     * Retrieve the CSV file
     */
    // TODO: Figure out how to get filename. One file per day? Previous day, current?
    $uploadsDir = strtoupper($type).'_UPLOADS_DIR';
    $filename = constant($uploadsDir) . "1" . '.csv';
    if(!file_exists($filename)) {
        // TODO: What type of return or error reporting is wanted?
        $log[] = "No new orders found. Could not find file: $filename";
    }

    /*
     * Read the csv file and create
     */
    $count = 0;
    if (($handle = fopen($filename, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $item = new $classname;
            $item->instantiateFromCsv($data);
            try {
                $item->importIntoErm();
                $count ++;
            } catch(Exception $e) {
                $log[] = $e->getMessage();
            }
        }
        fclose($handle);
    }
    array_unshift($log, "$count $type(s) imported.");

    return $log;
}
