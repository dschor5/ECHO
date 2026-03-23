---
layout: default
title: Mission Settings
section: administrators
permalink: /administration/admin-mission/
---

# Mission Settings

The Mission Settings page is the central configuration hub for ECHO missions. It defines the mission's identity, timeline, communication parameters, and feature set. These settings shape how participants experience the simulation and what capabilities are available during the mission.

## Overview

Mission settings establish the **context and constraints** of your analog mission. Changes take effect immediately but may require participants to refresh their browsers. Always coordinate major configuration changes with mission participants to avoid confusion.

---

## Mission Details

The mission details section defines the mission's identity, timeline, and participant roles. These settings appear throughout the interface and are critical for participant immersion.

### Mission Identity

**Mission Name**
- **Purpose**: Primary identifier displayed on login page, chat headers, and archives
- **Best Practices**:
  - Use descriptive names like "Mars Desert Research Station - Mission 245"
  - Include mission number or date for easy reference
  - Keep under 50 characters for display purposes
- **Examples**:
  - "ILMAH XV"
  
**Mission Start Date**
- **Purpose**: Defines the mission timeline epoch (T=0)
- **Critical for**: Mission day calculations, piece-wise delay functions, timeline displays
- **Format**: YYYY-MM-DD HH:MM:SS (24-hour format)
- **Impact**: Changing this date affects all time-based calculations

**Mission End Date**
- **Purpose**: Defines when mission day progression stops
- **Behavior**: After this date, mission day counter displays Earth calendar days. 

### Mission Control Configuration

**Name for Mission Control**
- **Examples**: "ILMAH", "Houston CAPCOM", "Mission Control Center"
- **Purpose**: Identifies the Earth-based team in chat interface

**Location for Mission Control**
- **Examples**: "Earth", "Houston, TX", "ILMAH Facility"
- **Purpose**: Provides geographic context for mission control

**User Role for Mission Control**
- **Examples**: "Mission Controller", "CAPCOM", "Flight Director"
- **Purpose**: Defines the functional role displayed in chat

**Timezone for Mission Control**
- **Format**: PHP timezone identifier (e.g., "America/New_York")
- **Purpose**: Determines how times are displayed for MCC messages
- **Impact**: Affects timestamp formatting in archives and logs

### Analog Habitat Configuration

**Name for Analog Habitat**
- **Examples**: "Mars Base Alpha", "Aquarius Reef Base", "Lunar Outpost"
- **Purpose**: Identifies the remote simulation site

**Location for Analog Habitat**
- **Examples**: "Mars", "Pacific Ocean", "Lunar Surface"
- **Purpose**: Provides planetary context for the habitat

**User Role for Analog Crew**
- **Examples**: "Astronaut", "Aquanaut", "Explorer"
- **Purpose**: Defines the role displayed for habitat participants

**Timezone for Analog Simulation**
- **Format**: PHP timezone identifier (e.g., "America/Phoenix" for MDRS)
- **Purpose**: Determines time display for habitat messages
- **Considerations**: Should match actual habitat location for realism

**Name for Mission Day**
- **Examples**: "Sol" (Mars), "Day" (Earth), "Mission Day"
- **Purpose**: Customizes the day counter label
- **Cultural Adaptation**: Use "Sol" for Mars missions, "Day" for Earth-based

---

## Feature Toggles

Feature toggles control which ECHO capabilities are available during the mission. These can be adjusted mid-mission but may require participant coordination.

### Communication Features

**Sound Notifications**
- **Purpose**: Audio alerts for new messages
- **Options**: Off, On (with/without high-importance differentiation)
- **Considerations**:
  - Essential for time-critical operations
  - May be disruptive in quiet environments
  - Test volume levels before mission start
- **High Importance**: Different sounds for normal vs. urgent messages

**Badge Notifications**
- **Purpose**: Browser tab badge showing unread message count
- **Browser Support**: Works on desktop browsers, limited mobile support
- **Use Cases**: Keeping track of message load without constant checking

**Unread Message Count**
- **Purpose**: Shows pending messages per conversation/thread
- **Display**: Numbers in conversation list, red indicators for high-priority
- **Impact**: Helps participants prioritize their attention

**Conversation Ordering**
- **Options**:
  - **By Latest Message**: Chronological by most recent activity (recommended)
  - **By Creation Order**: Fixed order based on when conversations were created
- **Recommendation**: Keep enabled for intuitive navigation

### Message Display Features

**Show Transit/Delivered Status**
- **Purpose**: Visual indicators showing message transmission state
- **Display**: "Transit" or "Delivered" in message corner
- **Use Cases**: Understanding communication delays, troubleshooting issues
- **Realism**: Essential for missions with significant delays

**Message Progress Bar**
- **Purpose**: Visual countdown showing message delivery progress
- **Appearance**: Animated bar showing time remaining until delivery
- **Use Cases**: Managing expectations during long delays
- **Impact**: Reduces anxiety during communication waits

**Out-of-Sequence Indicators**
- **Purpose**: Warns when message order might be confusing
- **Trigger**: When received order differs from sent order
- **Display**: Warning marker on potentially confusing messages
- **Use Cases**: Complex multi-threaded discussions, urgent interruptions

### Advanced Features

**Markdown Support**
- **Purpose**: Enables rich text formatting in messages
- **Syntax**: Standard Markdown (bold, italic, lists, links, code blocks)
- **Use Cases**: Technical discussions, structured reports, documentation
- **Considerations**: May increase message composition time

**Important Messages**
- **Purpose**: High-priority message system
- **Features**: Special styling, different sounds, priority indicators
- **Use Cases**: Emergency communications, time-critical information
- **Activation**: Toggle required to enable the feature

**Threads**
- **Purpose**: Conversation branching for topic organization
- **Behavior**: Allows creating sub-conversations within main chats
- **Use Cases**: Organizing complex discussions, maintaining context
- **Management**: Can be restricted to admins only

**Anyone Can Create Threads**
- **Purpose**: Controls who can start new conversation threads
- **Options**:
  - **Disabled**: Only administrators can create threads
  - **Enabled**: All participants can create threads
- **Best Practice**: Enable for collaborative missions, restrict for controlled scenarios

---

## Login Timeout

**Purpose**: Automatic session expiration for security
- **Default**: 24 hours (86400 seconds)
- **Security**: Prevents unauthorized access to abandoned sessions
- **Public Networks**: Use shorter timeouts (30 minutes - 2 hours)
- **Private Networks**: Can use longer timeouts (24-48 hours)
- **Impact**: Users must re-login after timeout expires

**Recommended Settings**:
- **Training/Test**: 24 hours (comfortable for development)
- **Public WiFi**: 30 minutes (security priority)
- **Secure Facility**: 8 hours (balance convenience/security)
- **High Security**: 2 hours (maximum security)

---

## Related Documentation

- [Communication Delay Settings]({{ '/administration/admin-delay/' | relative_url }}) - Configure message timing
- [User Management]({{ '/administration/admin-users/' | relative_url }}) - Manage participant accounts
- [Data Management]({{ '/administration/admin-data/' | relative_url }}) - Archives and backups
- [Security]({{ '/administration/security/' | relative_url }}) - Encryption and access control

![Mission settings page](../static/s05-admin-settings2.png)
