<?php
require_once 'bootstrap.php';

$fund = new Fund();
$fund->fundCode = 'test';
$fund->shortName = 'testing';
$fund->save();
