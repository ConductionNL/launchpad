# EspoCRM Documentation Structure

**Source:** https://docs.espocrm.com
**Fetched:** 2026-03-14
**Docs repo:** https://github.com/espocrm/documentation/ (MkDocs Material)

## Administration

### Server Configuration
- Configuration, Apache, Nginx, IIS

### System
- Installation (manual, by script, Docker, Traefik, Caddy)
- Upgrading
- Extensions management
- Jobs (scheduled tasks / cron)
- Config parameters
- Log
- Console commands
- WebSocket

### Essentials
- Terms & naming
- Troubleshooting
- Backup and restore
- Performance tweaking
- Moving to another server
- Security

### Customization
- Entity Manager (create/edit entities, fields, relationships)
- Fields (field types and configuration)
- Layouts (Layout Manager for list/detail/form views)
- Dynamic Logic (conditional field visibility/required)
- API Before-Save script

### Other Admin Topics
- Users management
- Roles management (ACL)
- Email administration
- Formula script + Functions + Function reference (general, string, datetime, number, entity, record, env, password, array, object, language, json, ext, util, log)
- Import
- Portal
- Web-to-Lead
- Currency
- Dashboards
- Authentication (2FA, OpenID Connect, LDAP/AD/OpenLDAP)
- Webhooks
- Passwords
- Phone numbers, Addresses, Maps
- B2C mode
- Multiple assigned users
- File storage
- SMS sending
- App secrets

## User Guide

### Emails
- General guidelines
- IMAP & SMTP configuration
- Mass email

### Other User Guide Topics
- Stream (activity feed)
- Sales management
- Case management
- Activities & calendar
- Mail merge
- Knowledge base
- Documents
- Export
- Text search
- Working time calendar
- Printing to PDF
- Shortcut keys
- Markdown syntax
- Browser support
- Data privacy
- Complex expressions
- Optimistic concurrency control

## Extensions Documentation

### Advanced Pack
- Overview
- Reports
- Workflows
- BPM (Overview, Gateways, Events, Activities, Examples, Signals, Compensation, Formula functions, Drip email campaign, Tracking URLs, Tips, Configuration)

### Sales Pack
- Overview, Products, Prices
- Sales: Quotes, Sales orders, Invoices, Credit notes, Delivery orders, Return orders, Write-offs, Subscriptions
- Purchases: Suppliers, Purchase orders, Receipt orders, Bills, Bill credits
- Inventory management, Payments, Taxes, Tax codes
- Issuance locking, Multi-currency, Reports

### Project Management
- Projects

### Meeting Scheduler

### Google Integration
- Setting-up, Calendar, Contacts, Gmail

### Outlook Integration
- Setting-up, Calendar, Contacts, Email

### VoIP Integration
- Overview, 3CX PBX, Asterisk, Twilio, Starface, Binotel, IexPBX
- Docker container, Customization, Troubleshooting

### Zoom Integration, Stripe Integration

### Export Import (tool)
- Overview, Export, Import, Compare, Run by code, Customization

## Developer Documentation

### Getting Started
- Index, How to start, Extension packages, Modules, Tests, Translation, Coding rules

### Backend
- Dependency injection
- Metadata (extensive reference: scopes, entityDefs, aclDefs, selectDefs, recordDefs, clientDefs, entityAcl, pdfDefs, logicDefs, notificationDefs, streamDefs, fields, dashlets, authenticationMethods, integrations)
- App metadata (acl, actions, admin-panel, api, authentication, cleanup, client, config, console commands, container services, currency, database platforms, date time, dashboard layouts, and many more)

### API
- Overview, CRUD operations, Related records, Stream, CurrencyRate, Attachment, I18n, Metadata
- OpenAPI specification (as of v9.3 - /api/v1/OpenApi endpoint)
- Client implementations: PHP, JavaScript (Node.js), Python, Rust, Java, Go, Zig
- Authentication: API Key, HMAC, Basic Auth
- Search parameters, Usage tutorial

### Frontend Development
- View system, templates, field types, controller and routing
- Custom views, buttons, actions, panels
- Dynamic handler, ajax requests

### Other Development Topics
- Services, hooks (before/after save/delete)
- Calculated fields, select builder
- Scheduled jobs, mass actions
- App params, ORM/query builder
- Email sending, duplicate checking
- API action, entry points
