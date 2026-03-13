# Registration Backends

## What Open Forms Does

Registration backends are the mechanism by which completed form submissions are sent to external case management or document systems. This is the **critical integration point** between Open Forms (intake) and case management (processing).

### Plugin Architecture
- `BasePlugin` abstract class with `register_submission()`, `pre_register_submission()`, `update_payment_status()`, `verify_initial_data_ownership()`
- `FormRegistrationBackend` model allows multiple registration backends per form (with unique key)
- Logic rules can switch which registration backend is used based on form data
- `PreRegistrationResult` dataclass returns reference number and intermediate data

### Registration Plugins

#### ZGW API Registration (`zgw-create-zaak`)
The primary integration with OpenZaak and other ZGW-compliant platforms:

**Pre-registration:**
- Creates a Zaak (case) via Zaken API
- Resolves ZaakType from Catalogi API (by catalogue + identification + valid date)
- Sets zaak identificatie as public reference number
- Stores zaak URL in `registration_result`

**Registration:**
- Creates Rol (initiator role) on the Zaak with BSN/KvK mapping
- Uploads confirmation PDF as Document via Documenten API
- Uploads user-submitted file attachments as Documents
- Creates Zaak-Eigenschappen (case properties) mapped from form variables
- Creates Status on the Zaak
- Optionally creates Zaak-Object relations
- Handles family member roles (partners, children)

**Configuration per form:**
- `ZGWApiGroupConfig`: API group with URLs to Zaken/Documenten/Catalogi APIs
- Catalogue, case type identification, document type
- Organisation RSIN, confidentiality level, author
- Property (eigenschap) mappings from form variables
- Option to use generated or ZGW-assigned case number

#### Objects API Registration (`objects-api`)
Registers submissions as Objects in the Objects API:

**Two versions:**
- **V1 (legacy)**: JSON template-based, renders submission data using Django templates (`content_json`), creates a "productaanvraag" object
- **V2 (mapped variables)**: Direct variable-to-JSON-path mapping, more structured

**Key operations:**
- Creates or updates object in Objects API
- Attaches documents via Documenten API
- Supports `initial_data_reference` for updating existing objects
- Validates object ownership via `auth_attribute_path`

#### StUF-ZDS Registration (`stuf-zds-create-zaak`)
Legacy SOAP-based registration for older municipal systems:
- Creates Zaak via StUF-ZDS (SOAP/XML)
- Adds documents, status updates
- Maps BSN/KvK to StUF initiator fields

#### Email Registration
- Sends submission data as email with optional PDF attachment
- Configurable recipient, subject, template

#### Camunda Registration
- Starts a Camunda BPMN process instance with submission data

#### Microsoft Graph Registration
- Uploads submission data/files to SharePoint/OneDrive via Microsoft Graph API

#### Generic JSON Registration
- POSTs submission data as JSON to a configurable endpoint

#### Demo Registration
- No-op plugin for testing

### Multi-Backend Support
- Forms can have multiple `FormRegistrationBackend` entries
- Logic rules can dynamically select which backend to use (`finalised_registration_backend_key`)
- Fallback: first configured backend

## Already in Procest

- OpenRegister as object store (objects can be created/updated)
- Basic Zaak concept (Procest models cases)
- n8n workflows for external integrations

## Not Yet in Procest

- **ZGW API case creation from form data** -- No automatic Zaak creation with Rol, Status, Eigenschappen, Documents
- **Catalogi API type resolution** -- No dynamic ZaakType/DocumentType resolution by catalogue
- **Objects API registration** -- No structured variable-to-object mapping for Objects API
- **StUF-ZDS SOAP integration** -- No legacy SOAP registration support
- **Multi-backend per form** -- No ability to configure multiple registration targets per form
- **Logic-driven backend selection** -- No dynamic backend switching based on form data
- **Pre-registration / registration split** -- No two-phase pattern (create case, then enrich)
- **Document upload to DMS** -- No automatic PDF report + attachment upload to Documenten API
- **Eigenschap mapping** -- No form-variable-to-case-property mapping
- **Rol creation** -- No automatic initiator role creation on cases
- **Payment status update on case** -- No post-payment callback to registration backend
- **Email registration** -- No email-as-registration-target option
- **Camunda process start** -- No BPMN process instance creation from form submission
