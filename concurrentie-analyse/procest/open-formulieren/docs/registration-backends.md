# Open Formulieren — Registration Backends

## Overview

Registration backends are the mechanism by which Open Formulieren persists form submissions to external systems. Each backend is implemented as a plugin — a Python callable with a specific signature that receives a `Submission` object and configuration options.

## Plugin Architecture

```python
from openforms.submissions.models import Submission

def plugin(submission: Submission, options: dict) -> dict | None:
    """
    Persist the submission data to an external system.

    Raises:
        openforms.registrations.exceptions.RegistrationFailed
    """
```

- Plugins are registered with the framework and become selectable in the admin interface
- Each form can have one or more registration backends configured
- Failed registrations raise `RegistrationFailed`, marking the submission for retry
- Celery handles automatic retry with configurable backoff

## Available Registration Backends

### 1. ZGW APIs (Zaakgericht Werken)

The primary registration backend for Dutch government use cases.

**Configuration requires three API services:**
- **Zaken API (ZRC)** — Creates the Zaak (case)
- **Documenten API (DRC)** — Stores documents and attachments as Informatieobjecten
- **Catalogi API (ZTC)** — Provides Zaaktype and Informatieobjecttype definitions

**Registration steps:**
1. Create a Zaak with the configured Zaaktype URL
2. Upload each attachment as an Informatieobject with the configured Informatieobjecttype URL
3. Link each Informatieobject to the Zaak via ZaakInformatieobject
4. Set Zaakeigenschappen (case properties) from mapped form variables
5. Create Rollen (roles) for the submitter (initiator)
6. Optionally generate and attach a PDF summary of the submission

**Per-component document type:** Each file upload component can specify its own Informatieobjecttype, applicable to both ZGW and Objects API registration.

**Compatible systems:** OpenZaak, Rx.Mission, Decos JOIN, and other ZGW-compliant systems.

### 2. Objects API

A more flexible registration backend for custom data structures.

**Key concepts:**
- **Variable mappings** — Define a "contract" between the form and the processing application
- **JSON templates** — Map form variables to object properties in the Objects API
- **Object updates** — Since v3.0, can update existing objects rather than creating new ones
- **Objecttype versioning** — Maps to specific Objecttype versions in the Objecttypes API

**Advantages over ZGW:**
- More flexible data structure (not bound to Zaak schema)
- Easier to adjust forms without breaking the processing application
- Supports arbitrary JSON structures
- Can reference existing records for renewals/updates

**Configuration:**
- Objects API service endpoint
- Objecttype URL and version
- Variable-to-property mapping configuration
- Template for JSON body construction

### 3. StUF-ZDS

Legacy SOAP/XML integration for older case management systems.

**Features:**
- Sends submission data via StUF-ZDS messages
- Supports partner registration
- Partner details included in registration
- SOAP endpoint configuration with certificate-based authentication

**Status:** Legacy — municipalities are migrating to ZGW APIs. Still supported for backward compatibility.

### 4. Email

The simplest registration backend.

**Features:**
- Sends form data as a formatted email
- Attachments included
- Configurable recipients
- HTML email with submission summary

**Use cases:** Low-volume forms, simple notifications, testing.

### 5. Microsoft Graph (SharePoint/OneDrive)

Stores submissions and documents in Microsoft 365 environments.

### 6. Custom Plugins

Third-party plugins can be developed and registered following the plugin interface. Extensions can be installed to add additional registration backends.

## Comparison with Procest

| Aspect | Open Formulieren | Procest |
|--------|-----------------|---------|
| ZGW API support | Yes (registration target) | Yes (native ZGW proxy + business rules) |
| ZGW direction | Outbound (creates Zaken) | Bidirectional (reads + writes Zaken) |
| Objects API | Yes (registration target) | Via OpenRegister |
| StUF-ZDS | Yes (legacy support) | No |
| Email notifications | Yes (registration backend) | Via n8n workflows |
| Document storage | Uploads to DRC | Nextcloud Files + DRC sync |
| Plugin extensibility | Python plugin system | PHP services + n8n |
| Retry mechanism | Celery automatic retry | N/A (local processing) |

### Strategic Analysis

Open Formulieren's registration backends are **output-only** — they push data to external systems. Procest's ZGW integration is **bidirectional** — it both receives and manages cases. This is the fundamental difference: Open Formulieren creates the initial case record, while Procest manages the entire case lifecycle. For municipalities using both, the flow would be:

```
Open Formulieren → ZGW APIs → OpenZaak → Procest (reads Zaak and manages it)
```
