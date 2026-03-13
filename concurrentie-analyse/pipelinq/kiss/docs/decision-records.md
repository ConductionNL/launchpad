# KISS Decision Records Summary

Source: https://github.com/Klantinteractie-Servicesysteem/KISS-frontend/tree/main/docs/decision-record

## 1. Contact Moments (Contactmomenten)

### Contact Moment Details
KISS stores additional data beyond what the Klantinteracties API supports:
- `vraag` (question from knowledge base)
- `specifiekeVraag` (specific free-text question)
- `gespreksresultaat` (conversation result)
- `startdatum` / `einddatum` (timestamps for duration)
- `verantwoordelijkeAfdeling` (responsible department)
- `bronnen` (array of consulted sources with soort/titel/url)

These are stored in KISS's own PostgreSQL database and exposed via the Contactmomentdetails API.

### Extended POST for e-Suite
For e-Suite integration, KISS extends the contact moment POST with internal task data, because e-Suite treats a contact request as a single contact with a handler, not a separate InterneTaak object.

## 2. Contact Requests (Contactverzoeken)
- Implemented as InterneTaak in Open Klant 2.x Klantinteracties API
- Previously used a custom object type in Objecten API
- A contact request consists of: Klantcontact + InterneTaak + Betrokkene + Digitale Adressen + Actor(en)
- Departments and Groups are separate object types in the Objecten API
- Hierarchical relationship between Afdeling/Groep is optional (not enforced)
- Smoelenboek (employee directory) indexed in Elasticsearch

## 3. Cases (Zaken)
- Read-only integration via ZGW APIs
- Case detail screen shows data from Zaak, Status, Zaaktype, Resultaat, Documenten
- Cases linked to contact moments via `onderwerpobjectidentificator`
- Business search without vestigingsnummer: tries both RSIN and KvK number
- Multiple case management backends: errors from one system are ignored, results from others shown

## 4. Customer Interactions & Open Klant
- Hard-coded values: `indicatieContactGelukt=true`, `taal=nld`, `vertrouwelijk=false`
- Partij-identificatoren follow Open Klant 2.7.0 format
- Actor identification uses email from OIDC for KCM, Objects API identifiers for departments/groups/employees
- Subject field (`klantcontact.onderwerp`) max 200 chars — question truncated to fit, specific question preserved in full
- Content field (`klantcontact.inhoud`) max 1000 chars — scratchpad has no limit but validation on finalization screen

## 5. Multiple Registers
- One default register for unlinked contact moments
- Per-zaaksysteem contact/customer registers
- Supports both OpenKlant2 and OpenKlant1 (e-Suite) simultaneously
- Environment variables structured as arrays (REGISTERS__0__, REGISTERS__1__)

## 6. Authorization
- Application-level auth for API calls (no user tokens by default)
- User-level auth (JWT with user ID) added for e-Suite fine-grained authorization
- Permission-based system with RequirePermissionAttribute
- Yarp proxy routes use PermissionAuthorizationPolicyProvider

## 7. Search in Sources
- PDC (Kennisartikelen) based on SDG invoervoorziening API
- VAC (Q&A) based on comparison with SDU-catalogus structure
- Both object types published as Community Concepts in Open Objecten
- HTML content supported with sanitization, inline styling ignored, header tags downsized
- Collapsible sections supported via `<details>` / `<summary>` tags

## 8. Processing Logging
- KISS logs which data was accessed during customer interactions for AVG/GDPR compliance
