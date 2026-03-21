---
status: draft
source: competitive-analysis
competitor: nocobase
analyzed_date: 2026-03-14
---

# Notification System

## Purpose

NocoBase provides a multi-channel notification system through `plugin-notification-manager` that supports email and in-app messages. Notifications can be triggered manually, from workflows, or from system events.

## Architecture Overview

```
Notification Trigger (workflow / API / system event)
    |
    v
Notification Manager
    |
    v
Channel Router (email / in-app / custom)
    |
    v
Channel Handler (SMTP / in-app storage)
    |
    v
Delivery
```

## Data Model

### Notification Channels
- `name` - Channel identifier
- `type` - Channel type (email, in-app-message, custom)
- `config` - Channel-specific configuration (SMTP settings, etc.)
- `enabled` - Active status

### Notification Logs
- `channelName` - Which channel was used
- `status` - Delivery status (sent, failed, pending)
- `content` - Message content
- `receiver` - Target user/address
- `error` - Error details if failed

## Business Logic

### Built-in Channels

1. **Email** (`plugin-notification-email`)
   - SMTP configuration
   - HTML and plain text templates
   - Variable substitution from workflow context
   - Attachment support

2. **In-App Messages** (`plugin-notification-in-app-message`)
   - Real-time notifications via WebSocket
   - Unread count badge
   - Read/unread status tracking
   - Notification center UI

### Workflow Integration
The workflow engine has dedicated notification nodes:
- `NotificationInstruction` - Send notification via configured channel
- `MailerInstruction` - Direct email sending
- `CCInstruction` - Carbon copy notification to users
- `ResponseMessageInstruction` - Set API response messages

### Custom Channels
Plugins can register custom notification channels by extending `BaseNotificationChannel`:
```typescript
class CustomChannel extends BaseNotificationChannel {
  async send(params) { /* ... */ }
}
```

## Requirements

### Functional
- Multi-channel notifications (email, in-app)
- Workflow-triggered notifications
- Notification templates with variables
- In-app notification center with unread counts
- Notification logging and status tracking
- Custom channel registration

### Non-functional
- Real-time in-app delivery via WebSocket
- Reliable email delivery with error logging
- Channel configuration via admin UI

## Comparison Notes

### vs Nextcloud Notifications
- Nextcloud has a robust notification system with push notifications (mobile)
- NocoBase has email + in-app; Nextcloud has in-app + push + email
- Nextcloud notifications are app-driven; NocoBase notifications are workflow-driven
- Both support real-time delivery via WebSocket/SSE
- Nextcloud has a notification API that any app can use; NocoBase uses channel registration
