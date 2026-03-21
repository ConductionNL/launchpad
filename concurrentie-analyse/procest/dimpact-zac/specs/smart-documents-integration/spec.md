---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# SmartDocuments Integration -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC integrates with SmartDocuments for document generation.
- **Product**: Dimpact ZAC
- **Category**: Document Creation
- **Relevance to Procest**: Automated document generation from templates is valuable for government workflows

## Architecture Overview
ZAC integrates with SmartDocuments, a commercial document generation service, to create documents from templates with case data. Templates are managed in the admin panel and mapped to zaaktypes.

Key services:
- `DocumentCreationService` -- orchestrates document creation
- `SmartDocumentsService` -- SmartDocuments API client
- `SmartDocumentsTemplatesService` -- template management
- `DocumentCreationRestService` -- REST endpoint

Supports both CMMN-based and BPMN-based document creation with different data models.

## Data Model

### Template Hierarchy
- `SmartDocumentsTemplateGroup` -- folder/group of templates
- `SmartDocumentsTemplate` -- individual template with:
  - name, id, informatieObjectTypeUUID (mapped document type)

### Document Creation Data
| Field | Type | Description |
|-------|------|-------------|
| zaakUUID | UUID | Source case |
| taskId | String | Optional source task |
| smartDocumentsTemplateGroupId | String | Template group |
| smartDocumentsTemplateId | String | Template to use |

### Attended vs Unattended
- **Attended**: Opens SmartDocuments wizard in browser for user editing
- **Unattended**: Generates document directly without user interaction

## Business Logic

### Attended Document Creation
1. Check `zaak.creeren_document` policy
2. Build data model from case + task + user context
3. Generate SmartDocuments wizard URL with callback
4. User edits document in SmartDocuments
5. On callback: store generated document in DRC
6. Link to case

### Template Management
1. Admin fetches template tree from SmartDocuments API
2. Maps templates to informatieobjecttypen per zaaktype
3. Stored in local database (SmartDocuments template tables)
4. Validates template structure

### Data Model for Templates
Case data, user data, task data, and zaaktype data are converted to SmartDocuments variables for template population.

## Requirements (as observed)

1. SmartDocuments integration is feature-flagged (can be disabled per zaaktype)
2. Templates are mapped to zaaktypes and informatieobjecttypen
3. Attended mode provides interactive editing
4. Generated documents are automatically stored in DRC and linked to case
5. Separate CMMN and BPMN data converters handle different context types

## Comparison Notes
- SmartDocuments is a commercial product -- Procest could use open-source alternatives
- The template management UI in admin is valuable for non-technical users
- Attended document creation is a rich feature for complex documents
- Procest could implement similar functionality with Docudesk or OnlyOffice templates
