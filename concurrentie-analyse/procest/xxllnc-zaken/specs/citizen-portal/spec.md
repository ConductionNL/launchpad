---
status: draft
source: competitive-analysis
competitor: xxllnc-zaken
analyzed_date: 2026-03-14
---
# Citizen Portal (Persoonlijke Internet Pagina / PIP)

category: citizen-facing
maturity: production
source: https://xxllnc.nl/applicaties/zaken

## Summary

xxllnc Zaken includes a Persoonlijke Internet Pagina (PIP) — a citizen-facing portal where residents and organizations can conduct digital transactions with the government. The PIP provides 24/7 self-service access to case status, documents, and messaging.

## Capabilities

### Self-Service Portal
- Personal internet page per citizen/organization
- 24/7 access to case status and information
- View all cases (open and closed) relevant to the citizen
- Track permit status (e.g., tree cutting permits)
- Check report processing status (e.g., street furniture reports)

### Document Exchange
- Publish documents from organization to citizen
- Citizens can upload documents to their cases
- Zaakdossier (case file) visible on PIP

### Communication
- Message exchange between citizen and organization
- Receive status updates and notifications
- Reduces pressure on front and mid-office departments

### Digital Transactions
- Submit digital requests via smart forms
- Identification and authentication integration
- Form data stored directly in case records

### Branding
- Custom styling based on organization's house style (huisstijl)
- White-label portal appearance

## Strengths
- Integrated with case management — no separate system needed
- 24/7 self-service reduces workload on staff
- Custom branding per organization
- Document exchange in both directions
- Proven at scale (gemeente Epe, gemeente Utrecht)

## Weaknesses
- No public information about authentication methods (DigiD, eHerkenning integration details)
- Portal appears to be a proprietary solution, not based on open standards
- No evidence of accessibility compliance (WCAG) documentation
- No mobile app — web-based only
- No mention of multi-language support
- Limited customization beyond branding

## Codebase Analysis (from GitLab source)

### Frontend Structure

```
frontend-mono/apps/my-pip/
  src/
    components/
      ErrorDialog/          -- Error handling UI
      PipCaseDocuments/     -- Case document viewer for citizens
    modules/
      communication/        -- Citizen-caseworker messaging (uses shared communication-module)
```

### Document Visibility Control

Documents are only visible to citizens when `publish.pip = true` is set by the caseworker. This is a `DocumentPublishSettings` typed dict: `{pip: bool, website: bool}`.

### Notification Preferences

Citizens can set `preferred_contact_channel`: `pip` | `email` | `phone` | `mail`

### Related Frontend Apps

| App | Purpose |
|-----|---------|
| mor | Meldingen Openbare Ruimte (public space reports) |
| objection-app | Bezwaar/objection filing |
| vergadering | Meeting management |
| external-components | Shared citizen-facing components |

### Shared Packages Used

The `communication-module` React package is shared between the caseworker app and the citizen portal, ensuring consistent messaging experience.

## Relevance to Procest

Procest should aim for:
1. **Open standards portal** -- use NL Design System tokens for government theming
2. **DigiD/eHerkenning** -- first-class Dutch authentication integration
3. **WCAG AA compliance** -- mandatory for government applications
4. **Progressive Web App** -- mobile-first citizen experience
5. **Multi-channel** -- portal + email + push notifications
6. **Nextcloud sharing** -- leverage Nextcloud's built-in file sharing for document exchange
7. **Document publish control** -- similar pip/website visibility flags on documents
