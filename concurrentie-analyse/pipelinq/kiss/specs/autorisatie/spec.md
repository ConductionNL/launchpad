---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Verwerking / Audit Logging (Privacy Compliance) - KISS

## Purpose
KISS implements "verwerking" (processing) logging to comply with the Dutch AVG (GDPR) requirements. Every time a KCM accesses personal data (BRP lookups, customer records, case details), the system logs what data was accessed, by whom, and for what purpose. This creates an audit trail that municipalities need for privacy compliance and for answering citizen data access requests (inzageverzoeken).

## Architecture Overview
- **Frontend**: Transparent — no explicit UI for verwerking logging; it happens automatically during normal operations
- **BFF**: Middleware/interceptors on YARP proxy routes that log data access events
- **Storage**: Verwerkingsactiviteiten logged via the Verwerking API or internal PostgreSQL logging
- **Standard**: Based on the VNG Verwerkingenlogging API standard (though implementation details vary)

## Data Model

### VerwerkingLogEntry
```typescript
interface VerwerkingLogEntry {
    id: string;
    tijdstip: string;              // Timestamp of data access
    actie: string;                 // Action performed (e.g., "opvragen", "wijzigen")
    verwerkingsactiviteit: string; // Processing activity (e.g., "klantcontact registreren")
    vertrouwelijkheid: string;     // Confidentiality level
    bewaartermijn: string;         // Retention period
    uitvoerder: string;            // Employee who performed the action (OIDC claim)
    systeem: string;               // System identifier ("KISS")
    bron: string;                  // Source system (e.g., "BRP", "KvK", "OpenKlant")
    verwerkteObjecten: VerwerkObjectRef[]; // Objects that were accessed
}

interface VerwerkObjectRef {
    objecttype: string;            // e.g., "persoon", "bedrijf", "zaak"
    soortObjectId: string;         // ID type (e.g., "BSN", "KvK-nummer")
    objectId: string;              // The actual identifier
}
```

## Business Logic

### What Gets Logged
| Action | Source | Logged Data |
|--------|--------|-------------|
| BRP person lookup | Haal Centraal | BSN, search parameters |
| KvK business lookup | KvK API | KvK number, search parameters |
| Customer record view | OpenKlant | Partij identifier |
| Case detail view | OpenZaak (ZGW) | Zaak identifier |
| Contact history view | OpenKlant | Klant identifier |
| Contact moment creation | OpenKlant | Klantcontact + betrokkene |

### Logging Flow
1. KCM performs an action that accesses personal data (e.g., BRP search)
2. BFF proxy route intercepts the request
3. Before forwarding to the external API, a log entry is created
4. The response is forwarded to the frontend
5. If the KCM views details (expanding a search result), additional log entries are created

### Citizen Data Access Requests
When a citizen submits a "verzoek tot inzage" (data access request), the municipality can query the verwerking logs to determine:
- Which employees accessed the citizen's data
- When the access occurred
- What was the purpose (verwerkingsactiviteit)
- Which systems were involved

### Retention
Log entries have a configurable retention period (`bewaartermijn`). After expiration, logs must be deleted to comply with data minimization principles.

### Employee Identification in Logs
The `uitvoerder` field is populated from the OIDC claim configured in `OIDC_MEDEWERKER_IDENTIFICATIE_CLAIM`. This links each log entry to the specific employee, enabling accountability without exposing the employee's personal data beyond their identifier.

## Requirements (as observed)
- Must log all personal data access events automatically
- Must identify the employee performing the access
- Must record the purpose and source of data access
- Must support citizen data access requests (inzageverzoeken)
- Must comply with AVG/GDPR logging requirements
- Must support configurable retention periods
- Must not impact performance of normal operations (async logging)

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Audit logging | Yes (automatic verwerking) | Nextcloud audit log (basic) |
| Personal data tracking | Yes (BSN, KvK, etc.) | No PII-specific logging |
| Purpose recording | Yes (verwerkingsactiviteit) | No purpose field |
| Employee attribution | Yes (OIDC claim) | Yes (Nextcloud user) |
| Citizen access requests | Yes (queryable logs) | No structured query |
| AVG/GDPR compliance | Yes (built-in) | Manual/app-level |
| Retention management | Yes (bewaartermijn) | Nextcloud log rotation |

**Gap for Pipelinq**: For Dutch government deployments, verwerking logging is a legal requirement. Pipelinq could implement this as an OpenRegister audit schema that automatically logs access to objects containing PII. Nextcloud's existing audit log provides a foundation but lacks the structured AVG fields.
