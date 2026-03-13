# Objecttypes API — Admin Interface

## UI Reference
Screenshots: `../../screenshots/`

## Login Page
Screenshot: `../../screenshots/01-objecttypes-login.png`

Standard Django Admin login with:
- Username/password fields
- "Forgotten your password or username?" link
- "Login with organization account" (OIDC) option
- Dark/light theme toggle

## Dashboard
Screenshot: `../../screenshots/02-objecttypes-dashboard.png`

### Navigation Tabs
| Tab | Sections |
|-----|----------|
| Dashboard | Overview with all model links |
| Accounts | Groups, TOTP devices, Users, Webauthn devices |
| API Authorizations | Token authorizations |
| Data | Object types |
| Configuration | Application groups, OIDC Providers, OIDC clients |
| Logging | Access attempts, Access logs, Outgoing request log config, Outgoing request logs |

### Header Bar
- App name: "Objecttypes admin"
- Welcome message with username
- Links: Starting point, Account security, Change password, Log out
- Theme toggle button

## Object Types List
Screenshot: `../../screenshots/03-objecttypes-list-empty.png`

### Actions
| Button/Action | Location | Behavior | Confirmation |
|--------------|----------|----------|-------------|
| Import from URL | Top right | Opens URL import form | No |
| Add object type | Top right | Opens creation form | No |
| Search | Search bar | Text search across object types | No |
| Delete selected | Bulk action dropdown | Deletes selected types | Yes |

### Table Columns
| Column | Sortable | Filterable | Notes |
|--------|----------|------------|-------|
| Name | Yes (implied) | Via search | Primary identifier |
| Name plural | Yes (implied) | Via search | |
| Allow geometry | Yes (implied) | No | Boolean icon |

## Object Type Create/Edit Form
Screenshots: `../../screenshots/04-objecttypes-add-form.png`, `../../screenshots/05-objecttypes-edit-after-create.png`

### Main Fields
| Field | Type | Required | Validation | Notes |
|-------|------|----------|------------|-------|
| Uuid | Text (auto-generated) | Yes | UUID format | Pre-populated, editable on create only |
| Name | Text | Yes | Max length | Primary name |
| Name plural | Text | No | Max length | Plural form |
| Description | Text | No | Max length | Free text |
| Data classification | Dropdown | Yes | Enum | Open, Intern, Confidential, Strictly confidential |
| Maintainer organization | Text | No | Max length | Organization responsible |
| Maintainer department | Text | No | Max length | Department responsible |
| Contact person | Text | No | Max length | |
| Contact email | Text | No | Email format | |
| Source | Text | No | Max length | |
| Update frequency | Dropdown | Yes | Enum | Real-time, Hourly, Daily, Weekly, Monthly, Yearly, Unknown |
| Provider organization | Text | No | Max length | |
| Documentation url | Text | No | URL format | |
| Labels | JSON textarea | No | Valid JSON | Default: {} |
| Allow geometry | Checkbox | No | Boolean | Default: checked |

### Object Version Section (Inline)
| Field | Type | Required | Validation | Notes |
|-------|------|----------|------------|-------|
| JSON schema | JSON editor (Raw/Visual) | Yes | Valid JSON Schema | Default: {} |
| Version | Read-only | Auto | Integer | Auto-incremented |
| Status | Read-only | Auto | Enum | Draft or Published |
| Published_at | Read-only | Auto | Date | Set when published |
| Created at | Read-only | Auto | Date | Shown after publish |
| Modified at | Read-only | Auto | Date | Shown after publish |

### Action Buttons
| Button/Action | Location | Behavior | Confirmation |
|--------------|----------|----------|-------------|
| Save | Bottom left | Save and return to list | No |
| Save and add another | Bottom left | Save and show empty form | No |
| Save and continue editing | Bottom left | Save and stay on form | No |
| Delete | Bottom right (red) | Delete object type | Yes (confirmation page) |
| Publish | Bottom right (appears after save) | Publishes current version | No (shows success message) |
| New version | Bottom right (appears after publish) | Creates new draft version | No |
| History | Top right | Shows change history | No |

## Version History
Screenshot: `../../screenshots/07-objecttypes-history.png`

### Table Columns
| Column | Notes |
|--------|-------|
| Version | Integer version number |
| Status | Draft or Published |
| Created at | Date |
| Modified at | Date |
| Published at | Date (empty for drafts) |
| JSON schema | Full schema JSON (truncated in display) |

## Token Authorizations
Screenshots: `../../screenshots/08-objecttypes-tokens-list.png`, `../../screenshots/09-objecttypes-token-detail.png`

### List Columns
| Column | Sortable | Notes |
|--------|----------|-------|
| Identifier | Yes | Unique token name |
| Contact person | Yes | |
| Organization | Yes | |
| Administration | Yes | |
| Application | Yes | |

### Token Detail Form
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Identifier | Text | Yes | Unique name for the token |
| Contact person | Text | Yes | Person responsible |
| Email | Text | Yes | Email format |
| Organization | Text | Yes | |
| Application | Text | Yes | |
| Administration | Text | Yes | |
| Token | Read-only | Auto | The actual API token (hex string) |

**Note:** Objecttypes API tokens are simple — no per-type permissions (unlike Objects API).

## Starting Point Page
Screenshot: `../../screenshots/11-objecttypes-startpage.png`

Public landing page with:
- Bilingual description (Dutch/English tabs)
- Links to: API docs (ReDoc), Open API specification, Administration
- Footer with: Maykin logo, documentation links, community links, Docker image link, GitHub link
- License: EUPL 1.2
- Commissioned by Municipality of Utrecht
