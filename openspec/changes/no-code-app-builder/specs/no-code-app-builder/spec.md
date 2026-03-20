---
status: draft
---

# No-Code App Builder

**Owned by**: Cross-app capability (builds on OpenRegister schemas, applicable to all Conduction apps)

## Purpose
Enable administrators and citizen developers to build complete web applications from OpenRegister data without writing code, as a cross-app capability that any Conduction app can leverage. The builder MUST provide a visual page editor with drag-and-drop components (data tables, forms, detail views, charts, kanban boards, calendars, galleries), configurable navigation, conditional field visibility, role-based access control, multi-step form wizards, and NL Design System theming. Applications MUST be publishable as standalone experiences accessible to both authenticated Nextcloud users and unauthenticated public visitors, bridging the gap between raw register data and user-facing government services. This is a new app concept that builds on OpenRegister's schema system as its data foundation, with shared components from `@conduction/nextcloud-vue` providing the rendering layer. Pipelinq, Procest, and other apps could use built applications to expose their domain data to end users.

**Source**: Gap identified in cross-platform analysis; Baserow offers a full application builder with 20+ element types, 9 workflow actions, custom domain publishing, and user authentication. NocoBase provides a schema-driven UI builder with block-based page composition, 9+ data block types (Table, Form, Details, Kanban, Calendar, Gantt, Grid Card), and pluggable UI schemas stored as JSON. NocoDB offers 5 view types (Grid, Form, Gallery, Kanban, Calendar) with per-view filtering, sorting, and public sharing. No Conduction app currently offers a visual app builder, drag-and-drop page editor, or component library for user-created applications.

## Requirements

### Requirement: The system MUST support creating application definitions
Administrators MUST be able to define applications that bundle pages, data sources, navigation, theming, and access control into a cohesive experience. Application definitions MUST be stored as OpenRegister objects in a system register (self-hosting pattern), making them version-controlled, exportable, and manageable through the standard Object API. Each application definition MUST include: name, slug, icon, description, pages array, data source references (register/schema pairs), navigation configuration, access control settings, theme configuration, and publication status (draft/published).

#### Scenario: Create a simple application
- **GIVEN** a register `meldingen-register` with schema `meldingen`
- **WHEN** the admin creates an application:
  - Name: `Meldingen Portaal`
  - Slug: `meldingen-portaal`
  - Pages: list page + detail page
  - Data source: `meldingen-register/meldingen`
- **THEN** the application MUST be accessible at `/apps/openregister/app/meldingen-portaal`
- **AND** the application MUST display the configured pages
- **AND** the application definition MUST be stored as an object in the system register `_applications`

#### Scenario: Multi-page application with navigation
- **GIVEN** an application with pages: `Overzicht`, `Nieuw`, `Statistieken`
- **WHEN** the application is loaded
- **THEN** a navigation sidebar or top bar MUST display all page links in configured order
- **AND** clicking a page link MUST load the corresponding view without full page reload (SPA navigation)
- **AND** the browser URL MUST update to reflect the current page path (e.g., `/app/meldingen-portaal/statistieken`)

#### Scenario: Application versioning and publication workflow
- **GIVEN** an application in `draft` status with unpublished changes
- **WHEN** the admin clicks "Publish"
- **THEN** the application definition MUST be versioned (snapshot stored as object version via content-versioning spec)
- **AND** the published version MUST be served to end users
- **AND** the admin MUST be able to continue editing a new draft without affecting the published version

#### Scenario: Duplicate an existing application as template
- **GIVEN** a published application `Meldingen Portaal`
- **WHEN** the admin selects "Duplicate as template"
- **THEN** a new application definition MUST be created with all pages, components, and configuration copied
- **AND** the new application MUST have status `draft` and a new slug
- **AND** data source bindings MUST be preserved but editable

#### Scenario: Delete an application
- **GIVEN** a published application `Meldingen Portaal`
- **WHEN** the admin deletes the application
- **THEN** the application MUST no longer be accessible at its URL
- **AND** the application definition object MUST be soft-deleted (audit trail preserved)
- **AND** existing data in the bound registers/schemas MUST NOT be affected

### Requirement: Pages MUST support drag-and-drop component placement on a grid layout
Each page MUST be composed of components placed on a responsive CSS Grid layout via a visual editor. The layout system MUST use a 12-column grid that adapts to screen size, consistent with NL Design System grid conventions. Component placement, sizing, and ordering MUST be configurable through drag-and-drop interactions. The page definition (component tree with layout positions) MUST be stored as a JSON structure within the application definition object, following a schema-driven approach similar to NocoBase's UI schema storage.

#### Scenario: Add a data table component
- **GIVEN** the admin is editing page `Overzicht`
- **WHEN** they drag a "Data Table" component from the component palette onto the canvas
- **AND** configure it to display schema `meldingen` with columns: `titel`, `status`, `datum`
- **THEN** the page MUST render a table showing meldingen objects with those columns
- **AND** the table MUST support sorting by clicking column headers
- **AND** the table MUST support pagination with configurable page size

#### Scenario: Add a form component
- **GIVEN** the admin is editing page `Nieuw`
- **WHEN** they drag a "Form" component onto the canvas
- **AND** configure it to create objects in schema `meldingen` with fields: `titel`, `beschrijving`, `locatie`
- **THEN** the page MUST render a form that creates new meldingen objects on submit
- **AND** form fields MUST be auto-generated from the schema JSON Schema properties (type, required, enum constraints)
- **AND** the form MUST use `CnFormDialog` field rendering patterns from `@conduction/nextcloud-vue`

#### Scenario: Add a chart component
- **GIVEN** the admin is editing page `Statistieken`
- **WHEN** they drag a "Chart" component onto the canvas
- **AND** configure it as a bar chart grouping meldingen by `status` with metric `count`
- **THEN** the page MUST render a bar chart showing meldingen counts per status value
- **AND** the chart MUST query the DashboardService aggregate API for data
- **AND** the chart MUST auto-refresh when dashboard-level filters change

#### Scenario: Resize and reposition components on the grid
- **GIVEN** a page with 3 components placed on the grid
- **WHEN** the admin drags a component to a new grid position or resizes it by dragging its edge
- **THEN** the component MUST snap to the 12-column grid at the new position/size
- **AND** other components MUST reflow to avoid overlap
- **AND** the updated layout MUST be persisted to the page definition JSON

#### Scenario: Responsive breakpoint configuration
- **GIVEN** a page with components laid out on a 12-column desktop grid
- **WHEN** the admin switches to the mobile preview (breakpoint: 768px)
- **THEN** the editor MUST show how components stack on smaller screens
- **AND** the admin MUST be able to configure per-breakpoint column spans (e.g., full-width on mobile, half-width on desktop)

### Requirement: The system MUST provide a component library with data-bound display components
The component library MUST include at minimum: Data Table, Detail View, Form, Chart (bar/line/pie), Kanban Board, Calendar View, Gallery/Card Grid, Rich Text Block, Image, Heading, and Container (layout wrapper). Each component MUST support data binding to an OpenRegister schema and MUST render data fetched from the Object API. Components MUST follow the `CnIndexPage` pattern from `@conduction/nextcloud-vue` for table/list rendering and `CnFormDialog` for form rendering.

#### Scenario: Data Table component with inline actions
- **GIVEN** a Data Table component bound to schema `meldingen`
- **WHEN** the component renders
- **THEN** it MUST display rows from the Objects API with configured visible columns
- **AND** each row MUST support configurable actions: view detail, edit, delete
- **AND** the table MUST support bulk selection and bulk actions (delete, status update)
- **AND** column headers MUST support click-to-sort and column visibility toggles

#### Scenario: Kanban Board component
- **GIVEN** a Kanban component bound to schema `meldingen` with stack field `status` (enum: `nieuw`, `in_behandeling`, `afgerond`)
- **WHEN** the component renders
- **THEN** it MUST display columns for each enum value with meldingen cards in the appropriate column
- **AND** dragging a card from `nieuw` to `in_behandeling` MUST update the object's `status` field via the Object API
- **AND** each card MUST display configurable fields (e.g., `titel`, `datum`, `prioriteit`)

#### Scenario: Calendar View component
- **GIVEN** a Calendar component bound to schema `afspraken` with date field `datum` and title field `onderwerp`
- **WHEN** the component renders
- **THEN** it MUST display a month/week/day calendar with events positioned on their dates
- **AND** clicking an empty date MUST open a pre-filled form to create a new object with that date
- **AND** clicking an event MUST open the detail/edit view for that object
- **AND** dragging an event to a different date MUST update the object's `datum` field

#### Scenario: Gallery/Card Grid component
- **GIVEN** a Gallery component bound to schema `producten` with cover image field `afbeelding`
- **WHEN** the component renders
- **THEN** it MUST display a responsive card grid with each card showing the cover image and configured display fields
- **AND** clicking a card MUST navigate to the detail page or open a detail popup

#### Scenario: Detail View component
- **GIVEN** a Detail View component bound to schema `meldingen` receiving object ID from URL parameter
- **WHEN** the component renders for object `melding-1`
- **THEN** it MUST display all configured fields in a read-only layout
- **AND** the admin MUST be able to configure field grouping into sections with labels
- **AND** related objects (via UUID references) MUST be displayed as clickable links

### Requirement: Components MUST support data binding, filtering, and inter-component communication
Components MUST read from and write to register data through the Object API. Components on the same page MUST be able to communicate via a page-level state object and URL parameters. A filter component MUST be able to filter data displayed in sibling table/chart/kanban components. Data binding expressions MUST support referencing URL parameters, page state variables, and the current user context.

#### Scenario: Table row click navigates to detail
- **GIVEN** a Data Table component on the list page and a Detail View component on the detail page
- **WHEN** the user clicks a row for `melding-1` in the table
- **THEN** the application MUST navigate to the detail page with URL parameter `id=melding-1`
- **AND** the Detail View component MUST read the `id` parameter and load the corresponding object

#### Scenario: Filter component controls sibling data components
- **GIVEN** a page with a Filter Form component and a Data Table component both bound to schema `meldingen`
- **WHEN** the user selects `status: in_behandeling` in the filter form
- **THEN** the Data Table MUST re-query with the filter applied and display only matching objects
- **AND** if a Chart component is also on the page, it MUST also re-render with the filtered data

#### Scenario: Form submit creates object and triggers navigation
- **GIVEN** a Form component bound to schema `meldingen` with a configured success action: navigate to list page
- **WHEN** the user fills in the form and clicks submit
- **THEN** a new object MUST be created in the register via the Object API
- **AND** the user MUST be redirected to the list page
- **AND** a success notification MUST be displayed using Nextcloud's notification system

#### Scenario: Data binding with current user context
- **GIVEN** a Data Table component with a filter expression `eigenaar == {{ currentUser.id }}`
- **WHEN** user `jan.devries` views the page
- **THEN** the table MUST only display objects where `eigenaar` equals `jan.devries`
- **AND** the filter MUST be applied server-side via the Object API query parameters

#### Scenario: Cascading data sources between components
- **GIVEN** a page with a "Registers" dropdown and a Data Table below it
- **WHEN** the user selects register `zaken-register` in the dropdown
- **THEN** the Data Table MUST reload with schemas from the selected register
- **AND** the dropdown selection MUST be stored in page state, accessible to other components

### Requirement: The system MUST support conditional field visibility and validation rules in forms
Form components MUST allow administrators to configure conditional visibility rules that show or hide fields based on the values of other fields. Validation rules beyond JSON Schema constraints MUST be configurable (regex patterns, cross-field comparisons, custom error messages). This follows the pattern from Baserow's FormView which supports conditional field visibility with show/hide rules.

#### Scenario: Show field based on another field's value
- **GIVEN** a form for schema `meldingen` with fields `type` (enum: `klacht`, `vraag`, `suggestie`) and `urgentie` (enum: `laag`, `midden`, `hoog`)
- **WHEN** the admin configures a visibility rule: show `urgentie` only when `type == 'klacht'`
- **AND** the user selects `type: 'vraag'`
- **THEN** the `urgentie` field MUST be hidden
- **AND** when the user changes `type` to `klacht`, the `urgentie` field MUST appear

#### Scenario: Multi-condition visibility with AND/OR logic
- **GIVEN** a form with fields `categorie`, `afdeling`, and `escalatie_reden`
- **WHEN** the admin configures: show `escalatie_reden` when `categorie == 'urgent'` AND `afdeling == 'klantenservice'`
- **THEN** both conditions MUST be true for `escalatie_reden` to appear
- **AND** the form MUST evaluate conditions in real-time as the user types

#### Scenario: Custom validation rule with error message
- **GIVEN** a form field `bsn` with a custom validation rule: regex `^\d{9}$` with message `BSN moet exact 9 cijfers zijn`
- **WHEN** the user enters `1234` (too short)
- **THEN** the form MUST display the custom error message below the field
- **AND** the submit button MUST be disabled until validation passes

#### Scenario: Cross-field validation
- **GIVEN** a form with fields `startdatum` and `einddatum`
- **WHEN** the admin configures a validation rule: `einddatum` must be after `startdatum`
- **AND** the user enters `startdatum: 2026-04-01` and `einddatum: 2026-03-15`
- **THEN** the form MUST display a validation error: `Einddatum moet na startdatum liggen`

### Requirement: The system MUST support multi-step form wizards
Administrators MUST be able to split a form into multiple steps (wizard pattern) with a progress indicator, step navigation (next/previous), and per-step validation. Each step MUST group a subset of the schema's fields. The wizard MUST validate the current step before allowing navigation to the next step. This is critical for citizen-facing intake forms (e.g., building permits, subsidy applications) that involve many fields across logical sections.

#### Scenario: Create a multi-step intake form
- **GIVEN** a schema `vergunningaanvraag` with 20 fields across categories: personal info, project details, documents, review
- **WHEN** the admin configures a 4-step wizard:
  - Step 1 `Persoonlijke gegevens`: `naam`, `bsn`, `adres`, `telefoon`, `email`
  - Step 2 `Projectomschrijving`: `projectnaam`, `locatie`, `omschrijving`, `type`
  - Step 3 `Documenten`: `tekening`, `foto`, `bewijs_eigendom`
  - Step 4 `Controle en verzenden`: summary of all entered data
- **THEN** the form MUST display a step indicator showing 4 steps with the current step highlighted
- **AND** "Volgende" and "Vorige" buttons MUST navigate between steps

#### Scenario: Per-step validation prevents skipping
- **GIVEN** step 1 requires fields `naam` and `bsn`
- **WHEN** the user clicks "Volgende" without filling in `bsn`
- **THEN** the wizard MUST NOT advance to step 2
- **AND** a validation error MUST be shown on the `bsn` field

#### Scenario: Summary step with review before submission
- **GIVEN** the user reaches step 4 (review step)
- **WHEN** the summary renders
- **THEN** it MUST display all data entered in steps 1-3 in a read-only format
- **AND** each section MUST have an "Wijzig" (edit) link that navigates back to that step
- **AND** clicking "Verzenden" on the review step MUST create the object via the Object API

#### Scenario: Save wizard progress as draft
- **GIVEN** a user has completed steps 1 and 2 of a 4-step wizard
- **WHEN** the user clicks "Opslaan als concept" (save as draft)
- **THEN** a draft object MUST be created with the partial data and `_status: draft`
- **AND** when the user returns to the form, the wizard MUST resume from step 3 with previously entered data pre-filled

### Requirement: Applications MUST support role-based view access control
Each application, page, and component MUST define who can access it: all authenticated users, specific Nextcloud groups, or public (unauthenticated). Access control MUST integrate with Nextcloud's `IGroupManager` for group membership checks. Components MUST support per-role visibility (e.g., show "Delete" button only for admin group). Write operations on public applications MUST require authentication by default, configurable to allow anonymous submissions for public intake forms.

#### Scenario: Internal application restricted to group
- **GIVEN** application `Meldingen Beheer` with access restricted to group `behandelaars`
- **WHEN** a user not in `behandelaars` tries to access the application
- **THEN** the system MUST return HTTP 403
- **AND** a friendly "Geen toegang" page MUST be displayed

#### Scenario: Public application with anonymous read access
- **GIVEN** application `Meldingen Portaal` with public access enabled
- **WHEN** an unauthenticated visitor accesses the application URL
- **THEN** the application MUST load with read-only data from the configured schema
- **AND** the form component MUST show a login prompt before allowing submission (unless anonymous submission is explicitly enabled)

#### Scenario: Component-level role visibility
- **GIVEN** a Data Table component with a "Verwijder" (delete) action column
- **WHEN** the admin configures the action column to be visible only to group `admins`
- **AND** a user in group `medewerkers` (but not `admins`) views the table
- **THEN** the "Verwijder" column MUST NOT be rendered for that user

#### Scenario: Anonymous form submission for public intake
- **GIVEN** a public application with a Form component configured for anonymous submissions
- **WHEN** an unauthenticated visitor fills in and submits the form
- **THEN** the object MUST be created in the register with `owner: null` or a system user
- **AND** CSRF protection and rate limiting MUST be applied to prevent abuse
- **AND** a schema hook on `creating` MAY trigger a workflow (e.g., send confirmation, anti-spam check)

#### Scenario: Page-level access control within an application
- **GIVEN** an application with pages: `Overzicht` (all users), `Beheer` (admins only), `Rapportage` (managers only)
- **WHEN** a user in group `medewerkers` loads the application
- **THEN** the navigation MUST only show `Overzicht`
- **AND** direct URL access to `/app/meldingen/beheer` MUST return HTTP 403

### Requirement: Applications MUST integrate with NL Design System for government theming
All generated application UIs MUST render using NL Design System CSS design tokens, ensuring visual consistency with Dutch government styling guidelines. The app builder MUST support selecting a municipality theme (e.g., `gemeente-utrecht`, `gemeente-amsterdam`) that applies design tokens for colors, typography, spacing, and component styling. Theme configuration MUST be per-application, allowing different applications to use different municipality themes. This aligns with the existing `nldesign` app's theme token system.

#### Scenario: Apply municipality theme to application
- **GIVEN** an application `Meldingen Portaal` configured with theme `gemeente-utrecht`
- **WHEN** the application is rendered
- **THEN** all components MUST use Utrecht's design tokens for colors, fonts, and spacing
- **AND** the theme MUST be loaded via NL Design System CSS custom properties (e.g., `--nl-button-primary-background-color`)

#### Scenario: Theme preview in the editor
- **GIVEN** the admin is editing an application and selects theme `gemeente-amsterdam`
- **WHEN** the theme is applied in the editor
- **THEN** the page preview MUST immediately reflect Amsterdam's design tokens
- **AND** the admin MUST be able to compare different themes before publishing

#### Scenario: WCAG AA compliance for generated applications
- **GIVEN** a published application using NL Design System components
- **WHEN** the application is rendered
- **THEN** all components MUST meet WCAG 2.1 Level AA contrast ratios
- **AND** all interactive elements MUST be keyboard navigable
- **AND** all form fields MUST have associated labels and ARIA attributes
- **AND** screen readers MUST be able to navigate the application structure

#### Scenario: Fallback to Nextcloud theme when no NL Design theme is set
- **GIVEN** an application without a configured NL Design theme
- **WHEN** the application is rendered
- **THEN** it MUST use Nextcloud's default theming (`@nextcloud/vue` components)
- **AND** the application MUST still be fully functional without NL Design tokens

### Requirement: Applications MUST support mobile-responsive layouts
All application pages MUST be responsive and usable on mobile devices (minimum viewport: 320px). The grid layout system MUST support configurable breakpoints with per-breakpoint column spans. Components MUST adapt their rendering for mobile (e.g., table columns collapse to card view, kanban stacks to vertical list, calendar to agenda view). The app builder editor MUST provide device preview modes (desktop, tablet, mobile).

#### Scenario: Table component adapts to mobile viewport
- **GIVEN** a Data Table component showing 6 columns on desktop
- **WHEN** the viewport width is below 768px (mobile breakpoint)
- **THEN** the table MUST switch to a card/list view showing each row as a stacked card
- **AND** the card MUST display the primary fields (configurable) and hide secondary columns
- **AND** tap on a card MUST open the detail view

#### Scenario: Kanban adapts to mobile
- **GIVEN** a Kanban Board with 4 status columns
- **WHEN** the viewport width is below 768px
- **THEN** the kanban MUST switch to a single-column view with a dropdown to select the visible status
- **AND** drag-and-drop MUST still function via long-press gesture on touch devices

#### Scenario: Form layout stacks on mobile
- **GIVEN** a form with 2-column layout on desktop (e.g., first name and last name side by side)
- **WHEN** the viewport is below 768px
- **THEN** all form fields MUST stack vertically (single column)
- **AND** field labels MUST be positioned above inputs (not inline)

### Requirement: The system MUST support workflow triggers from form submissions
Form submissions MUST be able to trigger schema hooks (as defined in the schema-hooks spec) that execute n8n workflows or external webhook calls. The app builder MUST allow administrators to configure post-submission actions: create notification, send email (via n8n), navigate to page, show success message, trigger workflow. This connects the no-code app builder to the existing event-driven architecture.

#### Scenario: Form submission triggers schema hook workflow
- **GIVEN** a form component creating objects in schema `meldingen` which has a hook on `created` event linked to n8n workflow `melding-notificatie`
- **WHEN** the user submits the form
- **THEN** the object MUST be created in the register
- **AND** the schema hook MUST fire, sending a CloudEvent to the n8n workflow
- **AND** the workflow MUST execute (e.g., send email notification to the assigned team)

#### Scenario: Sync hook validates form data before save
- **GIVEN** a schema `vergunningaanvraag` with a sync hook on `creating` that calls an n8n workflow for BSN validation
- **WHEN** the user submits a form with an invalid BSN
- **THEN** the sync hook MUST return `{"status": "rejected", "errors": [{"field": "bsn", "message": "BSN niet geldig"}]}`
- **AND** the form MUST display the server-side validation error inline on the `bsn` field
- **AND** the object MUST NOT be created

#### Scenario: Post-submission redirect with dynamic URL
- **GIVEN** a form configured with success action: navigate to `/app/meldingen/detail/{id}`
- **WHEN** the form creates object with UUID `abc-123`
- **THEN** the user MUST be redirected to `/app/meldingen/detail/abc-123`
- **AND** the detail page MUST load the newly created object

### Requirement: The system MUST support embedded views via iframe and widget embedding
Published applications and individual pages MUST be embeddable in external websites via iframe. The system MUST provide an embed code generator that produces an `<iframe>` snippet with configurable dimensions, theme, and data filters. Embedded views MUST respect the application's access control settings. This enables municipalities to embed forms, data views, and dashboards directly in their public websites (e.g., gemeente.nl).

#### Scenario: Generate iframe embed code for a public form
- **GIVEN** a public application page containing a form for citizen reports
- **WHEN** the admin clicks "Embed" on the page settings
- **THEN** the system MUST generate an iframe snippet: `<iframe src="https://nextcloud.gemeente.nl/apps/openregister/embed/meldingen-portaal/nieuw" width="100%" height="600"></iframe>`
- **AND** the embedded page MUST render without Nextcloud chrome (no header, no sidebar)

#### Scenario: Embedded view inherits NL Design theme
- **GIVEN** an embedded form configured with theme `gemeente-utrecht`
- **WHEN** the iframe loads on an external website
- **THEN** the form MUST render with Utrecht's NL Design tokens
- **AND** the form MUST visually integrate with the hosting municipality website

#### Scenario: Embedded data table with pre-applied filters
- **GIVEN** an embed URL with filter parameters: `/embed/meldingen-portaal/overzicht?status=open&wijk=centrum`
- **WHEN** the iframe loads
- **THEN** the Data Table MUST display only meldingen with `status: open` and `wijk: centrum`
- **AND** the filter parameters MUST be applied server-side for security

#### Scenario: Prevent clickjacking on embedded views
- **GIVEN** an application page configured as embeddable
- **WHEN** the embed endpoint serves the page
- **THEN** the `X-Frame-Options` header MUST be set to `ALLOW-FROM` with configured allowed origins
- **AND** the `Content-Security-Policy` frame-ancestors directive MUST list the allowed embedding domains

### Requirement: The system MUST support custom dashboard widgets built from register data
Administrators MUST be able to create dashboard widget components that display aggregated register data (KPI numbers, charts, recent items lists). These widgets MUST be placeable on application pages and MUST also be registerable as Nextcloud home dashboard widgets via the `IWidget`/`IAPIWidget` interface. Widget data MUST be sourced from the existing `DashboardService` aggregation API and MUST auto-refresh at configurable intervals. This cross-references the built-in-dashboards spec.

#### Scenario: KPI metric widget showing object count
- **GIVEN** a KPI widget configured with: data source = schema `meldingen`, filter = `status: nieuw`, metric = count, label = `Nieuwe meldingen`
- **WHEN** the widget renders
- **THEN** it MUST display a large number showing the current count of new meldingen
- **AND** the widget MUST auto-refresh every 60 seconds (configurable)
- **AND** clicking the widget MUST navigate to the filtered list view

#### Scenario: Recent items widget
- **GIVEN** a "Recent Items" widget configured with: data source = schema `meldingen`, sort = `created DESC`, limit = 5
- **WHEN** the widget renders
- **THEN** it MUST display the 5 most recently created meldingen with title and creation date
- **AND** clicking an item MUST navigate to its detail page

#### Scenario: Chart widget on application dashboard page
- **GIVEN** a dashboard page with a pie chart widget grouped by `categorie`
- **WHEN** the widget queries `DashboardService.calculate()` for the data
- **THEN** it MUST render a pie chart showing proportional distribution across categories
- **AND** the chart MUST integrate with dashboard-level date range filters (per built-in-dashboards spec)

#### Scenario: Register widget on Nextcloud home dashboard
- **GIVEN** the admin configures a KPI widget to also appear on the Nextcloud home dashboard
- **WHEN** the widget is registered via `OCP\Dashboard\IAPIWidget`
- **THEN** it MUST appear on the Nextcloud dashboard for users with appropriate access
- **AND** the widget MUST fetch data from the OpenRegister API respecting RBAC

### Requirement: The system MUST support a form submission API for programmatic access
All forms created in the app builder MUST be accessible via a REST API endpoint that accepts submissions programmatically (not just via the browser UI). The submission endpoint MUST validate data against the schema, apply conditional visibility rules (hidden fields excluded from validation), trigger schema hooks, and return the created object. This enables external systems (e.g., chatbots, mobile apps, other websites) to submit data to app builder forms.

#### Scenario: Submit form data via API
- **GIVEN** a form in application `meldingen-portaal` on page `nieuw` bound to schema `meldingen`
- **WHEN** an external system sends `POST /api/apps/openregister/app/meldingen-portaal/nieuw/submit` with JSON body `{"titel": "Kapotte lantaarn", "locatie": "Keizersgracht 100"}`
- **THEN** the system MUST validate the data against the `meldingen` schema
- **AND** the system MUST create the object in the register
- **AND** the response MUST return HTTP 201 with the created object

#### Scenario: API submission respects validation rules
- **GIVEN** a form with custom validation: `bsn` must be 9 digits
- **WHEN** an API submission includes `bsn: "123"`
- **THEN** the system MUST return HTTP 422 with validation errors: `[{"field": "bsn", "message": "BSN moet exact 9 cijfers zijn"}]`

#### Scenario: API submission triggers same hooks as UI submission
- **GIVEN** a schema with a sync hook on `creating`
- **WHEN** a form submission arrives via the API
- **THEN** the schema hook MUST fire with the same CloudEvent payload as a UI submission
- **AND** if the hook rejects the submission, HTTP 422 MUST be returned with the hook's error messages

### Requirement: The system MUST support navigation configuration with nested menus and external links
Application navigation MUST support hierarchical menus with nested items, section dividers, icons, badges (e.g., unread count), and external URL links. Navigation items MUST be configurable to show/hide based on user group membership. The navigation structure MUST be part of the application definition and editable through the visual editor.

#### Scenario: Configure hierarchical navigation
- **GIVEN** an application with pages for multiple entities
- **WHEN** the admin configures navigation:
  - `Meldingen` (section)
    - `Overzicht` -> page `meldingen-overzicht`
    - `Nieuw` -> page `meldingen-nieuw`
  - `Taken` (section)
    - `Mijn taken` -> page `taken-mijn`
    - `Alle taken` -> page `taken-alle`
  - `Help` -> external URL `https://handleiding.gemeente.nl`
- **THEN** the navigation MUST render with collapsible sections and proper hierarchy
- **AND** external links MUST open in a new browser tab

#### Scenario: Navigation badge showing count
- **GIVEN** a navigation item `Mijn taken` configured with badge source = count of `taken` where `eigenaar == currentUser.id` and `status == 'open'`
- **WHEN** the navigation renders
- **THEN** a badge MUST display the count of open tasks assigned to the current user
- **AND** the badge MUST update when the user navigates between pages

#### Scenario: Navigation item visibility by group
- **GIVEN** navigation item `Beheer` configured to be visible only to group `admins`
- **WHEN** a user not in `admins` loads the application
- **THEN** the `Beheer` navigation item MUST NOT appear in the menu

### Requirement: Applications MUST support template sharing and marketplace
Application definitions MUST be exportable as JSON templates that can be imported into other OpenRegister instances. Templates MUST include all page definitions, component configurations, navigation structure, and sample data schemas (but NOT the actual data). The system MUST provide a template library where administrators can browse and install pre-built application templates. This follows the pattern from Baserow's template system.

#### Scenario: Export application as template
- **GIVEN** a published application `Meldingen Portaal`
- **WHEN** the admin clicks "Export as template"
- **THEN** the system MUST generate a JSON file containing: application definition, all page definitions, component configurations, navigation structure, bound schema definitions, and NL Design theme reference
- **AND** the JSON MUST NOT include any actual object data from the registers

#### Scenario: Import application template
- **GIVEN** a JSON template file `meldingen-portaal-template.json`
- **WHEN** the admin imports the template into a new OpenRegister instance
- **THEN** the system MUST create the application definition, pages, and navigation
- **AND** the system MUST create the required schemas if they do not already exist
- **AND** the imported application MUST be in `draft` status for review before publishing

#### Scenario: Browse built-in template library
- **GIVEN** the admin navigates to the template library
- **WHEN** the library loads
- **THEN** it MUST display pre-built templates categorized by use case (e.g., `Meldingen`, `Vergunningen`, `Subsidies`, `Zaakafhandeling`)
- **AND** each template MUST show a preview screenshot, description, and required schemas
- **AND** clicking "Install" MUST import the template as a new draft application

#### Scenario: Share template to community catalog
- **GIVEN** an admin has created a useful application template
- **WHEN** the admin clicks "Publish to SoftwareCatalogus"
- **THEN** the template MUST be published to the Softwarecatalogus API as a reusable component
- **AND** other organizations MUST be able to discover and install it

### Requirement: Computed fields MUST be rendered correctly in app builder components
Components MUST correctly display computed field values (as defined in the computed-fields spec). In Data Tables, computed fields MUST appear as regular columns with a visual indicator (e.g., formula icon). In Forms, computed fields MUST be displayed as read-only fields with visual distinction (gray background, lock icon). In Detail Views, computed fields MUST show the current value with an optional tooltip explaining the formula. The `evaluateOn` mode (save/read/demand) MUST be transparent to end users.

#### Scenario: Computed field in Data Table
- **GIVEN** schema `facturen` with computed field `bedrag_incl_btw` (expression: `bedrag * 1.21`)
- **WHEN** a Data Table component displays facturen objects
- **THEN** the `bedrag_incl_btw` column MUST show the computed values
- **AND** the column header MUST display a small formula icon to indicate it is computed
- **AND** the column MUST be sortable (since `evaluateOn: save` values are persisted)

#### Scenario: Computed field excluded from form input
- **GIVEN** a Form component for schema `facturen`
- **WHEN** the form renders
- **THEN** `bedrag_incl_btw` MUST be displayed as a read-only field showing the current computed value
- **AND** the field MUST NOT be editable by the user
- **AND** after the user changes `bedrag`, the form MUST display a note: "Dit veld wordt berekend na opslaan"

#### Scenario: Read-time computed field in Detail View
- **GIVEN** schema `vergunningen` with read-time computed field `dagen_resterend` (days until expiry)
- **WHEN** the Detail View renders
- **THEN** `dagen_resterend` MUST show the freshly computed value from the API response
- **AND** a tooltip MUST display: "Automatisch berekend: dagen tot vervaldatum"

## Current Implementation Status
- **Not implemented -- application definitions**: No `Application` entity in the context of no-code app building exists. The existing `lib/Db/Application.php` is unrelated (it handles OpenRegister's own app-level entities like configurations, not user-built applications).
- **Not implemented -- drag-and-drop page editor**: No visual page builder, canvas, or component placement system exists in the frontend codebase.
- **Not implemented -- component library for app builder**: No data table, form, chart, kanban, calendar, or gallery components are available as configurable drag-and-drop widgets. However, `CnIndexPage` (table/list rendering) and `CnFormDialog` (form dialogs) from `@conduction/nextcloud-vue` provide foundational component patterns that the app builder components should wrap and extend.
- **Not implemented -- custom domains or paths**: No routing mechanism for user-defined application slugs exists. A catch-all route pattern (`/app/{slug}/{path+}`) would need to be registered in `routes.php`.
- **Not implemented -- multi-step form wizards**: No wizard/stepper component exists in the frontend.
- **Not implemented -- conditional field visibility**: No client-side conditional visibility engine exists for form fields.
- **Not implemented -- embedded views**: No iframe/embed endpoint exists for rendering pages without Nextcloud chrome.
- **Not implemented -- template marketplace**: No application template import/export or community sharing mechanism exists.
- **Tangentially related -- Views system**: `ViewsController` (`lib/Controller/ViewsController.php`), `ViewService` (`lib/Service/ViewService.php`), `ViewMapper` (`lib/Db/ViewMapper.php`), and the `View` entity (`lib/Db/View.php`) provide saved view configurations with query parameters (registers, schemas, filters), owner, public/default flags, and favoriting. These saved views could serve as a foundation for data source configurations in app builder components.
- **Tangentially related -- Dashboard service**: `DashboardService` (`lib/Service/DashboardService.php`) and `DashboardController` (`lib/Controller/DashboardController.php`) provide aggregate metrics and chart calculations. The frontend uses `CnDashboardPage` from `@conduction/nextcloud-vue` with KPI widgets. This provides the data aggregation backend that chart widgets in the app builder would consume.
- **Tangentially related -- Configuration entity**: `Configuration` (`lib/Db/Configuration.php`) manages app-level settings with views array, registers, schemas references, and GitHub sync. This entity pattern could inform the application definition data model.
- **Implemented -- schema hooks**: `HookExecutor`, `HookListener`, and the CloudEvents infrastructure are fully implemented, enabling form submissions to trigger external workflows via n8n or webhooks.
- **Partially implemented -- computed fields**: `ComputedFieldHandler` provides Twig-based computed field evaluation at save-time and read-time, which app builder components need to render correctly as read-only fields.

## Standards & References
- **WCAG 2.1 AA** -- Accessibility requirements for the visual editor and all generated applications. Canvas-based rendering (NocoDB approach) is explicitly rejected for government accessibility requirements.
- **NL Design System** -- CSS design tokens for Dutch government theming. Components MUST use design token custom properties, not hardcoded colors. Theme integration follows `nldesign` app patterns.
- **JSON Schema** -- Data binding and form field generation driven by schema property definitions (type, required, enum, format constraints).
- **Nextcloud App Framework** -- Authentication via `IUserSession`, authorization via `IGroupManager`, navigation via `INavigationManager`, background jobs via `IJobList`, caching via `ICacheFactory`.
- **Nextcloud Dashboard API** -- `OCP\Dashboard\IWidget` / `OCP\Dashboard\IAPIWidget` for registering app builder widgets on the Nextcloud home dashboard.
- **CSS Grid Layout** -- 12-column responsive grid for page layout, consistent with NL Design System grid specifications.
- **CloudEvents 1.0** -- Form submission events use the same CloudEvent format as schema hooks for workflow integration.
- **vue-grid-layout** -- Vue 2-compatible grid layout library for drag-and-drop component placement.
- **Chart.js or Apache ECharts** -- Chart rendering libraries compatible with Vue 2 and the Nextcloud frontend stack.

## Specificity Assessment
- **Large scope with clear decomposition**: This spec covers 15 requirements that can be implemented incrementally. The minimum viable product (MVP) should focus on: application definitions, basic page editor with table + form + detail components, role-based access control, and NL Design theming.
- **Well-defined component model**: Each component type has clear data binding semantics (schema reference, query parameters, display configuration) informed by competitive analysis of Baserow's 20+ elements, NocoBase's block types, and NocoDB's view system.
- **Missing implementation details**:
  - Exact JSON structure for page/component definitions (schema for the schema)
  - Component rendering engine implementation (Vue dynamic components vs. render functions)
  - State management between pages (Pinia store vs. URL parameters vs. both)
  - Editor undo/redo mechanism
  - Real-time collaborative editing (or single-editor-at-a-time locking)
  - Performance targets for page render time with many components
- **Open questions**:
  - Should the app builder be a separate Nextcloud app or remain part of OpenRegister core?
  - How does this relate to Pipelinq (CRM views) and Procest (process views) which already build custom UIs on OpenRegister data?
  - Should app builder applications share the same routing namespace as Nextcloud apps, or use a distinct prefix?
  - What is the minimum component set for the MVP release?
- **Recommended MVP component set**: Data Table (wrapping `CnIndexPage`), Form (wrapping `CnFormDialog`), Detail View, Chart (bar/pie/line), Heading, Rich Text Block, Container.

## Nextcloud Integration Analysis

**Status**: Not yet implemented. No visual app builder, drag-and-drop page editor, or component library exists. The Views system, Dashboard service, and `@conduction/nextcloud-vue` shared components (`CnIndexPage`, `CnFormDialog`, `CnDashboardPage`) provide tangential foundations.

**Nextcloud Core Interfaces**:
- `INavigationManager` (`OCP\INavigationManager`): Register each published application as a navigation entry in Nextcloud's app menu so users can access their applications from the Nextcloud top bar.
- `IGroupManager` (`OCP\IGroupManager`): Enforce access control on applications, pages, and components by checking the requesting user's group membership against the configured access groups.
- `IUserSession` (`OCP\IUserSession`): Resolve the current user context for data binding expressions (e.g., `currentUser.id`, `currentUser.groups`).
- `IWidget` / `IAPIWidget` (`OCP\Dashboard`): Register app builder dashboard widgets on the Nextcloud home screen, bridging the app builder with Nextcloud's native dashboard.
- `IJobList` (`OCP\BackgroundJob\IJobList`): Queue background jobs for template import processing and large-scale data operations triggered from app builder forms.
- `ICacheFactory` (`OCP\ICacheFactory`): Cache application definitions and compiled page layouts to minimize database queries on each page load.

**Implementation Approach**:
- Store application definitions as OpenRegister objects in a system register `_applications` using a dedicated schema. This self-hosting approach means app definitions benefit from the same versioning, audit trail, hooks, and RBAC infrastructure as regular data. The schema for application definitions should include: pages (JSON array of page definitions), navigation (JSON object), theme (string reference), access (JSON object with groups array and public flag).
- Build a `PageEditor.vue` component using `vue-grid-layout` for the 12-column grid. The editor provides a component palette (sidebar) that users drag onto the canvas. Each placed component stores its configuration as a JSON object within the page definition. Use Vue's `<component :is="...">` pattern for dynamic component instantiation based on the stored component type.
- Wrap existing `@conduction/nextcloud-vue` components as app builder widgets: `CnIndexPage` becomes the Data Table widget (with configurable columns, filters, and actions), `CnFormDialog` field rendering becomes the Form widget (with conditional visibility and wizard steps), `CnDashboardPage` widget patterns become the Chart/KPI widgets.
- Data binding between components uses a combination of URL parameters (for cross-page navigation) and a Pinia page-state store (for intra-page communication). Table row clicks set `{selectedObjectId}` in the URL, which the detail view reads. Filter forms update the page state, which data components watch and re-query on change.
- Register a catch-all route `/app/{slug}/{path+}` in `routes.php`. The `AppBuilderController` resolves the application definition by slug, loads the page definition matching the path, and renders the Vue SPA shell. For embed mode, `/embed/{slug}/{path+}` renders without Nextcloud chrome (no header, no sidebar).
- NL Design System theming integrates by loading the municipality theme CSS file as a `<link>` tag based on the application's theme configuration. Design tokens cascade to all child components via CSS custom properties.

**Dependencies on Existing OpenRegister Features**:
- `ObjectService` / Object API -- CRUD operations for all data reading and writing from components.
- `SchemaService` / `SchemaMapper` -- Schema property definitions drive form field generation, table column configuration, and validation rules.
- `ViewService` / `ViewMapper` -- Saved view configurations (filters, sorts, column visibility) as foundation for data component state persistence.
- `DashboardService` -- Aggregate metrics and chart calculations for chart/KPI widgets.
- `HookExecutor` / `HookListener` -- Schema hooks triggered by form submissions for workflow integration.
- `ComputedFieldHandler` -- Computed field evaluation for correct rendering in tables, forms, and detail views.
- `DeepLinkRegistryService` -- Register application page URLs for Nextcloud unified search integration.
- `@conduction/nextcloud-vue` -- `CnIndexPage`, `CnFormDialog`, `CnDashboardPage` as base component implementations.
