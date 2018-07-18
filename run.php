#!/usr/local/bin/php
<?php

require_once 'bootstrap.php';

switch($argv[1]) {
    case 'import':
        $log = ['order log'];
        $log = array_merge($log, import('order'));
        $log[] = 'invoice log';
        $log = array_merge($log, import('invoice'));
        break;
    case 'export':
        $log = export();
        break;
    case 'install':
        $log = install();
        break;
    default:
        $log = ['no command supplied'];
        break;
}

foreach ($log as $ol) {
    echo "$ol\n";
}