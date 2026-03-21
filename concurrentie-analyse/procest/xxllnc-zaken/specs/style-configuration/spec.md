---
status: draft
source: competitive-analysis
competitor: xxllnc-zaken
analyzed_date: 2026-03-14
---
# Style Configuration -- xxllnc Zaken

## Purpose

Multi-tenant theming system allowing each tenant (municipality) to customize the visual appearance of the zaaksysteem and citizen portal with logos, CSS, favicon, and JSON configuration.

## Architecture Overview

- **HTTP Service:** `zsnl_style_http` (path `/api/v2/style/`)
- **Domain:** `zsnl_domains/configuration/`
- **Entity version:** Minty entity_v2 (Pydantic v2)
- **Frontend Module:** `customStyling`

## Data Model

### StyleConfiguration Entity

```
StyleConfiguration:
  entity_type: "style_configuration"
  tenant: str          # tenant identifier
  name: str            # file name
  content: str         # file content (base64 for binary)
  extension: str       # file extension
  mimetype: str        # MIME type
  last_modified: datetime
```

### Tenant Entity

```
Tenant:
  tenant_id: str
  configuration: dict
```

### Allowed Configuration Files

| File | Purpose |
|------|---------|
| config.json | General configuration |
| cssProps.json | CSS custom properties |
| favicon.ico | Browser tab icon |
| logo-login.png/svg/jpg/jpeg | Login page logo |
| logo-pip.png/svg/jpg/jpeg | Citizen portal logo |
| stylesheet.css | Custom CSS stylesheet |

**Max file size:** 10 MB

## Business Logic

### Style Management

Per-tenant style files are stored and served:
1. Admin uploads configuration files (logos, CSS, config)
2. Files validated against whitelist of allowed names
3. Frontend loads tenant-specific styles at runtime
4. Citizen portal ("Mijn PIP") renders with tenant branding

### Events

- `StyleConfigurationCreated` -- emitted on create (fire_always)

## Requirements (as observed)

1. Per-tenant customization via named configuration files
2. Strict whitelist of allowed file names/types
3. 10 MB file size limit
4. Support for PNG, SVG, JPG/JPEG logos
5. CSS custom properties for theming
6. Separate logos for login page and citizen portal
7. JSON-based configuration alongside CSS

## Comparison Notes

**vs Procest/NLDesign:**
- xxllnc uses a file-based approach (upload specific named files)
- Procest/NLDesign uses CSS custom properties (design tokens) from NL Design System
- NLDesign's token-based approach is more flexible and standardized
- xxllnc's approach is simpler but less integrated with the NL Design System standard
- Both support multi-tenant theming; different implementation strategies
