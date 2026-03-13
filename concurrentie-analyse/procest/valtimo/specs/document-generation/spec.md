---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Document Generation -- Valtimo

## Purpose
Generates formatted documents (PDF, DOCX) from case data using templates. Supports both local PDF generation and integration with SmartDocuments, a commercial template-based document generation service popular in Dutch government organizations.

## Architecture Overview
- **Backend module**: `document-generation/` (interfaces and local implementation)
- **SmartDocuments plugin**: Dedicated plugin for external template-based generation
- **Storage**: Generated documents stored via the `resource/` module (local, S3, or temp)
- **Integration**: Triggered from BPMN service tasks via plugin actions
- **Linking**: Generated documents can be automatically linked to ZGW Documenten API

## Data Model

### DocumentGenerationRequest
| Field | Type | Description |
|-------|------|-------------|
| templateId | String | Template identifier (SmartDocuments template or local template) |
| documentId | UUID | Case document to pull data from |
| outputFormat | Enum | PDF, DOCX, etc. |
| placeholders | Map<String, Object> | Additional placeholder values |

### GeneratedDocument (output)
| Field | Type | Description |
|-------|------|-------------|
| fileName | String | Generated file name |
| content | byte[] | File content |
| mimeType | String | Content type |
| metadata | Map | Additional metadata |

## Business Logic

### SmartDocuments Integration
1. BPMN service task triggers SmartDocuments plugin action
2. Plugin resolves template ID and gathers case data via value resolvers
3. Data sent to SmartDocuments API for template rendering
4. Generated document returned as PDF/DOCX
5. Document stored via resource module
6. Optionally uploaded to ZGW Documenten API and linked to the zaak

### Local PDF Generation
1. Template-based PDF generation using local rendering
2. Case data injected into template placeholders
3. PDF generated in-memory and stored

### BPMN Integration
- Document generation actions linked to BPMN service tasks via ProcessLinks
- Fully automated -- no user interaction required
- Value resolvers (`doc:`, `pv:`) provide template data
- Output file reference stored as process variable for downstream use

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- Uses **Docudesk** Nextcloud app for document generation
- Templates stored in Nextcloud file system
- Twig-based templating for document rendering
- Tighter integration with Nextcloud's native file management

### Valtimo advantages
- SmartDocuments integration (widely used in Dutch municipalities)
- Automatic linking to ZGW Documenten API
- Template data resolution via value resolvers
- BPMN-triggered generation (fully automated in process flow)

### Valtimo disadvantages
- SmartDocuments is a commercial, closed-source dependency
- Local generation capabilities are basic compared to dedicated tools
- No template editing within the Valtimo UI
- No preview before generation
- Generated documents not stored in a collaborative file system (unlike Nextcloud)
