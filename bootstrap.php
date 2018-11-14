<?php

require_once 'config.php';

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

function logMessage($message) {
    return sprintf("[%s] -- %s", date(DATE_ISO8601), $message);
}

function import($type) {
    $log = [];
    $classname = ucfirst($type);
    /*
     * Retrieve the CSV file
     */
    $filename = constant(strtoupper($type).'_UPLOADS_FILE');
    if(!file_exists($filename)) {
        // TODO: What type of return or error reporting is wanted?
        $log[] = logMessage("No new ".$type."s found. Could not find file: $filename");
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
                $log[] = logMessage($item->importIntoErm());
                $count ++;
            } catch(Exception $e) {
                $log[] = logMessage($e->getMessage());
            }
        }
        fclose($handle);
    }
    array_unshift($log, logMessage("$count $type(s) imported."));
    $logFile = dirname(constant(strtoupper($type).'_UPLOADS_FILE'))."/import-$type-".date('Y_m_d').'.log';
    $handle = fopen($logFile, "w");
    fwrite($handle, implode("\r\n", $log)."\r\n");
}

function export() {
    $log = [];
    $resourcesWithoutOrders = [];

    $getter = new ResourceAcquisition();
    $resourceAcqusitions = $getter->all();

    $orders = [];

    foreach ($resourceAcqusitions as $ra) {
        $order = new Order();
        try {
            $order->instantiateFromErm($ra);
            $orders[] = $order->toFlatArray();
        } catch (Exception $e) {
            switch($e->getCode()) {
                case 1:
                    break;
                case 2:
                    $resourcesWithoutOrders[] = $e->getMessage();
                    break;
                default:
                    $log[] = logMessage($e->getMessage());
                    break;
            }
        }
    }

    $orderFile = fopen(ORDER_EXPORT_FILE, 'w');
    foreach($orders as $order) {
        fputcsv($orderFile, $order);
    }
    fclose($orderFile);

    array_unshift($log, logMessage('Exported '.count($orders).' Orders, Skipped '.count($log).' Orders'));
    $logFile = dirname(ORDER_EXPORT_FILE)."/export-".date('Y_m_d').'.log';
    $handle = fopen($logFile, "w");
    fwrite($handle, implode("\r\n", $log)."\r\n");

    array_unshift($resourcesWithoutOrders, logMessage(count($resourcesWithoutOrders).' resources have orders that have the same start and end dates, indicating a system created (ignored) order.'));
    $logFile = dirname(ORDER_EXPORT_FILE).'/resources-without-orders.log';
    $handle = fopen($logFile, "w");
    fwrite($handle, implode("\r\n", $resourcesWithoutOrders)."\r\n");

}