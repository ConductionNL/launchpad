# Monica CRM v4.1.2 - Complete Browser Walkthrough

**Date:** 2026-03-14
**Method:** Local Docker instance + automated Playwright browser walkthrough (browser-7)
**Version:** Monica 4.1.2 (official Docker Hub `monica` image, Apache variant)
**URL:** http://localhost:9013
**Database:** MariaDB 11
**Screenshots:** 41 captured in `../screenshots/`

---

## Docker Setup

- **Image:** `monica` (apache variant, Docker Hub official)
- **Database:** `mariadb:11`
- **Port:** 9013 (mapped to container port 80)
- **Network:** Isolated bridge network `monica-net`
- **Volumes:** `monica-data` (storage), `monica-mysql` (database)
- **APP_KEY:** Generated via `openssl rand -base64 32`
- **Startup time:** ~3-4 minutes (database migrations run on first boot)

**Important:** This is Monica v4.1.2, a traditional server-rendered Laravel app. It is NOT the Vue 3 + Inertia.js SPA described in the v5/main branch. Monica v5 introduces "Vaults" (workspaces) and a completely different UI architecture. The Docker Hub `monica` image is v4.x only.

---

## Navigation Structure

Monica v4 has a simple top navigation bar with 6 items:
1. **Dashboard** (`/dashboard`)
2. **Contacts** (`/people`)
3. **Journal** (`/journal`)
4. **Settings** (`/settings`) - 11 sub-pages
5. **Product changes** (`/changelog`)
6. **Logout**

Plus a global search bar: "Search your contacts..."

---

## Page-by-Page Walkthrough

### 1. Registration Page
**Screenshot:** `01-register-page.png`

First-time setup presents a registration form:
- **Fields:**
  - First name (required)
  - Last name (required)
  - Email address (required)
  - Password (required)
  - Password confirmation (required)
- **Language selector** dropdown at top (defaults to English)
- **Policy agreement** checkbox: "I accept the policy and the terms of use"
- Green "Register" button

No invitation code or admin key required. First registered user becomes the account owner.

---

### 2. Dashboard
**Screenshots:** `02-dashboard-empty.png`, `40-dashboard-with-data.png`

The dashboard has a 2-column layout:

**Left column:**
- "Events in the next 3 months" - upcoming reminders grouped by month (Mar, Apr, May 2026)

**Right column (top):**
- "Product changes" widget - latest 3 changelog entries with "View details" link

**Right column (middle) - Tabbed widget:**
- **Recent calls** tab
- **Favorite notes** tab
- **Debts** tab
- **Tasks** tab

**Right column (bottom) - Stats:**
- Contacts count
- Activities count
- Gifts count

**Top bar:**
- "Last consulted:" shows avatar of most recently viewed contact
- "Add someone" button (large, green)

---

### 3. Contacts List
**Screenshot:** `15-contacts-list.png`

- **Header:** "1 contact" count + "Sort" link
- **Search:** Full-text search bar ("Search your contacts...")
- **Table columns:** Avatar | Contact name | Description
- **Pagination controls:**
  - Rows per page: 30/50/100/All dropdown
  - Page number input with Previous/Next buttons
- **Sidebar:** "Add someone" button
- Clicking a contact row navigates to the contact detail page

---

### 4. Add Contact Form
**Screenshots:** `03-add-contact-form.png`, `04-add-contact-filled.png`

**Fields:**
- First name (required)
- Middle name (optional)
- Last name (required)
- Nickname (optional)
- Gender dropdown: Male/Female/Rather not say
- **Birthdate** section with 3 radio options:
  - "Don't know" (default)
  - "Know the exact date" - reveals date picker
  - "Know age at death"
- Green "Add" button + "Cancel" link

---

### 5. Contact Detail Page
**Screenshot:** `05-contact-detail-top.png`

The contact page is the core of Monica. It has a **header**, a **left sidebar** with modules, and a **main content area** with 3 tabs.

#### Header
- **Avatar** with "Update" photo link
- **Name** with favorite star toggle
- **Job title + company** (if set)
- **Stats row:** Date added (with age), Last activity together, Last called
- **Stay in touch** link (set reminder frequency)
- **Tags** section with "Add tags" link
- **"Edit contact information"** link

#### Left Sidebar Modules

**Love relationships** - Add link (creates relationship type 1)
**Family relationships** - Add link (creates relationship type 9)
**Other kind of relationships** - Add link (creates relationship type 18)
**Pets** - Add link (inline form)
**Contact information** - Edit link (inline edit mode)
**Addresses** - Add link (inline form)
**How you met** - "Indicate how you met [name]" link
**Work information** - Shows job + company; "Update work information" link
**Food preferences** - Add link

**Bottom links:**
- History (audit logs)
- Export as vCard
- Archive contact
- Delete contact

#### Content Tabs

1. **Life events** (with count)
2. **Notes, reminders, ...** (main interaction tab)
3. **Photos**

---

### 6. Contact Sidebar Forms (All Inline)

#### 6a. Add Pet Form
**Screenshot:** `31-add-pet-form.png`

**Fields:**
- Kind of pet (dropdown): Reptile, Bird, Cat, Dog, Fish, Hamster, Horse, Rabbit, Rat, Small animal, Other
- Name (optional) - text field
- Add/Cancel buttons

#### 6b. Add Address Form
**Screenshot:** `32-add-address-form.png`

**Fields:**
- Label (optional) - text
- Street (optional) - text
- City (optional) - text
- Province (optional) - text
- Postal code (optional) - text
- Country (optional) - dropdown with all world countries (~250 options, from Afghanistan to Zimbabwe)
- Latitude (numbers only) (optional) - number spinner
- Longitude (numbers only) (optional) - number spinner
- Add/Cancel buttons

#### 6c. Add Contact Information Form
**Screenshot:** `37-add-contact-info-form.png`

**Fields:**
- Contact type (dropdown): Twitter, Telegram, Whatsapp, LinkedIn, Phone, Facebook, Email
  - "Personalize" link goes to Settings > Personalization to add custom types
- Content - text field
- Add/Cancel buttons

Edit mode shows existing entries with edit/delete icons per entry, plus "Done" button to exit edit mode.

#### 6d. Edit Contact Form
**Screenshot:** `07-edit-contact-form.png`, `08-edit-contact-birthday.png`

Full page form at `/people/{id}/edit`:
- **Fields:**
  - First name (required)
  - Middle name (optional)
  - Last name (required)
  - Nickname (optional)
  - Gender dropdown
  - Description (textarea) - "An email address, or a physical description. Whatever that identifies the contact in the couple of seconds you have to identify the contact."
  - Birthdate (3 radio options: Don't know / Exact date / Age-only approximation)
  - Date picker calendar when "Exact date" selected
  - "This person is deceased" checkbox
    - When checked: reveals "Deceased date" field with same 3 options
  - "Stay in touch every X days" toggle
- Update/Cancel buttons

#### 6e. How You Met Form
**Screenshot:** `27-how-you-met-form.png`

Full page at `/people/{id}/introductions/edit`:
- **Fields:**
  - "Were you introduced by someone?" - toggle
    - If yes: contact search field to find the introducer
  - "When did you meet?" - date picker
  - "Where did you meet?" - text field (location description)
  - "Other details" - textarea with Markdown support
- Save/Cancel buttons

#### 6f. Work Information Form
**Screenshot:** `10-work-information-form.png`

Full page at `/people/{id}/work/edit`:
- **Fields:**
  - Job title (text)
  - Company name (text)
- Save/Cancel buttons

#### 6g. Food Preferences Form
**Screenshot:** `30-food-preferences-form.png`

Full page at `/people/{id}/food`:
- **Fields:**
  - Textarea: "Does Jane have specific dietary restrictions, allergies, etc?"
  - Supports Markdown
- Save/Cancel buttons

---

### 7. Contact Tab: Life Events
**Screenshots:** `13-life-events-categories.png`, `14-life-events-work-education.png`

Empty state shows illustration + "Add life event" button.

**Life event categories** (5 categories, each with sub-types):
1. **Work & education** - sub-types include: New job, Retirement, New school, Volunteer work, Published a book or paper, Got a degree or diploma, Military service
2. **Family & relationships** - marriage, divorce, birth, adoption, etc.
3. **Home & living** - moved, bought home, renovated, etc.
4. **Health & wellness** - surgery, illness, overcame illness, etc.
5. **Travel & experiences** - traveled, learned instrument, got tattoo, new hobby, etc.

Each life event records:
- Date
- Description/notes
- Category + sub-type

---

### 8. Contact Tab: Notes, Reminders, ...

This tab contains 8 module sections:

#### 8a. Notes
**Screenshot:** `06-contact-with-note.png`

- "Add note" text field at top (supports Markdown)
- Notes list with date, Edit/Delete links per note
- Notes are private and not shared

#### 8b. Conversations
**Screenshot:** `11-log-conversation-form.png`

Full page form at `/people/{id}/conversations/create`:
- **Fields:**
  - "When did the conversation happen?" - date picker
  - "How did you talk?" - 6 channel options:
    - Messenger, Email, Telegram, WhatsApp, SMS, Other
  - Message thread: alternating "you said" / "[name] said" text areas
    - "Add another message" button to extend the thread
    - Markdown support
- Save/Cancel buttons

#### 8c. Phone Calls
**Screenshot:** `35-phone-call-log-form.png`

Inline form:
- **Fields:**
  - "The phone call happened on" - date picker (defaults to today)
  - "Who called?" - radio: "You called" / "[Name] called"
  - "What did you talk about?" (optional) - textarea with Markdown
  - "Do you want to log how you felt during this call?" (optional) - emotion picker with emoji scale
- Add/Cancel buttons

#### 8d. Activities
**Screenshot:** `36-add-activity-form.png`

Inline form:
- **Fields:**
  - "What did you do with [name]?" - text field
  - "The activity happened on..." - date picker (defaults to today)
- **Optional expandable sections:**
  - "Add more details" - reveals description textarea
  - "Add emotions" - emotion picker
  - "Indicate a category" - activity type picker
  - "Add participants" - multi-contact selector
- Add/Cancel buttons

#### 8e. Reminders
**Screenshot:** `09-add-reminder-form.png`

Full page form at `/people/{id}/reminders/create`:
- **Fields:**
  - Title (required) - what to remember
  - Date (required) - date picker
  - Frequency dropdown: One time, Every week, Every month, Every year
  - Comment (optional) - additional context
- Add/Cancel buttons

Reminder list shows: date, frequency badge, title, comment, edit/delete icons.
Note: "Reminders automatically added for birthdays can not be deleted."

#### 8f. Tasks
**Screenshot:** `33-add-task-form.png`

Inline form:
- **Fields:**
  - Title (required)
  - Description (optional) - textarea
- Add/Cancel buttons

Tasks appear with checkboxes for completion.

#### 8g. Gifts
**Screenshot:** `34-add-gift-form.png`

Inline form:
- **Status radio buttons:**
  - Gift idea (default)
  - Gift given
  - Gift received
- **Fields:**
  - Gift name (required)
- **Optional expandable sections:**
  - Comment (optional)
  - Link to the web page (optional) - URL
  - Value (optional) - monetary amount
  - Photo (optional) - image upload
  - Date (optional) - date picker
- Add/Cancel buttons

Gift list organized in 3 tabs: Gift ideas (0), Gifts given (0), Gifts received (0)

#### 8h. Debts
**Screenshot:** `26-debt-creation-form.png`

Full page form at `/people/{id}/debts/create`:
- **Fields:**
  - Direction: "you owe [name]" / "[name] owes you" radio
  - Amount (required) - number field with currency
  - Reason (optional) - text field
- Add/Cancel buttons

---

### 9. Contact Tab: Photos
**Screenshot:** `38-photos-tab.png`

- "Upload photo" button
- Empty state: "You can store images about this contact. Upload one now!" with illustration
- Photos displayed in a grid when uploaded

---

### 10. Add Relationship Form
**Screenshot:** `12-add-relationship-form.png`

Full page at `/people/{id}/relationships/create?type={typeId}`:
- **Fields:**
  - "Search among existing contacts" or "Create a new one"
  - If creating new: First name, Last name, Gender dropdown
  - Relationship type dropdown (27 types across 3 categories):
    - **Love:** Partner, Spouse, Date, Lover, Ex-boyfriend/girlfriend, Affair
    - **Family:** Child, Parent, Sibling, Grandparent, Grandchild, Uncle/Aunt, Nephew/Niece, Cousin, Godparent, Godchild, Stepparent, Stepchild
    - **Other:** Friend, Best friend, Colleague, Boss, Subordinate, Mentor, Protege, Ex-colleague
  - Birthdate section (same 3 options as contact form)
  - "This person is deceased" checkbox
  - Real/partial contact toggle: "This person already has their own profile" checkbox
- Add/Cancel buttons

---

### 11. Journal
**Screenshots:** `16-journal-empty.png`, `17-journal-add-entry.png`

**Main page features:**
- "How was your day?" - 5-level emoji mood rating bar
- "Add a journal entry" button
- "All entries / Activities" filter tabs
- "All Years" year filter dropdown

**Add entry form** (full page):
- **Fields:**
  - Title (required) - text
  - Date (required) - date picker (defaults to today)
  - Entry (required) - textarea with Markdown support
    - "Want to format your text nicely? We support Markdown" with link to docs
- Post/Cancel buttons

Journal combines:
- Manual journal entries
- Automatic activity logs
- Daily mood ratings

---

### 12. Settings Pages (11 sub-pages)

#### 12a. Account Settings
**Screenshot:** `18-settings-account.png`

**Sections:**
- **Account info:** First name, Last name, Email, "Indicate who you are as a contact" dropdown
- **Internationalization:** Timezone dropdown, Locale dropdown, Temperature unit (Fahrenheit/Celsius)
- **Layout:** Fluid (full-width) / Fixed container toggle
- **Danger zone:**
  - "Reset account" button (deletes all data, keeps account)
  - "Delete account" button (deletes everything permanently)

#### 12b. Personalization
**Screenshot:** `19-settings-personalization.png`

Extensive customization page with sections:
- **Genders:** Manage gender options (Male, Female, Rather not say by default)
- **Default gender at contact creation:** Dropdown
- **Reminders rules:** Default reminder time, email notification toggle
- **Contact fields:** Manage contact info types (Twitter, Telegram, Whatsapp, LinkedIn, Phone, Facebook, Email)
  - Protocol column (e.g., `mailto:`, `tel:`, `https://twitter.com/`)
- **Activity types:** Customize what activity categories exist
- **Life event types:** Manage life event categories and sub-types
- **Enable/Disable modules toggle:** Turn on/off sections on contact pages

#### 12c. Storage
**Screenshot:** `20-settings-storage.png`

- Shows current storage usage
- Displays available vs. used space
- Storage is for uploaded photos and documents

#### 12d. Export Data
**Screenshot:** `21-settings-export.png`

Two export options:
- **Export to SQL** - full database dump
- **Export to JSON** (preview feature) - structured JSON export
- Download buttons for each

#### 12e. Import Data
**Screenshot:** `22-settings-import.png`

- **vCard import** - file upload for `.vcf` files
- Import history table showing past imports
- Supports standard vCard format

#### 12f. Users
**Screenshot:** `41-settings-users.png`

- Shows "You are the only one who has access to this account"
- "Would you like to invite someone else?" prompt
- "Invite someone" link to `/settings/users/create`
- Note: invited users get same access level (no role-based access control)

#### 12g. Tag Management
**Screenshot:** `28-settings-tags.png`

- List of all tags with associated contact counts
- Delete tag functionality
- Tags are applied to contacts from the contact detail page

#### 12h. API
**Screenshot:** `23-settings-api.png`

Two sections:
- **Personal Access Tokens:** Create/revoke API tokens for personal use
  - Create form: Token name input + Create button
  - Token list with creation date and Delete button
- **OAuth Clients:** Register OAuth2 applications (Laravel Passport)
  - Create form: Application name + Redirect URL
  - Client list with ID, Secret, Edit, Delete
  - For third-party integrations

#### 12i. DAV Resources
**Screenshot:** `24-settings-dav.png`

Shows WebDAV/CardDAV/CalDAV URLs:
- Base DAV URL
- CardDAV URL (for contact sync)
- CalDAV URL (for calendar/reminder sync)
- Instructions for connecting with external applications

#### 12j. Audit Logs
**Screenshot:** `29-settings-auditlogs.png`

- Chronological activity history
- Shows all account-level actions (contact created, updated, deleted, etc.)
- Date + description per entry

#### 12k. Security
**Screenshot:** `25-settings-security.png`

Three sections:
- **Change password:** Current password + New password + Confirm
- **Two Factor Authentication (TOTP):**
  - Enable/Disable toggle
  - QR code display for authenticator app setup
  - Recovery codes generation
- **WebAuthn Security Keys:**
  - Register hardware security keys (FIDO2/WebAuthn)
  - Key list with names and registration dates
  - Delete key functionality

---

### 13. Product Changes (Changelog)
**Screenshot:** `39-changelog-page.png`

Reverse-chronological list of feature announcements from Jan 2022 back to Apr 2018:
- Each entry has: date, title (heading), description paragraph, sometimes screenshots
- Notable entries include: JSON export, subscription frequency, avatar cropping, WebAuthn, life events, conversations, documents upload, photos upload
- Last update: Jan 11, 2022 - indicates v4.x development has slowed significantly

---

## CRUD Operations Tested

1. **Created** a contact: Jane Marie Smith with middle name, nickname (JJ), email
2. **Added** a note: Free-text note about meeting Jane at a tech meetup
3. **Set** a reminder: "Call Jane to discuss the UX project proposal" for Mar 14, 2027, one-time
4. **Updated** work information: Product Designer at TechStartup Inc.
5. **Viewed** all major pages and sub-pages
6. **Opened** all inline forms (pet, address, contact info, task, gift, phone call, activity)

---

## Technical Architecture (v4.1.2)

- **Backend:** PHP/Laravel (server-side rendered, Blade templates)
- **Frontend:** jQuery + Vue.js components (v2) sprinkled into Blade pages
- **Database:** MySQL/MariaDB
- **Authentication:** Session-based with optional 2FA (TOTP + WebAuthn)
- **API:** RESTful API with OAuth2 (Laravel Passport) + Personal Access Tokens
- **Sync:** CardDAV/CalDAV built-in
- **Storage:** Local filesystem (within Docker volume)
- **Search:** Database LIKE queries (no external search engine in v4)

---

## Key Differences: v4.1.2 (Docker) vs v5/main (GitHub)

| Feature | v4.1.2 (this walkthrough) | v5/main (GitHub) |
|---------|---------------------------|-------------------|
| **Architecture** | Server-rendered Laravel + jQuery/Vue2 | Vue 3 + Inertia.js SPA |
| **Workspaces** | Single account | Multi-vault (workspaces) |
| **Contact page** | Single long page with modules | Restructured with new layout |
| **Templates** | Fixed modules per contact | Customizable page templates |
| **Search** | DB LIKE queries | Meilisearch integration |
| **Cache** | None / basic | Redis + Memcached |
| **Queue** | None | Laravel Queue (Redis) |
| **Groups** | Tags only | Groups with member management |
| **Companies** | Work info field | Dedicated company entities |
| **Reports** | None | Activity reports page |
| **Files** | Documents + Photos | Dedicated files section |

---

## Form Field Inventory (Complete)

### Contact Creation/Edit
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| First name | text | Yes | |
| Middle name | text | No | |
| Last name | text | Yes | |
| Nickname | text | No | Displayed in parentheses |
| Gender | dropdown | No | Male/Female/Rather not say (customizable) |
| Description | textarea | No | Quick identifier |
| Birthdate | radio+date | No | 3 modes: unknown/exact/age-only |
| Is deceased | checkbox | No | Reveals deceased date field |
| Stay in touch | number | No | Days between reminders |

### Address
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Label | text | No | e.g., "Home", "Work" |
| Street | text | No | |
| City | text | No | |
| Province | text | No | |
| Postal code | text | No | |
| Country | dropdown | No | ~250 countries |
| Latitude | number | No | Decimal degrees |
| Longitude | number | No | Decimal degrees |

### Reminder
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Title | text | Yes | What to remember |
| Date | date | Yes | When to remind |
| Frequency | dropdown | Yes | One time/Weekly/Monthly/Yearly |
| Comment | textarea | No | Additional context |

### Activity
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Description | text | Yes | What you did |
| Date | date | Yes | Defaults to today |
| Details | textarea | No | Expandable |
| Emotions | picker | No | Expandable |
| Category | dropdown | No | Expandable, customizable types |
| Participants | multi-select | No | Expandable, search contacts |

### Phone Call
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Date | date | Yes | Defaults to today |
| Who called | radio | Yes | You / Contact |
| Content | textarea | No | Markdown supported |
| Emotion | picker | No | How you felt |

### Conversation
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Date | date | Yes | When it happened |
| Channel | radio(6) | Yes | Messenger/Email/Telegram/WhatsApp/SMS/Other |
| Messages | textarea[] | Yes | Thread of alternating you/them messages |

### Gift
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Status | radio(3) | Yes | Idea/Given/Received |
| Name | text | Yes | |
| Comment | textarea | No | Expandable |
| URL | url | No | Expandable |
| Value | number | No | Expandable, with currency |
| Photo | file | No | Expandable |
| Date | date | No | Expandable |

### Task
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Title | text | Yes | |
| Description | textarea | No | |

### Debt
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Direction | radio | Yes | You owe / They owe |
| Amount | number | Yes | With currency |
| Reason | text | No | |

### Pet
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Kind | dropdown | Yes | 11 options: Reptile through Other |
| Name | text | No | |

### Journal Entry
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Title | text | Yes | |
| Date | date | Yes | Defaults to today |
| Entry | textarea | Yes | Markdown supported |

### Relationship
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Contact | search/create | Yes | Existing or new |
| Type | dropdown | Yes | 27 types in 3 categories |
| Birthdate | radio+date | No | If creating new contact |
| Is deceased | checkbox | No | If creating new contact |

### Contact Information
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Type | dropdown | Yes | 7 default types (customizable) |
| Content | text | Yes | The actual value |

---

## Strengths

1. **Simplicity** - Very focused on personal relationship management, no feature bloat
2. **Privacy-first** - Self-hostable, no tracking, data stays with user
3. **Comprehensive contact model** - Captures many dimensions of a relationship
4. **CardDAV/CalDAV sync** - Standards-based contact/calendar sync
5. **API with OAuth2** - Proper API for integrations
6. **2FA support** - TOTP + WebAuthn security keys
7. **Markdown everywhere** - Consistent rich text support
8. **vCard import/export** - Standard format for data portability
9. **Emotion tracking** - Unique feature for personal reflection
10. **Customizable modules** - Can enable/disable features per preference

## Weaknesses

1. **No automation** - No webhooks, workflows, or triggers
2. **No team features** - Users get identical access (no roles/permissions)
3. **Stale development** - Last changelog entry is Jan 2022; v5 still in development
4. **Basic search** - Database LIKE queries, no full-text search
5. **No mobile app** - Web-only (responsive but desktop-first)
6. **No pipeline/workflow** - Pure CRM, no process management
7. **No integrations** - Beyond DAV sync, no external service connections
8. **Single-user focus** - Designed for personal use, not organizational
9. **No reporting** - No analytics, charts, or aggregate views
10. **Limited customization** - Can customize field types but not the overall data model

---

## Screenshot Index

| # | File | Description |
|---|------|-------------|
| 01 | `01-register-page.png` | Registration form with language selector |
| 02 | `02-dashboard-empty.png` | Empty dashboard after registration |
| 03 | `03-add-contact-form.png` | Add new person form (empty) |
| 04 | `04-add-contact-filled.png` | Add new person form (filled) |
| 05 | `05-contact-detail-top.png` | Full contact detail page |
| 06 | `06-contact-with-note.png` | Contact page with note added |
| 07 | `07-edit-contact-form.png` | Edit contact form (name, gender, description, birthday) |
| 08 | `08-edit-contact-birthday.png` | Edit contact with birthday datepicker |
| 09 | `09-add-reminder-form.png` | Add reminder form |
| 10 | `10-work-information-form.png` | Work info form (job title, company) |
| 11 | `11-log-conversation-form.png` | Log conversation form (threaded messages) |
| 12 | `12-add-relationship-form.png` | Add relationship form (27 types) |
| 13 | `13-life-events-categories.png` | Life events tab with 5 categories |
| 14 | `14-life-events-work-education.png` | Work & education life event subtypes |
| 15 | `15-contacts-list.png` | Contacts list with table + pagination |
| 16 | `16-journal-empty.png` | Journal page with mood rating |
| 17 | `17-journal-add-entry.png` | Journal entry form (Markdown) |
| 18 | `18-settings-account.png` | Settings: account, i18n, layout, reset/delete |
| 19 | `19-settings-personalization.png` | Settings: genders, reminders, fields, activity types |
| 20 | `20-settings-storage.png` | Settings: storage usage |
| 21 | `21-settings-export.png` | Settings: SQL + JSON export |
| 22 | `22-settings-import.png` | Settings: vCard import |
| 23 | `23-settings-api.png` | Settings: API tokens, OAuth clients |
| 24 | `24-settings-dav.png` | Settings: WebDAV/CardDAV/CalDAV URLs |
| 25 | `25-settings-security.png` | Settings: password, 2FA, WebAuthn |
| 26 | `26-debt-creation-form.png` | Debt management form |
| 27 | `27-how-you-met-form.png` | How did you meet form |
| 28 | `28-settings-tags.png` | Tag management page |
| 29 | `29-settings-auditlogs.png` | Audit logs with activity history |
| 30 | `30-food-preferences-form.png` | Food preferences form |
| 31 | `31-add-pet-form.png` | Add pet inline form (11 kinds) |
| 32 | `32-add-address-form.png` | Add address form (8 fields + geocoding) |
| 33 | `33-add-task-form.png` | Add task inline form |
| 34 | `34-add-gift-form.png` | Add gift form (3 statuses + 5 optional fields) |
| 35 | `35-phone-call-log-form.png` | Phone call log form (emotion tracking) |
| 36 | `36-add-activity-form.png` | Add activity form (4 expandable sections) |
| 37 | `37-add-contact-info-form.png` | Add contact info form (7 channel types) |
| 38 | `38-photos-tab.png` | Photos tab (empty state) |
| 39 | `39-changelog-page.png` | Product changes / changelog page |
| 40 | `40-dashboard-with-data.png` | Dashboard with contact data |
| 41 | `41-settings-users.png` | Settings: user management + invitations |
