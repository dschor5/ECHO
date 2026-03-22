---
layout: default
title: Data Management
section: administrators
permalink: /administration/admin-data/
---

# Data Management

The Data Management page allows administrators to create backups, export conversation archives, download system logs, and reset ECHO data. This section covers each feature in detail.

## Backup Conversations

The archive feature allows you to export conversations and all associated file attachments as a downloadable ZIP file for post-mission analysis, record-keeping, or disaster recovery.

### Creating an Archive

1. **Select Timezone**: Choose the timezone to use for all message timestamps in the archive (e.g., "America/New_York")
   - All sent/received times are converted to this timezone
   - Helpful for analyzing messages across different time zones

2. **Select Perspective**: Choose which site's timestamp perspective to use for ordering
   - **Habitat Perspective**: Messages ordered by HAB received time
   - **Mission Control Perspective**: Messages ordered by MCC received time

3. **Select Scope**: Choose which conversations to include
   - **Mission Chat Only**: Includes only the public "Mission Chat" conversation
   - **Mission Chat and Private Conversations**: Includes all conversations (public and private)

4. **Add Archive Notes** (optional): Add notes describing this archive (e.g., "Day 3 checkpoint", "Final archive before mission end")

5. **Create Archive**: Click the button to start archive generation
   - Large archives may take several minutes to generate
   - A progress bar shows the generation progress
   - Only one archive can be created at a time

### Archive Contents & Format

Archives are saved as ZIP files with the following naming convention:
```
archive-YYYY-MM-DDTHH-MM-SS.zip
```

**File Structure**:
```
archive-2024-03-21T14-30-00.zip
├── 00001-conversation/
│   ├── XXXXX-attachment1.jpg
│   ├── XXXXX-attachment2.pdf
│   └── 00001-conversation.html
├── 00001-00002-thread/
│   ├── YYYYY-attachment3.jpg
│   └── 00001-00002-thread.html
└── mission_notes.txt
```

Where:
- `CCCCC` = Conversation ID (5 digits, zero-padded)
- `TTTTT` = Thread ID (5 digits, zero-padded)
- `XXXXX` = Message ID (5 digits, zero-padded)

### Archive Contents

Each archive includes:

- **HTML Conversation Files**: Human-readable HTML files containing all messages with formatting
- **Attachments**: All file attachments uploaded during the mission (images, documents, audio, etc.)
- **Metadata**: Participant information, conversation details, timestamps
- **Archive Notes**: The notes you added when creating the archive

### Security Considerations

⚠️ **Important**: Archive files contain **plain text** (decrypted) messages.

While messages are encrypted in the database, conversation archives are exported with all messages decrypted for portability and offline use. This means:

- Archive files should be treated as sensitive documents
- Store archives securely (encrypted at rest on the server if possible)
- Only administrators can create archives
- Archives should be protected with the same security measures as database backups
- Consider implementing file-level encryption for stored archives
- Use secure file transfer methods when sharing archives

For more details, see the [Security documentation]({{ '/administration/security/' | relative_url }}).

### Downloading & Using Archives

1. After generation completes, the file appears in the "Backups Created" list
2. Click the file to download it
3. Extract the ZIP file to access the HTML conversation files and attachments
4. Open the `.html` files in any web browser to view formatted conversations

---

## Database Backup

The MySQL Database Backup creates a complete SQL dump of your ECHO database, useful for disaster recovery and data preservation.

### Creating a Backup

1. Click **Backup MySQL Database**
2. The system creates a SQL dump file of all database tables
3. Generation time depends on the size of your database (usually a few seconds to a few minutes)
4. A progress indicator shows the backup is generating

### Backup Contents

The SQL dump includes:

- All users and accounts
- All conversations and threads
- All messages (encrypted)
- File attachment metadata
- Message delivery status
- Configuration settings
- Participant information

### File Format

**Filename**: `backup-YYYY-MM-DD_HH-MM-SS.sql`

**Format**: Plain text SQL statements

**Timestamps**: All times in UTC timezone (regardless of mission timezone)

### Restoring from Backup

To restore from a backup:

```bash
mysql -u username -p database_name < backup-2024-03-21_14-30-00.sql
```

### Important Notes

- **Encryption**: Messages in the backup are stored encrypted
- **Size**: Backups can be large if your mission has many messages/attachments
- **Portability**: SQL backups can be restored to any MySQL 9.4+ compatible server
- **Table Prefix**: If using table prefixes, adjust the import command accordingly

---

## System Logs

The System Log contains all application events, errors, and administrative actions. These logs are essential for debugging and auditing.

### Log Contents

The system log records:

- Application startup and initialization events
- User login/logout activities
- Encryption key generation and initialization
- Message send/receive operations
- File upload/download operations
- Error conditions and exceptions
- Administrative actions (user creation, password resets, etc.)
- Configuration changes
- Archive creation
- Data deletion and resets

### Creating a Backup

1. Click **Backup System Log**
2. The system packages the entire log file for download
3. File is saved immediately (usually very fast)

### Log Format

**Filename**: `system-log-YYYY-MM-DD_HH-MM-SS.log`

**Timestamps**: All times in UTC timezone

**Format**: Structured log entries with timestamp, level, message, and context

### Viewing Logs

1. The Data Management page displays the last 50 log entries
2. Entries are displayed in reverse chronological order (newest first)
3. Full logs can be downloaded for analysis
4. Log entries are auto-rotation to prevent excessive disk usage

### Log Analysis

Use the logs to:

- **Troubleshoot errors**: Look for ERROR and WARNING entries
- **Track user activity**: See who logged in and when
- **Monitor encryption**: Verify encryption initialization and key operations
- **Audit operations**: Track administrative actions and data modifications

---

## Resetting ECHO

The reset functions are powerful tools that irreversibly delete data. Use with extreme caution.

### Delete All Data

**Purpose**: Remove all conversation messages, threads, and file attachments while preserving user accounts and system configuration. **Regenerates fresh encryption keys for all conversations** to maintain security.

**What Gets Deleted**:
- ❌ All messages (text, important, audio, video, files)
- ❌ All conversation threads
- ❌ All file attachments
- ❌ Message delivery status
- ❌ Saved messages

**What Gets Regenerated**:
- 🔄 **New encryption keys** for all conversations (security enhancement)

**What Remains**:
- ✅ User accounts
- ✅ User configurations and preferences
- ✅ Mission configuration
- ✅ System settings
- ✅ System logs
- ✅ Conversation structure (but with new encryption keys)



**Security Impact**: Each conversation receives a new, unique encryption key. Previous messages cannot be decrypted with the new keys, ensuring complete data separation between mission phases.

**Confirmation**: You will be asked to confirm this action (cannot be undone)

### Reset System Log

**Purpose**: Clear all system log entries to start fresh or reduce log file size.

**What Gets Deleted**:
- ❌ All system log entries
- ❌ Audit trail of administrative actions

**What Remains**:
- ✅ Application continues to log new events
- ✅ All saved data is preserved

**Use Cases**:
- Clearing old logs for a fresh mission start
- Reducing system log file size
- Removing training/test log entries

**Confirmation**: You will be asked to confirm this action (cannot be undone)

### Before Resetting

1. ✅ Create and download backups before resetting
2. ✅ Inform all mission stakeholders before destructive resets
3. ✅ Download and check archives were created successfully


> **Warning**: These operations are irreversible. Always create backups before resetting.

---

## Best Practices

### For Post-Mission Archives

1. Create final archive before resetting
2. Create a database backup alongside
3. Store backups in secure location
4. Encrypt archive files (consider file-level encryption)
5. Document archive contents and creation date
6. Test that archives can be extracted and viewed
7. Keep archives for required retention period

### For Regular Backups

1. Create backups after significant mission events
2. Create backups before major configuration changes
3. Rotate backups to manage disk space
4. Store backups off-site if possible
5. Document backup schedule and retention policy
6. Test backup restoration procedures periodically

### For Security Resets

1. Use database reset when encryption keys may be compromised
2. Create archives before reset to preserve old encrypted data
3. Inform participants that new conversations will use different encryption
4. Document the reset event and new key generation
5. Consider changing master encryption key if security breach suspected

### For Security Monitoring

1. Review security logs daily for suspicious activity
2. Monitor failed login attempts and account lockouts
3. Set up alerts for multiple failed login attempts from same IP
4. Archive security logs separately from application logs
5. Use log analysis tools to identify security patterns
6. Document security incidents and response actions

---

## Troubleshooting

### Archive Generation Fails

- **Check disk space**: Ensure server has sufficient storage
- **Check log**: View system log for error messages
- **Check database**: Verify database connection is working
- **Reduce scope**: Try archiving fewer conversations

### Backup Takes Too Long

- **Check server load**: High load can slow backup creation
- **Check database size**: Large databases take longer
- **Try off-peak**: Create backups during low-usage times
- **Optimize database**: Consider database maintenance/optimization

### Can't Download Archive/Backup

- **Check file exists**: Verify file created successfully
- **Check permissions**: Verify web server has read permissions
- **Check disk space**: Ensure disk space doesn't run out during transfer
- **Try again**: Temporary network issues may resolve on retry

---

## Related Documentation

- [Security]({{ '/administration/security/' | relative_url }}) - Encryption and archive security considerations
- [User Management]({{ '/administration/admin-users/' | relative_url }}) - Managing user accounts
- [Installation]({{ '/installation/' | relative_url }}) - Initial ECHO setup
