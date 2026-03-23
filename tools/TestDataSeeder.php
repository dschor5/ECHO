<?php

declare(strict_types=1);

/**
 * Standalone CLI utility to generate test chat data.
 *
 * Usage:
 *   php tools/TestDataSeeder.php
 */
class TestDataSeeder
{
    public static int $NUM_USERS = 10;
    public static int $MAIN_CHAT_MESSAGES = 200;
    public static int $NUM_THREADS = 2;
    public static int $THREAD_MESSAGES_MIN = 40;
    public static int $THREAD_MESSAGES_MAX = 50;
    public static int $PRIVATE_MESSAGES_MIN = 20;
    public static int $PRIVATE_MESSAGES_MAX = 40;

    public static ?DateTimeImmutable $START_DATE = null;
    public static ?DateTimeImmutable $END_DATE = null;
    public static ?DateTimeImmutable $MISSION_START_DATE = null;
    public static ?DateTimeImmutable $MISSION_END_DATE = null;

    private const MAIN_CHAT_ID = 1;
    private const OWLT_SECONDS = 60;

    /** @var array<int, int> user_id => is_crew(0/1) */
    private static array $generatedUsers = array();

    /** @var array<int, string> conversation_id => raw binary encryption key */
    private static array $conversationKeys = array();

    /** @var array<int, array<int, int>> conversation_id => (user_id => is_crew) */
    private static array $conversationParticipants = array();

    /** @var array<int, array<int, int>> conversation_id => from_crew(0|1) => max_alt */
    private static array $altIdCounters = array();

    public static function run(): void
    {
        self::bootstrap();
        self::initDateRange();

        echo "Clearing existing data...\n";
        self::clearData();
        echo "Data cleared.\n";

        echo "Creating users...\n";
        self::createUsers();
        echo "Created " . count(self::$generatedUsers) . " users.\n";

        echo "Adding generated users to Mission Chat and existing threads...\n";
        self::addGeneratedUsersToGlobalConversations();

        echo "Creating threads...\n";
        $threadIds = self::createThreads();
        echo "Created " . count($threadIds) . " threads under Mission Chat.\n";

        echo "Creating private conversations for generated users...\n";
        $privateConvoIds = self::createPrivateConversationsForGeneratedUsers();
        echo "Created " . count($privateConvoIds) . " private conversations among generated users.\n";

        echo "Creating private conversations with existing users...\n";
        $existingUserConvoIds = self::createPrivateConversationsWithExistingUsers();
        echo "Created " . count($existingUserConvoIds) . " private conversations with existing users.\n";
        $privateConvoIds = array_merge($privateConvoIds, $existingUserConvoIds);

        echo "Preparing message plan...\n";
        $plan = self::buildMessagePlan($threadIds, $privateConvoIds);
        echo "Planned " . count($plan) . " messages.\n";

        self::prepareConversationData(array_unique(array_map(function (array $item): int {
            return $item['conversation_id'];
        }, $plan)));

        echo "Inserting messages...\n";
        $inserted = self::insertPlannedMessages($plan);
        echo "Inserted {$inserted} messages.\n";

        echo "Updating conversation timestamps...\n";
        self::updateConversationLastMessageTimes(array_unique(array_map(function (array $item): int {
            return $item['conversation_id'];
        }, $plan)));

        echo "Done.\n";
    }

    /**
     * Delete all users (except user_id=1) and their associated conversations and messages.
     * Leaves the Mission Chat (conversation_id=1) intact but empty.
     */
    private static function clearData(): void
    {
        $messagesDao = MessagesDao::getInstance();
        $conversationsDao = ConversationsDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();
        $usersDao = UsersDao::getInstance();

        // Clear all messages and thread conversations.
        $messagesDao->clearMessagesAndThreads();

        // Delete all root conversations except the Mission Chat (id=1).
        // This covers private chats seeded for/between any users.
        $conversationsDao->drop('conversation_id != 1 AND parent_conversation_id IS NULL');

        // Safety net: remove any remaining participant rows for non-admin users
        // (e.g. if FK cascades did not handle the conversation deletion above).
        $participantsDao->drop('user_id != 1');

        // Delete all users except user_id=1.
        $usersDao->drop('user_id != 1');
    }

    private static function bootstrap(): void
    {
        error_reporting(E_ALL);
        date_default_timezone_set('UTC');

        $projectRoot = dirname(__DIR__);

        // Ensure relative includes in config/server files resolve from repo root,
        // even when invoked from the tools/ directory.
        chdir($projectRoot);

        require_once $projectRoot . '/config.inc.php';

        // config.inc.php is loaded inside this method scope; promote expected
        // arrays to true globals so DAO classes can access them via `global`.
        foreach (array('server', 'database', 'admin', 'config') as $key) {
            if (isset($$key)) {
                $GLOBALS[$key] = $$key;
            }
        }

        if (
            !isset($GLOBALS['database']) ||
            !is_array($GLOBALS['database']) ||
            !isset($GLOBALS['database']['db_user'], $GLOBALS['database']['db_pass'], $GLOBALS['database']['db_name'], $GLOBALS['database']['db_host'])
        ) {
            throw new RuntimeException('Database config is missing or incomplete after bootstrap.');
        }
    }

    private static function initDateRange(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        // Mission runs from 2 days before execution to 2 days after
        self::$MISSION_START_DATE = $now->modify('-2 days');
        self::$MISSION_END_DATE = $now->modify('+2 days');

        // Messages span from mission start through 1 hour after execution
        self::$START_DATE = self::$MISSION_START_DATE;
        self::$END_DATE = $now->modify('+1 hour');
    }

    private static function createUsers(): void
    {
        $usersDao = UsersDao::getInstance();
        $token = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('YmdHis');

        for ($i = 1; $i <= self::$NUM_USERS; $i++) {
            $isCrew = ($i % 2 === 0) ? 1 : 0;
            $username = 'seed_' . $token . '_' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $alias = 'Seed User ' . $i;
            $password = 'SeedPass!' . $token . 'Aa' . $i;

            $newUserId = $usersDao->insert(array(
                'username' => $username,
                'alias' => $alias,
                'password' => User::encryptPassword($password),
                'session_id' => null,
                'is_admin' => 0,
                'is_crew' => $isCrew,
                'last_login' => null,
                'is_password_reset' => 0,
                'is_active' => 1,
                'preferences' => '',
                'failed_attempts' => 0,
                'lockout_until' => null,
                'last_failed_attempt' => null,
            ));

            if ($newUserId === false || $newUserId <= 0) {
                throw new RuntimeException('Failed to create generated user #' . $i);
            }

            self::$generatedUsers[$newUserId] = $isCrew;
        }
    }

    private static function addGeneratedUsersToGlobalConversations(): void
    {
        $conversationsDao = ConversationsDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();

        $globalConvos = $conversationsDao->getGlobalConvos();
        $rows = array();

        foreach (array_keys(self::$generatedUsers) as $userId) {
            foreach ($globalConvos as $convoId) {
                $rows[] = array(
                    'conversation_id' => (int)$convoId,
                    'user_id' => (int)$userId,
                );
            }
        }

        if (!empty($rows)) {
            $participantsDao->insertMultiple($rows);
        }
    }

    /**
     * @return array<int> Thread conversation IDs
     */
    private static function createThreads(): array
    {
        $conversationsDao = ConversationsDao::getInstance();
        $conversations = $conversationsDao->getConversations(null, true);

        if (!isset($conversations[self::MAIN_CHAT_ID])) {
            throw new RuntimeException('Mission chat (conversation_id=1) not found.');
        }

        $mainConversation = $conversations[self::MAIN_CHAT_ID];
        $threadIds = array();

        for ($i = 1; $i <= self::$NUM_THREADS; $i++) {
            $threadName = 'Seed Thread ' . $i . ' ' . self::$START_DATE->format('YmdHis');
            $threadId = $conversationsDao->newThread($mainConversation, $threadName);

            if ($threadId === false || $threadId <= 0) {
                throw new RuntimeException('Failed creating test thread #' . $i);
            }

            $threadIds[] = $threadId;

            // Refresh parent object so subsequent newThread calls include the latest state.
            $conversations = $conversationsDao->getConversations(null, true);
            $mainConversation = $conversations[self::MAIN_CHAT_ID];
        }

        return $threadIds;
    }

    /**
     * Create pairwise private conversations among generated users.
     *
     * @return array<int>
     */
    private static function createPrivateConversationsForGeneratedUsers(): array
    {
        $conversationsDao = ConversationsDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();
        $generatedUserIds = array_values(array_map('intval', array_keys(self::$generatedUsers)));
        $privateConvoIds = array();

        for ($i = 0; $i < count($generatedUserIds); $i++) {
            for ($j = $i + 1; $j < count($generatedUserIds); $j++) {
                $userA = $generatedUserIds[$i];
                $userB = $generatedUserIds[$j];

                $convoId = $conversationsDao->insert(array(
                    'name' => 'Private Seed ' . $userA . '-' . $userB,
                    'parent_conversation_id' => null,
                    'date_created' => self::$START_DATE->format('Y-m-d H:i:s.v'),
                    'last_message_mcc' => self::$START_DATE->format('Y-m-d H:i:s.v'),
                    'last_message_hab' => self::$START_DATE->format('Y-m-d H:i:s.v'),
                ));

                if ($convoId === false || $convoId <= 0) {
                    throw new RuntimeException('Failed creating private conversation for users ' . $userA . ' and ' . $userB);
                }

                $participantsDao->insertMultiple(array(
                    array('conversation_id' => (int)$convoId, 'user_id' => $userA),
                    array('conversation_id' => (int)$convoId, 'user_id' => $userB),
                ));

                $privateConvoIds[] = (int)$convoId;
            }
        }

        return $privateConvoIds;
    }

    /**
     * Create private conversations between generated users and all existing users.
     *
     * @return array<int>
     */
    private static function createPrivateConversationsWithExistingUsers(): array
    {
        $usersDao = UsersDao::getInstance();
        $conversationsDao = ConversationsDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();

        $allUsers = $usersDao->getUsers();
        $generatedUserIds = array_keys(self::$generatedUsers);
        $conversationIds = array();

        foreach ($allUsers as $existingUserId => $existingUser) {
            if (in_array($existingUserId, $generatedUserIds)) {
                continue;
            }

            foreach ($generatedUserIds as $generatedUserId) {
                $convoId = $conversationsDao->insert(array(
                    'name' => 'Private Seed ' . $generatedUserId . '-' . $existingUserId,
                    'parent_conversation_id' => null,
                    'date_created' => self::$START_DATE->format('Y-m-d H:i:s.v'),
                    'last_message_mcc' => self::$START_DATE->format('Y-m-d H:i:s.v'),
                    'last_message_hab' => self::$START_DATE->format('Y-m-d H:i:s.v'),
                ));

                if ($convoId === false || $convoId <= 0) {
                    throw new RuntimeException('Failed creating private conversation for users ' . $generatedUserId . ' and ' . $existingUserId);
                }

                $participantsDao->insertMultiple(array(
                    array('conversation_id' => (int)$convoId, 'user_id' => (int)$generatedUserId),
                    array('conversation_id' => (int)$convoId, 'user_id' => (int)$existingUserId),
                ));

                $conversationIds[] = (int)$convoId;
            }
        }

        return $conversationIds;
    }

    /**
     * @param array<int> $threadIds
     * @param array<int> $privateConvoIds
     * @return array<int, array<string, mixed>>
     */
    private static function buildMessagePlan(array $threadIds, array $privateConvoIds): array
    {
        $plan = array();

        for ($i = 0; $i < self::$MAIN_CHAT_MESSAGES; $i++) {
            $plan[] = array('conversation_id' => self::MAIN_CHAT_ID);
        }

        foreach ($threadIds as $threadId) {
            $numThreadMsgs = random_int(self::$THREAD_MESSAGES_MIN, self::$THREAD_MESSAGES_MAX);
            for ($i = 0; $i < $numThreadMsgs; $i++) {
                $plan[] = array('conversation_id' => $threadId);
            }
        }

        foreach ($privateConvoIds as $privateConvoId) {
            $numPrivateMsgs = random_int(self::$PRIVATE_MESSAGES_MIN, self::$PRIVATE_MESSAGES_MAX);
            for ($i = 0; $i < $numPrivateMsgs; $i++) {
                $plan[] = array('conversation_id' => $privateConvoId);
            }
        }

        // Randomly distribute conversation activity across the full timeline.
        shuffle($plan);

        return self::assignChronologicalTimestamps($plan);
    }

    /**
     * @param array<int, array<string, mixed>> $plan
     * @return array<int, array<string, mixed>>
     */
    private static function assignChronologicalTimestamps(array $plan): array
    {
        $count = count($plan);
        if ($count === 0) {
            return $plan;
        }

        $startTs = (float)self::$START_DATE->format('U.u');
        $endTs = (float)self::$END_DATE->format('U.u');
        $windowSec = max(1.0, $endTs - $startTs);

        $weights = array();
        $sum = 0;
        for ($i = 0; $i < $count; $i++) {
            $w = random_int(1, 1000);
            $weights[] = $w;
            $sum += $w;
        }

        $elapsed = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $elapsed += ($weights[$i] / $sum) * $windowSec;
            $ts = $startTs + $elapsed;

            // Clamp to END_DATE while preserving strict non-decreasing order.
            if ($ts > $endTs) {
                $ts = $endTs;
            }
            if ($i > 0 && $ts < $plan[$i - 1]['sent_ts']) {
                $ts = $plan[$i - 1]['sent_ts'];
            }

            $plan[$i]['sent_ts'] = $ts;
        }

        return $plan;
    }

    /**
     * @param array<int> $conversationIds
     */
    private static function prepareConversationData(array $conversationIds): void
    {
        $conversationsDao = ConversationsDao::getInstance();
        $participantsDao = ParticipantsDao::getInstance();
        $dbConn = Database::getInstance();

        $conversations = $conversationsDao->getConversations(null, true);

        foreach ($conversationIds as $conversationId) {
            if (!isset($conversations[$conversationId])) {
                throw new RuntimeException('Conversation not found: ' . $conversationId);
            }

            $convo = $conversations[$conversationId];
            $key = $convo->getEncryptionKey();
            if ($key === null) {
                $convo->generateEncryptionKey();
                $conversationsDao->update(array('encryption_key' => $convo->encryption_key), $conversationId);
                $key = $convo->getEncryptionKey();
                if ($key === null) {
                    throw new RuntimeException('Unable to prepare encryption key for conversation ' . $conversationId);
                }
            }
            self::$conversationKeys[$conversationId] = $key;
            self::$conversationParticipants[$conversationId] = $participantsDao->getParticipantIds($conversationId);
        }

        $prefix = '';
        global $database;
        if (isset($database['table_prefix'])) {
            $prefix = $database['table_prefix'];
        }

        $tblMessages = $prefix . 'messages';
        $idList = implode(',', array_map('intval', $conversationIds));
        $query = 'SELECT conversation_id, from_crew, COALESCE(MAX(message_id_alt), 0) AS max_alt '
            . 'FROM `' . $tblMessages . '` '
            . 'WHERE conversation_id IN (' . $idList . ') '
            . 'GROUP BY conversation_id, from_crew';

        foreach ($conversationIds as $conversationId) {
            self::$altIdCounters[$conversationId] = array(0 => 0, 1 => 0);
        }

        $result = $dbConn->query($query);
        if ($result !== false) {
            while (($row = $result->fetch_assoc()) !== null) {
                $conversationId = (int)$row['conversation_id'];
                $fromCrew = (int)$row['from_crew'];
                $maxAlt = (int)$row['max_alt'];
                self::$altIdCounters[$conversationId][$fromCrew] = $maxAlt;
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $plan
     */
    private static function insertPlannedMessages(array $plan): int
    {
        $messagesDao = MessagesDao::getInstance();
        $msgStatusDao = MessageStatusDao::getInstance();
        $database = Database::getInstance();

        $database->queryExceptionEnabled(true);
        $messagesDao->startTransaction();

        $inserted = 0;

        try {
            foreach ($plan as $event) {
                $conversationId = (int)$event['conversation_id'];
                $participants = self::$conversationParticipants[$conversationId];

                $authorId = self::pickAuthorForConversation($conversationId, $participants);
                $fromCrew = self::$generatedUsers[$authorId];

                $sentTs = (float)$event['sent_ts'];
                $times = self::computeReceiveTimes($sentTs, (bool)$fromCrew);

                $alt = ++self::$altIdCounters[$conversationId][$fromCrew];

                $plaintext = self::generateRandomMessageContent();
                $encrypted = Encryption::encryptData($plaintext, self::$conversationKeys[$conversationId]);

                $messageId = $messagesDao->insert(array(
                    'user_id' => $authorId,
                    'conversation_id' => $conversationId,
                    'text' => $encrypted,
                    'message_type' => (random_int(1, 100) <= 12) ? Message::IMPORTANT : Message::TEXT,
                    'from_crew' => $fromCrew,
                    'message_id_alt' => $alt,
                    'recv_time_hab' => $times['recv_time_hab'],
                    'recv_time_mcc' => $times['recv_time_mcc'],
                ));

                if ($messageId === false) {
                    throw new RuntimeException('Failed inserting message for conversation ' . $conversationId);
                }

                $statusRows = array();
                foreach ($participants as $participantId => $isCrew) {
                    $statusRows[] = array(
                        'message_id' => $messageId,
                        'user_id' => (int)$participantId,
                    );
                }
                $msgStatusDao->insertMultiple($statusRows);

                $inserted++;
            }

            $messagesDao->endTransaction(true);
        } catch (Throwable $e) {
            $messagesDao->endTransaction(false);
            throw $e;
        } finally {
            $database->queryExceptionEnabled(false);
        }

        return $inserted;
    }

    /**
     * @param array<int> $conversationIds
     */
    private static function updateConversationLastMessageTimes(array $conversationIds): void
    {
        $conversationsDao = ConversationsDao::getInstance();

        foreach ($conversationIds as $conversationId) {
            $conversationsDao->convoUpdated((int)$conversationId);
        }
    }

    /**
     * @param array<int, int> $participants
     */
    private static function pickAuthorForConversation(int $conversationId, array $participants): int
    {
        $generatedCandidates = array();
        foreach ($participants as $participantId => $isCrew) {
            $pid = (int)$participantId;
            if (isset(self::$generatedUsers[$pid])) {
                $generatedCandidates[] = $pid;
            }
        }

        if (count($generatedCandidates) === 0) {
            throw new RuntimeException('No generated-user participants found in conversation ' . $conversationId);
        }

        return $generatedCandidates[array_rand($generatedCandidates)];
    }

    /**
     * @return array{recv_time_hab:string, recv_time_mcc:string}
     */
    private static function computeReceiveTimes(float $sentTs, bool $fromCrew): array
    {
        $sent = self::formatTs($sentTs);
        $delayed = self::formatTs($sentTs + self::OWLT_SECONDS);

        if ($fromCrew) {
            return array(
                'recv_time_hab' => $sent,
                'recv_time_mcc' => $delayed,
            );
        }

        return array(
            'recv_time_hab' => $delayed,
            'recv_time_mcc' => $sent,
        );
    }

    private static function formatTs(float $ts): string
    {
        $dt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $ts), new DateTimeZone('UTC'));
        if ($dt === false) {
            throw new RuntimeException('Failed formatting timestamp');
        }
        return $dt->format('Y-m-d H:i:s.v');
    }

    private static function generateRandomMessageContent(): string
    {
        static $vocab = array(
            'mission', 'status', 'update', 'habitat', 'systems', 'oxygen', 'thermal', 'battery',
            'checklist', 'comms', 'window', 'timeline', 'science', 'sample', 'handover',
            'confirm', 'copy', 'standby', 'complete', 'nominal', 'review', 'delta', 'plan',
            'tracking', 'uplink', 'downlink', 'crew', 'mcc', 'rover', 'airlock', 'power',
            'navigation', 'payload', 'log', 'procedure', 'attitude', 'guidance', 'support'
        );

        $parts = random_int(8, 20);
        $words = array();
        for ($i = 0; $i < $parts; $i++) {
            $words[] = $vocab[array_rand($vocab)];
        }

        $sentence = ucfirst(implode(' ', $words)) . '.';
        if (random_int(1, 100) <= 30) {
            $sentence .= ' Ref ' . random_int(100, 999) . '.';
        }

        return $sentence;
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        TestDataSeeder::run();
    } catch (Throwable $e) {
        fwrite(STDERR, 'Seeder failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
