---
layout: default
title: User Management
section: administrators
permalink: /administration/admin-users/
---

# User Management

User management is critical for maintaining security, role clarity, and smooth mission operations. ECHO supports flexible user roles and permissions that can be adjusted throughout the mission lifecycle.

## Overview

The User Accounts page provides comprehensive control over participant access, roles, and permissions. User management affects security, communication capabilities, and administrative access throughout the system.

---

## User Roles and Permissions

ECHO uses a dual-role system combining **Analog Role** and **Software Role** to define participant capabilities and context.

### Analog Roles

**Purpose**: Defines the participant's position in the mission simulation
- **Mission Controller**: Earth-based support team (CAPCOM, Flight Director, etc.)
- **Astronaut/Aquanaut**: Remote habitat crew (crew members, scientists, etc.)

**Impact**:
- Determines which timezone and location labels are used for their messages
- Affects how they're displayed in conversation threads
- Influences archive organization and reporting

### Software Roles

**Purpose**: Controls system access and administrative capabilities
- **Admin**: Full system access including user management, settings, and data operations
- **User**: Standard participant access with communication capabilities

**Admin Permissions**:
- ✅ Create, edit, delete user accounts
- ✅ Modify mission settings and configuration
- ✅ Manage communication delays
- ✅ Create archives and backups
- ✅ Reset system data
- ✅ Access all administrative functions

**User Permissions**:
- ✅ Send and receive messages
- ✅ Create threads (if enabled)
- ✅ Upload files and attachments
- ✅ Access assigned conversations
- ✅ View message history

---

## Creating User Accounts

### Pre-Mission Account Creation

1. **Navigate to Administration → User Accounts**
2. **Click "New User"**
3. **Fill Required Fields**:
   - **Username**: Unique login identifier (3-20 characters, alphanumeric + underscore)
   - **Name or Alias**: Display name shown in chat (2-50 characters)
   - **Analog Role**: Mission Controller or Astronaut/Aquanaut
   - **Software Role**: Admin or User
4. **Click "Save User"**
5. **Record Default Password**: `admin['default_password']` from `server.inc.php`

---

## Account Security Features

ECHO includes comprehensive password security features to protect against unauthorized access and brute force attacks.

### Password Security

**Enhanced Hashing**: Passwords are now hashed using Argon2id, a modern, memory-hard algorithm that provides excellent protection against rainbow table and brute force attacks.

**Complexity Requirements**: All passwords must meet the following requirements:
- Minimum 12 characters in length
- At least one uppercase letter (A-Z)
- At least one lowercase letter (a-z)
- At least one number (0-9)
- At least one special character (!@#$%^&*()_+-=[]{}|;':",./<>?)
- No more than 3 consecutive identical characters
- No sequential characters (e.g., 123, abc)
- Cannot contain the username

**Automatic Validation**: Password complexity is validated when:
- Creating new user accounts
- Administrators set default passwords
- Future password change functionality

### Account Lockout Protection

**Brute Force Prevention**: Accounts are automatically locked after multiple failed login attempts to prevent brute force attacks.

**Lockout Thresholds**:
- 5 failed attempts: 5-minute lockout
- 10 failed attempts: 30-minute lockout
- 15+ failed attempts: 2-hour lockout

**Lockout Features**:
- Progressive lockout times discourage repeated attempts
- Clear error messages inform users of lockout status
- Automatic unlock after timeout expires
- Manual unlock capability for administrators

**Security Logging**: All login attempts, successful and failed, are logged with:
- IP address and user agent information
- Attempt counts and timestamps
- Lockout events and unlock actions

### Password Reset Security

**Default Password Policy**: New accounts are created with a secure default password that meets complexity requirements.

**Reset Process**: Password resets require administrative approval and log all reset events.

**Future Enhancements**: Self-service password reset with email verification planned for future releases.

---

## Managing Existing Accounts

### Editing Account Information

1. **Go to User Accounts**
2. **Click the wrench icon** on the user row
3. **Update Fields** as needed:
   - Change display name (e.g., role changes, name corrections)
   - Modify analog role (e.g., crew rotation)
   - Adjust software role (promote/demote admin access)
4. **Click "Save User"**

**When to Edit Accounts**:
- **Name Changes**: Correct typos, update preferred names
- **Role Changes**: Crew rotations, responsibility shifts
- **Permission Updates**: Grant/revoke admin access
- **Status Updates**: Reactivate returning participants

### Account Deactivation/Reactivation

**Purpose**: Temporarily disable access without deleting data

1. **Click the power icon** on the user row
   - 🔴 Red = Account active
   - 🟢 Green = Account inactive
2. **Confirm the toggle action**

**Use Cases for Deactivation**:
- **Temporary Absence**: Participant on break/leave
- **Shift Changes**: Rotating team members
- **Security Concerns**: Suspected account compromise
- **Testing**: Disable test accounts

**Reactivation Process**:
- Click power icon again
- Account becomes active immediately
- User can login with existing credentials
- All previous permissions and data access restored

### Password Management

**Default Password Reset**:

1. **Click the rewind icon** on the user row
2. **Confirm password reset**
3. **Password resets to**: `$admin['default_password']` from `server.inc.php`

**Password Security Considerations**:
- **Default Password**: Change immediately after account creation
- **Shared Knowledge**: Only administrators should know default password
- **Secure Distribution**: Use encrypted channels for initial credentials
- **Regular Changes**: Encourage periodic password updates

**Emergency Password Reset**:
- Use reset function for forgotten passwords
- Coordinate with user to change immediately
- Document reset events in mission log

---

## Account Deletion

### Deletion Process

1. **Click the trash icon** on the user row
2. **Confirm deletion** (multiple confirmation steps)
3. **Account and associated data are permanently removed**

### What Gets Deleted

**User Account**:
- ❌ Login credentials and profile information
- ❌ All message associations and authorship

**Message Data**:
- ❌ Messages sent by the user (text content)
- ❌ File attachments uploaded by the user

**Conversation Impact**:
- ❌ Private conversations where user was the only participant
- ✅ Public conversations remain 
- ✅ Other participants' messages preserved

### Restrictions

**Deletion is Disabled During Active Missions**
- Prevents accidental data loss
- Protects ongoing mission communications
- Must wait for mission completion or use deactivation

### When to Delete Accounts

**Post-Mission Cleanup**:
- Remove temporary or test accounts
- Clean up after participant changes
- Free up usernames for future use

**Never Delete During Mission**:
- Use deactivation instead
- Preserve communication history
- Maintain audit trails

### Recovery Considerations

**Deletion is Irreversible**
- No way to recover deleted accounts
- Messages become anonymous in archives
- Consider archiving before deletion
- Document deletion reasons

---

## Related Documentation

- [Mission Settings]({{ '/administration/admin-mission/' | relative_url }}) - Configure mission parameters
- [Data Management]({{ '/administration/admin-data/' | relative_url }}) - Archives and user data
- [Security]({{ '/administration/security/' | relative_url }}) - Access control and encryption
- [Installation]({{ '/installation/' | relative_url }}) - Initial setup and configuration

![User accounts management](../static/s03-admin-users.png)
