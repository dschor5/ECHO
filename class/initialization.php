<?php

/**
 * Initialization utility for ECHO application.
 * Handles first-run setup and migration tasks.
 * 
 * @link https://github.com/dschor5/ECHO
 */
class Initialization
{
    /**
     * Config key for tracking initialization state
     */
    const INITIALIZED_FLAG = 'initialized';

    /**
     * Run initialization tasks if needed
     * Should be called early in application bootstrap
     * 
     * @return bool True if initialization was performed, false if already initialized
     */
    public static function init(): bool
    {
        try {
            $missionCfg = MissionConfig::getInstance();
            
            // Check if initialization has already been done
            $initialized = $missionCfg->initialized ?? false;
            if ($initialized) {
                return false;
            }

            // Run initialization tasks
            self::ensureConversationEncryptionKeys();
            
            // Mark initialization as complete
            $missionCfg->initialized = true;

            Logger::info('ECHO initialization completed');
            return true;
        } catch (Exception $e) {
            Logger::error('Initialization failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Ensure all conversations have encryption keys
     * Generates new keys for any conversations missing them
     * 
     * @return void
     */
    private static function ensureConversationEncryptionKeys(): void
    {
        $conversationsDao = ConversationsDao::getInstance();
        
        // Get all conversations
        $conversations = $conversationsDao->getConversations(null, true);
        
        if (empty($conversations)) {
            Logger::info('No conversations found during initialization');
            return;
        }

        $conversationsUpdated = 0;

        foreach ($conversations as $convoId => $conversation) {
            // Check if this conversation needs an encryption key
            if (empty($conversation->encryption_key)) {
                try {
                    // Generate a new encryption key
                    $conversation->generateEncryptionKey();
                    
                    // Update database
                    $conversationsDao->update(
                        ['encryption_key' => $conversation->encryption_key],
                        $convoId
                    );
                    
                    $conversationsUpdated++;
                    Logger::info('Generated encryption key for conversation', 
                        ['conversation_id' => $convoId]);
                } catch (Exception $e) {
                    Logger::error('Failed to generate encryption key for conversation',
                        ['conversation_id' => $convoId, 'error' => $e->getMessage()]);
                }
            }
        }

        if ($conversationsUpdated > 0) {
            Logger::info('Encryption initialization complete', 
                ['conversations_updated' => $conversationsUpdated]);
        }
    }
}

?>
