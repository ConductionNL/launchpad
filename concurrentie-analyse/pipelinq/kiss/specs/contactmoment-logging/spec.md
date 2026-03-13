---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Contactmoment Logging - KISS

## Purpose
The core workflow of KISS. When a KCM (klantcontactmedewerker) handles a customer interaction (phone call, email, walk-in), they start a "contactmoment" which tracks everything that happens during the interaction: which client, which questions were asked, which knowledge articles were consulted, which cases were linked, and what the result was.

## Architecture Overview
- **Frontend**: Pinia store (`useContactmomentStore`) manages state for all active contactmomenten
- **BFF**: `ContactmomentDetailsToevoegen` controller stores extended details in PostgreSQL
- **External**: OpenKlant 1/2 API stores the canonical contactmoment record
- Supports both OpenKlant v1 (contactmomenten API) and v2 (klantcontacten API) via feature flag

## Data Model

### ContactmomentState (Frontend)
```typescript
interface ContactmomentState {
  vragen: Vraag[];           // Multiple questions per contactmoment
  huidigeVraag: Vraag;       // Currently active question
  session: Session;          // Session-scoped state isolation
  route: string;
}
```

### Vraag (Question - Frontend)
```typescript
interface Vraag {
  zaken: ContactmomentZaak[];      // Linked cases
  notitie: string;                 // Free-text notes
  contactverzoek: ContactmomentContactVerzoek; // Contact request
  startdatum: string;
  kanaal: string;                  // Channel (phone/email/counter)
  gespreksresultaat: string;       // Conversation result
  klanten: { klant, shouldStore }[];
  medewerkers: { medewerker, shouldStore }[];
  websites: { website, shouldStore }[];
  kennisartikelen: { kennisartikel, shouldStore }[];
  nieuwsberichten: { nieuwsbericht, shouldStore }[];
  werkinstructies: { werkinstructie, shouldStore }[];
  vacs: { vac, shouldStore }[];
  vraag: Bron | undefined;         // Selected question/topic
  specifiekevraag: string;         // Specific question text
  afdeling?: Afdeling;
}
```

### ContactmomentDetails (BFF Entity)
```csharp
class ContactmomentDetails {
  string Id;
  DateTimeOffset Startdatum;
  DateTimeOffset Einddatum;
  string? Gespreksresultaat;
  string? Vraag;
  string? SpecifiekeVraag;
  string? EmailadresKcm;
  string? VerantwoordelijkeAfdeling;
  List<ContactmomentDetailsBron> Bronnen;  // Sources consulted
}
```

## Business Logic

### Multi-Contactmoment Switching
KISS supports multiple simultaneous contactmomenten. A KCM can put one call on hold and start handling another, then switch back. Each contactmoment has its own isolated Pinia session.

### Source Tracking
Every knowledge article, website, news item, VAC, and employee consulted during the contactmoment is tracked with a `shouldStore` flag. This creates an audit trail of what information the KCM used.

### Gespreksresultaat (Conversation Result)
Configurable dropdown of possible results: "Doorverbonden" (transferred), "Afgehandeld" (resolved), "Contactverzoek gemaakt" (contact request created), etc.

## Requirements (as observed)
- Must support starting/stopping multiple concurrent contactmomenten
- Must track which knowledge sources were consulted
- Must link contactmoment to client (persoon/bedrijf)
- Must link contactmoment to case (zaak)
- Must record channel (kanaal), result, and timing
- Must support both OpenKlant v1 and v2 APIs

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Multi-interaction | Yes (concurrent sessions) | Single active interaction |
| Multi-question | Yes (multiple vragen per CM) | Single thread |
| Source tracking | Yes (articles, VACs, etc.) | No |
| Channel tracking | Yes (kanaal field) | No explicit channel |
| Result tracking | Yes (gespreksresultaat) | Via pipeline stage |
| Case linking | Yes (zaaksysteem) | No zaaksysteem |
| Client linking | Yes (OpenKlant) | Yes (contact selection) |
| Notes | Yes (notitie per vraag) | Yes (contact moment notes) |
| Timing | Yes (start/end timestamps) | Yes (timestamps) |
