<?php

/**
 * ConversationArchiveMaker used to create large archives exceeeding 
 * the server memory limits. The class abstracts all the operations 
 * by wrapping the ZipArchive class. 
 * 
 * Implementation Notes:
 * - There are four concurrent arrays for archiveData, archiveZips, 
 *   archivePaths, archiveSizes. On initialization they are all empty. 
 *   Each time one archive is full (uncompressed data exceeds MAX_ARCHIVE_SIZE)
 *   then a new entry into the four arrays is created. 
 * - The last entry into the arrays is the current archive being modified. 
 * - Since the ZipArchive class does not allow you to see the compressed size
 *   until the file is closed, the class was implemented to break down files 
 *   based on the uncompressed size of the data. 
 * - The default settings in Apache/PHP defines the upper limit to use for 
 *   MAX_ARCHIVE_SIZE as 1.5G. For better performance it is better to keep 
 *   this at a lower value. The default for ECHO is 1G. 
 * - Only added wrappers for the ZipArchive functions needed. 
 * 
 * @link https://github.com/dschor5/ECHO
 */
class ConversationArchiveMaker
{
    /**
     * Max size of uncompressed data added to a single ZipArchive. 
     * See class implementation notes.
     * @access private
     * @var int
     */
   const MAX_ARCHIVE_SIZE = 1024*1024*1024;

   /**
    * Track success from creating a conversation archive.
    * @access private
    * @var bool 
    */
   private $success;

   /**
    * Associative array containing the archive data entry to 
    * enter into the databaase. 
    * @access private
    * @var array of strings
    */
   private $archiveData;

   /**
    * Array of ZipArchive objects. 
    * @access private
    * @var array of ZipArchives
    */
   private $archiveZips;

    /**
     * Array of paths to each archive created.
     * @access private
     * @var array of strings
     */
   private $archivePaths;

    /**
     * Array of uncompressed archive sizes. 
     * @access private
     * @var array of ints
     */
   private $archiveSizes;

   /**
    * Metadata to add to each archive created in the database.
    * @access private
    * @var string
    */
   private $dataNotes;

   /** 
    * Timezone selected for all the timestamps saved in the archive. 
    * @access private
    * @var string
    */
   private $dataTzSelected;

   /**
    * Timestamp used so that if there archive is broken into 3 parts
    * all the parts have the same timestamp. 
    * @access private
    * @var string
    */
   private $dataCurrTime;

   /**
    * ConversationArchiveMaker constructor. 
    *
    * @param string $notes Notes to add to each database entry.
    * @param string $tzSelected Timezone selected for the archive. 
    */
   public function __construct(string $notes, string $tzSelected)
   {
      // Flag to record success from the full operation. 
      $this->success = true;

      // Arrays to track all the archives created. 
      $this->archiveData  = array();
      $this->archiveZips  = array();
      $this->archivePaths = array();
      $this->archiveSizes = array();

      // Default values for all archives created
      $this->dataNotes      = $notes;
      $this->dataTzSelected = $tzSelected;
      $this->dataCurrTime   = (new DelayTime())->getTime();

      // Start a new archive. 
      $this->newArchive();
   }

   /**
    * Creates a new archive. 
    *
    * @return boolean Success
    */
   private function newArchive() : bool
   {
      global $config;
      global $server;

      // Create a new entry for the database.
      $newData = array(
         'archive_id'  => 0,
         'server_name' => ServerFile::generateFilename($config['logs_dir']),
         'notes'       => $this->dataNotes.' (part '.(count($this->archiveData)+1).')<br/>',
         'mime_type'   => 'application/zip',
         'timestamp'   => $this->dataCurrTime,
         'content_tz'  => $this->dataTzSelected,
      );

      // File path for new file
      $zipFilepath = $server['host_address'].$config['logs_dir'].'/'.$newData['server_name'];

      // Create a new file. 
      $newZip = new ZipArchive();

      // Open the new file. 
      if($newZip->open($zipFilepath, ZipArchive::CREATE)) 
      {
         $this->archiveData[]  = $newData;
         $this->archiveZips[]  = $newZip;
         $this->archivePaths[] = $zipFilepath;
         $this->archiveSizes[] = 0;
      }
      else
      {
         Logger::warning('archiveMaker::newArchive failed to create "'.$newData['server_name'].'"');
         $this->success = false;
      }      

      return $this->success;
   }

   /**
    * Check the size of the uncompressed data added to the current zip file. 
    * If it exceeds the MAX_ARCHIVE_SIZE, then close the current archive 
    * and start a new one. 
    *
    * @param int Size of new uncompressed file to add. 
    * @return void
    */
   private function checkZipSize(int $newFile) : void
   {
      if(end($this->archiveSizes) + $newFile >= ConversationArchiveMaker::MAX_ARCHIVE_SIZE)
      {
         end($this->archiveZips)->close();
         $this->newArchive();
      }
   }

   /**
    * Wrapper for ZipArchive::addEmptyDir. 
    *
    * @param string $folder
    * @return boolean Success
    */
   public function addEmptyDir(string $folder) : bool
   {
      // Assumed size for adding a new directory to the zip file.
      $extraSize = 4; 

      // Check if it needs to start a new archive. 
      $this->checkZipSize($extraSize);
      
      // Call funuction on ZipArchive. 
      if(($success = end($this->archiveZips)->addEmptyDir($folder)) == true)
      {
         $this->archiveSizes[count($this->archiveSizes)-1] += $extraSize;
      }

      return $success;
   }

   /**
    * Wrapper for ZipArchive::addFromString. 
    *
    * @param string $folder
    * @return boolean Success
    */
   public function addFromString(string $filename, string $text) : bool 
   {
      // Size of new text added + assumed overhead. 
      $extraSize = strlen($text) + 32; 

      // Check if it needs to start a new archive.
      $this->checkZipSize($extraSize);

      // Call function on ZipArchive.
      if(($success = end($this->archiveZips)->addFromString($filename, $text)) == true)
      { 
         $this->archiveSizes[count($this->archiveSizes)-1] += $extraSize;
      }

      return $success;
   }

   /**
    * Wrapper for ZipArchive::addFile. 
    *
    * @param string $folder
    * @return boolean Success
    */
   public function addFile(string $filepath, string $zippath) : bool 
   {
      // Size of new text added + assumed overhead. 
      $extraSize = filesize($filepath); 

      // Check if it needs to start a new archive.
      $this->checkZipSize($extraSize);

      // Call function on ZipArchive.
      if(file_exists($filepath) && ($success = end($this->archiveZips)->addFile($filepath, $zippath)) == true)
      {
         $this->archiveSizes[count($this->archiveSizes)-1] += $extraSize;
      }

      return $success;
   }

   /**
    * Close the current archive. Returns array of all database entries.
    *
    * @return array of strings.
    */
   public function close() : array
   {
      end($this->archiveZips)->close();

      // Add file size and MD5 for every archive created.
      for($i = 0; $i < count($this->archiveData); $i++)
      {
         $this->archiveData[$i]['notes'] .= 'Size: '.ServerFile::getHumanReadableSize(filesize($this->archivePaths[$i])).'<br/>'. 
                                  'MD5: '.md5_file($this->archivePaths[$i]);
      }

      return $this->archiveData;
   }

   /**
    * Delete all archives. Not called from within this class. 
    * This function is used externally to delete archives if
    * operations failed partway thorugh creating the ConversationArchive.
    *
    * @return void
    */
   public function deleteArchives() : void 
   {
      // Delete all files. 
      foreach($this->archivePaths as $file) 
      {
         // Only delete if it was actually created on the server. 
         if(file_exists($file))
         {
            unlink($file);
         }
         else
         {
            Logger::warning('ConversationArchiveMaker::deleteArchives tried to delete non-existing file.', 
               array($file));
         }
      }
   }
}

?>