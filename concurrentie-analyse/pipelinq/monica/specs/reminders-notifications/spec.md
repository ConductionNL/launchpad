---
competitor: monica
analyzed_date: 2026-03-14
feature: reminders-notifications
category: automation
---

# Reminders and Notifications

## Overview

Monica has a reminder system tied to contacts and their important dates. Reminders can be manually created or automatically generated (e.g., from birthdays). Notifications are delivered via configurable channels.

## Reminder Types

1. **Automatic birthday reminders:** When a contact has a birthday set, Monica automatically creates annual reminders
2. **Custom date reminders:** Users can set reminders for any important date (anniversary, meeting, etc.)
3. **One-time reminders:** Single occurrence reminders
4. **Recurring reminders:** Annual recurring reminders for dates

## Notification Channels

1. **Email:** Standard SMTP-based email notifications
2. **Telegram:** Webhook-based Telegram bot notifications
   - Telegram webhook controller for receiving updates
   - Dedicated notification channel management

## Notification Management

- Add/remove notification channels
- Verify channels (email verification, Telegram webhook verification)
- Toggle channels on/off
- View notification logs
- Send test notifications

## Technical Implementation

- Reminders: `app/Domains/Contact/ManageReminders/` (Services, Jobs, Web)
- Notification channels: `app/Domains/Settings/ManageNotificationChannels/` (Jobs, Services, Web)
- Queue-based delivery via Laravel queue system
- Cron-based scheduling (laravel.cron service in Docker)
- Notification sent tracking (UserNotificationSent model)

## Relevance to Pipelinq

Monica's reminder system is simple but effective. Key takeaways for Pipelinq:
1. Automatic reminders from data (birthdays) -- Pipelinq could auto-remind on pipeline stage deadlines
2. Multi-channel delivery (email + Telegram) -- Pipelinq has n8n for much richer notification workflows
3. Notification logging -- useful pattern for audit trails
4. The lack of webhook/API-based notifications is a weakness -- Pipelinq's n8n integration far surpasses this
