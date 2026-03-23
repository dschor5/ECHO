---
layout: default
title: Security
section: administrators
permalink: /administration/security/
---

# Security

This section describes the security features and considerations of ECHO, including encryption, data protection, and authentication mechanisms.

## Message & File Encryption

### Overview

ECHO implements server-side encryption for all messages and file attachments using **AES-256-GCM authenticated encryption**. This ensures that sensitive communications are protected at rest on the server.

### Encryption Specifications

- **Algorithm**: AES-256-GCM (Advanced Encryption Standard with 256-bit key)
- **Authentication**: Galois/Counter Mode (GCM) provides authenticated encryption
- **IV Length**: 12 bytes (96 bits)
- **Tag Length**: 16 bytes (128 bits)
- **Key Length**: 32 bytes (256 bits)

### Encryption Architecture

#### Per-Conversation Keys

Each conversation has its own unique encryption key. This means:

- **Isolation**: Messages in one conversation cannot be decrypted with a key from another conversation
- **Granularity**: Compromise of one conversation key doesn't affect other conversations
- **Flexibility**: Conversations can be re-keyed independently if needed

#### Master Key Security

Conversation keys are encrypted using a master key configured in `server.inc.php`:

```php
'encryption_master_key' => 'your_secure_master_key_here_32_chars_min'
```

**Important Security Considerations:**

- The master key must be kept secret and secure
- Change the default master key to a strong, random 32+ character value
- Different deployments should have different master keys

### Automatic Initialization

On the first application run:

1. ECHO detects newly created conversations
2. Unique encryption keys are automatically generated for each conversation
3. These keys are encrypted with the master key and stored in the database
4. This ensures each installation has unique encryption keys

This automatic setup ensures:
- ✅ Encryption is enabled in fresh installations automatically
- ✅ Each installation derives unique keys from its own master key
- ✅ No manual encryption setup is required
- ✅ Encryption happens silently on first application request

## Data Encryption Lifecycle

### Message Encryption

When a user sends a message:

1. Message text is retrieved from the conversation's encryption key
2. AES-256-GCM encrypts the plain text with the conversation key
3. Encrypted message is stored in the database
4. Duplicate message detection uses encrypted values

### Message Decryption

When a user views messages:

1. Application retrieves encrypted message from database
2. Conversation's encryption key is decrypted using master key
3. Message text is decrypted on-demand when accessed
4. Decrypted text is cached for the request duration
5. User sees only decrypted, plain text messages

### File Encryption

When a file is uploaded:

1. File is uploaded to the uploads directory
2. File is encrypted using the conversation's encryption key
3. Encrypted file replaces the original on disk
4. Original unencrypted file is securely deleted

When a file is downloaded:

1. Application retrieves encrypted file from disk
2. File is decrypted in memory using conversation key
3. Decrypted file is streamed to the user
4. Temporary decrypted file is securely deleted after serving

## Authentication & Authorization

### User Authentication

- User passwords are hashed using **SHA-256**
- Passwords are NOT encrypted (they cannot be decrypted)
- Failed login attempts are logged in the system log

### Access Control

- Only authenticated users can access conversations
- Users can only see conversations they are participants in
- File downloads require both authentication and conversation participation

## Security Limitations

### Message Duplication Detection

Duplicate message detection (prevents sending the same message twice within 3 seconds) has been modified:

- **Original behavior**: Compared plain text messages
- **Current behavior**: Compares encrypted messages
- **Impact**: Duplicate detection is less reliable for identical messages
- **Mitigation**: This is an acceptable trade-off for encryption security

### Archives Contain Plain Text

**Important**: Conversation archives exported from the Data Management interface contain **plain text** (decrypted) messages.

**Why?**

- Archives are static exports meant for offline review and long-term storage
- Archives are not tied to a specific deployment's master key
- Decrypting on export allows archives to be portable

**Security Considerations:**

- Archive files must be treated as sensitive documents
- Archives should be stored securely (encrypted at rest on server if possible)
- Only administrators can create archives
- Archive files should be protected with the same security measures as the original database backup

**Recommendations:**

- Restrict access to archive files
- Encrypt archives at rest on the server (implement file-level encryption)
- Use secure file transfer protocols when sharing archives
- Consider implementing audit logging for archive access

## Password Security

### Password Storage

- Passwords are hashed using SHA-256
- Passwords should be changed regularly in settings
- Administrators can force users to reset passwords on login

### Password Reset

- Default passwords for new users are configured in `server.inc.php`
- Users are forced to reset their password on first login
- Password reset should be done securely (not via email in production)

## Server Configuration

### Required Settings

Encryption requires proper configuration in `server.inc.php`:

```php
$server = array(
    'host_address' => '/path/to/ECHO/',
    'http' => 'https://',  // HTTPS is strongly recommended
    'site_url' => 'domain.com',
    'encryption_master_key' => 'CHANGE_ME_TO_SECURE_VALUE_32_CHARS',
);
```

### HTTPS Requirement

ECHO requires HTTPS for secure communication:

- Client-server communications must be encrypted with SSL/TLS
- WebRTC features (audio/video) require secure connections
- Unencrypted HTTP exposes all user communications to eavesdropping

### Database Security

- Database credentials should be protected in `server.inc.php`
- Database connections should use encrypted protocols if possible
- Database backups should be encrypted at rest
- Restrict database access to the application server only

## Security Best Practices

### For Administrators

1. **Change the master key** immediately after installation
2. **Use HTTPS** with a valid SSL certificate
3. **Keep backups** encrypted and secured
4. **Monitor logs** for suspicious activity
5. **Enable audit logging** for administrative actions
6. **Restrict admin access** to trusted administrators only
7. **Rotate credentials** periodically

### For Users

1. **Keep passwords secure** and don't share them
2. **Log out** when finished, especially on shared computers
3. **Report suspicious activity** to administrators
4. **Don't assume privacy** even with encryption
5. **Verify recipients** before sending sensitive messages

## Monitoring & Auditing

All encryption-related activities are logged:

- Key generation during initialization
- Encryption/decryption errors
- File upload and download operations
- Archive creation

Check the system log in the Data Management section for encryption-related entries.

## Compliance Notes

This encryption implementation provides:

- ✅ **Encryption at rest** for messages and files (AES-256-GCM)
- ✅ **Authentication** for all encrypted data (GCM mode)
- ✅ **Key isolation** per conversation
- ✅ **Secure key management** with master key
- ✅ **Automatic setup and initialization**

**Not provided:**

- ❌ End-to-end encryption (keys managed by server)
- ❌ Encrypted user passwords (hashed instead)
- ❌ Archive encryption (exported as plain text)
- ❌ Transport encryption (requires HTTPS configuration)

For additional security requirements, consult with security professionals and adjust configuration accordingly.

## References

- [NIST AES Specification (FIPS 197)](https://nvlpubs.nist.gov/nistpubs/FIPS/NIST.FIPS.197.pdf)
- [NIST GCM Mode (SP 800-38D)](https://nvlpubs.nist.gov/nistpubs/Legacy/SP/nistspecialpublication800-38d.pdf)
- [OWASP Data Protection Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Data_Protection_Cheat_Sheet.html)
