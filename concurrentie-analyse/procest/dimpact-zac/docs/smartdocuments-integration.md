# ZAC SmartDocuments Integration

Source: https://github.com/infonl/dimpact-zaakafhandelcomponent/blob/main/docs/solution-architecture/smartDocumentsIntegration.md

## Configuration

Each zaaktype configuration defines a mapping between SmartDocuments template and document type.

## Document Creation Flow

1. User starts SmartDocument wizard from ZAC (from zaak or task)
2. ZAC sends HTTPS request to SmartDocuments with case/task information
3. SmartDocuments opens document creation wizard with pre-filled data
4. User completes wizard; SmartDocuments:
   - Creates Word document and stores it
   - Calls ZAC callback endpoint
5. ZAC callback:
   - Downloads Word document from SmartDocuments
   - Creates document in Open Zaak (enkelvoudiginformatieobject)
   - Sets confidentiality to PUBLIC, status to IN_PROGRESS
   - Sets usage permissions (required for zaak closure)
   - Links document to case/task (zaakinformatieobject)
6. Open Zaak sends notification to Open Notificaties
7. Open Notificaties notifies ZAC of new document
8. User can optionally download document locally
9. Document appears in ZAC when user returns to browser tab
