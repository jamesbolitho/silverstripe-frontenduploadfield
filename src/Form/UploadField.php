<?php

/**
 * UploadField
 **/

namespace jamesbolitho\frontenduploadfield;

use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\FileField;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Controller;

use SilverStripe\Assets\Folder;
use SilverStripe\Assets\File;

use SilverStripe\Dev\Debug;
use SilverStripe\Dev\Backtrace;

class UploadField extends \SilverStripe\AssetAdmin\Forms\UploadField
{
    private static $allowed_actions = [
        'upload',
    ];
	
	public static $allowedMaxFileSize;

    public function __construct($name, $title = null, SS_List $items = null)
    {
        parent::__construct($name, $title, $items);
	
        $this->setAttribute('data-schema', '');
        $this->setAttribute('data-state', '');
        $this->setAttribute('name', $name);

    }
	
	/**
	* @param array $properties
	* @return string
	*/
    public function Field($properties = array())
    {
        $field = parent::Field($properties);
        Requirements::javascript('jamesbolitho/silverstripe-frontenduploadfield: resources/javascript/dropzone.js');
        Requirements::css('jamesbolitho/silverstripe-frontenduploadfield: resources/css/dropzone.css');
        Requirements::css('jamesbolitho/silverstripe-frontenduploadfield: resources/css/custom.css');
	
		//Check to see if data exists for this field in relation to uploaded files...
		$data = Controller::curr()->getRequest()->getSession()->get("FormData.{$this->getForm()->Name}.data");
		$files = "''";
		if(isset($data[$this->name]['Files'])){
		  	$files = $this->uploadedFiles($data[$this->name]['Files']);
	  	}
		
		Requirements::customScript("
			Dropzone.autoDiscover = false;
			var name = '" . $this->name . "';
				multipleUpload = '". $this->IsMultiUpload ."';
				token =	'".Controller::curr()->getRequest()->getSession()->get('SecurityID')."',
				fileurl = '".$this->Link()."/upload',
				maxFileSize = '".$this->getAllowedMaxFileSize()."',
				allowedFileTypes = '".$this->getAcceptFileTypes()."',
				numberAllowed = '". $this->AllowedMaxFileNumber."',
				uploadedFiles = ".$files.";
			
			if(multipleUpload == 1){
				if(numberAllowed) var maxFilesAllowed = numberAllowed;
			} else {
				var maxFilesAllowed = '1';
			}
			
			var config = {};
			config['previewsContainer'] = '.' + name + '-previews-container';
			config['params'] = {SecurityID: token};
			config['url'] = fileurl;
			if(maxFileSize) config['maxFilesize'] = maxFileSize;
			if(maxFilesAllowed) config['maxFiles'] = maxFilesAllowed;
			if(allowedFileTypes) config['acceptedFiles'] = allowedFileTypes;
			config['clickable'] = '#' + name + '-dropzone .droparea a';
			config['success'] = function(file, response){
					addFileFieldID(response[0].id);
					if (file.previewElement) {
            			return file.previewElement.classList.add('dz-success');
          			}		
				};
	
			window[name+'Dropzone'] = new Dropzone('#' + name + '-dropzone .droparea', config);
			
			//Show files that have already been uploaded as per the form session data i.e. during server side form error.
			if(uploadedFiles) {
				$.each(uploadedFiles, function(index, value) {
					console.log(value.ID)
					addFileFieldID(value.ID);
					var File = { 'name': value.Name, 'size': value.Size};
					window[name+'Dropzone'].emit('addedfile', File);
					window[name+'Dropzone'].emit('thumbnail', File, value.URL);
					window[name+'Dropzone'].createThumbnailFromUrl(value.Name, value.URL);
					window[name+'Dropzone'].emit('complete', File);
					var existingFileCount = 1; // The number of files already uploaded
					window[name+'Dropzone'].options.maxFiles = window[name+'Dropzone'].options.maxFiles - existingFileCount;
					
				});
			}
			
			function addFileFieldID(ID){
				$('.dropzone .placeholder').append('<input type=\"hidden\" name=\"' + name + '[Files][]\" value=\"' + ID + '\" />');
			}
		");
		
        return $field;
    }
	
    public function Type()
    {
        return "frontenduploadfield uploadfield";
    }

    public function upload(HTTPRequest $request)
    {
		if ($this->isDisabled() || $this->isReadonly()) {
            return $this->httpError(403);
        }

        // CSRF check
        $token = $this->getForm()->getSecurityToken();
        if (!$token->checkRequest($request)) {
            return $this->httpError(400);
        }

        $tmpFile = $request->postVar('file');
        /** @var File $file */
        $file = $this->saveTemporaryFile($tmpFile, $error);

        // Prepare result
        if ($error) {
            $result = [
                'error' => $error,
            ];
            $this->getUpload()->clearErrors();
            return (new HTTPResponse(json_encode($result), 400))
                ->addHeader('Content-Type', 'application/json');
        }

        // Return success response
        $result = [
            AssetAdmin::singleton()->getObjectFromData($file)
        ];

        // Don't discard pre-generated client side canvas thumbnail
        if ($result[0]['category'] === 'image') {
            unset($result[0]['thumbnail']);
        }
        $this->getUpload()->clearErrors();
        return (new HTTPResponse(json_encode($result)))
            ->addHeader('Content-Type', 'application/json');
    }
	
	/* If files have already been uploaded we need to specify which files have been uploaded to ensure that people can not upload more than they are supposed to if limits are set */ 
	public function uploadedFiles($ids = []){
		if(empty($ids)) return false;
		$files = [];
		foreach($ids as $id) {
			if($file = File::get()->byID((int) $id)) {
				$size = $file->ini2bytes($file->getSize());				
				$files[] = ["ID" => $file->ID,"Name" => $file->Name, 'URL' => $file->getAbsoluteURL(), 'Size' => $size];
			}
		}
		return json_encode($files);
	}	
	
    public function getAttributes()
    {
        $attributes = parent::getAttributes();

        unset($attributes['type']);

        return $attributes;
    }
	
	/**
     * Sets the file size allowed for this field
     * @param $count
     * @return $this
     */
    public function getAllowedMaxFileSize()
    {
      $size = $this->getValidator()->getAllowedMaxFileSize();
	  //Debug::show($size / 1024 / 1024);
		
	  if($size){
		  $sizeMB = $size / 1024 / 1024;
		  return $sizeMB;
	  }
    }
	
	/**
     * Returns a list of file extensions (and corresponding mime types) that will be accepted
     *
     * @return array
     */
    protected function getAcceptFileTypes()
    {
        $extensions = $this->getValidator()->getAllowedExtensions();
        if (!$extensions) {
            return [];
        }
		$extentionString = "";
		$i = 0;
		foreach ($extensions as $extension) {
			if ($i == 0){
				$extentionString .= ".{$extension}";
			} else {
				$extentionString .= ", .{$extension}";
			}
			$i++;
		}
		return $extentionString;
    }
}