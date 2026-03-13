# Spec: Audit Trail

## Feature: VNG-Compliant Audit Trail for All Write Operations

The VNG standard requires every write action on primary and related objects to be recorded in an audit trail.

### Already in Procest

- Nextcloud activity logging (basic)
- ZGW service-level logging

### Not Yet in Procest

- **Full VNG audit trail** — recording every write action (create, update, delete) on:
  - Zaken and all related objects (statussen, resultaten, rollen, eigenschappen, zaakobjecten, zaakinformatieobjecten)
  - Documenten (enkelvoudiginformatieobjecten, gebruiksrechten)
  - Besluiten (besluitinformatieobjecten)
- **User attribution** — recording user_id and user_representation from JWT for each action
- **Timestamp recording** — when each action occurred
- **Change description** — what was changed (before/after values)
- **Audit trail deletion** — when an object is permanently deleted, its audit trail must also be deleted
- **Audit trail API endpoint** — allowing clients to query the audit trail for a specific object
- **Admin audit view** — viewing audit history in the admin interface
- **Separation of API vs admin changes** — tracking the source of each change
