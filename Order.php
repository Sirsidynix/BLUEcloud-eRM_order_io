<?php

class Order extends BaseClass
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
     * acquisitionTypeId
     */
    public function getAcquisitionTypeId() {
        // Use a session variable so we don't have to query the DB for every order import
        if(isset($_SESSION['acquisitionTypeId'])) {
            return $_SESSION['acquisitionTypeId'];
        }
        // TODO: What should we set the acquisition type to?
        // Find the acquisition type that is paid. If not found, get the first entry from the sorted array
        $acquisitionTypeGetter = new AcquisitionType();
        $paidTypeId = $acquisitionTypeGetter->getAcquisitionTypeIDByName('paid');
        if(empty($paidTypeId)){
            $allTypes = $acquisitionTypeGetter->sortedArray();
            $paidTypeId = $allTypes[0]['acquisitionTypeID'];
        }
        $_SESSION['acquisitionTypeId'] = $paidTypeId;
        return $paidTypeId;
    }

    /*
     * enabledAlerts
     */
    public function getEnabledAlerts() {
        // Use a session variable so we don't have to read the config file for every order import
        if(isset($_SESSION['enableAlerts'])) {
            return $_SESSION['enableAlerts'];
        }
        $config = new Configuration();
        $enableAlerts = $config->settings->enableAlerts == 'Y' ? 1 : 0;
        $_SESSION['enableAlerts'] = $enableAlerts;
        return $enableAlerts;
    }

    /*
     * purchaseSites
     */
    public function purchaseSites() {
        // Use a session variable so we don't have to read the db for every order import
        $newSites = false;
        if(empty($_SESSION['purchaseSites'])) {
            $newSites = true;
        } else {
            foreach($this->distributionLibraries as $library) {
                if(!in_array($library, $_SESSION['purchaseSites'])) {
                    $newSite = new PurchaseSite();
                    $newSite->shortName = $library;
                    try {
                        $newSite->save();
                    } catch(Exception $e) {
                        throw new Exception("There was a problem creating a new purchase site $library. Error: ".$e->getMessage());
                    }
                    $newSites = true;
                }
            }
        }
        if($newSites) {
            $purchaseSitesGetter = new PurchaseSite();
            $purchaseSites = array();
            foreach($purchaseSitesGetter->allAsArray() as $site) {
                $purchaseSites[$site['purchaseSiteID']] = $site['shortName'];
            }
            $_SESSION['purchaseSites'] = $purchaseSites;
        }
        return $_SESSION['purchaseSites'];
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

        // Make a new resource acquisition entry
        $resourceAcquisition = new ResourceAcquisition();

        // Note: the following code should align with creating a new RA in coral, /resources/ajax_processing/submitAcquisitions.php
        $resourceAcquisition->resourceID = $resource->resourceID;
        $resourceAcquisition->subscriptionStartDate = $this->subsStartDate;
        $resourceAcquisition->subscriptionEndDate = $this->subsEndDate;
        $resourceAcquisition->acquisitionTypeID = $this->acquisitionTypeId;
        $resourceAcquisition->orderNumber = $this->orderId;
        // TODO: What is system number? Putting cat key
        $resourceAcquisition->systemNumber = $this->catalogKey;
        $resourceAcquisition->subscriptionAlertEnabledInd = $this->enabledAlerts;
        // TODO: what to do about organization
        $resourceAcquisition->organizationID = null;

        try {
            $resourceAcquisition->save();
        } catch(Exception $e) {
            throw new Exception("There was a problem creating a new order for $this->title. Please contact your administrator. Error: ".$e->getMessage());
        }

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
