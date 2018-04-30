<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../bootstrap.php';
?>

<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">

    <title>Demo BLUEcloud ERM Order I/O</title>
</head>
<body>
<div class="container">
    <div class="row">
        <div class="col">
            <h1>Demo BLUEcloud ERM Order I/O</h1>
            <?php
                if(!filter_input(INPUT_POST,'run', FILTER_SANITIZE_NUMBER_INT)) {
                    echo '<p>Direct access to this page is restricted';
                } else {
                    echo '<h2>Order Import Log</h2>';
                    $order_log = import('order');
                    foreach ($order_log as $ol) {
                        echo '<p>'.$ol.'</p>';
                    }
                    $invoice_log = import('invoice');
                    echo '<h2>Invoice Import Log</h2>';
                    foreach ($invoice_log as $ol) {
                        echo '<p>'.$ol.'</p>';
                    }
                }
            ?>
        </div>
    </div>
</div>
<!-- Optional JavaScript -->
<!-- jQuery first, then Popper.js, then Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
</body>
</html>