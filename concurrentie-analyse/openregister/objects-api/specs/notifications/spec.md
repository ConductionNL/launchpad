---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# Notifications — Objects API (Documentation View)

## Purpose
Publish notifications to a Notificaties API when objects are created, updated, or deleted. Enables event-driven architectures where other applications react to object changes.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/admin/notifications.html

## Configuration

1. Configure Notificatiescomponentconfiguratie with API root URL
2. Add the Notificaties API as a Service with:
   - Type: NRC (Notifications)
   - Client ID and Secret (registered in Autorisaties API)
   - Authorization type: ZGW client_id + secret
   - OAS URL
3. Configure API authorizations in Autorisaties API (e.g., Open Zaak)

## Notification Channel
- Channel: `objecten`
- Events: create, update, delete
- Published via Celery async task

## Auto-Retry
Binary exponential backoff for failed notifications:

| Setting | Default | Description |
|---------|---------|-------------|
| Max retries | 5 | Maximum retry attempts |
| Backoff factor | 3 | Delay multiplier |
| Backoff max | 48s | Maximum delay between retries |

Formula: `delay = backoff_factor * 2^retry_count`

### Default schedule:
1. 0s — Initial attempt fails
2. 3s — 1st retry (2^0 * 3)
3. 9s — 2nd retry (2^1 * 3)
4. 21s — 3rd retry (2^2 * 3)
5. 45s — 4th retry (2^3 * 3)
6. 93s — 5th retry (2^4 * 3, capped at 48s)

## Cloud Events (Experimental)
- `ENABLE_CLOUD_EVENTS` environment variable
- `zaak-gekoppeld` event when object linked to zaak
- `zaak-ontkoppeld` event when object unlinked from zaak
- Not recommended for production yet

## Integration Points
- Open Notificaties for subscription management
- Open Zaak for authorization configuration
- Celery + Redis for async delivery

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Event notifications | Notificaties API integration | n8n workflows |
| Channel-based | objecten channel | Webhook-based |
| Auto-retry | Binary exponential backoff | n8n retry logic |
| Cloud events | Experimental zaak events | Not available |
| Async delivery | Celery + Redis | n8n async |
| Subscription management | Open Notificaties | n8n triggers |

**Already in OpenRegister**: Event-driven via n8n workflows, webhook notifications
**Not yet in OpenRegister**: VNG Notificaties API integration, channel-based notifications, standardized cloud events, binary exponential backoff retry
