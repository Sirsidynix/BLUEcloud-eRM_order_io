<?php

class IO extends BaseClass
{

    protected $properties = [
        'catalogKey' => '',
        'isxn' => '',
        'title' => '',
        'subsStartDate' => '',
        'subsEndDate' => '',
        'fundId' => ''
    ];

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

    public function createNewResource()
    {

        $resource = new Resource();
        // set post values to be used by new resource script
        $_POST["resourceID"] = "";
        $_POST["resourceTypeID"] = "";
        $_POST["descriptionText"] = "";
        $_POST["providerText"] = "";
        $_POST["organizationID"] = "";
        $_POST["resourceURL"] = "";
        $_POST["resourceAltURL"] = "";
        $_POST["noteText"] = "";
        $_POST["orderTypes"] = "";
        $_POST["fundNames"] = "";
        $_POST["paymentAmounts"] = "";
        $_POST["currencyCodes"] = "";
        $_POST["resourceStatus"] = "progress";
        $_POST['years'] = "";
        $_POST['subStarts'] = "";
        $_POST['subEnds'] = "";
        $_POST['fundIDs'] = "";
        $_POST['paymentAmounts'] = "";
        $_POST['currencyCodes'] = "";
        $_POST['orderTypes'] = "";
        $_POST['costDetails'] = "";
        $_POST['costNotes'] = "";
        $_POST['invoices'] = "";

        $_POST["titleText"] = $this->title;

        // Find or set a resource format
        $formatGetter = new ResourceFormat();
        $formats = $formatGetter->sortedArray();
        $anyFormatId = 0;
        foreach($formats as $format) {
            if ($format['shortName'] == 'Any') {
                $anyFormatId = $format['resourceFormatID'];
            }
        }
        if($anyFormatId == 0) {
            $anyFormatId = $formats[0]['resourceFormatID'];
        }
        $_POST["resourceFormatID"] = $anyFormatId;

        // Find or set an acquisition type
        $atGetter = new AcquisitionType();
        $types = $atGetter->sortedArray();
        $anyTypeId = 0;
        foreach($types as $type) {
            if ($type['shortName'] == 'Paid') {
                $anyTypeId = $type['acquisitionTypeID'];
            }
            if ($type['shortName'] == 'Any' && $anyTypeId == 0) {
                $anyTypeId = $type['acquisitionTypeID'];
            }
        }
        if($anyTypeId == 0) {
            $anyTypeId = $types[0]['acquisitionTypeID'];
        }
        $_POST["acquisitionTypeID"] = $anyTypeId;

        $loginID = null;
        $resourceID = null;
        // if the path to the submit new resource changes, this will need to change as well
        include CORAL_INSTALL_DIR.'resources/ajax_processing/submitNewResource.php';
        $resource->setIsbnOrIssn([$this->isxn]);
        $resource->save();

        return $resource;
    }
}
