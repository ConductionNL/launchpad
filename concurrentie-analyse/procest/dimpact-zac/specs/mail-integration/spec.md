---
status: draft
source: competitive-analysis
competitor: dimpact-zac
analyzed_date: 2026-03-14
---

# Mail Integration -- Dimpact ZAC

## Purpose
Competitive analysis spec documenting how Dimpact ZAC implements email functionality.
- **Product**: Dimpact ZAC
- **Category**: Communication
- **Relevance to Procest**: Email is a key communication channel in government case processing

## Architecture Overview
ZAC sends emails via SMTP (Jakarta Mail). Emails are based on configurable templates with variable substitution. Sent emails are converted to PDF and stored as documents in DRC.

Key services:
- `MailService` -- SMTP sending, PDF generation, template resolution
- `MailTemplateHelper` -- template variable substitution
- Mail template management in admin panel

## Data Model

### MailGegevens (Mail Data)
| Field | Type | Description |
|-------|------|-------------|
| from | MailAdres | Sender address |
| to | String | Recipient(s) |
| subject | String | Email subject |
| body | String | HTML body |

### Mail Templates
- Stored in database (Flyway migrations V33-V48)
- Per-zaaktype template configuration
- Template variables resolved from case/task/document data

### Bronnen (Data Sources)
Templates can reference data from:
- Zaak (case) properties
- Taak (task) properties
- Document properties
- These are resolved at send time via `MailTemplateHelper`

## Business Logic

### Email Sending Flow
1. Compose email with template + variable data
2. Resolve template variables from bronnen
3. Send via SMTP
4. Convert email body to PDF (using iText)
5. Store PDF as informatieobject in DRC
6. Link document to case

### PDF Generation
- HTML email body converted to PDF using iTextPDF + HtmlConverter
- PDF stored with metadata in DRC
- Provides audit trail of all sent communications

### Receipt Confirmation (Ontvangstbevestiging)
- Automatic email confirmation on case creation
- Tracks whether confirmation was sent via Flowable variable
- Skipped for reopened cases

### Mail Template Administration
- Admin panel for managing mail templates
- Templates linked to zaaktype configurations
- Standard templates with defaults (migrations V33-V44)

## Requirements (as observed)

1. All sent emails are archived as PDF documents linked to the case
2. Template system supports variable substitution from case/task data
3. Receipt confirmation is automatic but conditional
4. Sender address configured per-zaaktype
5. SMTP credentials configurable via environment variables
6. Email body is HTML-based

## Comparison Notes
- ZAC's email-to-PDF archiving is notable for compliance/audit requirements
- Template management is built into the admin panel
- Procest uses n8n for email workflows which provides more flexibility
- The automatic receipt confirmation is a useful government workflow feature
