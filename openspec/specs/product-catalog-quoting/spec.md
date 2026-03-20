# Product Catalog & Quoting Specification (Cross-App)

## Purpose

Extend the existing product catalog with quote generation capabilities across Conduction apps: line items with quantities and discounts, quote lifecycle management, PDF proposal generation, and multi-app quote-to-order conversion. For government context, maps to "producten en diensten" offerings with formal pricing and legesberekening for permits.

This is a cross-app capability: Pipelinq provides the primary quoting UI for CRM leads, Procest can receive converted quotes as cases (e.g., permit applications with leges), and Docudesk handles PDF generation with NL Design System tokens for government-branded output.

**Consuming apps**: Pipelinq (CRM quoting), Procest (quote-to-case conversion, legesberekening), Docudesk (PDF generation)
**Tender frequency**: 12% leges/heffingen (8/69); 39% documentcreatie (27/69); 38% workflow (26/69)
**Standards**: Dutch BTW law, Schema.org Offer/Invoice, NL Design System, Docudesk PDF generation

---

## Requirements

### Requirement 1: Quote entity

The system MUST provide a Quote entity stored in OpenRegister with full lifecycle support.

#### Scenario 1.1: Create quote from lead
- GIVEN a lead "Gemeente ABC digital transformation" with value EUR 25,000
- WHEN the user clicks "Offerte maken"
- THEN a Quote MUST be created linked to the lead with status "concept" and generated quote number (e.g., "OFF-2026-0042")
- AND client and contact references MUST be inherited from the lead

#### Scenario 1.2: Quote number generation
- GIVEN the most recent quote is "OFF-2026-0041"
- THEN the next MUST be "OFF-2026-0042"
- AND the prefix MUST be configurable, year component auto-set, and sequence reset yearly

#### Scenario 1.3: Create standalone quote
- GIVEN the user navigates to Quotes section
- THEN a blank quote with status "concept" MUST be creatable without a lead

#### Scenario 1.4: Duplicate as new version
- GIVEN a rejected quote
- WHEN "Nieuwe versie" is clicked
- THEN a new Quote MUST be created with same data, incremented version, status "concept", and new number

#### Scenario 1.5: Quote for government leges (Procest)
- GIVEN a permit application in Procest with calculated leges
- THEN a quote/leges-overzicht MUST be generatable from the case with applicable tariffs
- AND the format MUST comply with Legesverordening requirements

---

### Requirement 2: Quote line items

QuoteLineItem entities MUST support quantities, unit pricing, percentage discounts, and tax calculation.

#### Scenario 2.1: Add product-based line item
- GIVEN an open quote in "concept"
- WHEN the user selects a product from the catalog
- THEN a line item MUST be created with product reference, pre-populated description and unitPrice, quantity 1, discount 0

#### Scenario 2.2: Custom line item
- GIVEN the user clicks "Vrije regel"
- THEN a line item without product reference MUST be creatable with manual description and price

#### Scenario 2.3: Discount calculation
- GIVEN item "Implementatie" at EUR 15,000 with 10% discount
- THEN line total MUST be: 1 * 15,000 * (1 - 10/100) = EUR 13,500

#### Scenario 2.4: Totals recalculation
- GIVEN multiple line items totaling EUR 24,500 subtotal with 21% BTW
- THEN taxAmount MUST be EUR 5,145 and total EUR 29,645

#### Scenario 2.5: Per-item tax rate for mixed-rate quotes
- GIVEN line items with different BTW rates (21% services, 9% goods, 0% exempt)
- THEN each item MUST support an individual tax rate override
- AND the quote total MUST correctly sum different tax rates

---

### Requirement 3: Quote lifecycle management

The system MUST enforce status transitions with validation and side-effects.

#### Scenario 3.1: Valid transitions
- THEN: concept->verzonden, verzonden->geaccepteerd/afgewezen/verlopen/concept, afgewezen/verlopen->concept MUST be allowed
- AND geaccepteerd->any and concept->geaccepteerd MUST be rejected

#### Scenario 3.2: Send quote
- GIVEN a concept quote with at least one line item
- WHEN "Verzenden" is clicked
- THEN status MUST change to "verzonden", sentDate set, expiryDate set (default: +30 days)
- AND notification and activity event MUST be published

#### Scenario 3.3: Cannot send empty quote
- GIVEN zero line items
- THEN "Verzenden" MUST show validation error and status MUST remain "concept"

#### Scenario 3.4: Accept quote updates lead
- GIVEN an accepted quote with total EUR 24,500
- THEN the linked lead's value MUST update to the subtotal
- AND the lead MAY advance to the next stage (configurable)

#### Scenario 3.5: Auto-expire overdue quotes
- GIVEN a "verzonden" quote past expiryDate
- THEN status MUST auto-update to "verlopen" with notification to assignee

---

### Requirement 4: PDF proposal generation

Professional PDFs MUST be generated via Docudesk with NL Design System tokens.

#### Scenario 4.1: Generate PDF
- GIVEN a complete quote
- THEN a PDF MUST contain: org header (name, logo, address), client details, quote number/date/expiry, line items table, subtotal/BTW/total, payment terms
- AND the PDF MUST be stored in Nextcloud Files

#### Scenario 4.2: NL Design tokens
- GIVEN NL Design System tokens configured
- THEN the PDF MUST apply: primary color, font family, logo placement
- AND MUST comply with WCAG AA contrast

#### Scenario 4.3: Customizable template
- GIVEN admin settings
- THEN organization details, logo, default payment terms, and footer MUST be configurable

#### Scenario 4.4: Government-branded output (Procest)
- GIVEN a case-related leges overzicht
- THEN the PDF MUST use the municipality's NL Design theme
- AND MUST include gemeente wapen/logo and official formatting

---

### Requirement 5: Quote list and overview views

Dedicated views MUST be provided for managing quotes.

#### Scenario 5.1: Quote list
- THEN a table with: number, title, client, subtotal, total, status, sent date, expiry MUST display
- AND filtering by status and sorting by any column MUST be supported

#### Scenario 5.2: Quote detail
- THEN: header with number/status/client, line items (inline editing for concept), financial summary, status timeline, action buttons, lead link MUST display

#### Scenario 5.3: Multiple quotes per lead
- GIVEN a lead with 3 quotes (v1 rejected, v2 rejected, v3 accepted)
- THEN an "Offertes" section on the lead MUST list all with accepted highlighted

---

### Requirement 6: Quote-to-order conversion

Accepted quotes MUST be convertible to actionable records.

#### Scenario 6.1: Convert to Pipelinq request
- GIVEN an accepted quote
- WHEN "Omzetten" is clicked
- THEN a request MUST be created with client, contact, quote reference, and description from line items

#### Scenario 6.2: Convert to Procest case
- GIVEN Procest is installed
- THEN the option to create a Procest case MUST be offered
- AND the case MUST carry quote reference and financial details

#### Scenario 6.3: Prevent duplicate conversion
- GIVEN a quote already converted
- THEN a warning MUST show with link to existing entity
- AND user MUST confirm before creating a duplicate

---

### Requirement 7: Quote permissions and audit

Access controls and audit trail MUST be maintained.

#### Scenario 7.1: Quote visibility
- THEN all quotes MUST be visible to all authenticated app users by default
- AND admin-configurable visibility (all/team/own) SHOULD be available

#### Scenario 7.2: Audit trail
- THEN each status transition MUST be recorded with: timestamp, user, previous/new status, optional reason

#### Scenario 7.3: Quote deletion
- THEN confirmation MUST be required
- AND accepted quotes MUST show additional warning
- AND delete MUST cascade to line items

---

### Requirement 8: Quote notifications and activity

CRM notifications and activity events MUST be published for all lifecycle events.

#### Scenario 8.1: Quote sent notification
- GIVEN a quote is sent by someone other than the lead's assignee
- THEN the assignee MUST receive a notification

#### Scenario 8.2: Quote expiry warning
- GIVEN a quote expiring within 3 days
- THEN the assignee MUST receive a warning notification

#### Scenario 8.3: Activity setting
- THEN a "Quotes" toggle MUST be available in Activity settings with stream and email controls

---

### Requirement 9: Admin settings for quoting

Configurable settings MUST be provided.

#### Scenario 9.1: Default settings
- THEN configurable: quote number prefix (OFF), default expiry (30 days), default tax rate (21%), payment terms, org details, logo path, PDF output directory, acceptance auto-advance (boolean), target stage

#### Scenario 9.2: Tax rate flexibility
- THEN the default rate MUST be configurable
- AND per-item tax rate overrides MUST be supported

#### Scenario 9.3: Currency field
- THEN a `currency` field (default EUR) MUST be present for future extensibility

---

### Requirement 10: Quote search and cross-app integration

Quotes MUST be searchable and cross-referenced across the CRM and case management.

#### Scenario 10.1: Search by quote number
- GIVEN search for "OFF-2026-004"
- THEN all matching quotes MUST be returned

#### Scenario 10.2: Client quote history
- GIVEN a client detail view
- THEN an "Offertes" section MUST show all quotes for this client

#### Scenario 10.3: Quote-product analytics
- GIVEN product revenue reporting
- THEN quote line items MUST be included with "quoted" vs "accepted" distinction

#### Scenario 10.4: Leges rapportage (Procest)
- GIVEN permit cases with associated leges
- THEN a leges overview report MUST be available showing: total leges, per zaaktype, per period
- AND the report MUST be exportable for financial administration

---

## Data Model

### Quote Schema (OpenRegister)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `quoteNumber` | string | computed | Auto-generated (e.g., "OFF-2026-0042") |
| `lead` | string (uuid) | no | Parent lead reference |
| `client` | string (uuid) | YES | Client reference |
| `contact` | string (uuid) | no | Contact person reference |
| `status` | string | YES | concept/verzonden/geaccepteerd/afgewezen/verlopen |
| `title` | string | YES | Quote title |
| `sentDate` | date | no | Date sent |
| `expiryDate` | date | no | Expiry date |
| `subtotal` | number | computed | Sum of line item totals |
| `taxRate` | number | no | Default tax rate (%) |
| `taxAmount` | number | computed | Calculated tax |
| `total` | number | computed | subtotal + taxAmount |
| `currency` | string | no | Currency code (default EUR) |
| `paymentTerms` | string | no | Payment terms text |
| `notes` | string | no | Internal notes |
| `version` | number | no | Version number (default 1) |
| `assignee` | string | no | Nextcloud user ID |
| `sourceApp` | string | YES | Originating app |

### QuoteLineItem Schema

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `quote` | string (uuid) | YES | Parent quote reference |
| `product` | string (uuid) | no | Product reference |
| `description` | string | YES | Line item description |
| `quantity` | number | YES | Units (min 0.01) |
| `unitPrice` | number | YES | Price per unit |
| `discount` | number | no | Discount % (0-100) |
| `taxRate` | number | no | Per-item tax rate override |
| `total` | number | computed | qty * price * (1 - discount/100) |
| `sortOrder` | number | no | Display order |

---

## Dependencies

- OpenRegister (quote and line item storage)
- Pipelinq (lead management, product catalog)
- Procest (case conversion, legesberekening, optional)
- Docudesk (PDF generation)
- NL Design System (PDF styling)
- Nextcloud Files (PDF storage)
- Activity and Notification services

## Standards & References

- Dutch BTW law (21% standard, 9% reduced)
- Schema.org Offer, Invoice
- Docudesk PDF generation API
- NL Design System tokens
- Krayin CRM Quote module (competitive reference)
