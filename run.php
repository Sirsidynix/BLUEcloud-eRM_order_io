#!/usr/local/bin/php
<?php

require_once 'bootstrap.php';

switch($argv[1]) {
    case 'import':
        import('order');
        import('invoice');
        break;
    case 'export':
        export();
        break;
    default:
        echo 'no command supplied';
        break;
}