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
            self::ensureMissionConfigDefaults();
            self::ensureMissionChat();
            self::ensureAdminUser();
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
     * Ensure all required mission config keys are present in the DB.
     * If missing, create them with safe defaults.
     *
     * @return void
     */
    private static function ensureMissionConfigDefaults(): void
    {
        $missionCfg = MissionConfig::getInstance();
        $defaults = array(
            'name' => 'Analog Mission Name',
            'date_start' => '2021-08-10 00:00:00',
            'date_end' => '2021-11-10 00:00:00',
            'mcc_name' => 'Mission Control',
            'mcc_planet' => 'Earth',
            'mcc_user_role' => 'Mission Control',
            'mcc_timezone' => 'America/New_York',
            'hab_name' => 'Analog Habitat',
            'hab_planet' => 'Mars',
            'hab_user_role' => 'Astronaut',
            'hab_timezone' => 'America/Chicago',
            'hab_day_name' => 'Mission Day',
            'delay_type' => 'manual',
            'delay_config' => array(array('ts' => '2021-01-01 00:00:00', 'eq' => 0)),
            'login_timeout' => 3600,
            'feat_audio_notification' => true,
            'feat_badge_notification' => true,
            'feat_unread_msg_counts' => true,
            'feat_convo_list_order' => true,
            'feat_est_delivery_status' => true,
            'feat_progress_bar' => true,
            'feat_markdown_support' => true,
            'feat_important_msgs' => true,
            'feat_convo_threads' => true,
            'feat_convo_threads_all' => true,
            'feat_saved_messages' => true,
            'feat_out_of_seq' => true,
            'debug' => false,
            'initialized' => false,
        );

        foreach ($defaults as $name => $value) {
            if (!isset($missionCfg->$name)) {
                $missionCfg->$name = $value;
                Logger::info('Inserted default mission config', ['name' => $name, 'value' => $value]);
            }
        }
    }

    /**
     * Ensure the Mission Chat conversation exists and has participants.
     *
     * @return void
     */
    private static function ensureMissionChat(): void
    {
        $conversationsDao = ConversationsDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();
        $usersDao = UsersDao::getInstance();

        $conversations = $conversationsDao->getConversations(null, true);
        $hasMissionChat = false;
        foreach ($conversations as $conversation) {
            if (trim($conversation->name) === 'Mission Chat') {
                $hasMissionChat = true;
                break;
            }
        }

        if (!$hasMissionChat) {
            $currTime = (new DelayTime())->getTime();
            $convoId = $conversationsDao->insert(array(
                'name' => 'Mission Chat',
                'parent_conversation_id' => null,
                'date_created' => $currTime,
                'last_message_mcc' => $currTime,
                'last_message_hab' => $currTime,
            ));

            if ($convoId !== false) {
                $users = $usersDao->getUsers();
                $participants = array();
                foreach ($users as $user) {
                    $participants[] = array('conversation_id' => $convoId, 'user_id' => $user->user_id);
                }
                if (!empty($participants)) {
                    $participantsDao->insertMultiple($participants);
                }

                Logger::info('Mission Chat created during initialization', ['conversation_id' => $convoId]);
            }
        }
    }

    /**
     * Ensure admin user exists; create with default password if missing.
     *
     * @return void
     */
    private static function ensureAdminUser(): void
    {
        global $admin;

        $usersDao = UsersDao::getInstance();
        $existingAdmin = $usersDao->getByUsername('admin');

        if ($existingAdmin !== null) {
            return;
        }

        $defaultPassword = $admin['default_password'] ?? 'ChangeMe123!';
        $validation = User::validatePasswordComplexity($defaultPassword, 'admin');
        if (!$validation['valid']) {
            throw new Exception('Default admin password does not meet complexity requirements: ' . implode(', ', $validation['errors']));
        }

        $newUserId = $usersDao->createNewUser(array(
            'username' => 'admin',
            'alias' => 'Admin',
            'password' => User::encryptPassword($defaultPassword),
            'session_id' => null,
            'is_admin' => 1,
            'is_crew' => 0,
            'last_login' => null,
            'is_password_reset' => 1,
            'is_active' => 1,
            'preferences' => '',
            'failed_attempts' => 0,
            'lockout_until' => null,
            'last_failed_attempt' => null,
        ));

        if ($newUserId > 0) {
            Logger::info('Created default admin user during initialization', ['user_id' => $newUserId]);
        } else {
            throw new Exception('Failed to create default admin user during initialization');
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
