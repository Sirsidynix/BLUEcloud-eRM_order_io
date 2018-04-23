<?php
require_once 'bootstrap.php';

$order_log = import('order');

function import($type) {
    $log = [];
    $classname = ucfirst($type);
    /*
     * Retrieve the CSV file
     */
    // TODO: Figure out how to get filename. One file per day? Previous day, current?
    $filename = ORDER_UPLOADS_DIR . "1" . '.csv';
    if(!file_exists($filename)) {
        // TODO: What type or return or error reporting is wanted?
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
    array_unshift($log, "$count orders imported.");

    return $log;
}




