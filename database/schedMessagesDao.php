<?php

/**
 * Data Abstraction Object for the scheduled messages table. Implements custom 
 * queries to search and update conversations as needed. 
 * 
 * @link https://github.com/dschor5/ECHO
 */
class SchedMessagesDao extends Dao
{
   /**
    * Singleton instance for MessageDao object.
    * @access private
    * @var SchedMessagesDao
    **/
   private static $instance = null;

   /**
    * Returns singleton instance of this object. 
    * 
    * @return SchedMessagesDao
    */
   public static function getInstance()
   {
      if(self::$instance == null)
      {
         self::$instance = new SchedMessagesDao();
      }
      return self::$instance;
   }

   /**
    * Private constructor to prevent multiple instances of this object.
    **/
   protected function __construct()
   {
      parent::__construct('sched_messages', 'sched_message_id');
   }

   /**
    * Get scheduled messages
    * 
    * 
    */
   public function sendScheduledMessages()
   {      
      // Get lock to read scheduled messages
      $result = $this->database->query("SELECT GET_LOCK('sched', 1);");
      

      // Only proceed if it successed. Prevents messages from being added more htan once.
      if(($result->fetch_column()) == 1)
      {
         Logger::ERROR('Got lock!', array($result));

         $queryStr = 'SELECT sched_messages.* '. 
                     'FROM sched_messages '.
                     'WHERE sched_send_time <= UTC_TIMESTAMP(3) '. 
                     'ORDER BY sched_send_time ASC';
                  
         $schedMessages = array();

         // Get all messages
         if(($result = $this->database->query($queryStr)) !== false)
         {
            if($result->num_rows > 0)
            {
               while(($rowData=$result->fetch_assoc()) != null)
               {
                     $schedMessages[$rowData['sched_message_id']] = new SchedMessage($rowData);
               }
            }
         }

         // Delete scheduled messages once sent
         if(count($schedMessages) > 0)
         {
            $queryStr = 'DELETE FROM sched_messages WHERE sched_message_id IN ('.join(',',array_keys($schedMessages)).')';
            $this->database->query($queryStr);
         }

         // Release lock
         $result = $this->database->query("SELECT RELEASE_LOCK('sched');");

         // Send messages
         $messagesDao = MessagesDao::getInstance();
         foreach($schedMessages as $id => $schedMsg)
         {
            $messagesDao->sendMessage($schedMsg->from_crew, $schedMsg->getMsgArrayToSend());
         }
      }
   }
}

?>
