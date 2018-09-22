<?php

class Order extends IO
{

    protected $properties = [
        'orderLibrary' => '',
        'catalogKey' => '',
        'orderId' => '',
        'isxn' => '',
        'title' => '',
        'subsStartDate' => '',
        'subsEndDate' => '',
        'fundId' => '',
        'distributionLibraries' => []
    ];

    public function instantiateFromCsv($row) {
        $this->orderLibrary = $row[0];
        $this->catalogKey = $row[1];
        $this->orderId = $row[2];
        $this->isxn = $row[3];
        $this->title = $row[4];
        $this->subsStartDate = $row[5];
        $this->subsEndDate = $row[6];
        $this->fundId = $row[7];
        $this->distributionLibraries = $row[8];
    }

    public function instantiateFromErm(ResourceAcquisition $resourceAcquisition) {

        $purchaseSites = $resourceAcquisition->getPurchaseSites();
        $invoices = $resourceAcquisition->getResourcePayments();

        $this->orderLibrary = count($purchaseSites) > 0 ? $purchaseSites[0]->shortName : '';
        $this->catalogKey = $resourceAcquisition->systemNumber;
        $this->orderId = $resourceAcquisition->orderNumber;
        $resource  = new Resource(new NamedArguments(array('primaryKey' => $resourceAcquisition->resourceID)));
        $this->isxn = $resource->getIsbnOrIssn[0]->isbnOrIssn;
        $this->title = $resource->titleText;
        $this->subsStartDate = $resourceAcquisition->subscriptionStartDate;
        $this->subsEndDate = $resourceAcquisition->subscriptionEndDate;
        // TODO: Is it sufficient to get only the first fund code?
        $fund = new Fund(new NamedArguments(array('primaryKey' => $invoices[0]->fundID)));
        $this->fundId = $fund->fundCode;
        $this->distributionLibraries = array_map(function($purchaseSite) {
            return $purchaseSite->shortName;
        }, $purchaseSites);
    }

    /*
     * distributionLibraries
     */
    public function setDistributionLibraries($value) {
        if(is_array($value)){
            return $value;
        } else {
            return explode(',', $value);
        }
    }

    /*
     * purchaseSites
     */
    public function purchaseSites() {
        $purchaseSitesGetter = new PurchaseSite();
        $purchaseSites = array();
        foreach($purchaseSitesGetter->allAsArray() as $site) {
            $purchaseSites[$site['purchaseSiteID']] = $site['shortName'];
        }
        foreach($this->distributionLibraries as $library) {
            if(!in_array($library, $purchaseSites)) {
                $newSite = new PurchaseSite();
                $newSite->shortName = $library;
                try {
                    $newSite->save();
                } catch(Exception $e) {
                    throw new Exception("There was a problem creating a new purchase site $library. Error: ".$e->getMessage());
                }
            }
        }
        $purchaseSites = array();
        foreach($purchaseSitesGetter->allAsArray() as $site) {
            $purchaseSites[$site['purchaseSiteID']] = $site['shortName'];
        }
        return $purchaseSites;
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
            $resource = $matches[0];
        } else {
            $matches = $resource->getResourceByTitle($this->title);
            if (count($matches) == 0) {
                // Create a new resource
                $resource = $this->createNewResource();
                $new = true;
            } else {
                $resource = $matches[0];
            }
        }

        // Get the list of Acquisitions
        $acquisitions = $resource->getResourceAcquisitions();

        // skip if the order already exists
        foreach($acquisitions as $ra) {
            if ($ra->orderNumber == $this->orderId) {
                throw new Exception("Order #$this->orderId already saved in coral");
            }
        }

        // Make a new resource acquisition entry
        if ($new) {
            $resourceAcquisition = $acquisitions[0];
        } else {
            $resourceAcquisition = new ResourceAcquisition();
        }

        $resourceAcquisition = $this->createOrUpdateResourceAcquisition($resourceAcquisition, $resource->resourceID,
            $this->subsStartDate, $this->subsEndDate, $this->orderId, $this->catalogKey);

        //add sites from order
        $resourceAcquisitionID = $resourceAcquisition->resourceAcquisitionID;
        $purchaseSites = $this->purchaseSites();
        foreach ($this->distributionLibraries as $library){
            $purchaseSiteId = array_search($library, $purchaseSites);
            if($purchaseSiteId) {
                $resourcePurchaseSiteLink = new ResourcePurchaseSiteLink();
                $resourcePurchaseSiteLink->resourceAcquisitionID = $resourceAcquisitionID;
                $resourcePurchaseSiteLink->purchaseSiteID = $purchaseSiteId;
                try {
                    $resourcePurchaseSiteLink->save();
                } catch(Exception $e) {
                    throw new Exception("There was a problem linking a purchase site to this Order #$this->orderId for $this->title. Site: $library. Error: ".$e->getMessage());
                }
            }
        }
        return true;
    }
}
