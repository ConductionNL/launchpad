---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Channel Management (Kanalen) - KISS

## Purpose
KISS tracks the communication channel through which each citizen interaction occurs. Channels (kanalen) are admin-configurable and selected by the KCM when starting or finalizing a contactmoment. This data feeds into management reporting and allows analysis of channel usage patterns across the municipality.

## Architecture Overview
- **Frontend**: `src/features/Kanalen/` — KanaalSelector component (dropdown in contactmoment form), admin CRUD views in beheer section
- **BFF**: Kanalen controller with CRUD endpoints, stored in PostgreSQL
- **External**: Channel value is passed to OpenKlant API as part of the klantcontact record (`kanaal` field)

## Data Model

### Kanaal (BFF Entity)
```csharp
class Kanaal {
    int Id;
    string Naam;    // e.g., "Telefoon", "Balie", "E-mail", "Chat", "Social media"
}
```

### Usage in Contactmoment
```typescript
// In Vraag (question within contactmoment):
interface Vraag {
    kanaal: string;   // Selected channel name
    // ... other fields
}
```

### OpenKlant 2 Mapping
```json
{
    "kanaal": "telefoon",
    "onderwerp": "Vraag over afvalcontainer",
    "inhoud": "Burger belt over kapotte container..."
}
```

## Business Logic

### Channel Selection
1. When a KCM starts a contactmoment, they can optionally select the channel
2. Channel selection is also available on the finalization screen
3. The channel is stored per "vraag" (question) within the contactmoment, allowing different channels for different questions in the same interaction (though this is uncommon)

### Default Channels
KISS ships with no hardcoded channels. Administrators must configure them during initial setup. Common channels:
- Telefoon (phone)
- Balie (counter/walk-in)
- E-mail
- Chat
- Social media
- Post (mail)
- Webformulier (web form)

### Channel in Reporting
The Management Information API includes the channel in its response, enabling BI tools to analyze:
- Volume per channel over time
- Average handling time per channel
- Common questions per channel
- Channel shift trends (e.g., phone to digital)

### Admin CRUD
Beheerder role can create, edit, and delete channels. Deleting a channel does not affect historical contactmomenten that used it (the channel name is stored as a string value, not a foreign key reference).

## Requirements (as observed)
- Must support admin-configurable channel list
- Must allow channel selection per contactmoment
- Must pass channel to OpenKlant API
- Must include channel in management information exports
- Must not break historical data when channels are modified/deleted

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Channel tracking | Yes (dedicated kanaal field) | No explicit channel concept |
| Channel admin | Yes (CRUD in beheer) | N/A |
| Multi-channel per interaction | Yes (per vraag) | N/A |
| Channel reporting | Yes (management info API) | N/A |
| Default channels | None (admin configures) | N/A |

**Gap for Pipelinq**: A "channel" or "source" field on contact moments would enable tracking how interactions originate (phone, email, web form, etc.). This is a simple addition to the existing contact moment schema.
