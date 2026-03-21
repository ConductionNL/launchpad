---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Productaanvraag (Product Request) Intake -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC handles incoming product requests from external forms.
- **Product**: Dimpact ZAC
- **Category**: Case Intake
- **Relevance to Procest**: Automated case creation from external systems is essential for digital service delivery

## Architecture Overview
Productaanvragen are external form submissions (typically from e-formulieren/Open Formulieren) that arrive via the Objecten API notification webhook. ZAC processes these into cases with documents.

Key components:
- `NotificationReceiver` -- webhook endpoint at `/rest/notificaties`
- `ProductaanvraagService` -- processes product requests
- Inbox for unprocessed requests
- JSON schema validation

## Data Model

### Productaanvraag (from Objecten API)
| Field | Type | Description |
|-------|------|-------------|
| type | String | Product request type |
| data | Map | Form submission data |
| bsn | String | Citizen BSN (optional) |
| kvkNummer | String | Business KVK number (optional) |
| bijlagen | List<URI> | Attached document URIs |

### Inbox Productaanvraag
| Field | Type | Description |
|-------|------|-------------|
| id | Long | Auto-generated |
| productaanvraagObjectUUID | UUID | Objecten API object UUID |
| type | String | Product request type |
| ontvangstdatum | LocalDate | Received date |
| initiatorID | String | BSN or KVK number |
| aantalBijlagen | Integer | Number of attachments |

## Business Logic

### Notification Webhook Flow
1. POST `/rest/notificaties` with auth header
2. Validate secret key
3. Determine notification type (zaak, informatieobject, object, etc.)
4. For product request objects: `productaanvraagService.handleProductaanvraag()`

### Product Request Processing
1. Read object from Objecten API
2. Validate against JSON schema
3. Determine zaaktype from productaanvraagtype mapping
4. If mapping found:
   - Create zaak in ZRC
   - Start CMMN or BPMN process
   - Add initiator (BSN or KVK)
   - Attach documents from bijlagen
   - Set communication channel to "E-formulier"
5. If no mapping found:
   - Add to inbox for manual processing

### Manual Processing from Inbox
- Admin/coordinator reviews inbox items
- Selects zaaktype and creates case manually
- Links original documents
- Removes from inbox

### BPMN Process Start
When a productaanvraag matches a BPMN configuration:
1. Look up `ZaaktypeBpmnProcessDefinition` by productaanvraagtype
2. Create zaak
3. Start BPMN process with zaak variables
4. Assign to configured group

## Requirements (as observed)

1. Incoming requests are validated against JSON schemas
2. Mapping from productaanvraagtype to zaaktype is configured in admin
3. Unmapped requests go to an inbox for manual handling
4. Both CMMN and BPMN process starts are supported
5. Attachments are automatically linked to the created case
6. Initiator is set from BSN/KVK in the request
7. Notification webhook requires shared secret authentication

## Comparison Notes
- ZAC's webhook-based intake is well-suited for integration with Open Formulieren
- The inbox fallback for unmapped types prevents data loss
- Procest could implement similar intake via n8n workflows
- The JSON schema validation adds reliability
- BPMN process start from productaanvraag enables complex intake workflows
