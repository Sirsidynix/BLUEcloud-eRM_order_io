# BLUECLOUD Order I/O

Imports and exports order/invoice information from a CSV.

### Installation

- Clone the repo onto a server containing the Coral ERM source code
- Copy `config.php.example` to `config.php`

### Usage
Set up a cron job to run the following commands:

__For exporting__
`php $PROJECT_ROOT/run.php export`

__For importing__ 
`php $PROJECT_ROOT/run.php import`

### Customization
The following constants can be adjusted if Coral is installed in a different location, or if you 
want the export/import files to live somewhere else on the server.

var name | description
--- | ---
CORAL_INSTALL_DIR | the directory on the server that coral is installed
ORDER_UPLOAD_FILE | the absolute filename where the nightly order import CSVs are saved
INVOICE_UPLOAD_FILE | the absolute filename where the nightly invoice import CSVs are saved
ORDER_EXPORT_FILE | the absolute filename where the nightly order export CSVs are saved 

