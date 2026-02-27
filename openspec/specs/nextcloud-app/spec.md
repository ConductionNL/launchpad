# Nextcloud App Conventions

## Purpose
Defines the standard patterns and requirements for all Nextcloud apps in this workspace.

## Requirements

### Requirement: App Structure
Every Nextcloud app MUST follow the standard directory layout:
- `appinfo/info.xml` — App metadata
- `appinfo/routes.php` — Route definitions
- `lib/Controller/` — Request handlers
- `lib/Service/` — Business logic
- `lib/Db/` — Entities and Mappers (ORM)
- `lib/Migration/` — Database migrations

#### Scenario: New app created
- GIVEN a new Nextcloud app
- WHEN the app structure is created
- THEN it MUST contain `appinfo/info.xml` with valid metadata
- AND it MUST register routes in `appinfo/routes.php`
- AND controllers MUST extend `OCP\AppFramework\Controller` or `OCP\AppFramework\ApiController`

### Requirement: Dependency Injection
All services and controllers MUST use constructor injection via the Nextcloud DI container.

#### Scenario: Service needs database access
- GIVEN a service that needs to query the database
- WHEN the service is constructed
- THEN the Mapper MUST be injected via the constructor
- AND the service MUST NOT create Mapper instances directly

### Requirement: Route Ordering
Specific routes MUST be registered before wildcard/catch-all routes in `routes.php`.

#### Scenario: App has both specific and wildcard routes
- GIVEN routes like `/api/config` and `/api/{slug}`
- WHEN routes are registered
- THEN `/api/config` MUST appear before `/api/{slug}` in the routes array
- AND Apache MUST be restarted after route changes (`apache2ctl graceful`)

### Requirement: Configuration Storage
App configuration MUST use `OCP\IAppConfig` interface, NOT direct database queries.

#### Scenario: App stores a setting
- GIVEN an app needs to persist a configuration value
- WHEN the setting is stored
- THEN it MUST use `IAppConfig::setValueString()` or typed equivalents
- AND it MUST be retrievable via `IAppConfig::getValueString()`

### Requirement: Error Handling
Controllers MUST return proper HTTP status codes and JSON error responses.

#### Scenario: Resource not found
- GIVEN a request for a non-existent resource
- WHEN the controller handles the request
- THEN it MUST return HTTP 404
- AND the response body MUST be JSON with an `error` field

### Requirement: Page Layout
All apps MUST use the standard Nextcloud layout containers from `@nextcloud/vue` and follow one of the two official layout patterns.

**Reference:** [Nextcloud Layout Docs](https://docs.nextcloud.com/server/latest/developer_manual/design/layout.html)

#### Pattern 1: Navigation → Content → Sidebar
Used by: Files, Calendar, Deck, Tasks. Left panel is `NcAppNavigation`, center is `NcAppContent`, right is `NcAppSidebar` (closed by default). Sidebar opens on item selection to show details.

#### Pattern 2: Navigation → List → Content
Used by: Mail, Contacts. Left panel is `NcAppNavigation`, center is a list of entries, right shows selected entry content.

#### Scenario: App implements page layout
- GIVEN a new Nextcloud app with a frontend
- WHEN the layout is implemented
- THEN it MUST wrap the app in `NcContent` with the `app-name` prop set
- AND it MUST use `NcAppNavigation` for the left navigation panel
- AND it MUST use `NcAppContent` for the main content area
- AND it MUST use `NcAppSidebar` for item detail panels (if applicable)
- AND it MUST follow ONE layout pattern consistently across all views
- AND it MUST NOT override the responsive behavior of the Nextcloud layout components

#### Scenario: Mobile responsiveness
- GIVEN a Nextcloud app viewed on a mobile device
- WHEN the layout components render
- THEN content MUST display by default
- AND navigation MUST be accessible via a toggle icon
- AND sidebar (if used) MUST be accessible via a toggle
- AND the app MUST NOT implement custom responsive overrides that conflict with `@nextcloud/vue` behavior

### Requirement: User Settings Dialog
Every app MUST provide an in-app user settings dialog using the Nextcloud-native `NcAppSettingsDialog` component (NOT `NcDialog`). This mirrors how the Files app exposes "Instellingen voor bestanden" at the bottom of the sidebar.

**Reference:** [NcAppSettingsDialog](https://nextcloud-vue-components.netlify.app/#/Components/App%20containers/NcAppNavigation?id=ncappnavigationsettings)

#### Architecture

The user settings dialog consists of:
1. **Frontend**: `src/views/settings/UserSettings.vue` — uses `NcAppSettingsDialog` + `NcAppSettingsSection`
2. **Backend**: `getUserSettings()` / `updateUserSettings()` methods on the existing `SettingsController` + `SettingsService`
3. **Storage**: Per-user values via `OCP\IConfig::getUserValue()` / `setUserValue()` (stored in `oc_preferences` table)
4. **Navigation**: A "Configuration" item inside `NcAppNavigationSettings` in the sidebar

#### Component Pattern

The user settings dialog MUST use `NcAppSettingsDialog` (NOT `NcDialog` or a custom modal):

```vue
<NcAppSettingsDialog :open.sync="open" :show-navigation="true" :name="t('myapp', 'MyApp settings')">
  <NcAppSettingsSection id="section-id" :name="t('myapp', 'Section Name')">
    <template #icon><IconComponent :size="20" /></template>
    <!-- settings content: NcCheckboxRadioSwitch, NcTextField, etc. -->
  </NcAppSettingsSection>
</NcAppSettingsDialog>
```

Key props:
- `:open.sync` — Boolean, controls dialog visibility (synced with parent)
- `:show-navigation="true"` — Shows section navigation when multiple sections exist
- `:name` — Dialog title (localized)

Each `NcAppSettingsSection` has:
- `id` — Unique section identifier
- `:name` — Section heading (localized)
- `#icon` slot — Icon for the section navigation

For apps with no user settings yet, show a placeholder:
```vue
<NcAppSettingsSection id="general" :name="t('myapp', 'General')">
  <NcEmptyContent :name="t('myapp', 'No settings available yet')"
    :description="t('myapp', 'User settings will appear here in a future update.')">
    <template #icon><Cog :size="64" /></template>
  </NcEmptyContent>
</NcAppSettingsSection>
```

#### Backend Pattern

User settings MUST be served from the existing `SettingsController` (not a separate controller):

```php
// In SettingsService:
private const USER_SETTING_DEFAULTS = [
    'notify_assignments' => 'true',
    // add more settings as needed
];

public function getUserSettings(string $userId): array { /* IConfig::getUserValue() */ }
public function updateUserSettings(string $userId, array $data): array { /* IConfig::setUserValue() */ }

// In SettingsController:
/** @NoAdminRequired */
public function getUserSettings(): JSONResponse { /* delegates to service */ }
/** @NoAdminRequired */
public function updateUserSettings(): JSONResponse { /* delegates to service */ }
```

Routes:
```php
['name' => 'settings#getUserSettings', 'url' => '/api/user/settings', 'verb' => 'GET'],
['name' => 'settings#updateUserSettings', 'url' => '/api/user/settings', 'verb' => 'PUT'],
```

Both endpoints MUST have `@NoAdminRequired` annotation since every user accesses their own settings.

#### Navigation Integration

The settings button MUST use `NcAppNavigationSettings` in `MainMenu.vue`:
```vue
<NcAppNavigationSettings>
  <NcAppNavigationItem name="Admin Feature" @click="$emit('navigate', 'admin-route')" />
  <NcAppNavigationItem name="Configuration" @click="$emit('navigate', 'settings')" />
</NcAppNavigationSettings>
```

The `App.vue` intercepts the `'settings'` route to open the dialog instead of navigating:
```js
navigateTo(route, id = null) {
  if (route === 'settings') {
    this.showSettingsDialog = true
    return
  }
  // normal navigation...
}
```

#### Scenario: App has user settings
- GIVEN a Nextcloud app with per-user configurable settings
- WHEN the user clicks "Configuration" in the sidebar settings menu
- THEN an `NcAppSettingsDialog` MUST open (not `NcDialog` or a page navigation)
- AND user settings MUST be stored via `OCP\IConfig` (not `IAppConfig` which is for app-wide admin config)
- AND the GET/PUT endpoints MUST be `@NoAdminRequired`
- AND the settings MUST be served from the existing `SettingsController`

#### Scenario: App has no user settings yet
- GIVEN a new Nextcloud app that has no per-user settings defined yet
- WHEN the user clicks "Configuration" in the sidebar settings menu
- THEN an `NcAppSettingsDialog` MUST still open
- AND it MUST show an `NcEmptyContent` placeholder with "No settings available yet"
- AND the dialog MUST be ready for future settings sections to be added

### Requirement: Webpack Configuration
Apps using `@nextcloud/webpack-vue-config` MUST NOT replace the base plugins array.

#### Scenario: App adds custom webpack plugins
- GIVEN an app that needs additional webpack plugins (e.g., `VueLoaderPlugin`)
- WHEN the webpack config extends `@nextcloud/webpack-vue-config`
- THEN it MUST use `webpackConfig.plugins.push(...)` to ADD plugins
- AND it MUST NOT use `webpackConfig.plugins = [...]` which REPLACES all base plugins
- AND the base config provides `DefinePlugin` for `appName` and `appVersion` which `@nextcloud/vue` requires at runtime (missing these causes "missing-app-name" in `NcAppSettingsDialog`)

### Requirement: Admin Settings Page Components
Every app's admin settings page MUST use `CnSettingsSection` and `CnVersionInfoCard` from `@conduction/nextcloud-vue`.

#### Scenario: Admin settings page structure
- GIVEN an app with an admin settings page
- WHEN the settings page is rendered
- THEN the first section MUST be a `CnVersionInfoCard` showing app name and version
- AND each logical settings group MUST be wrapped in a `CnSettingsSection`
- AND the app MUST NOT use raw `NcSettingsSection` directly — use `CnSettingsSection` instead

#### Scenario: CnSettingsSection usage
- GIVEN a settings section that needs action buttons
- WHEN the section is rendered
- THEN action buttons MUST use the `#actions` slot (positioned top-right)
- AND loading state MUST use the `:loading` prop (shows centered spinner)
- AND error state MUST use the `:error` and `:error-message` props
- AND documentation links MUST use the `:doc-url` prop (renders info icon via NcSettingsSection)

#### Scenario: CnVersionInfoCard as first section
- GIVEN an admin settings page
- WHEN the page loads
- THEN the first section MUST be a `CnVersionInfoCard` with at minimum `app-name` and `app-version` props
- AND if the app supports configuration updates, it SHOULD set `:show-update-button="true"` and handle the `@update` event
- AND additional status items SHOULD use the `:additional-items` prop

**Reference implementations**: `openregister/src/views/settings/Settings.vue` (full example with CnVersionInfoCard + 10 CnSettingsSections), `pipelinq/src/views/settings/Settings.vue`, `procest/src/views/settings/AdminRoot.vue`
