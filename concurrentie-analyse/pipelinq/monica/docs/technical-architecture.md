# Monica - Technical Architecture

**Source:** GitHub source code analysis (main branch)
**Date:** 2026-03-14

## Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.3+ |
| Framework | Laravel 12 |
| Frontend | Vue 3 + Inertia.js |
| CSS | Tailwind CSS 4 |
| UI Components | Ant Design Vue |
| Build | Vite |
| Database | MariaDB 10 (or SQLite for dev) |
| Cache | Redis / Memcached |
| Search | Meilisearch (via Laravel Scout) |
| Queue | Redis / Database / Sync |
| Mail | Mailpit (dev) / SMTP (prod) |
| Auth | Laravel Fortify + Sanctum + WebAuthn |
| API | REST with Sanctum tokens |
| DAV | CalDAV + CardDAV (custom implementation) |
| File Upload | Uploadcare |
| Docker | Laravel Sail (dev), Docker Hub image (prod) |
| i18n | laravel-vue-i18n (27 languages) |

## Domain-Driven Architecture

### Three Top-Level Domains

**1. Contact Domain** (22 sub-domains)
Each sub-domain follows the pattern: Services/ (business logic), Web/ (controllers), sometimes Dav/ and API/.

| Sub-domain | Purpose |
|-----------|---------|
| ManageAvatar | Contact photos/avatars |
| ManageCalls | Phone call logging |
| ManageContact | Core CRUD, favorites, labels, archiving, moving, sorting |
| ManageContactAddresses | Physical addresses with map images |
| ManageContactFeed | Activity feed timeline |
| ManageContactImportantDates | Birthdays, anniversaries |
| ManageContactInformation | Phone, email, social handles |
| ManageContactName | Name management |
| ManageDocuments | File attachments |
| ManageGoals | Goals and streaks |
| ManageGroups | Contact grouping |
| ManageJobInformation | Employment details |
| ManageLabels | Tags/labels |
| ManageLifeEvents | Major life events timeline |
| ManageLoans | Debts and loans |
| ManageMoodTrackingEvents | Daily mood tracking |
| ManageNotes | Private notes |
| ManagePets | Pet management |
| ManagePhotos | Photo galleries |
| ManagePronouns | Pronoun assignment |
| ManageQuickFacts | Quick facts |
| ManageRelationships | Inter-contact relationships |
| ManageReligion | Religion assignment |
| ManageReminders | Reminder scheduling |
| ManageTasks | Task management |
| Dav | CalDAV/CardDAV server |
| DavClient | CalDAV/CardDAV client sync |

**2. Vault Domain** (12 sub-domains)

| Sub-domain | Purpose |
|-----------|---------|
| ManageAddresses | Vault-level address management |
| ManageCalendar | Calendar views |
| ManageCompanies | Company directory |
| ManageFiles | File management |
| ManageJournals | Journals, posts, slices, metrics, photos, tags |
| ManageLifeMetrics | Custom life metric tracking |
| ManageReports | Address, date, and mood reports |
| ManageTasks | Vault-level tasks |
| ManageVault | CRUD, feed, reminders, dashboard tabs |
| ManageVaultImportantDateTypes | Custom date type definitions |
| ManageVaultSettings | Labels, life events, mood params, templates, users |
| Search | Full-text and recently consulted |

**3. Settings Domain** (22 sub-domains)

| Sub-domain | Purpose |
|-----------|---------|
| CancelAccount | Account deletion |
| CreateAccount | Registration and setup |
| ManageAddressTypes | Custom address types |
| ManageCallReasons | Custom call reasons |
| ManageContactInformationTypes | Custom contact field types |
| ManageCurrencies | Currency management |
| ManageGenders | Custom genders |
| ManageGiftOccasions | Gift occasion types |
| ManageGiftStates | Gift state types |
| ManageGroupTypes | Group types and roles |
| ManageModules | Configurable UI modules |
| ManageNotificationChannels | Email/Telegram channels |
| ManagePersonalization | Global personalization |
| ManagePetCategories | Pet category types |
| ManagePostTemplates | Journal post templates |
| ManagePronouns | Custom pronouns |
| ManageRelationshipTypes | Custom relationship types |
| ManageReligion | Religion options |
| ManageSettings | General settings |
| ManageStorage | Storage quotas |
| ManageTemplates | Contact page templates with modules |
| ManageUserPreferences | Per-user locale, timezone, etc. |
| ManageUsers | User CRUD |

## Data Models (69 models)

Key entities: Account, User, Vault, Contact, Address, Call, Company, Currency, File, Gender, Gift, Goal, Group, Journal, Label, LifeEvent, Loan, Module, MoodTrackingEvent, Note, Pet, Post, Pronoun, QuickFact, Relationship, Religion, Reminder, SliceOfLife, Streak, Tag, Task, Template, TimelineEvent.

## Database

- 74 migration files
- MariaDB 10 for production, SQLite for development/testing
- Full-text indexing support for search

## Frontend

- 254 Vue 3 single-file components
- Inertia.js for SPA-like navigation without API
- Page structure mirrors backend domains (Settings, Vault, Auth, Profile, API)
- Charts.css for data visualization
- Lucide icons (lucide-vue-next)
- vuedraggable for drag-and-drop
- vue-clipboard3 for copy functionality

## API

Minimal REST API surface:
- `GET /api/user` — current user
- `GET /api/users` — list users
- `GET /api/users/{id}` — show user
- `GET/POST/PUT/DELETE /api/vaults` — vault CRUD

All authenticated via Laravel Sanctum bearer tokens. Most functionality is only available through the Inertia.js web interface, not the API.

## Testing

- 516 test files (PHPUnit)
- Covers services, controllers, and DAV integration
- CI via GitHub Actions with SonarCloud coverage reporting

## Deployment Options

1. **Laravel Sail** (development): docker-compose with MariaDB, Redis, Memcached, Meilisearch, Mailpit
2. **Docker Hub** (production): Official `monica` image on Docker Hub
3. **Manual install**: Standard Laravel deployment on PHP 8.3+ server
4. **Managed hosting**: app.monicahq.com ($9/month)
