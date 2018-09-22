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

function import($type) {
    $log = [];
    $classname = ucfirst($type);
    /*
     * Retrieve the CSV file
     */
    $filename = constant(strtoupper($type).'_UPLOADS_FILE');
    if(!file_exists($filename)) {
        // TODO: What type of return or error reporting is wanted?
        $log[] = "No new ".$type."s found. Could not find file: $filename";
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

function export($install = false) {
    $log = [];

    $exportedOrderCache = __DIR__.'/exports/exportedOrderIds.txt';
    $exportedInvoiceCache = __DIR__.'/exports/exportedInvoiceIds.txt';
    if ($install) {
        $exportedOrders = [];
        $exportedInvoices = [];
    } else {
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
    }

    $getter = new ResourceAcquisition();
    $resourceAcqusitions = $getter->all();

    // TODO: What will the naming convention be?
    if ($install) {
        $orderFile = null;
        $invoiceFile = null;
    } else {

    }

    $orders = [];
    $invoices = [];

    foreach ($resourceAcqusitions as $ra) {
        if (!in_array($ra->resourceAcquisitionID, $exportedOrders)) {
            $order = new Order();
            $order->instantiateFromErm($ra);
            $exportedOrders[] = $ra->resourceAcquisitionID;
            $orders[] = $order->toFlatArray();
        }
        $resourcePayments = $ra->getResourcePayments();
        foreach ($resourcePayments as $payment) {
            if (!in_array($payment->resourcePaymentID, $exportedInvoices)) {
                $invoice = new Invoice();
                $invoice->instantiateFromErm($payment);
                $invoices[] = $invoice->toFlatArray();
                $exportedInvoices[] = $payment->resourcePaymentID;
            }
        }
    }

    if (!$install) {
        $orderFile = fopen(ORDER_EXPORT_FILE, 'w');
        foreach($orders as $order) {
            fputcsv($orderFile, $order);
        }
        fclose($orderFile);

        $log[] = 'Exported '.count($orders).' Orders';

        $invoiceFile = fopen(INVOICE_EXPORT_FILE, 'w');
        foreach($invoices as $invoice) {
            fputcsv($invoiceFile, $invoice);
        }
        fclose($invoiceFile);

        $log[] = 'Exported '.count($invoices).' Invoices';
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
    $exportedOrderCache = __DIR__.'/exports/exportedOrderIds.txt';
    $exportedInvoiceCache = __DIR__.'/exports/exportedInvoiceIds.txt';
    $orderFp = fopen($exportedOrderCache, 'w');
    fclose($orderFp);
    $invoiceFp = fopen($exportedInvoiceCache, 'w');
    fclose($invoiceFp);
    return export(true);
}