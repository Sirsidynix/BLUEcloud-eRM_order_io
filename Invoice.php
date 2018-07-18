<?php

class Invoice extends BaseClass
{

    protected $properties = [
        'invoiceId' => '',
        'catalogKey' => '',
        'isxn' => '',
        'title' => '',
        'subsStartDate' => '',
        'subsEndDate' => '',
        'fundId' => '',
        'amount' => []
    ];

    public function instantiateFromCsv($row) {
        $this->invoiceId = $row[0];
        $this->catalogKey = $row[1];
        $this->isxn = $row[2];
        $this->title = $row[3];
        $this->subsStartDate = $row[4];
        $this->subsEndDate = $row[5];
        $this->fundId = $row[6];
        $this->amount = $row[7];
    }

    public function instantiateFromErm(ResourcePayment $resourcePayment) {
        // TODO: Nowhere in the CORAL interface to set an Invoice number...
        $this->invoiceId = $resourcePayment->invoiceNum;
        $resourceAcquisition = new ResourceAcquisition(new NamedArguments(array('primaryKey' => $resourcePayment->resourceAcquisitionID)));
        $this->catalogKey = $resourceAcquisition->systemNumber;
        $resource  = new Resource(new NamedArguments(array('primaryKey' => $resourceAcquisition->resourceID)));
        $this->isxn = $resource->getIsbnOrIssn[0]->isbnOrIssn;
        $this->title = $resource->titleText;
        $this->subsStartDate = $resourcePayment->subscriptionStartDate;
        $this->subsEndDate = $resourcePayment->subscriptionEndDate;
        $fund = new Fund(new NamedArguments(array('primaryKey' => $resourcePayment->fundID)));
        $this->fundId = $fund->fundCode;
        // override amount
        $this->properties['amount'] = substr($resourcePayment->paymentAmount, 0 ,-2).'.'.substr($resourcePayment->paymentAmount, -2);
    }

    /*
     * subsStartDate
     */
    public function getSubsStartDate() {
        return $this->properties['subsStartDate']->format("Y-m-d");
    }
    public function setSubsStartDate($value) {
        return new DateTime($value);
    }

    /*
     * subsEndDate
     */
    public function getSubsEndDate() {
        return $this->properties['subsEndDate']->format("Y-m-d");
    }
    public function setSubsEndDate($value) {
        return new DateTime($value);
    }

    /*
     * amount
     */
    public function setAmount($value) {
        // For whatever reason, coral sets all costs to integers instead of floats.
        return intval($value) * 100;
    }

    /*
     * defaultCurrency
     */
    public function getDefaultCurrency() {
        // Use a session variable so we don't have to read the config file for every order import
        if(isset($_SESSION['defaultCurrency'])) {
            return $_SESSION['defaultCurrency'];
        }
        $config = new Configuration();
        $defaultCurrency = $config->settings->defaultCurrency;
        $_SESSION['defaultCurrency'] = $defaultCurrency;
        return $defaultCurrency;
    }

    /*
     * orderTypeId
     */
    public function getOrderTypeId() {
        // Use a session variable so we don't have to query the DB for every order import
        if(isset($_SESSION['orderTypeId'])) {
            return $_SESSION['orderTypeId'];
        }
        // TODO: What should we set the order type to?
        // Find the order type that is ongoing. If not found, get the first entry from the sorted array
        $orderTypeGetter = new OrderType();
        $orderTypes = $orderTypeGetter->getAllOrderType();
        $orderType = null;
        foreach($orderTypes as $ot) {
            if($ot['shortName'] == 'ongoing') {
                $orderType = $ot;
                break;
            }
        }
        if(empty($orderType)) {
            $orderType = $orderTypes[0];
        }

        $orderTypeId = $orderType['orderTypeID'];
        $_SESSION['orderTypeId'] = $orderTypeId;
        return $orderTypeId;
    }

    /*
     * coralFundId
     */
    public function getCoralFundId() {
        // Use a session variable so we don't have to read the db for every order import
        $newFunds = false;
        if(empty($_SESSION['coralFunds'])) {
            $newFunds = true;
        } else {
            if(!in_array($this->fundId, $_SESSION['coralFunds'])) {
                $newFund = new Fund();
                $newFund->shortName = $this->fundId;
                $newFund->fundCode = $this->fundId;
                try {
                    $newFund->save();
                } catch(Exception $e) {
                    throw new Exception("There was a problem creating a new fund $this->fundId. Error: ".$e->getMessage());
                }
                $newFunds = true;
            }
        }
        if($newFunds) {
            $fundGetter = new Fund();
            $funds = array();
            foreach($fundGetter->allAsArray() as $fund) {
                $funds[$fund['fundID']] = $fund['shortName'];
            }
            $_SESSION['coralFunds'] = $funds;
        }
        return array_search($this->fundId, $_SESSION['coralFunds']);
    }

    public function importIntoErm() {
        $matches = [];
        // Find matching resource
        $resource = new Resource();
        // need to put isxn in variable, because empty returns true with magic __get method
        $isxn = $this->isxn;
        if(!empty($isxn)){
            $matches = $resource->getResourceByIsbnOrISSN($isxn);
        }
        if (count($matches) > 1) {
            throw new Exception("More than one resource matched ISXN: $this->isxn");
        } elseif (count($matches) == 1) {
            $resource = $matches[0];
        } else {
            $matches = $resource->getResourceByTitle($this->title);
            if (count($matches) > 1) {
                throw new Exception("More than one resource matched title: $this->title");
            } elseif (count($matches) == 1) {
                $resource = $matches[0];
            } else {
                throw new Exception("Could not find a matching resource: $this->title, ISXN: $this->isxn");
            }
        }

        // Get the list of Acquisitions
        $acquisitions = $resource->getResourceAcquisitions();

        // Add invoice to each matching order
        foreach($acquisitions as $ra) {
            if ($ra->subscriptionStartDate == $this->subsStartDate && $ra->subscriptionEndDate == $this->subsEndDate) {
                // create an array of already existing invoices
                $existingInvocies = [];
                $invoices = $ra->getResourcePayments();
                foreach($invoices as $i) {
                    $existingInvocies[] = $i->invoiceNum;
                }
                //skip if the invoice already exists
                if(in_array($this->invoiceId, $existingInvocies)){
                    throw new Exception("Invoice #$this->invoiceId already saved in coral");
                    continue;
                }

                $resourcePayment = $this->createResourcePayment($ra->resourceAcquisitionID);
                try {
                    $resourcePayment->save();
                } catch (Exception $e) {
                    throw new Exception("There was a problem creating a new invoice for $this->title. Please contact your administrator. Error: ".$e->getMessage());
                }
            }
        }

        return true;
    }

    public function createResourcePayment($resourceAcquisitionId) {
        // Note: the following code should align with creating a new RA in coral, /resources/ajax_processing/submitCost.php
        $resourcePayment = new ResourcePayment();
        $resourcePayment->resourceAcquisitionID = $resourceAcquisitionId;
        $resourcePayment->year = $this->properties['subsStartDate']->format('Y');
        $resourcePayment->subscriptionStartDate = $this->subsStartDate;
        $resourcePayment->subscriptionEndDate   = $this->subsEndDate;
        $resourcePayment->fundID = $this->coralFundId;
        $resourcePayment->paymentAmount = $this->amount;
        $resourcePayment->currencyCode = $this->defaultCurrency;
        $resourcePayment->orderTypeID = $this->orderTypeId;
        $resourcePayment->costNote = '';
        $resourcePayment->invoiceNum = $this->invoiceId;
        return $resourcePayment;
    }
}
