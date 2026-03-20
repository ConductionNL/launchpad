# Public Intake Forms Specification (Cross-App)

## Purpose

Provide embeddable HTML forms for external websites that create entities in Conduction apps upon submission. Forms are customizable in styling, support spam protection, and can be embedded via iframe or JavaScript snippet. For CRM (Pipelinq): web-to-lead forms capture website visitors as contacts and leads. For government (Procest): citizen intake forms create case requests (zaak-aanvragen). For both: a no-code form builder enables non-technical users to create and manage forms.

This is a cross-app capability: Pipelinq uses it for web-to-lead capture, Procest uses it for citizen case intake (e-formulieren), and OpenRegister provides the public API endpoints and data storage.

**Consuming apps**: Pipelinq (web-to-lead), Procest (citizen intake/e-formulieren), OpenRegister (public API, storage)
**Tender frequency**: 61% formulieren/intake (42/69); 22% persoonlijke portaal (15/69)
**Standards**: CORS, HTTPS, GDPR/AVG, WCAG AA, Nextcloud PublicPage routes

---

## Requirements

### Requirement 1: No-code form builder

The system MUST provide a visual form builder for creating public intake forms without coding.

#### Scenario 1.1: Create basic contact form
- GIVEN a user with form management permissions
- WHEN they create a form with fields: naam, email, telefoon, bericht
- THEN the form MUST be saved with a unique public URL
- AND each field MUST be configurable: label, type, required/optional, placeholder

#### Scenario 1.2: Supported field types
- THEN the following types MUST be supported: text (single line), textarea (multi-line), email (with validation), phone (with validation), select/dropdown (configurable options), checkbox, radio, file upload (with size/type restrictions), hidden (for tracking source/campaign), date, number

#### Scenario 1.3: Map form fields to entity properties
- GIVEN a form with fields naam, email, bedrijf, vraag
- THEN each field MUST be mappable to a contact, lead, or case property
- AND unmapped fields MUST be stored in a notes/custom fields property

#### Scenario 1.4: Conditional field visibility
- GIVEN a field "Type verzoek" with options "Vergunning" and "Informatie"
- THEN the user MUST be able to configure: show field "Kadastraal perceel" only when "Vergunning" is selected
- AND conditions MUST support: equals, not equals, contains, is empty, is not empty

#### Scenario 1.5: Multi-step form wizard
- GIVEN a complex intake form with 20+ fields
- THEN the builder MUST support grouping fields into steps/pages
- AND the public form MUST display a progress indicator
- AND users MUST be able to navigate forward and backward between steps

---

### Requirement 2: Form submission creates entities

Submissions MUST create the configured entities in the target app.

#### Scenario 2.1: Submission creates contact + lead (Pipelinq)
- GIVEN a form configured for Pipelinq
- WHEN submitted with naam "Jan Bakker", email "jan@example.nl", bericht "Interesse in dienstverlening"
- THEN a Contact MUST be created (or matched by email) and a Lead created linked to the contact
- AND the lead MUST be placed on the configured default pipeline and first stage

#### Scenario 2.2: Submission creates case request (Procest)
- GIVEN a form configured for Procest with zaaktype "Omgevingsvergunning"
- WHEN submitted with naam, adres, beschrijving, and uploaded tekening.pdf
- THEN a new case MUST be created in Procest with the configured zaaktype
- AND the uploaded file MUST be stored as a case document
- AND the submitter MUST be linked as "aanvrager"

#### Scenario 2.3: Duplicate contact handling
- GIVEN an existing contact with email "jan@example.nl"
- WHEN a form submission arrives with the same email
- THEN the existing contact MUST be matched (not duplicated)
- AND a new lead/case MUST be linked to the existing contact

#### Scenario 2.4: Submission notification
- GIVEN a form with notification configured for user "maria"
- WHEN submitted
- THEN "maria" MUST receive a Nextcloud notification with submission details

#### Scenario 2.5: Submission confirmation to citizen
- GIVEN a form with email confirmation enabled
- WHEN submitted with a valid email address
- THEN the citizen MUST receive a confirmation email with reference number
- AND the email MUST NOT contain sensitive submitted data (for privacy)

---

### Requirement 3: Form embedding

Forms MUST be embeddable on external websites via multiple methods.

#### Scenario 3.1: Embed via iframe
- GIVEN a published form
- THEN an iframe HTML snippet MUST be provided with configurable width/height
- AND the form MUST be served over HTTPS

#### Scenario 3.2: Embed via JavaScript snippet
- GIVEN a published form
- THEN a JavaScript snippet MUST be available
- AND the script MUST inject the form and handle submission via AJAX (no page reload)

#### Scenario 3.3: Custom styling
- GIVEN the form configuration
- THEN colors, fonts, button styling, and NL Design tokens MUST be customizable
- AND a "Preview" MUST show the form as it will appear embedded

#### Scenario 3.4: NL Design System styling (government)
- GIVEN a municipality has NL Design System tokens configured
- THEN public forms MUST automatically apply the municipality's theme
- AND the form MUST comply with WCAG AA

#### Scenario 3.5: Direct URL access
- GIVEN a published form
- THEN it MUST also be accessible via a direct URL (not only embedded)
- AND the direct URL page MUST include a minimal header and footer

---

### Requirement 4: Spam protection

Public forms MUST include multiple spam protection mechanisms.

#### Scenario 4.1: Honeypot field
- GIVEN a published form
- THEN a hidden honeypot field MUST be included
- AND submissions with the honeypot filled MUST be silently discarded

#### Scenario 4.2: Rate limiting
- GIVEN a public form endpoint
- WHEN more than 10 submissions from the same IP within 5 minutes
- THEN subsequent MUST be rejected with 429 status
- AND the rate limit MUST be configurable per form

#### Scenario 4.3: Optional CAPTCHA
- GIVEN a form with CAPTCHA enabled
- THEN hCaptcha or Cloudflare Turnstile MUST be presented
- AND the token MUST be verified server-side

#### Scenario 4.4: File upload restrictions
- GIVEN a form with file upload fields
- THEN file type whitelist (e.g., pdf, jpg, png), maximum size (e.g., 10MB), and maximum file count MUST be configurable
- AND rejected files MUST show clear error messages

---

### Requirement 5: Form management

A management interface MUST be provided for forms.

#### Scenario 5.1: Form list
- THEN all forms MUST be listed with: name, status (active/inactive), submission count, created date, target app
- AND actions MUST include: edit, preview, embed code, deactivate, duplicate

#### Scenario 5.2: Submission history
- GIVEN a form with 50 submissions
- THEN all MUST be listed with: timestamp, submitter details, created entities, status
- AND CSV export MUST be available

#### Scenario 5.3: Form analytics
- GIVEN a published form over 30 days
- THEN analytics MUST show: total submissions, completion rate, average time to complete, drop-off per step (for multi-step forms)

---

### Requirement 6: Form storage and data model

Forms MUST be stored as structured entities.

#### Scenario 6.1: Form entity
- THEN a `form` schema MUST be defined with: title, description, fields (array of field definitions), targetApp, targetEntityType, fieldMappings, styling, active (boolean), publicUrl, spamProtection config

#### Scenario 6.2: Submission entity
- THEN a `formSubmission` schema MUST store: formId, submittedAt, submitterData, createdEntities (references), ipAddress (hashed for rate limiting), status

#### Scenario 6.3: Form versioning
- GIVEN a form is edited while submissions are active
- THEN changes MUST create a new version
- AND existing submission links MUST reference the form version at time of submission

---

### Requirement 7: DigiD/eHerkenning integration (government)

Government intake forms SHOULD support authenticated submission via DigiD or eHerkenning.

#### Scenario 7.1: DigiD authenticated submission
- GIVEN a form configured for DigiD authentication
- WHEN a citizen clicks "Inloggen met DigiD"
- THEN the citizen MUST be redirected to the DigiD login flow
- AND upon successful authentication, BSN MUST be available for pre-populating form fields

#### Scenario 7.2: eHerkenning for business forms
- GIVEN a form configured for eHerkenning
- THEN business users MUST authenticate via eHerkenning
- AND KVK number MUST be available for pre-populating business fields

#### Scenario 7.3: Anonymous submission option
- GIVEN a form for general inquiries
- THEN DigiD/eHerkenning MUST be optional
- AND the form MUST clearly indicate which submissions require authentication

---

### Requirement 8: Pre-population from base registries

Authenticated forms SHOULD pre-populate fields from BRP/KVK data.

#### Scenario 8.1: Pre-populate from BRP
- GIVEN a DigiD-authenticated citizen
- THEN naam, adres, geboortedatum MUST be pre-populated from BRP
- AND pre-populated fields MUST be read-only (citizen cannot override registry data)

#### Scenario 8.2: Pre-populate from KVK
- GIVEN an eHerkenning-authenticated business user
- THEN bedrijfsnaam, vestigingsadres, rechtsvorm MUST be pre-populated from KVK

#### Scenario 8.3: Pre-population failure handling
- GIVEN BRP/KVK lookup fails
- THEN the form MUST still function with empty fields
- AND the citizen MUST be able to manually enter the data
- AND the system MUST log the lookup failure

---

### Requirement 9: Payment integration for leges

Government intake forms SHOULD support leges payment as part of the submission flow.

#### Scenario 9.1: Leges calculation display
- GIVEN a permit application form with leges applicable
- THEN the form MUST display the calculated leges amount based on form inputs
- AND the calculation MUST update dynamically as the citizen fills in fields

#### Scenario 9.2: Online payment
- GIVEN leges are applicable
- THEN the form MUST support redirecting to an online payment provider (iDEAL)
- AND the case MUST only be created after successful payment (or marked as "betaling openstaand")

#### Scenario 9.3: Payment exemption
- GIVEN certain submissions are exempt from leges
- THEN the form MUST detect exemption conditions
- AND display "Geen leges verschuldigd" instead of the payment step

---

### Requirement 10: Accessibility and i18n

Public forms MUST be fully accessible and support multiple languages.

#### Scenario 10.1: WCAG AA compliance
- GIVEN a public form
- THEN all fields MUST have proper labels, error messages MUST be associated with fields, and keyboard navigation MUST work fully
- AND color contrast MUST meet WCAG AA minimums

#### Scenario 10.2: Dutch and English
- GIVEN the form builder
- THEN labels, validation messages, and system text MUST support Dutch and English
- AND the form language MUST be auto-detected from browser locale or explicitly selectable

#### Scenario 10.3: Screen reader support
- GIVEN a multi-step form
- THEN step transitions MUST be announced to screen readers
- AND validation errors MUST be announced on form submission attempt

---

### Requirement 11: Form submission workflow integration

Form submissions SHOULD trigger automation workflows.

#### Scenario 11.1: n8n workflow on submission
- GIVEN a form with workflow trigger configured
- WHEN a submission is received
- THEN the configured n8n workflow MUST be triggered with submission data
- AND the workflow can perform: email confirmation, assignment, enrichment, case creation

#### Scenario 11.2: Conditional routing
- GIVEN a form with routing rules
- THEN submissions MUST be routed to different teams based on form field values
- AND routing rules MUST be configurable per form

#### Scenario 11.3: Submission status tracking
- GIVEN a submission that triggers a case creation
- THEN the citizen MUST receive a reference number
- AND the citizen SHOULD be able to check submission status via a public status page

---

## Data Model

### Form Schema (OpenRegister)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `title` | string | YES | Form title |
| `description` | string | no | Form description |
| `fields` | array | YES | Field definitions with type, label, required, mappings |
| `steps` | array | no | Multi-step groupings |
| `targetApp` | string | YES | Target app (pipelinq, procest) |
| `targetEntityType` | string | YES | Entity to create on submission |
| `fieldMappings` | object | YES | Map of field IDs to entity properties |
| `styling` | object | no | Custom CSS/NL Design tokens |
| `active` | boolean | YES | Whether form accepts submissions |
| `publicUrl` | string | computed | Generated public URL |
| `spamProtection` | object | no | Honeypot, rate limit, CAPTCHA config |
| `authentication` | string | no | none, digid, eherkenning |
| `notifyUsers` | array | no | Users to notify on submission |
| `workflowId` | string | no | n8n workflow to trigger |

---

## Dependencies

- OpenRegister (form and submission storage, public API)
- Pipelinq (contact/lead creation)
- Procest (case creation, optional)
- OpenConnector (DigiD/eHerkenning integration, BRP/KVK pre-population)
- Docudesk (submission confirmation PDF)
- NL Design System (government theming)
- n8n (submission workflows)
- Nextcloud Notification API

## Standards & References

- CORS -- cross-origin form submissions
- HTTPS -- mandatory for public forms
- GDPR/AVG -- privacy for collected personal data
- WCAG AA -- accessibility
- DigiD -- citizen authentication
- eHerkenning -- business authentication
- Nextcloud `#[PublicPage]` -- unauthenticated routes
- hCaptcha / Cloudflare Turnstile -- CAPTCHA providers
