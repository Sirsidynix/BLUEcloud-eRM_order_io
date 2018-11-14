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
            $funds[$fund['fundID']] = $fund['fundCode'];
        }
        return $funds;
    }

    public function getCoralFundId() {
        $funds = $this->funds();
        if(!in_array($this->fundId, $funds)) {
            $newFund = new Fund();
            $newFund->shortName = $this->fundId;
            $newFund->fundCode = $this->fundId;
            $newFund->archived = 0;
            try {
                $newFund->save();
            } catch(Exception $e) {
                throw new Exception("There was a problem creating a new fund $this->fundId. Error: ".$e->getMessage());
            }
            $funds = $this->funds();
        }
        return array_search($this->fundId, $funds);
    }

    public function importIntoErm() {
        $matches = [];
        $createdNewResource = false;
        $createdNewInvoice = false;
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

        // Add invoice to each matching order
        if ($createdNewResource) {
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
            // create a new order if there are no matches
            if (empty($matchingAcquisition)) {
                $matchingAcquisition = $this->createOrUpdateResourceAcquisition(new ResourceAcquisition, $resource->resourceID,
                    $this->subsStartDate, $this->subsEndDate, "Order created from Invoice #$this->invoiceId", $this->catalogKey);
            }
        }


        // create an array of already existing invoices
        $existingInvocies = [];
        $invoices = $matchingAcquisition->getResourcePayments();
        foreach($invoices as $i) {
            if ($this->invoiceId.$this->coralFundId == $i->invoiceNum.$i->fundID) {
                $resourcePayment = $i;
            }
        }

        // Create new payment if does not already exist
        if (empty($resourcePayment)) {
            $resourcePayment = new ResourcePayment();
            $createdNewInvoice = true;
        }

        $resourcePayment->resourceAcquisitionID = $matchingAcquisition->resourceAcquisitionID;
        $resourcePayment->year = $this->properties['subsStartDate']->format('Y');
        $resourcePayment->subscriptionStartDate = $this->subsStartDate;
        $resourcePayment->subscriptionEndDate   = $this->subsEndDate;
        $resourcePayment->fundID = $this->coralFundId;
        $resourcePayment->paymentAmount = $this->amount;
        $resourcePayment->currencyCode = $this->defaultCurrency;
        $resourcePayment->orderTypeID = $this->orderTypeId;
        $resourcePayment->costNote = '';
        $resourcePayment->invoiceNum = $this->invoiceId;

        try {
            $resourcePayment->save();
        } catch (Exception $e) {
            throw new Exception("There was a problem creating a new invoice for $this->title. Please contact your administrator. Error: ".$e->getMessage());
        }

        return sprintf("Invoice #%s for Resource #%s (%s) %s", $this->invoiceId, $resource->resourceID, $resource->titleText, $createdNewInvoice ? 'created' : 'updated');
    }
}
