# NocoDB Docker Walkthrough

**Date:** 2026-03-14
**Image:** `nocodb/nocodb:latest`
**Port:** 9022 -> 8080

## Setup

```bash
docker run -d --name nocodb-eval -p 9022:8080 nocodb/nocodb:latest
```

Container starts with embedded SQLite database. No external dependencies needed.

## Walkthrough Summary

### 1. Signup (screenshot: 01-signup-page.png)
- First user becomes Super Admin
- Email + password signup
- Optional newsletter subscription

### 2. Onboarding (screenshot: 02-onboarding.png)
- Use case selection: Work, School, Non-Profit, Personal
- Can be skipped

### 3. Dashboard / Grid View (screenshots: 03, 04, 12, 13)
- Default "Getting Started" base with a "Features" table
- Canvas-based grid rendering (NOT DOM tables)
- Row numbers, row expand icons, column headers
- Status bar shows record count
- Toolbar: Fields, Filter, Group, Sort, Search, Fullscreen

### 4. Expanded Row (screenshot: 05-expanded-row.png)
- Modal with field values on left, comments on right
- Rich text comment editor (bold, italic, underline, strikethrough, link)
- Audit trail icon

### 5. Column Context Menu (screenshot: 06-column-context-menu.png)
- Right-click column header for: Edit field, Duplicate, Change display value
- Sort ascending/descending, Filter by field, Group by field
- Insert column right
- Shows Field ID for API usage

### 6. Field Type Picker (screenshot: 07-field-type-picker.png)
- 30+ field types organized in a scrollable list
- Search field type functionality
- Links, Lookup, Text, Number, Decimal, Attachment, Checkbox, Select, Date, etc.

### 7. View Types (screenshot: 08-create-view-menu.png)
- Grid, Form, Gallery, Kanban, Calendar
- Each view type has its own icon and creation dialog

### 8. Form View (screenshot: 09-form-view.png)
- Customizable banner image
- Logo upload
- Form title and rich text description
- Drag-and-drop field ordering
- Submit button

### 9. Gallery View (screenshot: 10-gallery-view.png)
- Card-based layout
- Optional cover image field
- Shows display value (title) per card

### 10. Kanban View (screenshots: 11, 14)
- Requires a SingleSelect field for stacking
- Cards show configurable fields (Status, Priority, etc.)
- Drag-and-drop between stacks
- "New stack" button to add options
- Per-stack record count

### 11. Calendar View (screenshot: 15-calendar-view.png)
- Month/week/day views
- Requires a Date or DateTime field
- Records shown on calendar dates
- Right sidebar with record list and sort options
- Navigation: previous/next month, "Today" button

### 12. Share Dialog (screenshot: 16-share-dialog.png)
- Share View: Enable public viewing for the current view
- Share Base: Enable public access to the entire base
- Manage Base Access button

### 13. Account Settings (screenshots: 17-22)
- **Setup** — Configure email and storage
- **Profile** — User profile
- **API Tokens** — Personal API tokens for automation
- **MCP Server** — MCP token management per base
- **App Store** — Notification plugins (Slack, Teams, Discord, etc.)
- **Users** — User management

### 14. Base Overview (screenshot: 21-base-overview.png)
- Tabs: Overview, Members, Data Sources, Settings
- Actions: Create New Table, Import Data, Connect External Data
- Data Sources: Add/manage database connections

### 15. Details Panel (screenshots: 23-25)
- **Fields** — Manage all fields with visibility toggles, reordering
- **Relations** — View table relationships
- **API Snippets** — Auto-generated code in 9 languages (Shell, JS, Node, NocoDB-SDK, PHP, Python, Ruby, Java, C)
- **Webhooks** — Create/manage webhook automations

## Key Observations

1. **Canvas grid is impressive** — Fast rendering but sacrifices accessibility
2. **Field types are comprehensive** — 30+ types cover most use cases out of the box
3. **Formula engine is powerful** — 65 functions with type inference
4. **MCP is built-in** — Native AI tool access (same protocol as OpenRegister)
5. **Multi-database** — Can connect to existing databases, not just internal storage
6. **API-first** — Every table gets a REST API with code snippets
7. **Single Docker container** — Easy deployment with embedded SQLite
