<?php
namespace jamesbolitho\frontenduploadfield;
use SilverStripe\ORM\DataExtension;

class FrontendUploadFieldFileExtension extends DataExtension
{
    private static $db = [
        "FrontEndUploadKey" => "Varchar(40)"
    ];

    /*
    * Generate key used to identify file uploaded through front end uploaders
    */
    public function generateFrontEndUploadKey() {
        $hash = sha1(rand());
        $this->owner->FrontEndUploadKey = $hash;
        $this->owner->write();
        $this->owner->publishSingle();
    }

    /*
    * Remove key used to identify file uploaded through front end uploaders
    */
    public function removeFrontEndUploadKey() {
        $this->owner->FrontEndUploadKey = '';
        $this->owner->write();
        $this->owner->publishSingle();
    }
}