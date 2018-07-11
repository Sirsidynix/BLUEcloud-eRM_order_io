<?php

define('CORAL_INSTALL_DIR', '/coral/');
define('ORDER_UPLOADS_DIR', '/uploads/orders/');
define('INVOICE_UPLOADS_DIR', '/uploads/invoices/');
define('ORDER_EXPORT_DIR', '/exports/orders');
define('INVOICE_EXPORT_DIR', '/exports/invoices');
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

function export($save = true) {
    $log = [];

    $exportedOrderCache = __DIR__.'/exportedOrderIds.txt';
    $exportedInvoiceCache = __DIR__.'/exportedInvoiceIds.txt';
    if (!file_exists($exportedOrderCache)) {
        $log[] = ("Could not find the exported orders cache file");
    }
    if (!file_exists($exportedInvoiceCache)) {
        $log[] = ("Could not find the exported invoices cache file");
    }
    if (!file_exists($exportedOrderCache) || !file_exists($exportedInvoiceCache)) {
        return $log;
    }
    $exportedOrders = explode(',',file_get_contents($exportedOrderCache));
    $exportedInvoices = explode(',',file_get_contents($exportedInvoiceCache));

    $getter = new ResourceAcquisition();
    $resourceAcqusitions = $getter->all();

    // TODO: What will the naming convention be?
    if ($save) {
        $orderFile = fopen(ORDER_EXPORT_DIR.'/'.date_format(time(),'YmdHis').'.csv', 'w');
        $invoiceFile = fopen(ORDER_EXPORT_DIR.'/'.date_format(time(),'YmdHis').'.csv', 'w');
    }


    foreach ($resourceAcqusitions as $ra) {
        if (!in_array($ra->resourceAcquisitionID, $exportedOrders)) {
            $order = new Order();
            $order->instantiateFromErm($ra);
            if ($save) {
                fputcsv($orderFile, $order->toFlatArray());
            }
            $exportedOrders[] = $ra->resourceAcquisitionID;
        }
        $resourcePayments = $ra->getResourcePayments();
        foreach ($resourcePayments as $payment) {
            if (!in_array($payment->resourcePaymentID, $exportedInvoices)) {
                $invoice = new Invoice();
                $invoice->instantiateFromErm($payment);
                if ($save) {
                    fputcsv($invoiceFile, $invoice->toFlatArray());
                }
                $exportedOrders[] = $ra->resourceAcquisitionID;
            }
        }
    }
    if ($save) {
        fclose($orderFile);
        fclose($invoiceFile);
    }

    // save orders
    $orderFp = fopen($exportedOrderCache, 'w');
    fwrite($orderFp, implode(',', $exportedOrders));
    fclose($orderFp);

    // save invoices
    $invoiceFp = fopen($exportedInvoiceCache, 'w');
    fwrite($invoiceFp, implode(',', $exportedInvoices));
    fclose($invoiceFp);

    return $log;

}

function install() {
    $exportedOrderCache = __DIR__.'/exportedOrderIds.txt';
    $exportedInvoiceCache = __DIR__.'/exportedInvoiceIds.txt';
    $orderFp = fopen($exportedOrderCache, 'w');
    fclose($orderFp);
    $invoiceFp = fopen($exportedInvoiceCache, 'w');
    fclose($invoiceFp);
    return export(false);
}