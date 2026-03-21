---
competitor: espocrm
analyzed_date: 2026-03-14
feature: lead-management
---

# Lead Management

## Overview

EspoCRM provides a complete lead management lifecycle: capture, qualification, assignment, and conversion to Account + Contact + Opportunity. The Lead entity extends `Person` (shared with Contact) and supports web form capture, campaign attribution, and duplicate detection during conversion.

## Lead Entity

### Core Fields
- **name** (personName: salutation, firstName, lastName)
- **status** (enum): New, Assigned, In Process, Converted, Recycled, Dead
- **source** (enum): Call, Email, Existing Customer, Partner, PR, Web Site, Campaign, Other
- **industry** (enum): References Account.industry options
- **opportunityAmount** (currency): Estimated deal value
- **title** (varchar): Job title
- **accountName** (varchar): Company name (text, not linked)
- **emailAddress** (email), **phoneNumber** (phone)
- **address** (composite address)
- **doNotCall** (bool)
- **convertedAt** (datetime, read-only)
- **campaign** (link): Source campaign
- **targetLists** (linkMultiple): Marketing lists

### Status Lifecycle
```
New -> Assigned -> In Process -> Converted
                              -> Recycled
                              -> Dead
```

Not-actual options (terminal states): Converted, Recycled, Dead.

## Lead Capture System

### LeadCapture Entity
Configurable web forms that create leads via public API:
- **fieldList**: Configurable fields exposed on the form (default: firstName, lastName, emailAddress)
- **isActive**: Enable/disable capture endpoint
- **campaign**: Auto-link captured leads to a campaign
- **targetList**: Auto-subscribe to target list
- **subscribeToTargetList** / **subscribeContactToTargetList**: Control list subscription behavior

### Capture API Endpoints
- `POST /LeadCapture/:apiKey` - Submit lead data (no auth required, CORS-enabled)
- `POST /LeadCapture/form/:id` - Form-based capture

Supports reCAPTCHA integration for spam prevention.

## Lead Conversion

### ConvertService (`Tools/Lead/ConvertService.php`)

The conversion process atomically creates up to three entities from a single lead:

1. **Account** - Created from `accountName` and address fields
2. **Contact** - Created from name, email, phone fields; linked to account with title as role
3. **Opportunity** - Created from `opportunityAmount` and `source`; linked to account and contact

### Field Mapping

Conversion uses two mapping strategies:

1. **Auto-mapping**: Fields with the same name and type in Lead and target entity are automatically copied
2. **Explicit mapping** (in `convertFields` metadata):
   - Account.name <- Lead.accountName
   - Account.billingAddress* <- Lead.address*
   - Opportunity.amount <- Lead.opportunityAmount
   - Opportunity.leadSource <- Lead.source

### Post-Conversion Processing
- Lead status set to "Converted"
- All linked Meetings are re-parented to Opportunity (or Account)
- All linked Calls are re-parented similarly
- All linked Emails are re-parented
- Documents are linked to new Account and Opportunity
- Contacts added to Meeting/Call attendee lists
- Stream followers are carried over

### Duplicate Detection
Before conversion, the service checks for duplicate Accounts, Contacts, and Opportunities. If duplicates are found, a `ConflictSilent` exception is thrown with the duplicate list, allowing the user to choose to skip or merge.

### Conversion Entities List
Configured in metadata: `"convertEntityList": ["Account", "Contact", "Opportunity"]`. This is extensible via custom metadata.

## Lead Distribution

The `Business/Distribution/Lead/` directory contains lead distribution logic for round-robin or least-busy assignment to team members.

## Meeting/Call Acceptance

Leads can be invited to meetings and calls with an `acceptanceStatus` column on the M:N relationship, tracking whether they accepted, declined, or tentatively accepted.

## Relevance to Pipelinq

### Strengths
- Complete lead lifecycle with status tracking
- Web form capture with API key authentication
- Sophisticated conversion with field mapping and duplicate detection
- Post-conversion link migration (meetings, calls, emails follow the conversion)

### Opportunities for Pipelinq
- **Nextcloud Forms integration**: Instead of custom LeadCapture, Pipelinq could use Nextcloud Forms as lead capture mechanism
- **n8n-powered routing**: Lead assignment via n8n workflows instead of built-in round-robin
- **Flexible conversion**: OpenRegister schemas allow custom conversion mappings per use case
- **No separate Lead entity needed**: In OpenRegister, a lead is just an object with a status; conversion is a schema change rather than entity migration
