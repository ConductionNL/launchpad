---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# Notifications

## Overview

Directus provides an in-app notification system that allows sending targeted messages to users. Notifications can be sent by the system (Flows, operations), by other users (mentions, shares), or programmatically via the API.

## Notification Model

Notifications are stored in `directus_notifications`:
- **Recipient**: Target user ID
- **Sender**: Originating user (or null for system notifications)
- **Subject**: Notification title
- **Message**: Body text (supports markdown)
- **Collection/Item**: Optional link to a specific data item
- **Status**: Read/unread state

## Delivery Channels

### In-App
- Badge counter in the admin UI header
- Notification drawer with list of notifications
- Click-through to linked items

### Email
When a notification is created, the `NotificationsService` optionally sends an email:
- Checks recipient's email notification preferences
- Uses the configured mail service (SMTP)
- Renders notification content as HTML email
- Includes a link to the referenced item

## System Notifications

Notifications are automatically sent for:
- Export job completion (with download link)
- Import job completion (with summary)
- Share invitations
- User mentions in comments
- Flow-triggered notifications (via the notification operation)

## API

```
GET /notifications                  # List user's notifications
POST /notifications                 # Send notification
PATCH /notifications/:id            # Mark as read
DELETE /notifications/:id           # Delete notification
```

## Flow Operation

The built-in `notification` operation in Directus Flows allows sending notifications as part of automated workflows:
```json
{
  "type": "notification",
  "options": {
    "recipient": "{{$trigger.user_created}}",
    "subject": "Your item was approved",
    "message": "The item '{{$trigger.title}}' has been approved.",
    "collection": "{{$trigger.collection}}",
    "item": "{{$trigger.key}}"
  }
}
```

## Relevance to OpenRegister

OpenRegister can leverage Nextcloud's native notification system:
- Nextcloud has a built-in notification app with push support
- Mobile and desktop push notifications via Nextcloud push proxy
- Email notifications via Nextcloud mail settings
- Notification API is well-documented

This is an area where OpenRegister has an advantage through Nextcloud integration, as it gets a more mature notification system "for free" compared to Directus's simpler built-in approach.
