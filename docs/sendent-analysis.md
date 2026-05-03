# Sendent Workspace — File-by-File Analysis

> **Purpose.** Reverse-engineer the Sendent Workspace Nextcloud app (`sendent-workspace-main/`, v1.0.0, AGPL-3.0) feature-by-feature so we can replicate every capability inside `mydash` with a clean-room implementation. This document records *what* each file does, *what options/configurations it exposes*, and *how* the pieces fit together. **No code is copied verbatim** — only signatures, configuration schemas, and behavioural descriptions.
>
> **Source archive:** `mydash/2026.02.12_sendent-workspace-main.zip` (gitignored).
> **Source tree:** `mydash/sendent-workspace-main/` (gitignored).
> **This doc:** `mydash/docs/sendent-analysis.md` (gitignored).
>
> Sections marked **TBD** are filled in as the walk progresses.

---

## 1. Overview

### App identity

| Field | Value |
|---|---|
| App ID | `sendentworkspace` |
| Display name | Sendent Workspace |
| Summary | Customizable workspace with dynamic grid layout |
| Version | 1.0.0 |
| License | AGPL-3.0-or-later |
| Author | Luc Pasmans (Sendent B.V.) |
| PHP namespace | `OCA\SendentWorkspace` |
| Categories | `customization`, `dashboard` |
| Bug tracker | https://sendent.freshdesk.com/ |
| Nextcloud compat | min 31, max 33 |

### Navigation entry

A single nav item titled "Workspace", icon `app.svg`, route `sendentworkspace.workspace.index`, sort order `-9` (so it appears near the top, just below Files).

### Admin settings registration

- Settings page class: `OCA\SendentWorkspace\Settings\Admin\AdminSettings`
- Settings section class: `OCA\SendentWorkspace\Settings\Admin\Section`

### Webpack / build

- Two entry bundles, configured in `webpack.js`:
  - `main` ← `src/main.js` → `js/main.js` (the workspace UI)
  - `admin` ← `src/admin.js` → `js/admin.js` (the admin settings UI)
- Extends `@nextcloud/webpack-vue-config` v6.3.0.
- SVG files are loaded with `asset/source` (inlined as raw strings — important for icon usage in components).
- Vue alias forced to `vue/dist/vue.esm-bundler.js` (so runtime template compilation is available).
- `webpack.DefinePlugin` sets `__VUE_OPTIONS_API__ = true` and `__VUE_PROD_DEVTOOLS__ = false`. Confirms the codebase mixes Vue 3 Composition API and Options API.

### NPM dependencies (runtime)

| Package | Version | Used for |
|---|---|---|
| `vue` | 3.5.15 | UI framework (Vue 3) |
| `gridstack` | ^12.2.1 | Drag/resize grid layout engine — the heart of "dynamic grid layout" |
| `vuedraggable` | ^4.1.0 | Drag-and-drop list reordering (likely dashboard tabs / widget list) |
| `vue-material-design-icons` | ^5.3.1 | Icon component library (used by `dashboardIcons.js`) |
| `dompurify` | ^3.3.1 | XSS sanitisation for user-supplied HTML/text widgets |
| `@nextcloud/axios` | ^2.5.1 | XHR wrapper with CSRF + session cookies |
| `@nextcloud/dialogs` | ^7.1.0 | Toast/alert/confirm/file-picker dialogs |
| `@nextcloud/initial-state` | ^2.2.0 | Read PHP-injected initial state from the page |
| `@nextcloud/l10n` | ^3.4.0 | Translations (`t`, `n`) |
| `@nextcloud/router` | ^3.0.1 | URL builders (`generateUrl`, `generateOcsUrl`) |

> **Implication for mydash replica:** we already have most of these. We will need to add **gridstack** if not present, and pick a sanitiser (DOMPurify) for free-text widgets.

### Dev dependencies (notable)

- `@nextcloud/eslint-config` 8.4.2, `@nextcloud/stylelint-config` 3.0.1, `@nextcloud/babel-config` 1.3.0
- Webpack 5.99.9 + webpack-cli 6.0.1
- Engines pinned to Node ^20 / npm ^10.

### Repo layout

```
sendent-workspace-main/
├─ appinfo/              info.xml + routes.php
├─ lib/                  PHP backend (7 files, namespace OCA\SendentWorkspace)
│  ├─ AppInfo/Application.php
│  ├─ Controller/        WorkspaceController.php, WorkspaceApiController.php
│  ├─ Service/           WorkspaceService.php
│  ├─ Settings/Admin/    AdminSettings.php, Section.php
│  └─ ResponseDefinitions.php
├─ src/                  Vue 3 frontend
│  ├─ main.js, admin.js                 entry points
│  ├─ WorkspaceApp.vue, AdminApp.vue    shell apps
│  ├─ components/                       widgets + workspace UI (10 .vue)
│  ├─ composables/useGridManager.js     gridstack wrapper
│  └─ constants/dashboardIcons.js       icon registry
├─ templates/            admin.php, index.php (Nextcloud page templates)
├─ css/                  admin.css, workspace.css
├─ img/                  app.svg, app-dark.svg, sendent-logo.png
└─ l10n/                 translations (we'll skim, no logic)
```

---

## 2. Backend (PHP, `lib/`)

> Walked in dependency order: AppInfo → Settings → Service → Controllers → ResponseDefinitions.

### 2.1 `appinfo/routes.php` — full route table

Two web routes (page + binary resource) and 17 OCS API routes (all under `/ocs/v2.php/apps/sendentworkspace/api/v1/...`):

| # | Verb | URL | Controller#method | Purpose |
|---|---|---|---|---|
| 1 | GET | `/` | `workspace#index` | Renders the workspace SPA page (`index.php` template) |
| 2 | GET | `/resource/{filename}` | `workspace_api#getResource` | StreamResponse of an uploaded resource (image/binary) — non-OCS so it can stream raw bytes |
| 3 | GET | `/api/v1/layout/{groupId}` | `workspace_api#getLayout` | **Legacy** — fetch a single per-group dashboard layout; delegates to "default dashboard" path |
| 4 | POST | `/api/v1/layout/{groupId}` | `workspace_api#saveLayout` | **Legacy** — save a single per-group layout |
| 5 | GET | `/api/v1/groups` | `workspace_api#getGroups` | List groups configured for workspace assignment |
| 6 | POST | `/api/v1/groups` | `workspace_api#updateGroups` | Replace/update group configuration |
| 7 | GET | `/api/v1/dashboards/{groupId}` | `workspace_api#getGroupDashboards` | List dashboards bound to a group |
| 8 | POST | `/api/v1/dashboards/{groupId}` | `workspace_api#createGroupDashboard` | Create a new group-scoped dashboard |
| 9 | POST | `/api/v1/dashboards/{groupId}/default` | `workspace_api#setDefaultDashboard` | Mark which group dashboard is the default |
| 10 | GET | `/api/v1/dashboards/{groupId}/{dashboardId}` | `workspace_api#getGroupDashboard` | Fetch one group dashboard by id |
| 11 | PUT | `/api/v1/dashboards/{groupId}/{dashboardId}` | `workspace_api#updateGroupDashboard` | Update a group dashboard |
| 12 | DELETE | `/api/v1/dashboards/{groupId}/{dashboardId}` | `workspace_api#deleteGroupDashboard` | Remove a group dashboard |
| 13 | GET | `/api/v1/user-dashboards` | `workspace_api#getUserDashboards` | List the current user's personal dashboards |
| 14 | POST | `/api/v1/user-dashboards` | `workspace_api#createUserDashboard` | Create a personal dashboard |
| 15 | PUT | `/api/v1/user-dashboards/{dashboardId}` | `workspace_api#updateUserDashboard` | Update a personal dashboard |
| 16 | DELETE | `/api/v1/user-dashboards/{dashboardId}` | `workspace_api#deleteUserDashboard` | Remove a personal dashboard |
| 17 | POST | `/api/v1/active-dashboard` | `workspace_api#setActiveDashboard` | Persist which dashboard the user has active (per-user pref) |
| 18 | GET | `/api/v1/settings` | `workspace_api#getSettings` | Read app-level settings (admin + user-readable subset) |
| 19 | POST | `/api/v1/settings` | `workspace_api#updateSettings` | Update app-level settings (admin) |
| 20 | GET | `/api/v1/widget-items` | `workspace_api#getWidgetItems` | Enumerate available widget *types* (catalogue used by AddWidgetModal) |
| 21 | POST | `/api/v1/create-file` | `workspace_api#createFile` | Server-side file creation (e.g. "create new doc" widget action) |
| 22 | POST | `/api/v1/upload-resource` | `workspace_api#uploadResource` | Upload a binary resource (image used by ImageWidget etc.) |
| 23 | GET | `/api/v1/resources` | `workspace_api#listResources` | List previously uploaded resources |

> **Architectural notes from routes alone:**
> - Three persistence scopes: **app-level** (settings, groups, widget-items, resources), **group-level** (dashboards bound to a Nextcloud group), **user-level** (personal dashboards + active-dashboard preference).
> - "Layout" routes (#3/#4) are flagged `legacy` and described as delegating to "default dashboard" — confirms the model evolved from one-layout-per-group → many-dashboards-per-group with a default flag.
> - Resource upload + serving is a self-contained mini file API (not piggy-backing on Files app), so widgets can reference uploaded images by `/resource/{filename}`.

### 2.2 `lib/AppInfo/Application.php`

- Class: `Application extends OCP\AppFramework\App implements IBootstrap`.
- Single public constant `APP_ID = 'sendentworkspace'`.
- `__construct(array $urlParams = [])` — calls parent with `APP_ID`.
- `register(IRegistrationContext $context): void` — empty body, comment notes settings are wired via `info.xml`.
- `boot(IBootContext $context): void` — empty body.
- **Implication:** there is no DI container plumbing here, no event listeners, no service registrations. All wiring relies on Nextcloud auto-resolution + `info.xml`. This is a deliberately thin bootstrap.

### 2.3 `lib/Settings/Admin/Section.php`

Implements `OCP\Settings\IIconSection`. Registers a left-side admin nav section.

| Method | Returns |
|---|---|
| `getID()` | `'sendentworkspace'` |
| `getName()` | translated string `'Sendent Workspace'` |
| `getPriority()` | `75` (mid-range placement in admin sidebar) |
| `getIcon()` | URL of `app-dark.svg` from app's image dir |

Constructor deps: `IL10N`, `IURLGenerator`. Pure presentation — no logic.

**Replication note:** mydash already has admin settings; we only need to ensure section ID, priority, and icon match the app's branding.

### 2.4 `lib/Settings/Admin/AdminSettings.php`

Implements `OCP\Settings\ISettings`. The actual admin form page builder.

**Constructor deps:** `IConfig`, `IL10N`, `IGroupManager`, `IUserSession`, `IInitialState`, `OCP\Dashboard\IManager` (the official Nextcloud Dashboard registry — used to enumerate the system's `IWidget` instances so admins can pick from existing Dashboard widgets too).

**Methods:**

- `getForm(): TemplateResponse`
  - Side effects: `Util::addScript('sendentworkspace', 'admin')`, `addStyle('sendentworkspace', 'admin')`, `addStyle('sendentworkspace', 'workspace')` — loads both the admin bundle and the workspace stylesheet (so admin previews can render real widgets).
  - Builds four pieces of initial state:
    - **`allGroups`** — every Nextcloud group (from `IGroupManager::search('')`), shape `[{id, displayName}, …]`.
    - **`configuredGroups`** — JSON-decoded value of app config key `group_order` (default `'[]'`). Persists ordering & opt-in selection of which groups have a workspace.
    - **`widgets`** — every `IWidget` registered with the official Dashboard manager, shape `[{id, title, iconClass, iconUrl, url}, …]` (`iconUrl` only present if the widget implements `IIconWidget`). This is the catalogue that surfaces *Nextcloud Dashboard* widgets inside the workspace.
    - **`allowUserDashboards`** — boolean from app config `allow_user_dashboards` (default `'0'`).
  - Returns `TemplateResponse('sendentworkspace', 'admin', [])` — empty params, all data flows via initial state.
- `getSection()` → `'sendentworkspace'`
- `getPriority()` → `10` (top of section)

**App config keys used (admin scope):**

| Key | Default | Type | Set by |
|---|---|---|---|
| `group_order` | `'[]'` | JSON list of group IDs | (admin UI calls `updateGroups`) |
| `allow_user_dashboards` | `'0'` | `'0'` / `'1'` | (admin UI calls `updateSettings`) |
| `dashboards_{groupId}` | `''` | JSON object (see schema below) | service auto-saves on edit |
| `layout_{groupId}` | `''` | **legacy** JSON | only read during lazy migration |

**Implication for replica:** the admin page leans hard on initial-state injection (no fetch on mount). Three concerns are bundled in one page: group selection/order, app-wide settings (allow_user_dashboards), and a *preview* of dashboards per group (since workspace styles are also loaded). We may want to split these into tabs.

### 2.5 `lib/Service/WorkspaceService.php` — the persistence model

A single service that owns all reads/writes through `IConfig`. **No database tables are created** — everything lives in the `appconfig` (admin/group scope) and `preferences` (per-user scope) tables that Nextcloud exposes through `IConfig`. The constructor injects `IDBConnection` but it is **never used** in the file (likely placeholder for future migration).

**Versioned data shape (app config `dashboards_{groupId}`):**

```jsonc
{
  "version": 2,
  "defaultDashboardId": "dash_<uniqid>",
  "dashboards": [
    {
      "id": "dash_<uniqid>",        // group dashboard prefix: dash_
      "name": "Default",
      "icon": "ViewDashboard",       // name from dashboardIcons.js registry
      "layout": [ /* WorkspaceWidget[] */ ],
      "createdAt": <unix-ts>,
      "updatedAt": <unix-ts>
    }
  ]
}
```

User dashboards (per-user pref `user_dashboards`) follow the same shape but **without `defaultDashboardId`** and IDs are prefixed `udash_`.

**Lazy migration on read.** `getGroupDashboards()` first tries the new key, and if absent reads the legacy `layout_{groupId}` key, wraps it as `[{id: dash_<uniqid>, name: 'Default', icon: 'ViewDashboard', layout, …}]`, **writes back the new format immediately**, and **deletes the old key**. So the legacy `getLayout` / `saveLayout` endpoints transparently transition any old install.

**Method catalogue:**

| Method | Scope | Behaviour |
|---|---|---|
| `getWorkspaceLayout(string $groupId): array` | legacy | Returns the layout array of the *default* dashboard (or first if no default) for a group. |
| `saveWorkspaceLayout(string $groupId, array $layout): void` | legacy | Overwrites the layout of the *default* dashboard, bumps `updatedAt`. |
| `getGroupDashboards(string $groupId): array` | group | Reads new format, falls back + auto-migrates legacy. Always returns `{version, defaultDashboardId, dashboards: [...]}`. |
| `saveGroupDashboards(string $groupId, array $dashboards): void` | group | Forces `version=2`, writes JSON, deletes old `layout_{groupId}` key. |
| `getGroupDashboard(string $groupId, string $dashboardId): ?array` | group | Linear search for one dashboard. |
| `createGroupDashboard(string $groupId, string $name, array $layout=[], string $icon='ViewDashboard'): array` | group | Generates `dash_<uniqid>`, appends, saves, returns new dashboard. |
| `saveGroupDashboard(string $groupId, string $dashboardId, array $updates): void` | group | Partial update — only `name`, `icon`, `layout` are honoured; `updatedAt` always bumped. |
| `deleteGroupDashboard(string $groupId, string $dashboardId): bool` | group | **Refuses to delete the last remaining dashboard** (returns `false`). If deleted dashboard was default, promotes first remaining. |
| `setDefaultDashboard(string $groupId, string $dashboardId): void` | group | Just updates `defaultDashboardId`. **No validation that the id exists.** |
| `getUserDashboards(string $userId): array` | user | Reads user pref, returns `{dashboards: []}` when absent. **No version field, no defaultDashboardId.** |
| `saveUserDashboards(string $userId, array $data): void` *(private)* | user | Plain JSON write. |
| `createUserDashboard(string $userId, string $name, array $layout=[], string $icon='ViewDashboard'): array` | user | Same shape as group create but id prefix `udash_`. |
| `updateUserDashboard(string $userId, string $dashboardId, array $updates): bool` | user | Partial update; returns `false` when id not found. |
| `deleteUserDashboard(string $userId, string $dashboardId): bool` | user | Removes; **no "last one" guard** (unlike group dashboards). |
| `getAllowUserDashboards(): bool` | app | Reads `allow_user_dashboards`. |
| `setAllowUserDashboards(bool $allow): void` | app | Writes `'1'`/`'0'`. |
| `getActiveDashboard(string $userId): string` | user | Reads pref `active_dashboard` (empty string when unset). |
| `setActiveDashboard(string $userId, string $dashboardId): void` | user | Plain write. **No validation.** |

**Invariants & gotchas worth replicating (or fixing) in mydash:**

1. **Auto-migration on first read** is elegant — old layouts never break, but the read path mutates state. We should keep this pattern but make it explicit (log a one-line info).
2. **Group dashboards have a "must keep one" guard; user dashboards don't.** Inconsistency; we should decide on one rule.
3. **`setDefaultDashboard` and `setActiveDashboard` accept any string** — no existence check. We should validate.
4. **No DB tables.** Everything is JSON in `appconfig`/`preferences`. This caps total size (Nextcloud's appconfig values are usually `LONGTEXT` so practical limits are in MB, but layouts grow unbounded if many widgets). For mydash with potentially many dashboards we may want a real `dashboards` table from day one.
5. **`uniqid()` is not collision-safe under load** — fine for personal dashboards, dodgy for multi-admin scenarios. Suggest a UUID v4.
6. **`updatedAt`/`createdAt`** are integer Unix timestamps (not ISO strings). Front-end must format.

### 2.6 `lib/Controller/WorkspaceController.php` — page renderer

A regular `Controller` (not OCSController), marked `#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]` so it stays out of the generated OpenAPI spec.

**Constructor deps:** `IInitialState`, `IManager` (Dashboard), `IConfig`, `?string $userId`, `WorkspaceService`, `IGroupManager`, `IUserSession`.

**Single endpoint:**

- `index(): TemplateResponse`
  - Attributes: `#[NoCSRFRequired]`, `#[NoAdminRequired]`, `#[FrontpageRoute(verb: 'GET', url: '/')]`.
  - Loads workspace bundle: `Util::addStyle('sendentworkspace', 'workspace')` + `addScript('sendentworkspace', 'main')`.
  - **Resolves which dashboard to render and what initial state to push.** This is the most behaviour-rich method in the whole backend.

**Resolution algorithm (the heart of group→user routing — worth replicating exactly):**

1. **Enumerate available Nextcloud-Dashboard widgets** (`IManager::getWidgets()`) → push as `widgets` initial-state, shape `[{id, title, iconClass, iconUrl, url}]` (`iconUrl` only if `IIconWidget`).
2. **Determine the user's "primary group"** for routing:
   - Read app config `group_order` (JSON list).
   - Fetch user's group IDs (`IGroupManager::getUserGroupIds`).
   - Walk `group_order` in order, pick the **first group the user is a member of**. If none match, primary group is the literal string `'default'`.
3. **Load that group's dashboards** via `service->getGroupDashboards($primaryGroup)` (which auto-migrates legacy data).
4. **Always fold in the `'default'` group's dashboards** (so workspace ships with a baseline). Any default dashboard that isn't already in the user's group is appended with extra field `source: 'default'` so the UI can label it. If the user's group has no `defaultDashboardId`, fall back to the default group's `defaultDashboardId`.
5. **Load user dashboards** if `allow_user_dashboards` is on AND user is logged in.
6. **Resolve "active" dashboard** by precedence:
   1. user's persisted `active_dashboard` pref → check user dashboards first, then group dashboards (closure `findGroupDash` searches both the matched group and the default group)
   2. group's `defaultDashboardId`
   3. first dashboard in the group's list
   - If preference points to a deleted/invalid id, it's silently reset to `''` and falls through.
7. **Eagerly call `$widget->load()` on every Nextcloud widget** so their JS callbacks register at page load (matches Nextcloud's own dashboard behaviour). Note: side effect with potentially heavy cost — every widget's `load()` runs even if it's not on screen.
8. **Resolve display name** for primary group via `IGroupManager::get($id)->getDisplayName()` (skipped for the literal `'default'`).

**Initial state pushed (10 keys — this is the contract the frontend reads on boot):**

| Key | Type | Notes |
|---|---|---|
| `widgets` | `Array<{id,title,iconClass,iconUrl,url}>` | Catalogue of system Dashboard widgets |
| `layout` | `Array<WorkspaceWidget>` | Layout of the active dashboard |
| `primaryGroup` | `string` | `'default'` or a real group ID |
| `primaryGroupName` | `string` | Display name for sidebar |
| `isAdmin` | `bool` | **Always pushed as `false` here.** Admin context comes from `AdminSettings` page only. |
| `activeDashboardId` | `string` | Resolved id (may be empty if no dashboards exist) |
| `dashboardSource` | `'group'` \| `'user'` | Where the active dashboard came from |
| `groupDashboards` | `Array<{id,name,icon, source?}>` | Sidebar list (group + folded default group) |
| `userDashboards` | `Array<{id,name,icon}>` | Empty if feature disabled or anon |
| `allowUserDashboards` | `bool` | Feature flag mirror |

**Template render:**
- `TemplateResponse('sendentworkspace', 'index', ['id-app-content' => '#app-workspace', 'id-app-navigation' => null])` — uses the standard NC template params to override which DOM ids the chrome wraps.

**Replication notes:**
- The `'default'` magical group ID is doing real work (synthetic key for app-level dashboards that everyone sees). Keep this convention or replace with a typed sentinel.
- Step 4 (folding default into user group) means **a dashboard appearing in two groups dedupes by id**, but two dashboards with the same name will both show — UX may want a label.
- Step 7's eager `load()` could be lazy in mydash if widgets are heavy.

### 2.7 `lib/Controller/WorkspaceApiController.php` — OCS API surface

`OCSController` subclass. **No class-level `#[OpenAPI]` annotation** so endpoints are included in the generated spec.

**Constructor deps:** `IManager` (Dashboard), `IConfig`, `?string $userId`, `WorkspaceService`, `IGroupManager`, `IRootFolder` (for `createFile`), `IAppData` (for resource storage).

#### Cross-cutting conventions

| Concern | Pattern observed |
|---|---|
| Admin gating | `IGroupManager::isAdmin($this->userId)` → `403 {status:'error', message:'Unauthorized'}` |
| Validation | Inline `if`/`isset`/regex checks → `400 {status:'error', message:...}` |
| Errors | All endpoints wrap business calls in `try/catch \Exception` → `500 {status:'error', message: $e->getMessage()}` (leaks raw exception text — sanitise in mydash) |
| Success envelope | `{status: 'success', ...payload}` |
| Method attributes | `#[NoAdminRequired]` (where allowed) + `#[NoCSRFRequired]` + `#[ApiRoute(verb, url)]` |
| Group-id safety | Only validated for `saveLayout` via `isValidGroupId()` — most other endpoints accept any string. Inconsistent. |

> Every endpoint is `#[NoCSRFRequired]`. OCS endpoints get CSRF protection from the OCS framework via the `OCS-APIRequest` header — make sure we don't loosen this further in our replica.

#### Endpoint-by-endpoint behaviour

##### Layout (legacy)

- `getLayout(string $groupId = 'default'): DataResponse` — returns `{status, layout, groupId}`. Default param fills missing slug.
- `saveLayout(string $groupId, array $layout): DataResponse`
  - Admin check.
  - **`isValidGroupId()` validation** — `$groupId !== 'default'` AND length 1-64 AND matches `^[a-zA-Z0-9_\- ]+$`. Useful regex for our replica.
  - **Layout structure validation:** each item must be an array with `id, type, x, y, w, h`. Note: `content` is NOT required. Bad item → 400 with descriptive message.
  - Calls `service->saveWorkspaceLayout`.

##### Groups

- `getGroups(): DataResponse` — public; returns `{status, active: [...], inactive: [...]}` where `active` is `group_order` config and `inactive` is set difference vs all groups.
- `updateGroups(array $groups): DataResponse` — admin only; replaces `group_order` config wholesale (no validation that ids exist).

##### Multi-dashboard (group scope)

- `getGroupDashboards(string $groupId): DataResponse` — public; returns `{status, dashboards: [{id,name,icon,createdAt,updatedAt}], defaultDashboardId}`. **Layout NOT included** to keep response small. Frontend must call `getGroupDashboard` for the full layout.
- `getGroupDashboard(string $groupId, string $dashboardId): DataResponse` — public; full dashboard incl. layout. 404 if not found.
- `createGroupDashboard(string $groupId, string $name, array $layout = [], string $icon = 'ViewDashboard'): DataResponse` — admin only.
- `updateGroupDashboard(string $groupId, string $dashboardId, ?string $name, ?array $layout, ?string $icon): DataResponse` — admin only; partial update (only non-null fields applied).
- `deleteGroupDashboard(string $groupId, string $dashboardId): DataResponse` — admin only; surfaces service's "can't delete last dashboard" rule as 400.
- `setDefaultDashboard(string $groupId, string $dashboardId): DataResponse` — admin only; no existence check.

##### User dashboards

- `getUserDashboards(): DataResponse` — returns `{status, dashboards, allowUserDashboards}`. **Layouts ARE included** (unlike group endpoint — inconsistency).
- `createUserDashboard(string $name, array $layout = []): DataResponse` — gated on `allow_user_dashboards` feature flag.
- `updateUserDashboard(string $dashboardId, ?string $name, ?array $layout): DataResponse` — partial update; **no `icon` parameter** (only group dashboards support icon update via API).
- `deleteUserDashboard(string $dashboardId): DataResponse`.

##### Active dashboard

- `setActiveDashboard(string $dashboardId): DataResponse` — minimal; no validation.

##### Settings

- `getSettings(): DataResponse` — admin only; currently surfaces only `allowUserDashboards`.
- `updateSettings(bool $allow_user_dashboards): DataResponse` — admin only; flips the flag.

##### Widget items (proxy to Nextcloud Dashboard system)

- `getWidgetItems(array $sinceIds = [], int $limit = 7, array $widgets = []): DataResponse`
  - Builds a list of widget IDs to query from the `widgets` arg (private `getShownWidgets()` filter; **returns empty array if `$widgets` is empty** — caller must explicitly opt in).
  - For each widget:
    - If `IIconWidget`, capture `iconUrl` into `meta`.
    - **Try v2 API first** (`IAPIWidgetV2::getItemsV2($userId, $sinceId, $limit)->getItems()`), fallback to `IAPIWidget::getItems(...)`. Widgets without either API are silently skipped.
    - Map each `WidgetItem` to a flat array `{subtitle, title, link, iconUrl, overlayIconUrl, sinceId}`.
  - Returns `{items: {widgetId: WidgetItem[]}, meta: {widgetId: {iconUrl}}}`.
  - **Default `limit = 7`** — matches Nextcloud Dashboard convention.
  - This is what enables ApiWidget to pull from any Nextcloud Dashboard widget the admin has installed.

##### File creation

- `createFile(string $filename, string $dir = '/', string $content = ''): DataResponse`
  - **Filename validation:** non-empty, ≤255 chars, no `..` / `/` / `\` / null byte, must match `^[a-zA-Z0-9_\-. ]+$`. Quite strict (no parens, no `+`).
  - **Dir validation:** rejects `..` and null byte (does NOT regex-restrict; allows nested paths).
  - Resolves user folder via `IRootFolder::getUserFolder`; creates directory if missing.
  - **Behaviour:** if file exists, **overwrites** with `putContent` (not append, not fail). Document this.
  - Returns `{status, fileId, url}` where url uses `files.view.index?openfile={id}` — opens the file in the Files app viewer.
  - Used by widget actions like "create new note" or "open template".

##### Resource upload

- `uploadResource(): DataResponse` — admin only. **Reads raw `php://input` JSON** instead of typed parameters (because the body carries a base64 image, possibly several MB).
  - Expects `{base64: 'data:image/<type>;base64,...'}`.
  - **Allowed image types:** `jpeg, jpg, png, gif, svg, webp` (5 MB hard limit on decoded bytes).
  - **For raster images:** verifies actual content via `getimagesizefromstring`; cross-checks declared type vs detected MIME (e.g. a `.png` claiming to be JPEG is rejected).
  - **For SVG:** delegates to `sanitizeSvg()` — DOM-based whitelist (see below). SVG is normalised to extension `svg` regardless of `svg+xml`.
  - Storage: uses `IAppData->getFolder('resources')` (auto-creates), filename pattern `resource_<uniqid>.<ext>`. **App data, not user files.**
  - Returns `{url: '/apps/sendentworkspace/resource/<filename>'}`. Note: minimal envelope, no `status` key here (inconsistency with rest of API).

##### Resource listing & serving

- `listResources(): DataResponse` — public; returns `{resources: [{name, url}, ...]}`. Empty array if no folder yet.
- `getResource(string $filename): StreamResponse` — public, **non-OCS** (no admin or auth gate beyond Nextcloud session).
  - Reads file from `IAppData::getFolder('resources')`.
  - Streams via in-memory `php://memory` buffer (whole file loaded into RAM — bad for big files; capped by the 5 MB upload limit).
  - Sets `Content-Type` from extension whitelist (jpeg, png, gif, svg+xml, webp, default `application/octet-stream`).
  - **`Cache-Control: public, max-age=31536000`** — one year, immutable. Filenames are uniqid-suffixed so cache busting works by URL.
  - 404 returns an empty StreamResponse with status 404 (a touch unusual; could be a JsonResponse).

##### Private helpers

- `isValidGroupId(string $groupId): bool` — length 1-64 chars, regex `^[a-zA-Z0-9_\- ]+$`. **Allows spaces** in group ids.
- `sanitizeSvg(string): ?string` — DOM-based whitelist, returns null on parse failure or malicious content.
  - **Allowed elements (~24):** `svg, g, path, rect, circle, ellipse, line, polyline, polygon, text, tspan, defs, clippath, use, image, style, lineargradient, radialgradient, stop, mask, pattern, symbol, title, desc`. Notably **`script` and `foreignObject` are excluded** (the two main SVG-XSS vectors).
  - **Allowed attributes (~50):** geometry, styling, transform, gradient, href/xlink:href, etc. **All `on*` event-handler attributes are stripped** regardless of whitelist (defense-in-depth).
  - **URL filtering on `href`/`xlink:href`:** rejects values starting with `javascript:` or `data:` (preserves http/https/relative).
  - **Style filtering:** removes `style` attribute if it contains `expression(`, `javascript:`, or `url(data:`.
  - Recursive node walk; uses `LIBXML_NONET | LIBXML_NOENT` to disable network access and entity expansion (XXE protection).
- `sanitizeSvgNode(\DOMNode, array, array): void` — recursive worker; snapshots child list before mutation to handle removals safely.
- `getShownWidgets(array $widgetIds): array` — returns widgets whose IDs are in the request (empty input → empty output, **does not default to "all widgets"**).

#### Cross-cutting issues to address when replicating

1. **Inconsistent payload shape in resource endpoints** — `uploadResource` returns `{url}` while everywhere else uses `{status, ...}`. Standardise.
2. **`updateUserDashboard` lacks `icon` param** even though service supports it.
3. **`getGroupDashboards` strips layout, `getUserDashboards` includes it** — pick one.
4. **Raw exception messages leak** in error responses. Wrap.
5. **Admin checks repeated 11×** — extract a guard or attribute.
6. **CSRF posture**: `#[NoCSRFRequired]` everywhere because OCS handles it via header — make sure we don't loosen this.
7. **Resource serving has no per-user ACL** — anyone authenticated can fetch any resource by name. Acceptable for app-wide branding images, **risky if we ever store sensitive resources**. Document this.
8. **Resource storage has no MIME header on store, no checksum, no garbage collection** — uploaded resources accumulate forever, even when no widget references them. Plan a cleanup task.
9. **`sanitizeSvg` is good** but we already have `dompurify` on the frontend — consider a unified server-side SVG sanitiser library, or at minimum extract this into a service class.

### 2.8 `lib/ResponseDefinitions.php`

A psalm-only types holder. The class body is empty; the file's value is the docblock `@psalm-type` declarations:

- `WorkspaceWidget = { id: string, type: string, x: int, y: int, w: int, h: int, content?: mixed }`
- `WorkspaceLayout = { widgets: list<WorkspaceWidget> }`

**Observations:**

- `content` is `mixed` — every widget owns its own settings shape; backend doesn't validate it.
- Position fields (`x, y, w, h`) are **integers in grid units**, not pixels — confirms the Gridstack model (12-column grid by convention; we'll verify when we read `useGridManager.js`).
- This is the *only* declared shape — but we already saw richer structures in `WorkspaceService` (`{version, defaultDashboardId, dashboards: [...]}`). The author hasn't yet promoted those to psalm types. For our replica we should declare the full shape upfront and use it everywhere (response definitions feed the auto-generated OpenAPI).

---

## 3. Frontend (Vue 3, `src/`)

### 3.1 Entry points (`src/main.js`, `src/admin.js`)

Both entry files share the same skeleton: import gridstack CSS, install a Dashboard-widget intercept, mount a Vue app, and inject initial state via `provide()`. They diverge in **how aggressively they protect the OCA.Dashboard intercept** — `main.js` is paranoid, `admin.js` is simple.

#### Common pattern

```
createApp(<App>)
  → globalProperties.t = translate
  → loadState('sendentworkspace', '<key>', <default>)  ←  read injected initial state
  → app.provide('<key>', <value>)                       ←  expose to all descendants
  → app.mount('#<container-id>')
```

`@nextcloud/initial-state.loadState()` reads JSON the PHP layer pushed via `IInitialState::provideInitialState()`. The provide/inject pairs are the only state plumbing — there is **no Vuex/Pinia store**. Every component pulls what it needs via `inject()`.

#### Dashboard-widget intercept (heart of ApiWidget integration)

The Sendent app needs to render **Nextcloud's official Dashboard widgets** (Mail, Calendar, Talk, etc.) inside its own grid. Those widgets register themselves at runtime via `OCA.Dashboard.register(widgetId, callback)` and `OCA.Dashboard.registerStatus(widgetId, callback)`. The callbacks know how to render that widget into a given DOM element.

**`src/main.js` (workspace runtime — production-grade):**

- Builds a private `dashboardWidgetRegistry = { [widgetId]: { callback, statusCallback } }`.
- Wraps both `register` and `registerStatus` so any future call captures into the registry **and** still forwards to whatever the original function was.
- **Uses `Object.defineProperty` getter/setter traps on `OCA.Dashboard.register` and `OCA.Dashboard.registerStatus`** so that even if `@nextcloud/vue-dashboard` (or any other package) overwrites those properties later, the new function is captured into a closed-over `_currentRegister` / `_currentRegisterStatus` and the next read returns a freshly-wrapped function.
- **Even guards `window.OCA.Dashboard` itself** with a setter — if some script tries to replace the entire `OCA.Dashboard` object, the setter copies the new object's keys into the existing intercepted object, preserving `register`/`registerStatus` traps.
- Exposes the captured registry on `window._sendentDashboardRegistry` for `ApiWidget` to consume.

**`src/admin.js` (admin preview — simple):**

- Same registry shape, but **just monkey-patches `register`/`registerStatus` once**. Relies on the admin page not having other Dashboard libraries that overwrite later. Acceptable trade-off for an admin-only page, but **inconsistent** — if we want admin previews to be 100% accurate we should reuse the paranoid version.

#### Initial-state keys consumed

| Entry | Provided keys (defaults) |
|---|---|
| `main.js` | `widgets`, `layout`, `primaryGroup`, `isAdmin`, `activeDashboardId('')`, `dashboardSource('group')`, `groupDashboards([])`, `userDashboards([])`, `allowUserDashboards(false)`, `primaryGroupName('')` |
| `admin.js` | `allGroups`, `configuredGroups`, `widgets([])`, `allowUserDashboards(false)` |

#### Mount points

- `main.js` → `#workspace-vue` (defined in `templates/index.php`).
- `admin.js` → `#workspace-admin-vue` (defined in `templates/admin.php`).

#### Replication notes

- The Dashboard intercept is the **only way** to pull NC widgets into a non-`/dashboard` page. Reuse the paranoid pattern for both runtime and admin in mydash. Encapsulate it in a single helper module (`@/lib/dashboardRegistry.js`) so both entry files share it.
- `provide`/`inject` works fine for initial state but **is not reactive across writes** — once the workspace mutates `layout`, downstream components that `inject('layout')` see the original snapshot. Sendent works around this by passing `layout` as a `ref` from `setup()` of the shell. We can do the same OR introduce a Pinia store; the trade-off is more setup vs. simpler mental model. Recommend Pinia for mydash since we'll have more dashboards/widgets.

### 3.2 Shell apps

#### `src/WorkspaceApp.vue` — the runtime workspace (572 lines)

The user-facing dashboard page. Composition API.

**Template structure (top → bottom):**

| Region | Component / element | Visible when |
|---|---|---|
| Sidebar | `<DashboardSwitcher>` | `groupDashboards.length > 0` OR `userDashboards.length > 0` OR `allowUserDashboards` |
| Backdrop | `<div class="sidebar-backdrop">` | `sidebarOpen` |
| Hamburger toggle + active dash name | div with `<MenuIcon>` + label | sidebar visible |
| Admin toolbar | dropdown with widget-type buttons + Save | `canEdit` (admin OR viewing own user dashboard) |
| Grid container | `<div class="grid-stack">` with `<grid-stack-item>` per widget; renders `<component :is="getWidgetComponent(widget)">` per cell | always |
| Add-widget modal | `<AddWidgetModal>` | always (controlled by `showModal`) |
| Context menu | `<ContextMenu>` | `showContextMenu` |

**Provided injects (all reactive sources):**

- `widgets`, `layout` (initial), `primaryGroup`, `primaryGroupName`, `isAdmin`, `activeDashboardId`, `dashboardSource`, `groupDashboards`, `userDashboards`, `allowUserDashboards`.

**Local reactive state (refs):**

- `layout` — clone of injected layout, the live editable copy.
- `gridContainer` — DOM ref handed to gridstack.
- `saving`, `sidebarOpen`.
- `activeDashboardId`, `dashboardSource`, `groupDashboards`, `userDashboards` — local clones so writes don't leak through `inject`.

**Computed:**

- `canEdit = isAdmin || dashboardSource === 'user'` — **users can always edit their own dashboards** (admins can edit everything).
- `showSwitcher` — sidebar appears whenever there's more than the default to choose from OR when user dashboards are allowed.
- `activeDashName` — display name of currently active dashboard (lookup across both lists).

**Composable used:** `useGridManager({ layout, gridContainer, isAdmin: canEdit.value })` — returns the entire add/edit/remove modal+menu surface (see §3.3).

**Widget type → CSS class mapping (`getItemContentClass`):**

| `widget.type` | CSS class on `.grid-stack-item-content` |
|---|---|
| `'text'` | `widget-text` |
| `'image'` | `widget-image` |
| `'link'` | `widget-link` (special: transparent, no shadow — link buttons style themselves) |
| `'label'` | `widget-label` |
| `'widget'` | `widget-api` (the Nextcloud-Dashboard-proxy widget) |

> **Important:** the `widget` *type* (string) maps to the **ApiWidget** component. Naming collision with the umbrella term — in our replica we should rename this to `'nc-widget'` or `'dashboard-widget'`.

**Event handlers (the runtime API):**

| Handler | Trigger | Behaviour |
|---|---|---|
| `switchDashboard(dashboardId, source)` | DashboardSwitcher emits `@switch` | Loads layout via `getUserDashboards` (full payload) or `getGroupDashboard` (full payload). Persists `active_dashboard` pref via fire-and-forget `axios.post('/api/v1/active-dashboard', {dashboardId})`. Calls `reinitGrid(newLayout)` which destroys & rebuilds gridstack to swap layouts cleanly. |
| `createUserDashboard()` | DashboardSwitcher emits `@create-dashboard` | POST `/api/v1/user-dashboards` with `{name: 'My Dashboard', layout: <current layout>}` — **forks the currently visible layout** (great UX). Switches to the new dashboard and persists active-dashboard pref. Toasts success/error. |
| `deleteUserDashboard(id)` | DashboardSwitcher emits `@delete-dashboard` | DELETE `/api/v1/user-dashboards/{id}`; if active → switch to first group dashboard. |
| `saveLayout()` | toolbar Save button | PUT to either `/api/v1/dashboards/{groupId}/{dashboardId}` (group) or `/api/v1/user-dashboards/{dashboardId}` (user) with `{layout}`. Toasts result. |
| `openAddWidgetModal(type)` | toolbar dropdown items | preselects widget type and opens modal (delegates to composable). |
| `onWidgetRightClick(event, widget)` | grid item `@contextmenu.prevent` | opens custom ContextMenu at cursor. |

**Lifecycle:**

- `onMounted`: `nextTick → initGrid()` (composable spins up gridstack); `document.addEventListener('click', handleClickOutside)` for closing the dropdown.
- `onBeforeUnmount`: removes click listener; `destroyGrid()`.

**API endpoints called from this file:**

- GET `/api/v1/user-dashboards` (in `switchDashboard` for user source)
- GET `/api/v1/dashboards/{groupId}/{dashboardId}` (in `switchDashboard` and `editDashboard`)
- POST `/api/v1/user-dashboards`
- POST `/api/v1/active-dashboard`
- DELETE `/api/v1/user-dashboards/{dashboardId}`
- PUT `/api/v1/dashboards/{groupId}/{dashboardId}`
- PUT `/api/v1/user-dashboards/{dashboardId}`

**Styles (scoped SCSS, ~150 lines):**

- 20 px page padding.
- Sidebar backdrop fixed at `top: 50px` (Nextcloud header offset).
- Add-widget button uses `--color-primary`; Save uses `--color-success`.
- `widget-link` cells are transparent (link buttons own their background).
- All colors via `var(--color-*)` — fully NC-theme compatible.
- Scrollbar/grid container has `min-height: 500px`.

**Replication notes:**

- The "edit OR own user dashboard" rule for `canEdit` is **a key UX decision** — adopt it.
- Forking the current layout on user-dashboard create is the friendliest behaviour. Keep it.
- The fire-and-forget `active_dashboard` POST silently swallows errors — fine for a preference, but log it.
- **`reinitGrid` destroys and rebuilds gridstack on every dashboard switch** — simple and correct, but causes a flash. Mydash could keep the gridstack instance and just `removeAll() + addWidget()` for smoother transitions.

#### `src/AdminApp.vue` — the admin settings page (944 lines)

Three-state SPA inside the Nextcloud admin section, all in a single `<script>`:

```
viewState: 'groups' | 'dashboards' | 'editor'
```

**View 1 — Groups (`viewState === 'groups'`):**

- Two **vuedraggable** lists: **Active Groups (in priority order)** and **Inactive Groups**, with a `group="groups"` binding so items can be dragged between them.
- Filter input above each list (`activeFilter`, `inactiveFilter`) — `matchesFilter()` does a case-insensitive substring match on `displayName || id`.
- `@change="updateGroups"` on both fires `saveGroupOrder()` which POSTs `groups: activeIds[]` to `/api/v1/groups`. **Auto-saves on every drag.** Toast on success/error.
- Below the lists: a "Default Workspace" callout with an "Edit Default Dashboards" button that calls `openDashboards('default', t('Default'))`.
- A "Settings" block with one toggle: **Allow users to create their own dashboards** → POST `/api/v1/settings` with `{allow_user_dashboards: bool}`. Reverts the checkbox state on failure.

**View 2 — Dashboards (`viewState === 'dashboards'`):**

- Heading "Dashboards for {groupName}" + Back button → `viewState = 'groups'`.
- List of dashboards (`dashboards.value`) with:
  - Icon (component from `dashboardIcons.js` registry, **OR** `<img>` if `isCustomIconUrl(dash.icon)` — i.e. it's a `/apps/sendentworkspace/resource/...` URL from `uploadResource`).
  - Name.
  - "Default" badge when `dash.id === defaultDashboardId`.
  - Action buttons: **Set Default** (only if not already default), **Edit**, **Delete** (only if more than one dashboard — guards against losing the last one).
- "Create dashboard" row at bottom:
  - Name input (Enter submits).
  - **Icon picker:** `<select>` populated from `DASHBOARD_ICONS` constants OR an "Upload icon" file input that base64-encodes via `FileReader.readAsDataURL` then POSTs `/api/v1/upload-resource` and stores the returned URL in `newDashboardIcon`.
  - Live preview of the uploaded icon.
  - Submit calls `createDashboard()` → POST `/api/v1/dashboards/{groupId}` with `{name, icon}`.

**View 3 — Editor (`viewState === 'editor'`):**

- Header: Back button, dashboard name + (group label), Save button (`saveWorkspace`).
- `<WorkspaceEditor :group-id :initial-layout :widgets @layout-changed>` — full-screen editor (covered in §3.5).
- `toggleSectionConstraint(true/false)` adds/removes the `editor-active` CSS class on `#sendentworkspace-admin` so the admin section can be styled differently when in editor mode (probably to remove max-width constraints).
- Save: PUT `/api/v1/dashboards/{groupId}/{dashboardId}` with `{layout}`.

**API endpoints called from this file:**

- POST `/api/v1/groups` (group order)
- POST `/api/v1/settings` (allow_user_dashboards toggle)
- GET `/api/v1/dashboards/{groupId}` (open dashboard list for a group)
- POST `/api/v1/dashboards/{groupId}` (create dashboard)
- POST `/api/v1/dashboards/{groupId}/default` (set default)
- DELETE `/api/v1/dashboards/{groupId}/{dashboardId}` (delete)
- GET `/api/v1/dashboards/{groupId}/{dashboardId}` (load layout for editor)
- PUT `/api/v1/dashboards/{groupId}/{dashboardId}` (save edited layout)
- POST `/api/v1/upload-resource` (custom dashboard icon)

**Styles (scoped SCSS, ~430 lines):**

- Active groups container has `--color-primary` border (visual signal for "this is the active list").
- Default-dashboard row gets `--color-primary-element-light` background tint.
- Editor view uses `min-height: calc(100vh - 100px)` to take over the admin section.

**Replication notes:**

- The 3-state SPA inside one component is fine but bloated at 944 lines. Mydash should split into `<GroupsView>`, `<DashboardListView>`, `<EditorView>` (or use the router we already have).
- Auto-save on drag is the right UX for this kind of list — copy it.
- The custom icon upload flow (file → base64 → upload-resource → URL stored as `dash.icon`) is clever: `dash.icon` is **either** an icon-name string (`'ViewDashboard'`) **or** a resource URL. The discriminator is `isCustomIconUrl()` — replicate this pattern.

---

### 3.3 `src/composables/useGridManager.js` — gridstack wrapper + widget CRUD (245 lines)

A composable that owns **the gridstack instance plus the entire add/edit/delete-widget surface** (modal state, context menu state, click-outside handler). It's effectively the controller for the workspace; both `WorkspaceApp` and `WorkspaceEditor` consume it.

#### Module constant — widget type registry

```
WIDGET_TYPE_MAP = {
  text:   'TextDisplayWidget',
  image:  'ImageWidget',
  link:   'LinkButtonWidget',
  label:  'LabelWidget',
  widget: 'ApiWidget',         // Nextcloud-Dashboard proxy
}
```

> **5 widget types.** This is the canonical registry — the AddWidgetModal uses the same set. The string `'widget'` doubling as a type is a name collision (see §3.2 note).

#### Signature

```
useGridManager({ layout, gridContainer, isAdmin = false, onLayoutChanged = null })
  → { grid, showModal, showAddDropdown, preselectedType, showContextMenu,
      contextMenuX, contextMenuY, selectedWidget, editingWidgetData,
      initGrid, destroyGrid, getWidgetComponent, getWidgetProps,
      openAddWidgetModal, closeModal, handleWidgetSubmit,
      onWidgetRightClick, closeContextMenu, editWidget, removeWidget,
      handleClickOutside }
```

- `layout` is expected to be a `Ref<WorkspaceWidget[]>` — the composable mutates it directly (push, splice, in-place x/y/w/h updates).
- `gridContainer` is a `Ref<HTMLElement>`.
- `isAdmin` is **a static boolean read at setup time**, not a ref. **Bug pattern:** if `canEdit` flips after grid init, the grid's `staticGrid` won't update. WorkspaceApp passes `canEdit.value` (snapshot). Worth fixing in mydash.
- `onLayoutChanged` is the editor-mode hook (`@layout-changed` emit).

#### Gridstack configuration (the visual contract)

```
column: 12,
cellHeight: 60,                  // px per row
margin: 8,                       // px between cells
float: true,                     // widgets can sit anywhere, not just top-packed
animate: true,
staticGrid: !isAdmin,            // viewers cannot drag
acceptWidgets: isAdmin,          // admins can drag widgets in from outside
removable: false,                // no drag-to-trash zone
columnOpts: {
  breakpoints: [
    { w: 1400, c: 12 },          // ≥1400px → 12 cols
    { w: 1100, c: 8  },          // ≥1100px → 8 cols
    { w:  768, c: 4  },          // ≥768px  → 4 cols
    { w:  480, c: 1  },          // ≥480px  → 1 col (mobile)
  ],
  layout: 'moveScale',           // proportionally scale on column change
}
```

> **Important constants for replication:** 12-column, 60 px row height, 8 px margins, four responsive breakpoints (1400/1100/768/480), `moveScale` reflow.

#### Method behaviour

| Method | Behaviour |
|---|---|
| `initGrid(options = {})` | Idempotent: returns early if no container or grid already exists. Spreads custom `options` over defaults. **Wires a `'change'` listener that mutates `layout` items in place** (matched by `item.el.dataset.id` ↔ `widget.id`) and fires `notifyLayoutChanged()`. Listener only added when `isAdmin` is true. |
| `destroyGrid()` | `removeAll(false)` (don't remove DOM) → `destroy(false)` (don't remove DOM) → null the ref. The `false` flags are key: Vue owns the DOM. |
| `getWidgetComponent(widget)` | Lookup in `WIDGET_TYPE_MAP`; falls back to `'TextDisplayWidget'` for unknown types. |
| `getWidgetProps(widget)` | Spreads `widget.content` into props plus passes the whole `widget` as a `widget` prop. Means widget components receive their config as flat props (e.g. `imageUrl`, `caption`) AND have access to the full record. |
| `openAddWidgetModal(type)` | Sets `preselectedType` and opens modal; closes the dropdown if open. |
| `closeModal()` | Clears all modal state including `editingWidgetData`. |
| `moveCollidingWidgets(newW, newH)` | **Pre-emptively shifts existing widgets out of the way** before placing a new one at `(0, 0)`. Iterates `layout`, computes overlap with the rect `(0..newW, 0..newH)`, and if overlapping bumps `widget.y` down to `newH` (just below the new widget). Both updates the data and calls `grid.update(el, { y })`. **This is a naive algorithm** — it pushes everything to row `newH` regardless of how far down it really needs to go. Adequate for "drop top-left" UX but causes layout disruption. |
| `handleWidgetSubmit(widgetData)` | Two paths: **(a) editing** — when `editingWidgetData` set, mutate the existing widget in-place (type, w, h, content) and `grid.update()`; **(b) creating** — call `moveCollidingWidgets`, generate `id = 'widget_' + Date.now()`, push to layout, await `nextTick` for Vue render, then `grid.makeWidget(el)`. Defaults `w=2, h=2` when `widgetData.w/h` missing. Always closes modal and notifies. |
| `onWidgetRightClick(event, widget)` | Admin-only; sets context menu state at `event.clientX/Y`. |
| `closeContextMenu()` | Clears menu + selected widget. |
| `editWidget(widget)` | Closes context menu and opens the AddWidgetModal preloaded with `editingWidgetData = widget` and `preselectedType = widget.type`. |
| `removeWidget(widget)` | Splices from `layout`, calls `grid.removeWidget(el)`, fires `notifyLayoutChanged()`. |
| `handleClickOutside(event)` | Global listener: closes the add-widget dropdown when clicking outside `.add-widget-dropdown`; closes the context menu when clicking outside `.context-menu`. |

#### IDs

- Widget ID format: `'widget_' + Date.now()`.
- Collision risk: rapid double-clicks could produce duplicate IDs (Date.now is millisecond resolution). For a single-user editing experience this is fine; in multi-user we'd want UUIDs.

#### Replication notes

- Encapsulate gridstack into a similar composable in mydash (`useGridManager` is a clean abstraction — keep the shape).
- Replace `Date.now()` IDs with UUID v4 (matches the backend recommendation).
- Make `isAdmin`/`canEdit` a `Ref` so toggling edit mode at runtime works.
- Replace `moveCollidingWidgets` with gridstack's own auto-place (newer gridstack versions handle this — check `acceptWidgets` + `addWidget({ autoPosition: true })`).

### 3.4 `src/constants/dashboardIcons.js` — icon registry (51 lines)

The single source of truth for built-in dashboard icons.

**Exports:**

| Export | Type | Value |
|---|---|---|
| `DASHBOARD_ICONS` | `Record<string, VueComponent>` | 15 named entries (see below) |
| `DEFAULT_ICON` | `string` | `'ViewDashboard'` |
| `isCustomIconUrl(name)` | `(string?) → bool` | `name && (startsWith('/') OR startsWith('http'))` — the discriminator that tells icon-name from resource URL |
| `getIconComponent(name)` | `(string) → VueComponent\|null` | returns null for custom URLs (caller renders `<img>`); else returns the registered component or the default |

**The 15 built-in icons** (all from `vue-material-design-icons`, used as the picker `<option>` list in admin):

`ViewDashboard, Home, ChartBar, Cog, AccountGroup, Calendar, FileDocument, Bell, Star, Heart, BookOpenVariant, Lightbulb, RocketLaunch, Earth, Briefcase`.

**Replication notes:**

- The discriminator pattern (icon name OR URL stored in the same field) is elegant — keep it.
- 15 hardcoded icons is fine but limiting; consider a fuller MDI search picker for mydash (we already use MDI elsewhere).
- `getIconComponent` returns `null` for custom URLs — callers must check `isCustomIconUrl()` first to render `<img>` vs `<component :is>`. Slightly awkward; could return a `{ kind: 'component'|'url', value }` discriminated union.

### 3.5 Workspace UI components

Four components, in dependency order: `WorkspaceEditor` (host editor), `DashboardSwitcher` (sidebar), `AddWidgetModal` (the big config form), `ContextMenu` (tiny right-click menu).

#### `src/components/WorkspaceEditor.vue` — admin-mode editor (303 lines)

A nearly-complete duplicate of `WorkspaceApp`'s grid section, but **always in edit mode**. Used inside `AdminApp` view 3 (Editor). The grid lives here, not in AdminApp.

**Props:**

| Prop | Type | Required | Default |
|---|---|---|---|
| `groupId` | `String` | yes | — |
| `initialLayout` | `Array<WorkspaceWidget>` | no | `[]` |
| `widgets` | `Array<NCDashboardWidget>` | no | `[]` |

**Emits:** `layout-changed(layout)` — fired by gridstack on every drag/resize and by `useGridManager` on add/edit/remove.

**Behaviour:**

- Wraps `useGridManager({ layout, gridContainer, isAdmin: true, onLayoutChanged: emit })`.
- **Watches `props.initialLayout` deep** so when AdminApp loads a different dashboard for editing, the local `layout.value` resyncs (clones the new array).
- Toolbar duplicates the `WorkspaceApp` add-widget dropdown verbatim — same 5 type buttons, same icons.
- Renders `<component :is="getWidgetComponent(widget)" :is-admin="true">` so widgets always render their admin affordances.

**Why it exists separately from WorkspaceApp:** the runtime app handles dashboard switching, save buttons, sidebar; the editor is a pure surface. This separation is good — but two near-identical templates (50 lines each, almost word-for-word) is a smell. **Mydash should extract a single `<WidgetGrid>` component used by both** (props: `layout`, `isAdmin`, `widgets`; emits: `layout-changed`).

**Replication notes:**

- Adopt the deep `watch(initialLayout)` resync pattern.
- Consolidate the toolbar dropdown into a reusable `<AddWidgetDropdown>` component used by both editor and runtime.

#### `src/components/DashboardSwitcher.vue` — sidebar nav (346 lines)

The slide-in left sidebar that lists all dashboards available to the user, grouped into three sections.

**Props:**

| Prop | Type | Default |
|---|---|---|
| `isOpen` | `Boolean` | `false` |
| `groupName` | `String` | `''` (the matched group's display name) |
| `groupDashboards` | `Array<{id, name, icon, source?}>` | `[]` (combined matched + folded default) |
| `userDashboards` | `Array<{id, name, icon}>` | `[]` |
| `activeDashboardId` | `String` | `''` |
| `allowUserDashboards` | `Boolean` | `false` |

**Emits:** `switch(id, source)`, `create-dashboard`, `delete-dashboard(id)`, `update:open(bool)` — supports `v-model:open`.

**Sections rendered (top → bottom):**

1. **Matched group dashboards** — `groupDashboards.filter(d => d.source !== 'default')`. Section label = `groupName || 'Dashboards'`.
2. **Default group dashboards** — `groupDashboards.filter(d => d.source === 'default')`. Section label = `'Default'`. Click → emits `switch(id, 'default')` (forces source = 'default' regardless of what was on the dashboard record — ensures correct API path on switch).
3. **My Dashboards** — only when `userDashboards.length > 0` OR `allowUserDashboards`. Each item has a hover-revealed close button (delete). Includes a "+ New Dashboard" button at the bottom when `allowUserDashboards`.

**Visual / behavioural details:**

- Slide-in from the left edge using CSS `transform: translateX(-100%)` ↔ `translateX(0)` driven by `&.open` selector (250 ms ease).
- `top: 50px` to clear the Nextcloud header.
- Width: 280 px, z-index 1500 (above the grid, below the modal at 10000).
- Each `nav-item` displays icon + label; **icon resolution mirrors the AdminApp pattern** — `<img>` for custom URL, `<component :is="getIconComponent(icon)">` for named icon.
- Active item gets `--color-primary-element-light` background and primary-color icon tint.
- Delete button on user dashboards uses `display: none` until parent hover (CSS-only, no JS toggle).
- All dashboard selections close the sidebar (`emit('update:open', false)` before `emit('switch', ...)`).

**Replication notes:**

- The "matched + default + user" three-section structure is the right mental model — keep it.
- The `source` discriminator is **load-bearing**: it tells the runtime which API endpoint to hit (`/dashboards/{groupId}/...` vs `/user-dashboards/...`), and which `groupId` to use (`'default'` vs the user's matched group). Encode this as an enum in mydash, not a string field.
- Consider showing a "shared" badge on default-group items so users understand they can't edit them (currently no visual signal that those are read-only).

#### `src/components/AddWidgetModal.vue` — the configuration form (595 lines)

The single source of truth for **every widget's configurable settings**. Five sub-forms in one component (text, image, link, label, widget) plus type selector.

**Props:**

| Prop | Type | Default |
|---|---|---|
| `show` | `Boolean` | `false` |
| `widgets` | `Array<NCDashboardWidget>` | `[]` (catalogue passed to the Nextcloud-Widget form) |
| `preselectedType` | `String?` | `null` (skips type selector if set) |
| `editingWidget` | `Object?` | `null` (puts modal in edit mode and pre-loads form) |

**Emits:** `close`, `submit({type, content})`.

**State machine:**

- `show: false → true` triggers `resetForm()` and, if `editingWidget`, `loadEditingWidget()` (which spreads `editingWidget.content` over `form` via `Object.assign`).
- `editMode = !!editingWidget` — title becomes "Edit Widget" / button becomes "Save".
- `isFormValid` is per-type and validates only the **required minimum** (e.g. text requires `text`, link requires `label` AND `url`, widget requires `widgetId`).

**Configuration schema by widget type (this is the spec for each widget):**

##### Text widget (`type: 'text'`)
| Field | UI control | Default | Notes |
|---|---|---|---|
| `text` | `<textarea rows=4>` | `''` | **Required.** |
| `fontSize` | `<input type=text>` | `'14px'` | Free-form CSS value. |
| `color` | `<input type=color>` | `'#000000'` | |
| `backgroundColor` | (data only) | `''` | Set in form data but **no UI control** — dead field. |
| `textAlign` | (data only) | `'left'` | Same — dead field. |

##### Image widget (`type: 'image'`)
| Field | UI control | Default | Notes |
|---|---|---|---|
| `url` | `<input type=text>` + file upload | `''` | **Required.** Upload uses `uploadFile()` → POST `/api/v1/upload-resource` → returns URL string. |
| `alt` | `<input type=text>` | `''` | |
| `link` | `<input type=text>` | `''` | Optional click-through URL (image becomes clickable). |
| `fit` | `<select>` | `'cover'` | Options: `cover`, `contain`, `fill`. |

##### Link button widget (`type: 'link'`)
| Field | UI control | Default | Notes |
|---|---|---|---|
| `label` | `<input type=text>` | `''` | **Required** — button text. |
| `url` | `<input type=text>` | `''` | **Required** — meaning depends on `actionType`. |
| `actionType` | `<select>` | `'external'` | Options: `external` (open URL in new tab), `internal` (some app-internal path), `createFile` (in this case `url` field is treated as a **file extension** — placeholder shows `docx`). |
| `icon` | file upload | `''` | Optional; uploaded via same flow as image. |
| `backgroundColor` | `<input type=color>` | (none) | |
| `textColor` | `<input type=color>` | `'#ffffff'` | |

##### Label widget (`type: 'label'`)
| Field | UI control | Default | Notes |
|---|---|---|---|
| `text` | `<input type=text>` | `''` | **Required** — single-line. |
| `fontSize` | `<input type=text>` | `'16px'` | Free-form CSS. |
| `color` | `<input type=color>` | `'#000000'` | |
| `backgroundColor` | `<input type=color>` | (none) | |
| `fontWeight` | (data only) | `'normal'` | Dead field — collected but no UI. |
| `textAlign` | (data only) | `'left'` | Dead field. |

##### Nextcloud Widget proxy (`type: 'widget'`)
| Field | UI control | Default | Notes |
|---|---|---|---|
| `widgetId` | `<select>` from `widgets` prop | `''` | **Required.** Dropdown of all NC Dashboard widgets. |
| `displayMode` | `<select>` | `'vertical'` | `vertical` (list) vs `horizontal` (cards). |

**Image/icon upload pipeline (`uploadFile(file)`):**

1. `FileReader.readAsDataURL(file)` → `data:image/...;base64,...`.
2. POST `/api/v1/upload-resource` with `{base64: dataUrl}`.
3. Server returns `{url: '/apps/sendentworkspace/resource/<filename>'}`.
4. URL stored in `form.url` (image) or `form.icon` (link).
5. **Errors** surface as toast for image (`uploadError` ref) but are silently logged for icon (UX inconsistency — fix in mydash).

**`submit()` method:**

Builds a typed `content` object from the relevant subset of `form` fields per type, then `emit('submit', {type, content})`. Critically, it strips fields that don't apply to the current type — keeps the persisted shape clean.

**Replication notes:**

- The dead-field problem (`backgroundColor`/`textAlign` for text, `fontWeight`/`textAlign` for label) means the form *collects* values but never *renders* a control for them. Either expose the controls or strip the fields. Easy fix.
- Per-widget config schemas need to drive a typed object (e.g. `WidgetContent = TextContent | ImageContent | …`). Mydash should declare these as TypeScript interfaces in `src/types/widgets.ts`.
- The whole modal should be split into per-type subcomponents (`<WidgetForm.Text>`, `<WidgetForm.Image>`, etc.) — 595 lines is unwieldy.
- Validation is per-type and can be moved into a per-widget `validate(content): string[]` function for reuse.

#### `src/components/ContextMenu.vue` — minimal right-click menu (91 lines)

Three buttons (Edit / Remove / Cancel) absolutely-positioned at `(x, y)`. No animation, no submenu, no auto-close on outside click (relies on `useGridManager.handleClickOutside` registered globally on document).

**Props:** `show: Bool`, `x: Number`, `y: Number`, `widget: Object?`. **Emits:** `edit(widget)`, `remove(widget)`, `close`.

**Replication notes:**

- Keep the same minimal shape. Possibly add: "Duplicate", "Bring to front" (if z-index ever matters), keyboard nav (Up/Down/Enter/Esc).

### 3.6 Widgets

Five widget components, ranging from 74 to 371 lines. All take `widget` as a required prop (the full record from the layout) plus the same fields flattened (because `useGridManager.getWidgetProps` does `{widget, ...widget.content}`). Pattern: `prop || widget.content[prop] || default` — so widgets work whether props are passed flat or only via `widget.content`.

#### `src/components/TextDisplayWidget.vue` (81 lines) — multi-line text

**Purpose:** display arbitrary text content, with HTML allowed (sanitised).

**Props:** `widget` (Object, required), `text: ''`, `fontSize: '14px'`, `color: ''`, `backgroundColor: ''`, `textAlign: 'left'`.

**Behaviour:**

- Renders `v-html="sanitizedContent"` — that is, **the text widget supports HTML**, sanitised via `DOMPurify.sanitize(content)`. This means users can use `<b>`, `<a>`, etc.; scripts are stripped.
- Empty content → italic placeholder "No text content".
- `textStyle` computed: `fontSize`, `color` (defaults to `var(--color-main-text)`), `backgroundColor` (defaults to transparent), `textAlign`.

**Container layout:** `display: flex; align-items: center; justify-content: center; padding: 16px; overflow: auto`. Centred both axes, scrolls on overflow.

**Replication notes:** keep DOMPurify; consider exposing `textAlign`, `backgroundColor` as form controls (currently dead in AddWidgetModal — see §3.5).

#### `src/components/LabelWidget.vue` (74 lines) — single-line label

**Purpose:** short bold heading, intended as section markers in a layout.

**Props:** `widget`, `text: ''`, `fontSize: '16px'`, `color: ''`, `backgroundColor: ''`, `fontWeight: 'bold'`, `textAlign: 'center'`.

**Behaviour:**

- Single `<span>` inside a flex-centred container. **No HTML**, plain text only.
- Defaults: bold, centre-aligned, 16 px, transparent background.
- Empty → renders the literal string `'Label'` (translated via `t()`).

**Replication notes:** `fontWeight` and `textAlign` are accepted as props but no AddWidgetModal control exists — same dead-field issue as TextDisplay.

#### `src/components/ImageWidget.vue` (111 lines) — image with optional click-through

**Purpose:** show an image (uploaded or URL), optionally make it a link.

**Props:** `widget`, `url: ''`, `alt: ''`, `link: ''`, `fit: 'cover'` (validator restricts to `cover|contain|fill|none`).

**Behaviour:**

- If `url` is empty, renders a placeholder (camera icon + "No image").
- The container is **always clickable** (cursor: pointer) — `handleClick()` opens `linkUrl` in `_blank` with `noopener,noreferrer` if set, otherwise no-op.
- `imageStyle` only sets `objectFit` from `fit` prop (default `'cover'`). Sizes follow the grid cell.
- `altText` defaults to translated 'Image' if absent.

**Replication notes:**

- The validator on `fit` is good practice — adopt for all enum-like props.
- `cursor: pointer` is set even when `linkUrl` is empty — mildly misleading. Conditional cursor in mydash.
- No image error handler — broken URLs show a busted image. Add a fallback.

#### `src/components/LinkButtonWidget.vue` (371 lines) — actionable button

**Purpose:** styled button that performs one of three actions: external link, internal function, or **create file**.

**Props:** `widget`, `label`, `url`, `icon`, `actionType: 'external'`, `backgroundColor`, `textColor`, `isAdmin: false` (suppresses click handlers in admin preview mode).

**State:** `showDocModal`, `docName`, `docType: 'docx'`, `creatingDoc`, `isExecuting`.

**`actionType` semantics:**

| Value | `url` field interpretation | Click behaviour |
|---|---|---|
| `external` | absolute URL | `window.open(url, '_blank', 'noopener,noreferrer')` |
| `internal` | (currently unused — placeholder) | `executeInternalFunction()` — hardcoded demo: creates `example.txt` via the create-file API |
| `createFile` | a **file extension** (e.g. `'docx'`) | Opens `<DocModal>`, prompts for filename, posts `/api/v1/create-file`, opens result URL |

**Heuristic auto-detection:** `isDocumentAction` is true if `actionType === 'createFile'` OR if `linkUrl.toLowerCase()` is one of `['docx', 'odt', 'xlsx', 'txt']`. **So the widget can detect document creation even without an explicit `actionType`** — fragile but pragmatic.

**Icon resolution (`iconUrl` computed):**

- Custom URL (`http*` or `/`-prefixed): use as-is.
- Bare name: prefix with `/apps/sendentworkspace/img/`.
- Empty: no icon, label only.

**Document-creation flow:**

1. Click → `openDocModal()` derives `docType` from `linkUrl.replace('.', '').toLowerCase()` and pre-fills `docName = 'document_<timestamp>'`.
2. User edits filename; extension shown as suffix.
3. Submit → `confirmCreateDocument()` → `createFileAndOpen(filename, '/', '')`.
4. POST `/api/v1/create-file` with `{filename, dir, content}`.
5. On success, `window.open(response.data.ocs.data.url, '_blank')` — opens the file in the Files app viewer.

**Visual:**

- Flex column layout (icon over label), `--color-primary` background and `--color-primary-text` text by default, full-cell sized.
- Hover effect: `translateY(-2px)` + shadow.
- Document modal: 400 px min-width, separate filename input + read-only `.{extension}` suffix display.
- Disabled state during `isExecuting` or `creatingDoc`.

**Replication notes:**

- The `internal` action type is essentially unimplemented (just calls a hardcoded demo). Either flesh it out into a registry of named actions (e.g. `'open-talk'`, `'new-event'`) or remove it.
- The auto-detect heuristic for `createFile` is too clever — make `actionType` mandatory and explicit.
- The hardcoded extension list (`docx, odt, xlsx, txt`) should be configurable per-app or per-user (e.g. add Markdown, CSV).
- Document modal is duplicated UI — could reuse the AddWidgetModal pattern.
- **Security:** `_blank` + `noopener,noreferrer` for external — good. But `createFile` opens whatever URL the server returns without verification — fine here because backend constructs it via `URLGenerator`, but document this assumption.

#### `src/components/ApiWidget.vue` (332 lines) — Nextcloud Dashboard widget proxy

**Purpose:** render any Nextcloud Dashboard widget (Mail, Calendar, Talk, …) inside the workspace grid. The most architecturally interesting widget.

**Props:** `widget`, `widgetId`, `displayMode: 'vertical'`.

**Module constants:**

```
CALLBACK_POLL_INTERVAL = 200   // ms
CALLBACK_MAX_RETRIES   = 15    // → 3 s total wait
```

**Two-mode rendering strategy:**

1. **Native callback** (preferred) — when the target widget has registered `OCA.Dashboard.register(widgetId, callback)`, ApiWidget exposes a `<div ref="appContainer">` and calls `callback(appContainer)`. The widget then renders itself inside our cell, complete with its own UI, click handlers, etc. This gives **full feature parity** with the official `/dashboard` page.
2. **API list fallback** — when no callback is registered (or hasn't loaded yet), ApiWidget calls `GET /api/v1/widget-items?widgets[]=<id>&limit=7` and renders the returned items as a flat list of clickable `<a>` cards. Item shape: `{title, subtitle, link, iconUrl, overlayIconUrl, sinceId}`.

**Lifecycle:**

- `mounted()`: try the callback once; if not registered, **start API loading immediately** (don't wait), AND start a **3-second poll** (15 retries × 200 ms) for the callback to register late. If the callback shows up while items are loading, switch to native mode mid-flight.
- `beforeUnmount()`: clear the poll timer.

**Initial-state safety:** `availableWidgets` is normalised: PHP `json_encode` may serialise as `{0: …, 1: …}` if keys are non-sequential, so `Array.isArray(injected) ? injected : Object.values(injected)`. Defensive — keep this idiom for any PHP→JSON list.

**Header rendering:**

- `widgetIconUrl` (from `IIconWidget`) → `<img>`.
- Else `widgetIconClass` → `<span :class="...">` (CSS-driven background-image).
- Title: `widgetMeta.title || widgetId || 'Widget'` (translated fallback).

**Item rendering (`displayMode`):**

| Mode | Layout |
|---|---|
| `vertical` | flex-column list, 32 px square icon left, title + subtitle right; ellipsis overflow. |
| `horizontal` | flex-row wrap, 120 px square cards, 44 px icon top, centred title + subtitle below. |

**Interaction:** items are `<a target="_blank" rel="noopener noreferrer">`. **No infinite scroll, no "load more"** despite the API supporting `sinceIds` pagination.

**Replication notes:**

- This is the killer feature — replicate it carefully. Start with native-callback mode; the API fallback is also valuable when (e.g.) the widget bundle hasn't loaded.
- Increase `CALLBACK_MAX_RETRIES` or switch to `IntersectionObserver` (only poll when widget enters viewport).
- Add pagination ("Load more" using `sinceIds`).
- Show the widget URL (`widget.url`) as a "View all" link in the header (the metadata is already there).
- The `displayMode: 'horizontal'` cards are 120 px wide regardless of cell width — won't wrap nicely in narrow cells. Make widths fluid.
- Consider a third mode: `compact` (no header, no padding) for dense dashboards.

## 4. Templates & CSS

Both templates and global CSS are deliberately tiny — the heavy lifting happens in Vue.

### 4.1 `templates/index.php` (12 lines)

Loads workspace bundle and renders mount points:

- `style('sendentworkspace', 'workspace')` + `script('sendentworkspace', 'main')` — Nextcloud helpers that emit `<link>` / `<script>` for our `css/workspace.css` and `js/main.js`.
- Mounts a `<div id="app-workspace" class="sendentworkspace">` wrapping `<div id="workspace-vue">`.
- The outer `id="app-workspace"` matches the `'id-app-content' => '#app-workspace'` template param passed by the controller — this tells the Nextcloud chrome which DOM id to wrap as the main content area.
- `'id-app-navigation' => null` in controller → no left navigation slot allocated (Sendent uses its own slide-in DashboardSwitcher instead).

### 4.2 `templates/admin.php` (10 lines)

- Loads `admin` bundle (style + script).
- Renders `<div id="sendentworkspace-admin" class="section">` containing an `<h2>` translated heading "Sendent Workspace Configuration" and `<div id="workspace-admin-vue">` mount point.
- The outer id is what `AdminApp.toggleSectionConstraint()` toggles `editor-active` on (see §3.2 view 3).

### 4.3 `css/workspace.css` (40 lines) — runtime stylesheet

- `#app-workspace`: full-width, scrollable, momentum scrolling on iOS.
- `.grid-stack-item { touch-action: auto }` — restores default touch handling overridden by gridstack defaults.
- `.grid-stack-item-content { cursor: default; overflow: hidden }`.
- `.widget-wrapper`: 100%/100% sizing helper.
- `.gs-dragging .grid-stack-item, .gs-resizing .grid-stack-item { opacity: 0.8 }` — visual feedback during drag/resize (gridstack adds these classes to the body or container during gestures).

### 4.4 `css/admin.css` (19 lines) — admin section overrides

- `#sendentworkspace-admin { padding: 20px }`.
- **`#sendentworkspace-admin.editor-active { max-width: none !important; width: 100%; padding: 0 }`** — this is what `AdminApp.toggleSectionConstraint(true)` triggers when entering the editor view. It overrides the standard NC admin page max-width so the WorkspaceEditor can use the full viewport.
- `#workspace-admin-vue { min-height: 400px }`.

### 4.5 `img/`

- `app.svg` and `app-dark.svg` — navigation/admin section icons, automatically theme-switched by NC.
- `sendent-logo.png` — branding, not referenced from any template/script we walked (likely embedded in admin styling we haven't audited, or unused). **Worth confirming before replicating** — we don't need a Sendent logo in mydash.

### 4.6 `l10n/` (skim)

Standard Nextcloud `.json`/`.js` translation pairs per locale. Generated by NC tooling from `t()` calls in PHP/Vue. Nothing logic-bearing; mydash's i18n setup will regenerate these from our own `t()` calls.

---

## 5. API surface cross-reference

Every backend endpoint, the request/response shape, and the frontend file(s) that call it. Auth column: **A** = admin only (403 otherwise), **U** = any authenticated user, **G** = gated on `allow_user_dashboards`.

| # | Method + path (OCS prefix `/ocs/v2.php/apps/sendentworkspace`) | Request body / params | Response (success) | Auth | Caller (frontend) |
|---|---|---|---|---|---|
| 1 | GET `/` (frontpage) | — | HTML page (`index.php`) | U | (browser navigation) |
| 2 | GET `/resource/{filename}` | path: `filename` | StreamResponse, `Content-Type` from ext, `Cache-Control: public, max-age=31536000` | U | `<img :src="/apps/...">` from ImageWidget, LinkButtonWidget icon, AdminApp icon picker |
| 3 | GET `/api/v1/layout/{groupId}` | path: `groupId='default'` | `{status, layout, groupId}` | U | (legacy — no current caller in v1.0.0; kept for old clients) |
| 4 | POST `/api/v1/layout/{groupId}` | path: `groupId`; body: `{layout}` | `{status, message}` | A | (legacy — no current caller) |
| 5 | GET `/api/v1/groups` | — | `{status, active: [id…], inactive: [id…]}` | U | (no caller in v1.0.0 frontend; admin reads `allGroups` + `configuredGroups` via initial state) |
| 6 | POST `/api/v1/groups` | `{groups: [id…]}` | `{status, message}` | A | `AdminApp.saveGroupOrder()` (auto-fires on every drag) |
| 7 | GET `/api/v1/dashboards/{groupId}` | path: `groupId` | `{status, dashboards: [{id,name,icon,createdAt,updatedAt}], defaultDashboardId}` | U | `AdminApp.openDashboards()` |
| 8 | GET `/api/v1/dashboards/{groupId}/{dashboardId}` | paths | `{status, dashboard: {…full incl. layout}}` | U | `AdminApp.editDashboard()`, `WorkspaceApp.switchDashboard()` (group source) |
| 9 | POST `/api/v1/dashboards/{groupId}` | `{name, layout?, icon?='ViewDashboard'}` | `{status, dashboard}` | A | `AdminApp.createDashboard()` |
| 10 | PUT `/api/v1/dashboards/{groupId}/{dashboardId}` | `{name?, layout?, icon?}` | `{status, message}` | A | `AdminApp.saveWorkspace()`, `WorkspaceApp.saveLayout()` (group source) |
| 11 | DELETE `/api/v1/dashboards/{groupId}/{dashboardId}` | paths | `{status, message}` (400 if last dashboard) | A | `AdminApp.deleteDashboard()` |
| 12 | POST `/api/v1/dashboards/{groupId}/default` | `{dashboardId}` | `{status, message}` | A | `AdminApp.setDefaultDashboard()` |
| 13 | GET `/api/v1/user-dashboards` | — | `{status, dashboards: [{…incl. layout}], allowUserDashboards}` | U | `WorkspaceApp.switchDashboard()` (user source) |
| 14 | POST `/api/v1/user-dashboards` | `{name, layout?}` | `{status, dashboard}` | G | `WorkspaceApp.createUserDashboard()` |
| 15 | PUT `/api/v1/user-dashboards/{dashboardId}` | `{name?, layout?}` (no icon!) | `{status, message}` | U | `WorkspaceApp.saveLayout()` (user source) |
| 16 | DELETE `/api/v1/user-dashboards/{dashboardId}` | path | `{status, message}` | U | `WorkspaceApp.deleteUserDashboard()` |
| 17 | POST `/api/v1/active-dashboard` | `{dashboardId}` | `{status}` | U | `WorkspaceApp.switchDashboard()`, `createUserDashboard()` (fire-and-forget) |
| 18 | GET `/api/v1/settings` | — | `{status, allowUserDashboards}` | A | (no caller in v1.0.0 frontend; admin reads from initial state) |
| 19 | POST `/api/v1/settings` | `{allow_user_dashboards: bool}` | `{status, message}` | A | `AdminApp.toggleAllowUserDashboards()` |
| 20 | GET `/api/v1/widget-items` | query: `widgets[]`, `limit=7`, `sinceIds{}` | `{items: {wid: [{title,subtitle,link,iconUrl,overlayIconUrl,sinceId}]}, meta: {wid: {iconUrl}}}` | U | `ApiWidget.loadWidgetItems()` |
| 21 | POST `/api/v1/create-file` | `{filename, dir='/', content=''}` | `{status, fileId, url}` (url opens Files app) | U | `LinkButtonWidget.createFileAndOpen()` |
| 22 | POST `/api/v1/upload-resource` | raw JSON `{base64: 'data:image/<type>;base64,...'}` (≤5 MB) | `{url: '/apps/sendentworkspace/resource/<file>'}` | A | `AddWidgetModal.uploadFile()`, `AdminApp.handleDashboardIconUpload()` |
| 23 | GET `/api/v1/resources` | — | `{resources: [{name, url}]}` | U | (no caller in v1.0.0; future widget gallery?) |

**Endpoints declared but not called by any frontend in v1.0.0:** #3, #4, #5, #18, #23. These are surface area we may not need to replicate immediately.

**Frontend axios calls and their endpoints (reverse index):**

| Frontend file | Endpoints used |
|---|---|
| `WorkspaceApp.vue` | #8, #10, #13, #14, #15, #16, #17 |
| `AdminApp.vue` | #6, #7, #8, #9, #10, #11, #12, #19, #22 |
| `AddWidgetModal.vue` | #22 (image + icon uploads) |
| `LinkButtonWidget.vue` | #21 (createFile) |
| `ApiWidget.vue` | #20 (widget-items) |
| `WorkspaceEditor.vue` | (none — pure UI; AdminApp does the saves) |

**Initial-state keys (PHP → JS, not via axios):**

| Page | Key | From PHP | Read by JS |
|---|---|---|---|
| Workspace | `widgets, layout, primaryGroup, primaryGroupName, isAdmin, activeDashboardId, dashboardSource, groupDashboards, userDashboards, allowUserDashboards` | `WorkspaceController::index` | `main.js` → provides to all components |
| Admin | `allGroups, configuredGroups, widgets, allowUserDashboards` | `AdminSettings::getForm` | `admin.js` → provides to AdminApp |

**Persistence keys (`IConfig`):**

| Scope | Key | Type | Owner |
|---|---|---|---|
| App | `group_order` | JSON `string[]` | admin (group ordering) |
| App | `allow_user_dashboards` | `'0'`\|`'1'` | admin |
| App | `dashboards_{groupId}` | JSON `{version,defaultDashboardId,dashboards[]}` | service (auto-migrates from old key) |
| App | `layout_{groupId}` | **legacy** JSON | read-only fallback during migration |
| User | `user_dashboards` | JSON `{dashboards[]}` | service |
| User | `active_dashboard` | string (dashboard id) | service |

**App data (`IAppData`):**

- Folder `resources/` — uploaded image bytes (jpeg/png/gif/svg/webp), filenames `resource_<uniqid>.<ext>`. Served via `getResource` (#2).

---

## 6. Feature inventory (target for spec proposals)

A flat checklist of every distinct user-facing capability, mapped to its source files. Each row is a candidate for one spec proposal in `mydash/openspec/changes/replicate-sendent-workspace/`. Suggested spec slug in **bold**.

### A. Foundations

- [ ] **`workspace-grid`** — 12-column responsive gridstack layout (60 px row, 8 px margin, 4 breakpoints), drag/resize, viewer mode = static. → `useGridManager.js`, `WorkspaceApp.vue`, `WorkspaceEditor.vue`, `gridstack` dep.
- [ ] **`widget-runtime-contract`** — `WorkspaceWidget` data shape (`{id,type,x,y,w,h,content}`), `WIDGET_TYPE_MAP` registry, prop-flatten convention for widget components. → `useGridManager.js`, `ResponseDefinitions.php`.
- [ ] **`widget-add-edit-remove`** — toolbar dropdown, add-widget modal (per-type subforms), context menu on right-click, edit/remove flows, "move colliding" placement. → `AddWidgetModal.vue`, `ContextMenu.vue`, `useGridManager.js`.

### B. Persistence model

- [ ] **`group-dashboards`** — group-scoped multi-dashboard model, default-dashboard flag, "can't delete the last one" guard, lazy migration from legacy single-layout. → `WorkspaceService.php` (group methods), endpoints #7-#12.
- [ ] **`user-dashboards`** — per-user multi-dashboard model, fork-from-current-layout creation flow, gated by `allow_user_dashboards`. → `WorkspaceService.php` (user methods), endpoints #13-#16.
- [ ] **`active-dashboard-pref`** — per-user "remember last viewed" preference; resolution precedence in controller. → `WorkspaceService.php`, `WorkspaceController.php`, endpoint #17.
- [ ] **`group-routing`** — admin-configured `group_order`, controller picks user's "primary group" by walking the order, falls back to synthetic `'default'` group; folds in default-group dashboards under each user's view with `source` discriminator. → `WorkspaceController.php`, endpoints #5/#6.

### C. Admin UX

- [ ] **`admin-section`** — Nextcloud admin section + settings page registration, navigation entry. → `Section.php`, `AdminSettings.php`, `info.xml`, `templates/admin.php`.
- [ ] **`admin-group-management`** — two-list drag/drop with vuedraggable, filter inputs, auto-save on drag, "Default Workspace" callout, allow-user-dashboards toggle. → `AdminApp.vue` view 1, endpoints #6/#19.
- [ ] **`admin-dashboard-list`** — per-group dashboard CRUD UI, default badge, set-default action, delete guard, icon picker (built-in + custom upload). → `AdminApp.vue` view 2, `dashboardIcons.js`, endpoints #7/#9/#11/#12.
- [ ] **`admin-workspace-editor`** — full-screen editor that takes over the admin section (`editor-active` CSS toggle), live `@layout-changed` propagation, save button. → `AdminApp.vue` view 3, `WorkspaceEditor.vue`, endpoint #10, `css/admin.css`.

### D. Runtime UX

- [ ] **`runtime-shell`** — workspace page with toolbar, hamburger + active dashboard label, save button, edit-mode permission rule (`isAdmin || dashboardSource === 'user'`). → `WorkspaceApp.vue`, `templates/index.php`.
- [ ] **`dashboard-switcher-sidebar`** — slide-in sidebar with three sections (matched group / default / user), icons, active highlight, hover-reveal delete on user dashboards, "+ New Dashboard" affordance. → `DashboardSwitcher.vue`.
- [ ] **`dashboard-switching`** — runtime switch between group/default/user dashboards (separate fetch endpoints), grid destroy+rebuild, preference persistence. → `WorkspaceApp.switchDashboard`, endpoints #8/#13/#17.
- [ ] **`user-dashboard-create-delete`** — fork-current-layout into a user dashboard, delete with auto-fall-back to first group dashboard. → `WorkspaceApp.vue` (createUserDashboard, deleteUserDashboard), endpoints #14/#16.

### E. Widgets

- [ ] **`widget-text`** — multi-line text with HTML sanitised via DOMPurify; font-size, color, (background/text-align dead fields). → `TextDisplayWidget.vue`, AddWidgetModal text section.
- [ ] **`widget-label`** — single-line bold heading; font-size, color, background, (font-weight/text-align dead fields). → `LabelWidget.vue`, AddWidgetModal label section.
- [ ] **`widget-image`** — image from URL or upload, alt text, optional click-through link, object-fit (cover/contain/fill/none). → `ImageWidget.vue`, AddWidgetModal image section.
- [ ] **`widget-link-button`** — styled button; three action types (`external`, `internal`, `createFile`); document-creation modal with extension prefilled; icon upload; bg/text colors. → `LinkButtonWidget.vue`, AddWidgetModal link section, endpoint #21.
- [ ] **`widget-nc-dashboard-proxy`** — render any `OCA.Dashboard.register`-ed widget native or fall back to API list (`vertical|horizontal` modes); 3 s callback poll for late registrations; `widgets` initial state catalogue. → `ApiWidget.vue`, `main.js`/`admin.js` register intercept, endpoint #20.

### F. Resources

- [ ] **`resource-upload`** — base64 raw-input upload endpoint, 5 MB cap, MIME cross-check for raster, DOM-based SVG sanitiser (whitelist + on*-handler strip + javascript:/data: URL strip + style filtering). → `WorkspaceApiController::uploadResource`/`sanitizeSvg`, AddWidgetModal/AdminApp upload flows, endpoint #22.
- [ ] **`resource-serving`** — non-OCS StreamResponse, content-type from extension, immutable 1-year cache, app-data folder storage. → `WorkspaceApiController::getResource`, endpoint #2.
- [ ] **`custom-icon-pattern`** — `icon` field doubles as icon-name OR resource URL; `isCustomIconUrl()` discriminator; `<img>` vs `<component>` rendering. → `dashboardIcons.js`, used by `DashboardSwitcher`/`AdminApp`/`LinkButtonWidget`.

### G. Cross-cutting

- [ ] **`dashboard-icons-registry`** — 15 named MDI icons + `DEFAULT_ICON`; central source for picker + renderers. → `dashboardIcons.js`.
- [ ] **`oca-dashboard-intercept`** — intercept `OCA.Dashboard.register/registerStatus` at JS startup so any later-loaded NC widget callback is captured into `_sendentDashboardRegistry`. Two flavours: paranoid (runtime, `Object.defineProperty` traps) and simple (admin). → `main.js`, `admin.js`.
- [ ] **`initial-state-contract`** — exact set of keys pushed by each PHP page; provide/inject conventions on the JS side (no Pinia/Vuex). → `WorkspaceController.php`, `AdminSettings.php`, `main.js`, `admin.js`.

### H. Issues / improvements to bake into the replica from day one

These are conscious deviations we should make rather than copying as-is (referenced from notes throughout):

- [ ] Use UUID v4 for dashboard + widget IDs (not `uniqid()` / `Date.now()`).
- [ ] Validate `setDefaultDashboard` / `setActiveDashboard` against existing IDs.
- [ ] Symmetric "can't delete the last one" rule between group and user dashboards.
- [ ] Strip raw exception messages from error responses.
- [ ] Standardise success envelope across all endpoints (currently `uploadResource` differs).
- [ ] `getGroupDashboards` and `getUserDashboards` should both either include or exclude layout (pick one).
- [ ] Add `icon` parameter to `updateUserDashboard`.
- [ ] Reusable admin guard (attribute or trait) instead of repeating `isAdmin` check 11×.
- [ ] Per-user ACL on resource serving when used for non-public assets.
- [ ] Resource garbage collection (uploaded resources never get cleaned up).
- [ ] Replace dead form fields (`backgroundColor`/`textAlign` for text, etc.) with real controls or remove.
- [ ] Make `actionType` mandatory and explicit on link-button (drop the extension-string heuristic).
- [ ] Consider Pinia for layout state (current provide/inject + cloned refs is workable but verbose).
- [ ] Extract one `<WidgetGrid>` shared by runtime and editor.
- [ ] Split `AddWidgetModal` into per-type subforms.

> **Next step.** Pick a slice (or the whole list) to convert into spec proposals under `mydash/openspec/changes/replicate-sendent-workspace/` — most natural cut: **A → B → D → E → C** (widgets and runtime first because they're the demoable surface; admin can come later via initial-state shortcuts).
