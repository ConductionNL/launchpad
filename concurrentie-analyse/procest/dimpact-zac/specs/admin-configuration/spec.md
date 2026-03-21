---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Admin Configuration -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC implements administrative configuration.
- **Product**: Dimpact ZAC
- **Category**: Administration
- **Relevance to Procest**: Admin configuration determines how case types behave -- critical for deployability

## Architecture Overview
ZAC has an extensive admin panel for configuring case handling parameters, mail templates, reference tables, form definitions, process definitions, and group notification settings. Configuration is stored in PostgreSQL.

Key admin routes:
- `/admin/check` -- configuration health check (inrichtingscheck)
- `/admin/parameters` -- zaaktype handling parameters
- `/admin/mailtemplates` -- mail template management
- `/admin/referentietabellen` -- reference tables
- `/admin/formulierdefinities` -- custom form definitions
- `/admin/formioformulieren` -- Form.io form management
- `/admin/processdefinitions` -- BPMN process definitions
- `/admin/groepen` -- group notification settings

## Data Model

### Zaakafhandelparameters (Case Handling Parameters)
| Field | Type | Description |
|-------|------|-------------|
| zaaktypeUUID | UUID | Case type reference |
| defaultBehandelaar | String | Default handler username |
| defaultGroepId | String | Default group |
| caseDefinitionKey | String | CMMN case definition |
| intakeMail | Boolean | Auto-send intake mail |
| afrondenMail | Boolean | Auto-send completion mail |
| productaanvraagtype | String | Product request type mapping |
| domein | String | Domain for zaaktype access (legacy) |
| creatiedocumentEnabled | Boolean | Allow document creation |

### Reference Tables (Referentietabellen)
System-defined and custom key-value tables:
- `COMMUNICATIEKANAAL` -- communication channels
- `AFZENDER` -- sender addresses
- `SERVER_ERROR_PAGINA_TEKST` -- error page text
- `BRP_DOELBINDING_ZOEK_WAARDE` -- BRP purpose binding for search
- `BRP_DOELBINDING_RAADPLEEG_WAARDE` -- BRP purpose binding for lookup
- `BRP_VERWERKINGSREGISTER_WAARDE` -- BRP processing register

### Zaaktype BPMN Configuration
| Field | Type | Description |
|-------|------|-------------|
| zaaktypeOmschrijving | String | Case type description |
| processDefinitionKey | String | BPMN process key |
| productaanvraagtype | String | Product request type |
| groepNaam | String | Assigned group name |

### Zaaktype CMMN Configuration
Similar to BPMN but for CMMN-driven case types, with human task parameters, form definitions, and status mail settings.

### Health Check (Inrichtingscheck)
Validates per zaaktype:
- Required role types present (initiator, behandelaar, etc.)
- Required status types present (Intake, In behandeling, Afgerond, etc.)
- Required information object types present
- Result types configured
- Zaakafhandelparameters configured
- Reference table values populated

## Business Logic

### Configuration Health Check
1. Lists all active zaaktypes from ZTC
2. For each zaaktype, validates:
   - Has required roltypen: INITIATOR, BEHANDELAAR, ADVISEUR, BELANGHEBBENDE, BESLISSER, KLANTCONTACTER, MEDE_INITIATOR, ZAAKCOORDINATOR
   - Has required statustypen: Intake, In behandeling, Afgerond, Heropend, Aanvullende informatie
   - Has email informatieobjecttype
   - Has resultaattypen
   - Has zaakafhandelparameters configured
   - All configuration values are valid ASCII
3. Returns per-zaaktype checklist with pass/fail indicators

### Zaaktype Parameter Management
- Admin configures per-zaaktype: default group, handler, CMMN/BPMN definition, mail settings
- Human task parameters configure forms and deadlines per plan item
- Smart Documents template mappings per zaaktype

### Reference Table Management
- CRUD for reference table values
- Some tables are system-defined (cannot be deleted)
- Values have ordering and active/system flags

## Requirements (as observed)

1. Every zaaktype needs explicit configuration before use
2. Health check validates against ZTC catalog data
3. CMMN and BPMN configurations are separate entities
4. Reference tables provide configurable dropdown values
5. Admin requires `beheerder` role
6. Build information displayed (branch, commit, version, build date)

## Configuration Tabs (from Inrichting Manual V4.1)

### Per-Zaaktype Configuration Tabs
1. **Gegevens** -- default group assignment for case creation
2. **Taakgegevens** -- toggle task availability, form definitions, default assignees, task durations
3. **Mailgegevens** -- enable/disable status emails, default sender, email templates per communication type
4. **Zaakbeeindig Gegevens** -- outcome mapping for case closure scenarios (not admissible, wrong org, withdrawn, duplicate)
5. **Koppelingen** -- toggle BRP/KvK connections, SmartDocuments integration with document type selection

### Email Template Management
- Edit subject and message with variable insertion for case/task data
- Categories: task notifications, status updates, alerts
- Custom templates per mail category
- Templates linked to zaaktypes cannot be deleted

### Catalog Synchronization
Sync from registry system:
- Zaaktypes, document types, decision types, status types, role definitions

### System Reference Tables (non-deletable)
- ADVIES, AFZENDER, COMMUNICATIEKANAAL, DOMEIN, BRP config values, error page text

## Comparison Notes
- ZAC's health check (inrichtingscheck) is a unique feature -- validates deployment readiness per zaaktype
- The extensive per-zaaktype configuration is powerful but requires careful admin work
- Reference tables provide flexibility without code changes
- Procest could benefit from a similar configuration validation system
- The dual CMMN/BPMN configuration adds complexity but supports diverse workflows
- Email template management with variable insertion is mature
- Catalog sync from registry system keeps configurations aligned with ZGW standards
