<?php

class Invoice extends IO
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
        $config = new Configuration();
        return $config->settings->defaultCurrency;
    }

    /*
     * orderTypeId
     */
    public function getOrderTypeId() {
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

        return $orderType['orderTypeID'];
    }

    /*
     * coralFundId
     */
    public function funds () {
        $fundGetter = new Fund();
        $funds = array();
        foreach($fundGetter->allAsArray() as $fund) {
            $funds[$fund['fundID']] = $fund['shortName'];
        }
        return $funds;
    }

    public function getCoralFundId() {

        if(!in_array($this->fundId, $this->funds())) {
            $newFund = new Fund();
            $newFund->shortName = $this->fundId;
            $newFund->fundCode = $this->fundId;
            $newFund->archived = 0;
            try {
                $newFund->save();
            } catch(Exception $e) {
                throw new Exception("There was a problem creating a new fund $this->fundId. Error: ".$e->getMessage());
            }
        }
        return array_search($this->fundId, $this->funds());
    }

    public function importIntoErm() {
        $matches = [];
        $new = false;
        // Find matching resource
        $resource = new Resource();
        // need to put isxn in variable, because empty returns true with magic __get method
        $isxn = $this->isxn;
        if(!empty($isxn)){
            $matches = $resource->getResourceByIsbnOrISSN($isxn);
        }
        if (count($matches) > 0) {
            $resource =  $matches[0];
        } else {
            $matches = $resource->getResourceByTitle($this->title);
            if (count($matches) > 0) {
                $resource = $matches[0];
            } else {
                // Create a new resource
                $resource = $this->createNewResource();
                $new = true;
            }
        }

        // Get the list of Acquisitions
        $acquisitions = $resource->getResourceAcquisitions();

        if (count($acquisitions) < 1) {
            throw new Exception("Invoice #$this->invoiceId has no matching order.1");
        } else {
            $matchingAcquisition = false;
        }

        // Add invoice to each matching order
        if ($new) {
            $matchingAcquisition = $acquisitions[0];
            $matchingAcquisition->subscriptionStartDate = $this->subsStartDate;
            $matchingAcquisition->subscriptionEndDate = $this->subsEndDate;
            $matchingAcquisition->save();
        } else {
            foreach($acquisitions as $ra) {
                if ($ra->subscriptionStartDate == $this->subsStartDate && $ra->subscriptionEndDate == $this->subsEndDate) {
                    $matchingAcquisition = $ra;
                }
            }
        }
        // If there are not matches, return error
        if (empty($matchingAcquisition)) {
            throw new Exception("Invoice #$this->invoiceId has no matching order.");
        }


        // create an array of already existing invoices
        $existingInvocies = [];
        $invoices = $matchingAcquisition->getResourcePayments();
        foreach($invoices as $i) {
            $existingInvocies[] = $i->invoiceNum;
        }
        //skip if the invoice already exists
        if(in_array($this->invoiceId, $existingInvocies)){
            throw new Exception(" Invoice #$this->invoiceId already saved in coral");
        }

        $resourcePayment = $this->createResourcePayment($matchingAcquisition->resourceAcquisitionID);
        try {
            $resourcePayment->save();
        } catch (Exception $e) {
            throw new Exception("There was a problem creating a new invoice for $this->title. Please contact your administrator. Error: ".$e->getMessage());
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
