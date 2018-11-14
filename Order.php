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
        'distributionLibraries' => [],
        'vendor' => ''
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
        $resource  = new Resource(new NamedArguments(array('primaryKey' => $resourceAcquisition->resourceID)));

        $errors = [];

        //--- Dates
        $this->subsStartDate = $resourceAcquisition->subscriptionStartDate;
        $this->subsEndDate = $resourceAcquisition->subscriptionEndDate;
        // Skip if start and end date are the same
        if ($this->subsStartDate === $this->subsEndDate) {
            $errors[] = sprintf("Subscription start and end dates not different (%s, %s)", $this->subsStartDate, $this->subsEndDate);
        }
        // Skip if end date is in past
        $now = new DateTime();
        if ($this->subsEndDate < $now->format('Y-m-d')) {
            $errors[] = sprintf("The subscription has ended (%s)", $this->subsEndDate);
        }

        //--- Order Library
        // Skip if order library is empty
        if (empty($purchaseSites)) {
            $errors[] = "Order has no associated ordering libraries";
        } else {
            $this->orderLibrary = $purchaseSites[0]->shortName;
        }

        //--- Order Number
        $orderId = $resourceAcquisition->orderNumber;
        // Skip if order id is empty
        if (empty($orderId) || preg_match('/created from/', $orderId)) {
            $errors[] = "There is no order number.";
        }
        $this->orderId = $orderId;

        //--- Fund Id
        $fundId = null;
        if (!empty($invoices)) {
            $fund = new Fund(new NamedArguments(array('primaryKey' => $invoices[0]->fundID)));
            $fundId = $fund->fundCode;
        }
        // Skip if fund id is empty
        if (empty($invoices) || empty($fundId)) {
            $errors[] = "There is no fund id.";
        }
        $this->fundId = $fundId;

        //--- At least one of title, isbn, or cat key
        $catalogKey = $resourceAcquisition->systemNumber;
        $isxn = $resource->getIsbnOrIssn[0]->isbnOrIssn;
        $title = $resource->titleText;
        if (empty($catalogKey) && empty($isxn) && empty($title)) {
            $errors[] = "Resource is missing a title and isxn and catalog key";
        }
        $this->catalogKey = trim($catalogKey);
        $this->isxn = trim($isxn);
        $this->title = trim($title);

        //--- Distribution Libraries
        $this->distributionLibraries = array_map(function($purchaseSite) {
            return $purchaseSite->shortName;
        }, $purchaseSites);

        if(!empty($errors)) {
            $message = sprintf("Skipped Resource #%s (%s), Order #%s (%s - %s) for the following reasons: [%s]",
                $resource->resourceID, $this->title, $this->orderId, $this->subsStartDate, $this->subsEndDate, implode(', ', $errors));
            throw new Exception($message);
        }

        //--- Vendor
        $organizations = $resource->getOrganizationArray();
        $vendor = '';
        if (!empty($organizations)) {
            // search for 'vendor' first
            foreach($organizations as $org) {
                if(strtolower($org['organizationRole']) === 'vendor') {
                    $vendor = $org['organization'];
                }
            }
            // then search for 'provider'
            if (empty($vendor)) {
                foreach($organizations as $org) {
                    if(strtolower($org['organizationRole']) === 'provider') {
                        $vendor = $org['organization'];
                    }
                }
            }
        }
        $this->vendor = $vendor;
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
        $createdNewResource = false;
        $createdNewOrder = false;
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
                $createdNewResource = true;
            } else {
                $resource = $matches[0];
            }
        }

        // Get the list of Acquisitions
        $acquisitions = $resource->getResourceAcquisitions();

        // use the order if it already exists
        foreach($acquisitions as $ra) {
            if ($ra->orderNumber == $this->orderId) {
                $resourceAcquisition = $ra;
            }
        }

        // Make a new resource acquisition entry if matching order does not exist
        if (empty($resourceAcquisition)) {
            if($createdNewResource) {
                $resourceAcquisition = $acquisitions[0];
            } else {
                $resourceAcquisition = new ResourceAcquisition();
            }
            $createdNewOrder = true;
        }

        $resourceAcquisition = $this->createOrUpdateResourceAcquisition($resourceAcquisition, $resource->resourceID,
            $this->subsStartDate, $this->subsEndDate, $this->orderId, $this->catalogKey);

        //add sites from order
        $resourceAcquisitionID = $resourceAcquisition->resourceAcquisitionID;
        $purchaseSites = $this->purchaseSites();
        $existingPurchaseSites = [];
        foreach ($resourceAcquisition->getPurchaseSites() as $site) {
            $existingPurchaseSites[$site->purchaseSiteID] = $site->shortName;
        }

        foreach ($this->distributionLibraries as $library){
            if(in_array($library, $existingPurchaseSites)) {
                continue;
            }
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
        return sprintf("Order #%s for Resource #%s (%s) %s", $this->orderId, $resource->resourceID, $resource->titleText, $createdNewOrder ? 'created' : 'updated');
    }

    public function toFlatArray() {
        return [$this->orderLibrary, $this->catalogKey, $this->orderId, $this->isxn, $this->title, $this->subsStartDate, $this->subsEndDate, $this->fundId, implode(',',$this->distributionLibraries), $this->vendor];
    }
}
