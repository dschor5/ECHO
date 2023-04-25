<?php

/**

 */
class ConversationArchiveMaker
{
   const MAX_ARCHIVE_SIZE = 1024*1024*1024;

   private $success;

   private $archiveData;
   private $archiveZips;
   private $archivePaths;
   private $archiveSizes;

   private $dataNotes;
   private $dataTzSelected;
   private $dataCurrTime;

   public function __construct(string $notes, string $tzSelected)
   {
      $this->success = true;

      $this->archiveData  = array();
      $this->archiveZips  = array();
      $this->archivePaths = array();
      $this->archiveSizes = array();

      $this->dataNotes      = $notes;
      $this->dataTzSelected = $tzSelected;
      $this->dataCurrTime   = (new DelayTime())->getTime();

      $this->newArchive();
   }

   private function newArchive() : bool
   {
      global $config;
      global $server;

      $newData = array(
         'archive_id'  => 0,
         'server_name' => ServerFile::generateFilename($config['logs_dir']),
         'notes'       => $this->dataNotes.' (part '.(count($this->archiveData)+1).')',
         'mime_type'   => 'application/zip',
         'timestamp'   => $this->dataCurrTime,
         'content_tz'  => $this->dataTzSelected,
      );

      $zipFilepath = $server['host_address'].$config['logs_dir'].'/'.$newData['server_name'];

      $newZip = new ZipArchive();
      if(!$newZip->open($zipFilepath, ZipArchive::CREATE)) 
      {
         Logger::warning('archiveMaker::newArchive failed to create "'.$newData['server_name'].'"');
         $this->success = false;
      }
      else
      {
         $this->archiveData[]  = $newData;
         $this->archiveZips[]  = $newZip;
         $this->archivePaths[] = $zipFilepath;
         $this->archiveSizes[] = 0;
      }

      return $this->success;
   }

   private function checkZipSize() 
   {
      if(end($this->archiveSizes) >= ConversationArchiveMaker::MAX_ARCHIVE_SIZE)
      {
         end($this->archiveZips)->close();
         $this->newArchive();
      }
   }

   public function addEmptyDir(string $folder) : bool
   {
      $this->checkZipSize();
      
      if(($success = end($this->archiveZips)->addEmptyDir($folder)) == true)
      {
         $this->archiveSizes[count($this->archiveSizes)-1] += 4;
      }

      return $success;
   }

   public function addFromString(string $filename, string $text) : bool 
   {
      $this->checkZipSize();

      if(($success = end($this->archiveZips)->addFromString($filename, $text)) == true)
      { 
         $this->archiveSizes[count($this->archiveSizes)-1] += strlen($text) + 32;
      }

      return $success;
   }

   public function addFile(string $filepath, string $zippath) : bool 
   {
      $this->checkZipSize();

      if(($success = end($this->archiveZips)->addFile($filepath, $zippath)) == true)
      {
         $this->archiveSizes[count($this->archiveSizes)-1] += filesize($filepath);
      }

      return $success;
   }

   public function close() : array
   {
      end($this->archiveZips)->close();
      return $this->archiveData;
   }

   public function deleteArchives() 
   {
      foreach($this->archivePaths as $file) 
      {
         unlink($file);
      }
   }
}

?>