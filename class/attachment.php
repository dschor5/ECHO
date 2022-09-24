<?php

/**
 * FileUpload objects represent any file attached to a message on the chat. 
 * Encapsulates 'msg_files' row from database.
 * 
 * Table Structure: msg_files
 * - message_id         (int)       Message id containing this attachment
 * - server_name        (string)    Name of file on the server
 * - original_name      (string)    Original filename
 * - mime_type          (string)    Mime type for attachment
 * 
 * Implementation Notes:
 * - Files uploaded are renamed with a unique id of numeric characters
 *   with extension TMP before being saved to the uploads directory. 
 * - The 'msg_files' database table is used to map the names:
 *      - original_name --> Original filename as uploaded by the user.
 *      - server_name   --> New name assiged by the application.
 * - Renaming helps to:
 *      1. Prevents accidentally overriding files if a user uploads 
 *         multiple version of the same file. 
 *      2. Hides upload file names to reduce the likelihood of someone 
 *         being able to read information they are not suppowed to see. 
 * 
 * 
 * @link https://github.com/dschor5/ECHO
 */
 class FileUpload extends ServerFile
{
    /**
     * FileUpload constructor. Appends object with field 'size' that
     * contains the size of the file in bytes.
     * 
     * @param array $data Row from 'msg_files' database table. 
     */
    public function __construct($data)
    {
        global $config;
        parent::__construct($data, $config['uploads_dir']);
    }

    /**
     * Get the file type from the object's mime-type. 
     * In general, this means differentiating files as: image, audio, video, or other (file). 
     * 
     * @return string File type.
     */
    public function getTemplateType() : string
    {
        $fileType = explode('/', $this->mime_type, 2)[0];
        if(!in_array($fileType, array('image', 'audio', 'video')))
        {
            $fileType = 'file';
        }
        return $fileType;
    }

}

?>