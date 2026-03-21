---
competitor: krayin
analyzed_date: 2026-03-14
feature: marketing-campaigns
priority: low
---

# Marketing Campaigns

## Overview

Krayin includes a basic marketing module with events and campaigns. Events are date-based marketing moments. Campaigns send email templates triggered by events, with spooling support for batch sending.

## Data Model

### Event (`marketing_events` table)
| Field | Type | Description |
|-------|------|-------------|
| name | string | Event name |
| description | text | Description |
| date | date | Event date |

### Campaign (`marketing_campaigns` table)
| Field | Type | Description |
|-------|------|-------------|
| name | string | Campaign name |
| subject | string | Email subject line |
| status | boolean | Active/inactive |
| marketing_template_id | FK | Email template |
| marketing_event_id | FK | Trigger event |
| spooling | string | Batch sending configuration |

## Architecture

- `CampaignCommand` -- Artisan command for processing scheduled campaigns
- `CampaignMail` -- Mailable class for sending
- `Campaign` helper -- Campaign processing logic
- Campaigns are linked to email templates and events

## Pipelinq Comparison Notes

- Very basic marketing automation -- just event-triggered email sends
- No audience segmentation or contact list management
- No A/B testing, analytics, or open/click tracking
- Not relevant to Pipelinq's core pipeline management scope
- n8n workflows would provide superior campaign automation
