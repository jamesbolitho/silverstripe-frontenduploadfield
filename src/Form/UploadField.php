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
use SilverStripe\Control\Director;

use SilverStripe\Assets\Folder;
use SilverStripe\Assets\File;
use SilverStripe\ORM\SS_List;

class UploadField extends \SilverStripe\AssetAdmin\Forms\UploadField
{
    private static $allowed_actions = [
        'upload',
		'remove'
    ];
    
    public static $allowedMaxFileSize;

    /**
     * Set the timeout (in milliseconds) allowed by dropzone
     * (defaults to Dropzone default of 30 seconds).
     * 
     * @var int
     */
    protected $timeout = 30000;
	
	/**
     * Helper to set image thumbnail types allowed by dropzone.
	 * This is different to allowed upload file types set by e.g. $upload->getValidator()->setAllowedExtensions(array('pdf','jpg','jpeg','gif'));
	 * string seperated by '|' e.g. jpg|jpeg|gif|png.
     * 
     * @var string
     */
    protected $thumbnailTypes = 'jpg|jpeg|gif|png';
    
     /**
     * Set thumbnail size for image preview
     *
     * return @array 
     */
    protected $thumbsizes = ["width" => 134, "height" => 134];
    
	 /**
     * Set custom url path for uploading
     *
     * return string
     */
    protected $customUploadUrl = null;
    
    /**
     * Set the ability to remove uploaded files.
     * 
     * @var bool
     */
    protected $removeFiles = false;

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
        Requirements::javascript('https://unpkg.com/dropzone@5/dist/min/dropzone.min.js');
        Requirements::css('https://unpkg.com/dropzone@5/dist/min/dropzone.min.css');
        Requirements::css('jamesbolitho/silverstripe-frontenduploadfield: resources/css/custom.css');
    
        //Check to see if data exists for this field in relation to uploaded files...
        //$data = Controller::curr()->getRequest()->getSession()->get("FormData.{$this->getForm()->Name}.data");
        
		$thumbsize = $this->getThumbSize();
		$files = "''";
		//if(isset($data[$this->name]['Files']))
        if($this->getItemIDs())
		{
			//$files = $this->uploadedFiles($data[$this->name]['Files']);
			$files = $this->uploadedFiles($this->getItemIDs());
	  	}
		
		//Check if Custom Upload URL is set to ensure that the correct upload and remove paths are set...
        if($this->getCustomUploadUrl())
        {
            $uploadURL = $this->getCustomUploadUrl("upload");
            $removeFileURL = $this->getCustomUploadUrl("remove");
        } 
        else
        {
            $uploadURL = $this->Link("upload");
            $removeFileURL = $this->Link("remove");
        }
        
       Requirements::customScript("
        (function($) {
			Dropzone.autoDiscover = false;
			var name = '" . $this->name . "';
				multipleUpload = '". $this->IsMultiUpload ."';
				token =	'".Controller::curr()->getRequest()->getSession()->get('SecurityID')."',
				fileurl = '".$uploadURL."',
                maxFileSize = '".$this->getAllowedMaxFileSize()."',
				allowedFileTypes = '".$this->getAcceptFileTypes()."',
				numberAllowed = '". $this->AllowedMaxFileNumber."',
				uploadedFiles = ".$files.",
                addRemoveLinks = '". $this->getRemoveFiles() ."',
                timeout = {$this->getTimeOut()},
                thumbnailWidth = {$thumbsize['width']},
                thumbnailHeight = {$thumbsize['height']},
                thumbnailImageTypes = '".$this->getThumbnailTypes()."';
			
			if(multipleUpload == 1){
				if(numberAllowed) var maxFilesAllowed = numberAllowed;
			} else {
				var maxFilesAllowed = '1';
			}
			
			var config = {};
			config['previewsContainer'] = '.' + name + '-previews-container';
			config['params'] = {SecurityID: token};
			config['url'] = fileurl;
            if(timeout) config['timeout'] = timeout;
			if(maxFileSize) config['maxFilesize'] = maxFileSize;
			if(maxFilesAllowed) config['maxFiles'] = maxFilesAllowed;
			if(allowedFileTypes) config['acceptedFiles'] = allowedFileTypes;
			config['thumbnailWidth'] = thumbnailWidth;
  			config['thumbnailHeight'] = thumbnailHeight;
			config['clickable'] = '#' + name + '-dropzone .droparea a';
			config['renameFilename'] = function (filename) {
        		return token + '-' + filename;
    		};
			config['success'] = function(file, response){
                addFileFieldID(response[0].id);
                if (file.previewElement) {
                    $(file.previewElement).attr('data-id', response[0].id);
                    return file.previewElement.classList.add('dz-success');
                }		
			};
	
			window[name+'Dropzone'] = new Dropzone('#' + name + '-dropzone .droparea', config);
			
			//Show files that have already been uploaded as per the form session data i.e. during server side form error.
			if(uploadedFiles) {
				$.each(uploadedFiles, function(index, value) {
					addFileFieldID(value.ID);
					var File = {'name': value.Name, 'size': value.Size, 'type': value.Type, 'dataURL': value.dataURL};
					window[name+'Dropzone'].emit('addedfile', File);
					
                    // Check to see if we are dealing with an image if not load generic icon
                    // Set a default thumbnail:
                    if(File.type.toLowerCase().match(thumbnailImageTypes)){
							window[name+'Dropzone'].createThumbnailFromUrl(File, thumbnailWidth, thumbnailHeight, 'crop', true, function(thumbnail){
                            window[name+'Dropzone'].emit('thumbnail', File, thumbnail);
                        });
                    } else {
						thumbnailForFiles(File, window[name+'Dropzone']);
                    }
                   
					window[name+'Dropzone'].emit('complete', File);
                    
                    $(File.previewElement).attr('data-id', value.ID);
					
                    var existingFileCount = 1; // The number of files already uploaded
					window[name+'Dropzone'].options.maxFiles = window[name+'Dropzone'].options.maxFiles - existingFileCount;
					
                    if(addRemoveLinks){
                        //create remove file buttons
                        removeFile(File, window[name+'Dropzone']);
                    }
				});
			}
			
			function addFileFieldID(ID){
				$('.dropzone.uploadfield-holder').append('<input type=\"hidden\" name=\"' + name + '[Files][]\" value=\"' + ID + '\" />');
			}
            
            /* Create a remove file function to allow the current uploaded files to be deleted from the temporary folder */
            window[name+'Dropzone'].on('addedfile', function(file) {
                if(addRemoveLinks){
                    removeFile(file, this);
                }
                
                thumbnailForFiles(file, this);
            });
            
            function thumbnailForFiles(file, dropzoneObject){
                if (!file.type.toLowerCase().match(thumbnailImageTypes)) {
                    // This is not an image, so Dropzone doesn't create a thumbnail.
                    // Set a default thumbnail:
                    dropzoneObject.emit('thumbnail', file, '" . Director::absoluteBaseURL() . "_resources/vendor/jamesbolitho/silverstripe-frontenduploadfield/resources/images/File-Icon.jpg');
					// add width and height to the image to ensure size remains the same as other images.
					var thumb = file.previewTemplate.querySelector('.dz-image img');
						thumb.style.width=thumbnailWidth + 'px';
						thumb.style.height=thumbnailHeight + 'px';
                }
            }
            
            function removeFile(file, dropzoneObject){
                // Create the remove button
                var removeButton = Dropzone.createElement('<button class=\"btn btn-primary btn-remove\">Remove</button>');
                var _this = dropzoneObject;

                // Listen to the click event
                removeButton.addEventListener('click', function(e) {
                  // Make sure the button click doesn't submit the form:
                  e.preventDefault();
                  e.stopPropagation();
                  
                  // do the AJAX request here.
				  var fileID = $(this).parent().data('id'),
                  	  postData = {SecurityID: token, ID: fileID};
                  $.ajax({
                        type: 'POST',
                        url: '". $removeFileURL ."',
                        data: postData,
                        success: function(data){
                            // Remove the file preview.
                            _this.removeFile(file);
							$('.dropzone.uploadfield-holder input[value='+ fileID +']').remove();
                        }
                    });
                  
                });

                // Add the button to the file preview element.
                file.previewElement.appendChild(removeButton);
            }
            
        }(jQuery));
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
                'message' => [
                    'type' => 'error',
                    'value' => $error,
                ]
            ];
            $this->getUpload()->clearErrors();
            return (new HTTPResponse(json_encode($result), 400))
                ->addHeader('Content-Type', 'application/json');
        }

		// We need an ID for getObjectFromData
        if (!$file->isInDB()) {
            $file->write();
        }
		
		$uploadedFileObject = AssetAdmin::singleton()->getObjectFromData($file);
		
		/* File needs to be published so doing a check here and publishing the file if necessary...
		*  Look into making this optional as could be an issue if you don't want files to be visible publicly...
		*/
		if($uploadedFileObject['published'] != true){
			$file = File::get()->byID((int) $uploadedFileObject['id']);
            if($file)
            {
                $file->publishSingle();
			}
		}
		
        // Return success response
        $result = [
			$uploadedFileObject
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
                $ext = File::get_file_extension($file->Filename);
				$files[] = ["ID" => $file->ID, "Name" => $file->Name, 'dataURL' => $file->getAbsoluteURL(), 'Size' => $size, 'Type' => $ext];
			}
		}
		return json_encode($files);
	}
    
    /*
    * Allow ability to remove uploaded files
    */
    public function remove(HTTPRequest $request)
    {
        if ($this->isDisabled() || $this->isReadonly()) {
            return $this->httpError(403);
        }
        
        // CSRF check
        $token = $this->getForm()->getSecurityToken();
        
        if (!$token->checkRequest($request)) {
            return $this->httpError(400);
        }
        
        if($id = $request->postVar('ID'))
        {
            $file = File::get()->byID((int) $id);
            if($file)
            {
                $file->deleteFromStage('Live');
                $file->deleteFromStage('Stage');
                $file->deleteFile();
                
                $result = [
                    'Success' => 'File Deleted'
                ];

                return (new HTTPResponse(json_encode($result)))
            ->addHeader('Content-Type', 'application/json');
            } else {
				$result = [
                    'Error' => 'File not removed!'
                ];
				return (new HTTPResponse(json_encode($result)))
            ->addHeader('Content-Type', 'application/json');
			}
        }
    }
    
    public function getAttributes()
    {
        $attributes = parent::getAttributes();
        unset($attributes['type']);
        return $attributes;
    }
    
    /**
     * Sets the custom upload url if forms url is manipulated by javascript for example...
     * @param $customUploadUrl
     * @return null | $this
     */
    public function getCustomUploadUrl($segment = null)
    {
		$link = $this->customUploadUrl;
        if($segment) $link .= '/' . $segment;
        return $link;
    }
    
     /**
     * Sets the custom upload url if forms url is manipulated by javascript for example...
     * @param $count
     * @return $this
     */
    public function setCustomUploadUrl($url)
    {
		$this->customUploadUrl = $url;
        
        return $this;
    } 
	
	/**
     * Sets the file size allowed for this field
     * @param $count
     * @return $this
     */
    public function getAllowedMaxFileSize()
    {
		$size = $this->getValidator()->getAllowedMaxFileSize();
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
    
    /**
     * Get the timeout allowed by dropzone (defaults to null/Dropzone default)
     *
     * @return int|null
     */ 
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Set the timeout allowed by dropzone (defaults to null/Dropzone default)
     *
     * @param int $timout Set the timeout in milliseconds
     *
     * @return self
     */ 
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }
    
	 /**
     * Get the thumbnailTypes allowed by dropzone
     *
     * @return string
     */
	public function getThumbnailTypes()
	{
		return $this->thumbnailTypes;
	}
	
	/**
     * Set the image thumbnail types allowed by dropzone
     *
     * @param string $thumbnailTypes set image extensions e.g. "jpg|jpeg|gif|png"
     *
     * @return self
     */ 
	public function setThumbnailTypes()
	{
		$this->thumbnailTypes = $thumbnailTypes;
		return $this;
	}

    /**
     * Get the thumbnail dimensions for the thumbnail preview
     *
     * @param array width and height
     *
     * @return self
     */ 
    public function getThumbSize()
    {
        return $this->thumbsizes;
    }
    
    public function setThumbSize(array $dimensions)
    {
        $this->thumbsizes = $dimensions;
        return $this;
    }
    
    /**
     * Get whether able to remove files from the upload field
     *
     * @return true | false
     */ 
    public function getRemoveFiles()
    {
        return $this->removeFiles;
    }

    /**
     * Set whether able to remove files from the upload field
     *
     * @param true | false
     *
     * @return self
     */ 
    public function setRemoveFiles(bool $allowRemoveFiles)
    {
        $this->removeFiles = $allowRemoveFiles;
        return $this;
    }
}
