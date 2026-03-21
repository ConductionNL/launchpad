# ZAC Configuration (Inrichting) Manual Summary

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/manuals/inrichting-zaakafhandelcomponent/inrichting-zaakafhandelcomponent.md
Latest version: V4.1

## Zaakafhandelparameters (Case Handling Parameters)

Core configuration per zaaktype:
- Select workflow type: CMMN (generic) or BPMN (custom)
- Assign default group for case creation
- Link product request types to zaaktypes
- Configure warning windows for target/deadline dates
- Enable/disable individual tasks within workflows
- Assign form definitions to tasks
- Set task groups and throughput times
- Link reference tables to task choice lists
- Define results for case termination scenarios (not admissible, wrong org, withdrawn, duplicate)

## Zaaktype Configuration Tabs

### Gegevens (Data)
- Default group assignment for case creation

### Taakgegevens (Task Data)
- Toggle task availability
- Configure form definitions and default assignees
- Set task duration for deadline calculation

### Mailgegevens (Email Data)
- Enable/disable status emails (intake, completion phases)
- Default email sender
- Select email templates per communication type

### Zaakbeeindig Gegevens (Case Closure)
- Outcome mapping for case closure conditions

### Koppelingen (Links)
- Toggle BRP and KvK connections
- SmartDocuments integration with document type selection

## Email Template Management

- Edit subject and message with variable insertion
- Categories: task notifications, status updates, alerts
- Custom templates per mail category
- Templates linked to zaaktypes cannot be deleted

## User & Group Management (Keycloak)

- Group email addresses via attributes
- Functional role management
- Users inherit permissions via group membership
- Multiple group membership for different domain authorizations
- Group-level email notifications for unassigned cases

## Reference Tables

### System Tables (non-deletable)
- ADVIES — advice options
- AFZENDER — email sender addresses
- COMMUNICATIEKANAAL — contact method options
- DOMEIN — domain assignments (legacy)
- BRP configuration
- SERVER_ERROR_ERROR_PAGINA_TEKST — custom error pages

### Custom Tables
- Admin can add/edit/remove values

## Configuration Health Check (Inrichtingscheck)

Validates minimum required setup across:
- Case parameters
- Required status types
- Role definitions
- Document type links
- Decision type assignments

## Catalog Synchronization

Sync from registry system:
- Zaaktypes
- Document types
- Decision types
- Status types
- Role definitions
