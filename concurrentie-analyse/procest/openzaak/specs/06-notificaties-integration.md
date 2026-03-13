# Spec: Notificaties API Integration

## Feature: VNG Notificaties API Producer and Consumer

OpenZaak requires Open Notificaties for routing events between ZGW components via a publish-subscribe model.

### Already in Procest

- NotificatieService.php — basic notification handling
- NrcController.php — Notificaties API controller
- Settings for notification endpoint configuration

### Not Yet in Procest

- Full Notificaties API producer implementation (publishing to channels on data changes)
- Full Notificaties API consumer implementation (subscribing to channels, receiving webhooks)
- Channel management (registering channels for zaken, documenten, besluiten)
- Subscription management (registering webhook endpoints with auth headers)
- Kenmerk-based filtering on subscriptions (e.g., filtering by zaaktype.catalogus)
- Failed notification tracking and retry UI
- Information-lean notification design (URLs only, no data)
- Notification channel definitions: zaken, documenten, besluiten, autorisaties
- Integration with Open Notificaties as external service
- Cloud Events support (experimental)
- Webhook security (Authorization header on callbacks)
- Audit trail integration with notification events
