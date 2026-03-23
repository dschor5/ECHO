<?php

/**
 * Encryption utility class for server-side encryption of messages and files.
 * Uses AES-256-GCM for authenticated encryption with per-conversation keys.
 *
 * SCOPE:
 * - Encrypts messages in the database
 * - Encrypts file attachments on disk
 * - Decrypts data when accessed by authorized users
 * 
 * LIMITATIONS & SECURITY NOTES:
 * - Conversation archives (exports) contain PLAIN TEXT messages
 *   Archives are static exports and are not encrypted
 *   Treat archive files as sensitive documents
 * - User passwords are NOT encrypted (use bcrypt hashing instead)
 * - Encryption keys are stored encrypted using a master key
 * - Only server administrators can access encrypted data
 *
 * @link https://github.com/dschor5/ECHO
 */
class Encryption
{
    /**
     * Encryption algorithm
     */
    const ALGORITHM = 'aes-256-gcm';

    /**
     * Key length in bytes (32 bytes for AES-256)
     */
    const KEY_LENGTH = 32;

    /**
     * IV length in bytes (12 bytes for GCM)
     */
    const IV_LENGTH = 12;

    /**
     * Tag length in bytes (16 bytes for GCM)
     */
    const TAG_LENGTH = 16;

    /**
     * Master key for encrypting conversation keys (should be in config)
     * In production, this should be stored securely (e.g., in environment variables)
     */
    private static $masterKey = null;

    /**
     * Get the master key for encrypting conversation keys
     *
     * @return string
     */
    private static function getMasterKey(): string
    {
        if (self::$masterKey === null) {
            global $server;
            // Use a configured master key, or generate one if not set
            if (isset($server['encryption_master_key'])) {
                self::$masterKey = $server['encryption_master_key'];
            } else {
                // Fallback - in production, this should be properly configured
                self::$masterKey = 'default_master_key_change_in_production_32_chars';
                Logger::warning('Using default master key. Please configure encryption_master_key in server.inc.php.');
            }
        }
        return self::$masterKey;
    }

    /**
     * Generate a new encryption key for a conversation
     *
     * @return string Raw binary key
     */
    public static function generateConversationKey(): string
    {
        return openssl_random_pseudo_bytes(self::KEY_LENGTH);
    }

    /**
     * Encrypt a conversation key with the master key
     *
     * @param string $conversationKey Raw binary conversation key
     * @return string Encrypted conversation key (base64 encoded)
     */
    public static function encryptConversationKey(string $conversationKey): string
    {
        $masterKey = self::getMasterKey();
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        $tag = '';

        $encrypted = openssl_encrypt(
            $conversationKey,
            self::ALGORITHM,
            $masterKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        // Return IV + tag + encrypted data as base64
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt a conversation key with the master key
     *
     * @param string $encryptedConversationKey Base64 encoded encrypted key
     * @return string Raw binary conversation key
     */
    public static function decryptConversationKey(string $encryptedConversationKey): string
    {
        $masterKey = self::getMasterKey();
        $data = base64_decode($encryptedConversationKey);

        $iv = substr($data, 0, self::IV_LENGTH);
        $tag = substr($data, self::IV_LENGTH, self::TAG_LENGTH);
        $encrypted = substr($data, self::IV_LENGTH + self::TAG_LENGTH);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::ALGORITHM,
            $masterKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new Exception('Failed to decrypt conversation key');
        }

        return $decrypted;
    }

    /**
     * Encrypt data using a conversation key
     *
     * @param string $data Data to encrypt
     * @param string $conversationKey Raw binary conversation key
     * @return string Encrypted data (base64 encoded with IV and tag)
     */
    public static function encryptData(string $data, string $conversationKey): string
    {
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        $tag = '';

        $encrypted = openssl_encrypt(
            $data,
            self::ALGORITHM,
            $conversationKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        // Return IV + tag + encrypted data as base64
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt data using a conversation key
     *
     * @param string $encryptedData Base64 encoded encrypted data
     * @param string $conversationKey Raw binary conversation key
     * @return string Decrypted data
     */
    public static function decryptData(string $encryptedData, string $conversationKey): string
    {
        $data = base64_decode($encryptedData);

        if ($data === false || strlen($data) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw new Exception('Invalid encrypted data format');
        }

        $iv = substr($data, 0, self::IV_LENGTH);
        $tag = substr($data, self::IV_LENGTH, self::TAG_LENGTH);
        $encrypted = substr($data, self::IV_LENGTH + self::TAG_LENGTH);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::ALGORITHM,
            $conversationKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new Exception('Failed to decrypt data');
        }

        return $decrypted;
    }

    /**
     * Encrypt a file using a conversation key
     *
     * @param string $inputFile Path to input file
     * @param string $outputFile Path to output file
     * @param string $conversationKey Raw binary conversation key
     * @return bool Success
     */
    public static function encryptFile(string $inputFile, string $outputFile, string $conversationKey): bool
    {
        $data = file_get_contents($inputFile);
        if ($data === false) {
            return false;
        }

        $encrypted = self::encryptData($data, $conversationKey);
        return file_put_contents($outputFile, $encrypted) !== false;
    }

    /**
     * Decrypt a file using a conversation key
     *
     * @param string $inputFile Path to encrypted file
     * @param string $outputFile Path to output file
     * @param string $conversationKey Raw binary conversation key
     * @return bool Success
     */
    public static function decryptFile(string $inputFile, string $outputFile, string $conversationKey): bool
    {
        $encrypted = file_get_contents($inputFile);
        if ($encrypted === false) {
            return false;
        }

        try {
            $decrypted = self::decryptData($encrypted, $conversationKey);
            return file_put_contents($outputFile, $decrypted) !== false;
        } catch (Exception $e) {
            Logger::error('File decryption failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

?>