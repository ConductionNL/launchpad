# KISS Features Overview

Source: ReadTheDocs table of contents, GitHub source, decision records, manual pages

## Core Features

### 1. Contact Moment Registration (Contactmomenten)
The primary function of KISS. When a citizen or business contacts the municipality, the KCM (Klantcontactmedewerker) registers the interaction as a "contact moment" (klantcontact).

Data captured:
- Channel (phone, counter, email, chat, social media)
- Question (from knowledge base) and specific question (free text)
- Notes (scratchpad during conversation, max 1000 chars in Open Klant)
- Conversation result (e.g., "Doorverbonden", "Zelfstandig afgehandeld")
- Responsible department
- Consulted sources (knowledge articles, VACs, websites, news, work instructions)
- Start/end time (for duration tracking)
- Linked case (zaak) if applicable
- Customer identification

### 2. Unified Search (Zoeken in Bronnen)
Elasticsearch-powered search across multiple knowledge sources:
- PDC (Kennisartikelen / product pages) — based on SDG invoervoorziening API
- VAC (Vraag-Antwoord Combinaties) — Q&A pairs
- News and work instructions (OpenPub standard)
- Employee directory (Smoelenboek)

Search uses two-step Elasticsearch process:
1. App Search search-explain endpoint builds query template
2. Elasticsearch search endpoint executes with template

### 3. Customer Lookup (Klant / Integraal Klantbeeld)
- Person search via Haal Centraal BRP (by BSN, name, date of birth)
- Business search via KvK API (by KvK number, name, vestigingsnummer)
- View contact history per customer
- View linked cases per customer
- Customer registration in Open Klant (Partij with identifiers)
- Digital addresses (email, phone) with validation

### 4. Contact Requests (Contactverzoeken)
Internal task creation for follow-up:
- Assign to department, group, or individual employee
- Internal notes for colleague
- Customizable contact request forms per department/group
- Stored as InterneTaak in Open Klant 2.x Klantinteracties API
- Actor identification (department/group/employee)

### 5. Case Management Integration (Zaaksysteem)
Read-only integration with case management via ZGW APIs:
- View case details (status, type, dates, documents)
- Search cases by number, BSN, KvK number
- Link contact moments to cases
- Deep links to case management system
- Support for multiple case management backends

### 6. Staff Directory (Smoelenboek)
Employee information lookup:
- Search by name
- View contact details, department, skills
- Direct assignment for contact requests
- Data from Objects API, indexed in Elasticsearch

### 7. News & Work Instructions (Nieuws en Werkinstructies)
Admin-managed content:
- News articles for KCM awareness
- Work instructions for procedures
- Published via Objects API (OpenPub standard)
- Searchable via Elasticsearch

### 8. Admin / Management (Beheer)
Back-office management features:
- Skills management (tag employees with skills)
- Links management (configurable quick links for KCMs)
- Conversation results (configurable dropdown values)
- Contact request forms (per department/group, customizable fields)
- Channel management (add/edit/delete channels)
- VAC management (Q&A pairs)
- Search source management

### 9. Management Information API
REST API for extracting KCC performance data:
- JWT-authenticated
- Query parameters for date range filtering
- Returns contact moment details (question, specific question, result, duration, department, sources consulted)
- Designed for BI/reporting tools

### 10. Feedback on Knowledge Articles
- KCMs can provide feedback on knowledge articles
- Feedback sent via email to content managers
- Helps improve knowledge base quality

### 11. Multi-Channel Support (Kanalen)
Configurable communication channels:
- Phone, counter, email, chat, social media
- Each contact moment tagged with channel used
- Channels managed by administrators

### 12. Authorization (Autorisatie)
- OIDC-based authentication (Azure AD / EntraID)
- Application-level authorization for API calls
- Optional user-level authorization (JWT with user ID) for e-Suite integration
- Permission-based authorization system (RequirePermissionAttribute)
- Yarp proxy routes with PermissionAuthorizationPolicyProvider
