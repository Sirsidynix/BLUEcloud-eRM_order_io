# BLUECLOUD Order I/O

Imports and exports order/invoice information from a CSV.

### Installation

- Clone the repo onto a server containing the Coral ERM source code
- Copy `config.php.example` to `config.php`
- Edit  `config.php` with the following paths

var name | description
--- | ---
CORAL_INSTALL_DIR | the directory on the server that coral is installed
ORDER_UPLOADS_DIR | the directory on the server that the order CSVs will be uploaded to
INVOICE_UPLOADS_DIR | the directory on the server that the invoice CSVs will be uploaded to
ORDER_EXPORT_DIR | the directory in which the scripts should deposit the order export CSV
INVOICE_EXPORT_DIR | the directory in which the scripts should deposit the invoice export CSV 

- install the script `php run.php install`

### Usage
Set up a cron job to run the following commands:

__For exporting__
`php run.php export`

__For importing__ 
`php run.php import`