<?php

/**
 * ConversationArchiveMaker used to create large archives exceeeding 
 * the server memory limits. The class abstracts all the operations 
 * by wrapping the ZipArchive class. 
 * 
 * Implementation Notes:
 * - There are four concurrent arrays for archiveData, archiveZips, 
 *   archivePaths, archiveSizes. On initialization they are all empty. 
 *   Each time one archive is full (uncompressed data exceeds MAX_ARCHIVE_SIZE
 *   or the number of files in a single zip exceeds MAX_FILES_PER_ARCHIVE)
 *   then a new entry into the four arrays is created. 
 * - The last entry into the arrays is the current archive being modified. 
 * - Since the ZipArchive class does not allow you to see the compressed size
 *   until the file is closed, the class was implemented to break down files 
 *   based on the uncompressed size of the data. 
 * - The default settings in Apache/PHP defines the upper limit to use for 
 *   MAX_ARCHIVE_SIZE as 1.5G. For better performance it is better to keep 
 *   this at a lower value. The default for ECHO is 1G. 
 * - The default settings in PHP allows for at most 1000 files to be 
 *   tracked at a time. Since the ZipArchive keeps all the files opened 
 *   until it finishes compressing and closing the archive, then 
 *   the upper limit for MAX_FILES_PER_ARCHIVE is 1000. However, for better 
 *   performance it is better to keep this at a default of 500 files. 
 * - Only added wrappers for the ZipArchive functions needed. 
 * - Depending on the number of files to add, the zip creation can take 
 *   minutes to complete. Thus, the constructor requires that you know apriori
 *   how many files you will need to add (total for all archives). Then, internally
 *   it tracks the status that can be used to send progress indicators 
 *   to the user. 
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
   const MAX_ARCHIVE_SIZE = 1024*1024*1024; // bytes = 1GB
   
   /**
     * Max execution time for script. Will overwrite PHP.ini settings.
     * @access private
     * @var int
     */
   const MAX_EXECUTION_TIME = 1200; // sec

   /**
    * Maximum number of files per archive. 
    * PHP documentation recommends keeping this below 1000. 
    * @access private 
    * @var int
    */
   const MAX_FILES_PER_ARCHIVE = 500; 

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
    * Curr ZipArchive object. 
    * @access private
    * @var ZipArchive
    */
   private $currZip;

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
     * Array of number of files added to teh archive.
     * @access private
     * @var array of ints
     */
   private $archiveNumFiles;

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
    * Track progress while creating the archive.
    * @access private
    * @var array
    */
   private $status;

   /**
    * ConversationArchiveMaker constructor. 
    *
    * @param string $notes Notes to add to each database entry.
    * @param string $tzSelected Timezone selected for the archive. 
    * @param int $totalMsg Total number of files that need to be processed. 
    *    Count +1 for each file/video/audio attachment +1 for each HTML conversation.
    */
   public function __construct(string $notes, string $tzSelected, int $totalMsgs)
   {
      // Flag to record success from the full operation. 
      $this->success = true;

      // Arrays to track all the archives created. 
      $this->archiveData     = array();
      $this->archivePaths    = array();
      $this->archiveSizes    = array();
      $this->archiveNumFiles = array();

      // Initialize current zip file.
      $this->currZip = null;

      // Default values for all archives created
      $this->dataNotes      = $notes;
      $this->dataTzSelected = $tzSelected;
      $this->dataCurrTime   = (new DelayTime())->getTime();

      // Increase max execution time. 
      if(ini_set('max_execution_time', ConversationArchiveMaker::MAX_EXECUTION_TIME) === false)
      {
         Logger::warning('ConversationArchiveMaker::__construct - Could not change max_execution_time.');
      }

      // Start a new archive. 
      $this->newArchive();

      // Set array to store information on current archive
      $this->status = array(
         'currCount'  => 0,
         'totalCount' => $totalMsgs, 
         'date'       => (new DelayTime())->getTime()
      );
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

      if($this->currZip == null)
      {
         // Create a new file. 
         $this->currZip = new ZipArchive();

         // Open the new file. 
         if($this->currZip->open($zipFilepath, ZipArchive::CREATE)) 
         {
            $this->archiveData[]  = $newData;
            $this->archivePaths[] = $zipFilepath;
            $this->archiveSizes[] = 0;
            $this->archiveNumFiles[] = 0;
         }
         else
         {
            Logger::warning('ConversationArchiveMaker::newArchive - Failed to create "'.$newData['server_name'].'"');
            $this->currZip = null;
            $this->success = false;
         }      
      }
      else
      {
         Logger::warning('ConversationArchiveMaker::newArchive - Improper initialization.');
         $this->currZip = null;
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
      if(end($this->archiveSizes) + $newFile >= ConversationArchiveMaker::MAX_ARCHIVE_SIZE ||
         end($this->archiveNumFiles) >= ConversationArchiveMaker::MAX_FILES_PER_ARCHIVE)
      {
         if($this->currZip != null)
         {
            // PHP documentation says ZipArchive will not close properly if it has 0 files. 
            if($this->currZip->count() > 0)
            {
               if(!$this->currZip->close())
               {
                  Logger::error('ConversationArchiveMaker::checkZipSize - Reported "'.$this->currZip->getStatusString().'"');
               }
            }
            else
            {
               Logger::warning('ConversationArchiveMaker::checkZipSize - Cannot close file with 0 files.');
            }

            $this->currZip = null;
         }
         else
         {
            Logger::error('ConversationArchiveMaker::checkZipSize - Called on null archive.');
         }
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
      $success = false;

      // Assumed size for adding a new directory to the zip file.
      $extraSize = 16; 

      // Check if it needs to start a new archive. 
      $this->checkZipSize($extraSize);
      
      // Call funuction on ZipArchive. 
      if($this->currZip != null && ($success = $this->currZip->addEmptyDir($folder)) === true)
      {
         $this->archiveSizes[count($this->archiveSizes)-1] += $extraSize;
      }
      else
      {
         Logger::error('ConversationArchiveMaker::addEmptyDir - Reported "'.$this->currZip->getStatusString().'"');
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
      $success = false;

      // Size of new text added + assumed overhead. 
      $extraSize = strlen($text) + 256; 

      // Check if it needs to start a new archive.
      $this->checkZipSize($extraSize);

      if($this->currZip != null && ($success = $this->currZip->addFromString($filename, $text)) === true)
      { 
         $this->archiveSizes[count($this->archiveSizes)-1] += $extraSize;
         $this->archiveNumFiles[count($this->archiveSizes)-1]++;
         $this->status['currCount']++;
      }
      else
      {
         Logger::error('ConversationArchiveMaker::addFromString - Reported "'.$this->currZip->getStatusString().'"');
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
      $success = false;

      // Size of new text added + assumed overhead. 
      $extraSize = filesize($filepath); 

      // Check if it needs to start a new archive.
      $this->checkZipSize($extraSize);

      // Call function on ZipArchive.
      if($this->currZip != null && file_exists($filepath) && ($success = $this->currZip->addFile($filepath, $zippath)) === true)
      {
         $this->archiveSizes[count($this->archiveSizes)-1] += $extraSize;
         $this->archiveNumFiles[count($this->archiveSizes)-1]++;
         $this->status['currCount']++;
      }
      else
      {
         Logger::error('ConversationArchiveMaker::addFile - Reported "'.$this->currZip->getStatusString().'"');
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
      if($this->currZip != null)
      {
         if($this->currZip->count() > 0)
         {
            if(!$this->currZip->close())
            {
               Logger::error('ConversationArchiveMaker::close - Reported "'.$this->currZip->getStatusString().'"');
            }
         }
         else
         {
            Logger::warning('ConversationArchiveMaker::close - Cannot call on empty zip.');
         }
         
         $this->currZip = null;
      }
      else
      {
         Logger::error('ConversationArchiveMaker::close - Called on null archive.');
      }

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

   /**
    * Get the status of the current archive creation.
    *
    * @return array
    */
   public function getDownloadStatus() : array 
   {
      return $this->status;
   }
}

?>