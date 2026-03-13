---
status: draft
source: competitive-analysis
competitor: open-klant
analyzed_date: 2026-03-13
---

# Maak Klantcontact (Composite Contact Creation) -- Open Klant

## Purpose

A convenience endpoint that creates a Klantcontact, Betrokkene, and Onderwerpobject in a single atomic request. This avoids the need for three separate API calls to register a complete contact interaction.

- **Product**: Open Klant
- **Category**: API Convenience / Composite Endpoint
- **Relevance to Pipelinq**: Pattern for atomic multi-object creation in a single API call.

## API Endpoint

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| POST | `/klantinteracties/api/v1/maak-klantcontact/` | Create Klantcontact + Betrokkene + Onderwerpobject | Token |

### Request Shape

```json
{
  "klantcontact": {
    "kanaal": "telefoon",
    "onderwerp": "Vraag over paspoort",
    "inhoud": "Burger belt over verlenging paspoort",
    "taal": "nld",
    "vertrouwelijk": false,
    "indicatieContactGelukt": true,
    "plaatsgevondenOp": "2026-03-13T10:00:00Z"
  },
  "betrokkene": {
    "wasPartij": {"uuid": "..."},
    "rol": "klant",
    "initiator": true,
    "contactnaam": {
      "voornaam": "Jan",
      "achternaam": "Jansen"
    }
  },
  "onderwerpobject": {
    "onderwerpobjectidentificator": {
      "objectId": "095be615-...",
      "codeObjecttype": "zaak",
      "codeRegister": "open-zaak",
      "codeSoortObjectId": "uuid"
    }
  }
}
```

### Response

Returns the created objects with all their fields populated, including auto-generated UUIDs and URLs.

## Business Logic

1. Creates `Klantcontact` from the `klantcontact` data
2. If `betrokkene` is provided:
   - Sets `had_klantcontact` to the created klantcontact
   - Creates `Betrokkene` via its serializer
3. If `onderwerpobject` is provided:
   - Sets `klantcontact` to the created klantcontact
   - Creates `Onderwerpobject` via its serializer
4. All wrapped in `@transaction.atomic`

Both `betrokkene` and `onderwerpobject` are optional in the request.

## Pipelinq Comparison

**Already in Pipelinq**: None
**Not yet in Pipelinq**: Composite endpoints for atomic multi-object creation. This pattern could be valuable for Pipelinq's client management to register a complete interaction in one call.
