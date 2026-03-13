# Objects API — Admin Interface

## UI Reference
Screenshots: `../../screenshots/`

## Dashboard
Screenshot: `../../screenshots/13-objects-dashboard.png`

The Objects API dashboard has MORE sections than Objecttypes:

### Navigation Tabs
| Tab | Sections |
|-----|----------|
| Dashboard | Overview |
| Accounts | Groups, Session profiles, TOTP devices, Users, Webauthn devices |
| API Authorizations | Permissions, Token authorizations |
| Data | Object types, Objects |
| Configuration | Application groups, Notifications config, OIDC Providers/Clients, Outgoing request log config/logs, Services, Sites |
| Logging | Access attempts, Access logs |

**Key differences from Objecttypes admin:**
- Has **Permissions** (separate from tokens — per-objecttype access control)
- Has **Objects** (the actual data store)
- Has **Session profiles** (read-only)
- Has **Services** (ZGW Consumers — external API connections)
- Has **Sites** (Django sites framework)
- Has **Notifications component configuration** (webhook delivery settings)

## Object Types in Objects API
Screenshots: `../../screenshots/14-objects-objecttypes-list.png`, `../../screenshots/15-objects-objecttype-detail.png`

The Objects API has its **own local copy** of object types — identical form to Objecttypes API.
These can be:
- Created manually via admin
- Imported from URL (synced from Objecttypes API)
- Created/managed via the REST API

### List Columns
| Column | Sortable | Notes |
|--------|----------|-------|
| Name | Yes | |
| Name plural | Yes | |
| Allow geometry | Yes | Boolean icon |

## Objects List
Screenshots: `../../screenshots/19-objects-list-empty.png`, `../../screenshots/21-objects-list-with-data.png`

### Table Columns
| Column | Sortable | Filterable | Notes |
|--------|----------|------------|-------|
| ID | Yes | No | Internal database ID |
| Object type | Yes | Yes (sidebar filter) | Name of the object type |
| Current record | No | No | Shows "index (startAt)" |
| Uuid | Yes | No | The object UUID |
| Object type UUID | No | No | |
| Modified on | Yes | Yes (sidebar filter) | Date/time |
| Created on | Yes | Yes (sidebar filter) | Date/time |

### Sidebar Filters
| Filter | Options |
|--------|---------|
| By object type | All, Object Type 1, Object Type 2, ... |
| By created on | Any date, Today, Past 7 days, This month, This year |
| By modified on | Any date, Today, Past 7 days, This month, This year |

### Actions
| Button/Action | Location | Behavior |
|--------------|----------|----------|
| Add object | Top right | Opens creation form |
| Search instructions | Top left link | Shows search help |
| Search | Search bar | Text search |
| Delete selected | Bulk action | Deletes selected objects |
| Show counts | Filter sidebar | Shows count per filter option |

## Object Create Form
Screenshot: `../../screenshots/20-objects-add-form.png`

### Main Fields
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Uuid | Text (auto-generated) | Yes | Pre-populated UUID |
| Object type | Dropdown + edit/add/delete/view buttons | Yes | Select from registered types |

### Object Records (Inline Table)
| Column | Type | Required | Notes |
|--------|------|----------|-------|
| Index | Read-only | Auto | Auto-incremented per object |
| Version | Spinbutton | No | Schema version number |
| Data | JSON textarea | Yes | The actual object data (default: {}) |
| Geometry | Textarea | No | GeoJSON geometry |
| Start at | Date picker | Yes | Material validity start date |
| End at | Read-only | Auto | Set when superseded |
| Registration at | Read-only | Auto | Date of registration |
| Corrected by | Read-only | Auto | Index of correcting record |
| Correction for | Dropdown | No | Select previous record to correct |
| Created on | Read-only | Auto | Timestamp |
| Modified on | Read-only | Auto | Timestamp |

### Action Buttons
| Button | Behavior |
|--------|----------|
| Add another Object record | Adds a new inline record row |
| Remove | Removes the inline record (before save) |
| Save / Save and add another / Save and continue editing | Standard Django save actions |

## Object Detail/Edit View
Screenshot: `../../screenshots/22-objects-detail-with-records.png`

Shows read-only header fields (UUID, Object type UUID, Object type link, Created on, Modified on) and all Object records in a table. Historical records are read-only; only the "new record" row at the bottom is editable.

## Token Authorization Detail
Screenshot: `../../screenshots/17-objects-token-detail-with-permissions.png`

### Token Fields
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Identifier | Text | Yes | Unique token name |
| Contact person | Text | Yes | |
| Email | Text | Yes | Email format |
| Organization | Text | Yes | |
| Application | Text | Yes | |
| Administration | Text | Yes | |
| Is superuser | Checkbox | No | Bypasses all permission checks |
| Token | Read-only | Auto | The actual API token |

### Inline Permissions Table
| Column | Notes |
|--------|-------|
| Object type | Link to the object type |
| Mode | Read-only or Read and write |
| Use fields | Boolean — enables field-level auth |
| Mode | (duplicate column in UI) |
| Object type UUID | UUID of the object type |
| Acties | Change link |
| Delete? | Remove button |

Plus "Voeg een Permission toe" (Add a Permission) link at the bottom.

## Permission Detail Form
Screenshot: `../../screenshots/18-objects-permission-detail.png`

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Token auth | Dropdown | Yes | Select which token this permission is for |
| Object type | Dropdown | Yes | Select which object type this grants access to |
| Mode | Dropdown | Yes | Read-only or Read and write |
| Use field-based authorization | Checkbox | No | Disabled by default — enables per-field access control |

**Warning text:** "Changing the Object type will not maintain the previously selected authorization fields."

## Services (ZGW Consumers)
Screenshot: `../../screenshots/23-objects-services.png`

### List Columns
| Column | Notes |
|--------|-------|
| Label | Service name |
| Type | API type (NRC, ZRC, ZTC, etc.) |
| Api root url | Base URL |
| Service slug | URL-safe identifier |
| NLX url | NLX gateway URL |
| Authorization type | No auth, API key, ZGW client_id + secret |

### Filter Sidebar
- By type: AC, NRC, ZRC, ZTC, DRC, BRC, Contactmomenten, Klanten, Verzoeken, ORC
- By authorization type: No auth, API key, ZGW client_id + secret

## Notifications Configuration
Screenshot: `../../screenshots/24-objects-notifications-config.png`

| Field | Type | Notes |
|-------|------|-------|
| Notifications api service | Dropdown (Service) | Which external Notifications API to use |
| Notification delivery max retries | Integer | Default: 1 |
| Notification delivery retry backoff | Integer | Default: 2 |
| Notification delivery retry backoff max | Integer | Default: 3 |
| Notification delivery base factor | Integer | Default: 4 |
