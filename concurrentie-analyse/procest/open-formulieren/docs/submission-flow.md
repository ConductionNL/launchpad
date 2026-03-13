# Open Formulieren — Submission Flow

## End-to-End Submission Lifecycle

### 1. Form Discovery & Loading

- Citizen visits a municipality website
- CMS page loads the Open Forms SDK via JavaScript
- SDK fetches available forms from the public API (`/api/v2/forms/`)
- Form definition (JSON schema) is loaded including steps, components, and logic

### 2. Authentication (Optional)

- If the form requires authentication, the user is redirected to the configured authentication provider
- Supported: DigiD, eHerkenning, eIDAS, OIDC, SAML
- After authentication, BSN (DigiD) or KvK number (eHerkenning) becomes available
- Authentication triggers prefill of known data

### 3. Prefill

- Based on authenticated identity, form fields are automatically populated
- Data sources:
  - **BRP** (Basisregistratie Personen) via Haal Centraal or StUF-BG — name, address, date of birth
  - **KvK** (Kamer van Koophandel) via Haal Centraal HR — company name, address, registration number
  - **Objects API** — existing records/products associated with the user (since v3.0)
- Prefilled fields can be marked as read-only or editable

### 4. Form Completion

- User fills in the form step by step
- Logic rules evaluate in real-time, showing/hiding fields and steps
- Validation runs on each step transition
- "Save and continue later" stores partial submission server-side
- File uploads are stored temporarily

### 5. Submission Confirmation

- User reviews a summary of all entered data
- Co-signing: if configured, a co-signer receives an email and must approve
- Payment: if a payment is required, user is redirected to the payment provider (Ogone/Worldline)
- User submits the form

### 6. Registration (Backend Processing)

Registration happens asynchronously via Celery after submission:

#### ZGW APIs Registration
- Creates a Zaak in the Zaken API (ZRC) with the configured Zaaktype
- Uploads documents/attachments as Informatieobjecten in the Documenten API (DRC)
- Links Informatieobjecten to the Zaak via ZaakInformatieobjecten
- Optionally sets Zaakeigenschappen (case properties) from form variables
- Optionally creates Rollen (roles) for the submitter
- Requires: ZRC, DRC, and ZTC (Catalogi API) service configuration

#### Objects API Registration
- Creates or updates an Object in the Objects API
- Variable mappings define a "contract" between form and processing application
- JSON template maps form variables to object properties
- More flexible than ZGW for custom data structures
- Can update existing objects (since v3.0)

#### StUF-ZDS Registration
- Sends submission data via SOAP/XML to StUF-ZDS compliant systems
- Legacy integration for older case management systems
- Supports partner registration

#### Email Registration
- Sends form data and attachments as an email to configured recipients
- Simplest registration backend, suitable for low-volume or non-critical forms

### 7. Confirmation & Notifications

- User sees a confirmation page with a reference number
- Confirmation email is sent to the submitter
- Payment confirmation (if applicable)
- PDF summary of the submission can be generated and attached

### 8. Post-Submission

- Failed registrations are retried automatically (Celery retry mechanism)
- Failed submissions are flagged for manual intervention via admin digest email
- Submissions can be viewed in the admin interface for debugging
- Temporary submission data is cleaned up after configurable retention period

## Comparison with Procest Submission Flow

| Stage | Open Formulieren | Procest |
|-------|-----------------|---------|
| Intake channel | Web form (SDK embedded in CMS) | ZGW API (receives Zaken), manual creation |
| Citizen-facing UI | Yes (SDK renders form) | No (internal case workers only) |
| Citizen authentication | DigiD, eHerkenning, eIDAS | N/A (no citizen-facing portal) |
| Data prefill | BRP, KvK, Objects API | N/A |
| Payment collection | Yes (Ogone/Worldline) | No |
| Case creation | Submits to external ZGW/Objects API | Native ZGW case management |
| Post-submission workflow | None (hands off to external system) | Full lifecycle: tasks, deadlines, documents, decisions |
| Retry on failure | Yes (Celery automatic retry) | N/A (case is created locally) |
| Audit trail | Submission log only | Full case audit trail |

### Analysis

Open Formulieren excels at the **intake** phase (citizen-facing form, authentication, prefill, payment) but stops at submission registration. Procest excels at everything **after** intake (case lifecycle, task management, decisions, document handling). They are architecturally complementary — Open Formulieren creates the Zaak, Procest manages it.
