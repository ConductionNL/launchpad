---
status: done
or-policy: reviewed-2026-06-01
---

# Admin Templates Specification

## Purpose

Admin templates allow Nextcloud administrators to create pre-configured dashboards that are automatically distributed to users based on group membership. When a user opens LaunchPad for the first time (or when a new template targets their group), the system creates a personal copy of the matching template. This copy is an independent dashboard that the user can modify within the limits of the inherited permission level. Templates enable organizations to provide standardized dashboard layouts with compulsory widgets while still allowing user customization where appropriate.

## Storage policy

Admin templates and admin settings (including template configuration) persist
in LaunchPad's **local tables**: `oc_launchpad_dashboards` (template rows with
`type = 'admin_template'`) and `oc_launchpad_admin_settings` (key-value admin
config). This is a deliberate architectural decision.

**Requirement: MUST NOT store admin templates in OpenRegister.**
LaunchPad MUST work standalone — on a fresh Nextcloud installation with no
OpenRegister present, every template feature MUST function identically.
Templates MUST NOT be written to, read from, or depend on any OR register,
schema, or object store.

**Rationale:** (1) The existing REQ-TMPL-001..017 capability is fully shipped
and database-backed — switching storage models would be a breaking change.
(2) `WHERE type='admin_template'` is a single indexed query; OR object
retrieval would require a REST round-trip per template. (3) LaunchPad supports
installations without a GroupFolder and therefore without the OR filesystem
convention. (4) ACL is already provided by the dashboard-sharing capability.
See the `D1 — Storage divergence` note at the bottom of this spec.

**Exports/imports** of templates are as JSON files using the
`FILENAME_PATTERN` safe-name regex enforced by `FileService`
(see `openspec/specs/resource-uploads/spec.md`).

## Data Model

Admin templates are stored as dashboards in `oc_launchpad_dashboards` with `type: "admin_template"`. Additional template-specific fields:
- **targetGroups**: JSON string of Nextcloud group IDs (e.g., `["marketing", "all-staff"]`), accessed via `getTargetGroupsArray()`/`setTargetGroupsArray()`
- **isDefault**: SMALLINT (0/1) flag -- if 1 (true), this template is distributed to all users regardless of group membership
- **permissionLevel**: One of `view_only`, `add_only`, `full` -- inherited by user copies
- **userId**: Set to null for admin templates (they are not owned by a specific user)
- **basedOnTemplate**: Not used for templates themselves; used on user copies to reference the template ID

Templates own their widget placements (in `oc_launchpad_widget_placements`) which serve as the blueprint for user copies. The template's placements include `isCompulsory` flags that are copied to user dashboards. When a user copy is created, `TemplateService::createDashboardFromTemplate()` clones all placements from the template to the new user dashboard.
## Requirements

@e2e exclude all scenarios test REST CRUD for admin template dashboards — template distribution and UI admin forms are not yet implemented in this version

### Requirement: Create Admin Template (REQ-TMPL-001)

Nextcloud administrators MUST be able to create dashboard templates for distribution to users.

#### Scenario: Create a template targeting specific groups
- GIVEN a Nextcloud admin user
- WHEN they send POST /api/admin/templates with body:
  ```json
  {
    "name": "Marketing Dashboard",
    "description": "Standard dashboard for the marketing team",
    "targetGroups": ["marketing", "communications"],
    "isDefault": false,
    "permissionLevel": "add_only"
  }
  ```
- THEN the system MUST create a dashboard with `type: "admin_template"`
- AND `userId` MUST be set to null (admin templates are not owned by a specific user)
- AND `gridColumns` MUST default to 12
- AND the response MUST return HTTP 201 with the full template object

#### Scenario: Create a default template for all users
- GIVEN a Nextcloud admin user
- WHEN they send POST /api/admin/templates with body:
  ```json
  {
    "name": "Company Dashboard",
    "isDefault": true,
    "permissionLevel": "view_only",
    "targetGroups": []
  }
  ```
- THEN the system MUST create a template with `isDefault: 1`
- AND any previously default template MUST have its `isDefault` set to 0 via `clearDefaultTemplates()`
- AND this template MUST be distributed to all users regardless of group membership

#### Scenario: Non-admin user cannot create templates
- GIVEN a regular (non-admin) Nextcloud user "alice"
- WHEN she sends POST /api/admin/templates
- THEN the system MUST return HTTP 403
- AND the template MUST NOT be created

#### Scenario: Create template with invalid permission level
- GIVEN a Nextcloud admin user
- WHEN they send POST /api/admin/templates with `permissionLevel: "super_admin"`
- THEN the system MUST return HTTP 400 with a validation error
- AND only `view_only`, `add_only`, and `full` MUST be accepted
- NOTE: Permission level validation is NOT currently implemented -- any string is accepted

#### Scenario: Create template with UUID generation
- GIVEN a Nextcloud admin user
- WHEN they create a new template
- THEN the system MUST assign a UUID v4 via `Ramsey\Uuid\Uuid::uuid4()` (unlike user dashboards which use a custom UUID generator in `DashboardFactory`)
- AND the UUID MUST be unique across all dashboards

### Requirement: List Admin Templates (REQ-TMPL-002)

Administrators MUST be able to view all existing templates with their configuration.

#### Scenario: List all templates
- GIVEN 3 admin templates exist: "Marketing Dashboard", "Company Dashboard" (default), "Engineering Dashboard"
- WHEN the admin sends GET /api/admin/templates
- THEN the system MUST return HTTP 200 with an array of all 3 templates
- AND each template MUST include: id, uuid, name, description, targetGroups, isDefault, permissionLevel, gridColumns, type, basedOnTemplate, isActive, createdAt, updatedAt

#### Scenario: Non-admin cannot list templates
- GIVEN a regular user "alice"
- WHEN she sends GET /api/admin/templates
- THEN the system MUST return HTTP 403

#### Scenario: Template list includes widget placement count
- GIVEN the "Marketing Dashboard" template has 6 widget placements
- WHEN the admin sends GET /api/admin/templates
- THEN the template object SHOULD include a widget_count field showing 6
- AND this helps admins understand the template's complexity at a glance
- NOTE: Widget count is NOT currently included in the list response

#### Scenario: Empty template list
- GIVEN no admin templates have been created
- WHEN the admin sends GET /api/admin/templates
- THEN the system MUST return HTTP 200 with an empty array

#### Scenario: Templates filtered from user dashboard list
- GIVEN 3 admin templates and 2 user dashboards exist
- WHEN user "alice" sends GET /api/dashboards
- THEN the response MUST contain only her user dashboards
- AND admin templates MUST NOT appear in the user's dashboard list

### Requirement: Update Admin Template (REQ-TMPL-003)

Administrators MUST be able to modify template configuration including name, description, target groups, permission level, and grid columns.

#### Scenario: Update template target groups
- GIVEN template id 1 targets groups ["marketing"]
- WHEN the admin sends PUT /api/admin/templates/1 with body `{"targetGroups": ["marketing", "sales"]}`
- THEN the system MUST update the targetGroups
- AND newly targeted users (in "sales") MUST receive the template on their next dashboard load
- AND existing user copies for "marketing" users MUST NOT be affected

#### Scenario: Update template permission level
- GIVEN template id 1 has `permissionLevel: "add_only"`
- WHEN the admin sends PUT /api/admin/templates/1 with body `{"permissionLevel": "full"}`
- THEN the template's permissionLevel MUST be updated to "full"
- AND existing user copies MUST inherit the new permission level at runtime because `PermissionService::getEffectivePermissionLevel()` dynamically resolves from the source template via `basedOnTemplate`
- NOTE: This means permission level changes DO propagate to existing copies. The resolution chain is: template's level -> dashboard's own level -> admin default.

#### Scenario: Update template widget layout
- GIVEN template id 1 has 4 widget placements
- WHEN the admin adds a new widget to the template and repositions existing ones
- THEN the template's placements MUST be updated
- AND existing user copies MUST NOT be affected (placement copies are independent after creation)

#### Scenario: Mark template as default
- GIVEN template id 1 is not the default and template id 2 is the default
- WHEN the admin sends PUT /api/admin/templates/1 with body `{"isDefault": true}`
- THEN template 1 MUST become the default
- AND template 2 MUST have `isDefault` set to 0 (false) -- enforced by `clearDefaultTemplates()` called before setting the new default

#### Scenario: Non-admin cannot update templates
- GIVEN template id 1 exists
- WHEN regular user "alice" sends PUT /api/admin/templates/1
- THEN the system MUST return HTTP 403

### Requirement: Delete Admin Template (REQ-TMPL-004)

Administrators MUST be able to delete templates, with proper cleanup of associated widget placements.

#### Scenario: Delete a template with no user copies
- GIVEN template id 1 has no user copies
- WHEN the admin sends DELETE /api/admin/templates/1
- THEN the system MUST delete the template
- AND all template widget placements MUST be cascade-deleted via `placementMapper->deleteByDashboardId()`
- AND the response MUST return HTTP 200

#### Scenario: Delete a template with existing user copies
- GIVEN template id 1 has been copied to 15 users
- WHEN the admin sends DELETE /api/admin/templates/1
- THEN the system MUST delete the template
- AND existing user copies MUST NOT be affected (they are independent dashboards)
- AND user copies with `basedOnTemplate: 1` will fall back to their own `permissionLevel` or admin default since the template no longer exists (caught by `DoesNotExistException` in `getEffectivePermissionLevel()`)

#### Scenario: Non-admin cannot delete templates
- GIVEN template id 1 exists
- WHEN regular user "alice" sends DELETE /api/admin/templates/1
- THEN the system MUST return HTTP 403

#### Scenario: Delete non-template dashboard via template endpoint
- GIVEN dashboard id 5 is a user dashboard (type: "user"), not an admin template
- WHEN the admin sends DELETE /api/admin/templates/5
- THEN the system MUST throw an exception indicating "Not an admin template"
- AND the dashboard MUST NOT be deleted

#### Scenario: Delete the default template
- GIVEN template id 1 is the default template (`isDefault: true`)
- WHEN the admin deletes template id 1
- THEN the system MUST delete the template
- AND no template MUST be the default afterward (this is allowed)
- AND new users without a group-targeted template will get no template on first access

### Requirement: Template Distribution on First Access (REQ-TMPL-005)

When a user accesses LaunchPad for the first time, the system MUST create personal copies of matching templates via the `DashboardResolver` chain.

#### Scenario: First-time user receives default template
- GIVEN a default template "Company Dashboard" exists with `isDefault: true` and 5 widget placements (3 compulsory)
- AND user "alice" has never opened LaunchPad
- WHEN alice navigates to LaunchPad (triggers GET /api/dashboard)
- THEN the system MUST create a personal dashboard for alice as a copy of the template
- AND the copy MUST have `type: "user"` and `userId: "alice"`
- AND the copy MUST inherit the template's permissionLevel
- AND the copy MUST include all 5 widget placements with their positions, sizes, and isCompulsory flags
- AND `basedOnTemplate` on the copy MUST reference the template's ID
- AND the copy MUST be set as alice's active dashboard

#### Scenario: First-time user receives group-targeted template
- GIVEN template "Marketing Dashboard" targets groups ["marketing"]
- AND user "bob" is a member of the "marketing" group
- AND bob has never opened LaunchPad
- WHEN bob navigates to LaunchPad
- THEN the system MUST create a personal copy of "Marketing Dashboard" for bob
- NOTE: `TemplateService::getApplicableTemplate()` returns only ONE template (a group-targeted template takes priority over the default; REQ-TMPL-021 says which one when several target the user's groups). Multiple template distribution is NOT implemented.

#### Scenario: First-time user not in any target group
- GIVEN template "Marketing Dashboard" targets groups ["marketing"]
- AND no default template exists
- AND user "carol" is only in the "engineering" group
- WHEN carol navigates to LaunchPad
- THEN the system MUST NOT create any dashboard for carol from the marketing template
- AND if `allowUserDashboards` is true, the system MUST create a default "My Dashboard" with recommendations and activity widgets

#### Scenario: Template already distributed to user
- GIVEN user "alice" already has a personal copy of template "Company Dashboard"
- WHEN alice navigates to LaunchPad again
- THEN the system MUST NOT create a duplicate copy
- AND `DashboardResolver::tryGetActiveDashboard()` MUST find her existing dashboard first

#### Scenario: Multiple templates match the user
- GIVEN templates "Company Dashboard" (default) and "Marketing Dashboard" (targets marketing group)
- AND user "alice" is in the "marketing" group
- WHEN alice navigates to LaunchPad for the first time
- THEN alice MUST receive a copy of "Marketing Dashboard" (group-targeted template takes priority over default)
- NOTE: Only ONE template per first-access. Group-targeted templates are evaluated first; the default template is the fallback.

### Requirement: Template Copy Independence (REQ-TMPL-006)

User copies of templates MUST be fully independent from the source template after creation, with the exception of permission level resolution.

#### Scenario: User modifies their template copy
- GIVEN user "alice" has a copy of "Marketing Dashboard" with `permissionLevel: "add_only"`
- WHEN she adds a new widget to her copy
- THEN the template MUST NOT be modified
- AND other users' copies MUST NOT be affected

#### Scenario: Admin updates template after distribution
- GIVEN template "Marketing Dashboard" has been copied to 10 users
- WHEN the admin adds a new widget to the template
- THEN existing user copies MUST NOT receive the new widget
- AND only new copies created after the change MUST include the new widget

#### Scenario: Admin deletes template after distribution
- GIVEN template "Marketing Dashboard" has been copied to user "alice"
- WHEN the admin deletes the template
- THEN alice's copy MUST continue to function normally
- AND alice's dashboard MUST retain all placements
- AND permission resolution MUST fall back to the dashboard's own `permissionLevel` (template lookup caught by `DoesNotExistException`)

### Requirement: Template Widget Management (REQ-TMPL-007)

Administrators MUST be able to manage widget placements on templates using the same API as regular dashboards.

#### Scenario: Add widget to template
- GIVEN template id 1 exists
- WHEN the admin sends POST /api/dashboard/1/widgets with widget data including `isCompulsory: 1`
- THEN the widget placement MUST be created on the template
- AND `isCompulsory` MUST be set to 1

#### Scenario: Remove widget from template
- GIVEN template id 1 has widget placement id 20
- WHEN the admin sends DELETE /api/widgets/20
- THEN the placement MUST be removed from the template
- AND existing user copies MUST NOT be affected

#### Scenario: Configure template grid layout
- GIVEN template id 1 exists
- WHEN the admin arranges widgets on the template via the grid
- THEN the positions MUST be saved as the template's widget placements
- AND new user copies MUST receive these exact positions

#### Scenario: Template placements include tile data
- GIVEN the admin adds a tile placement to template id 1 with inline tile data (tileTitle, tileIcon, etc.)
- WHEN the template is distributed to users
- THEN the tile placement MUST be cloned with all inline tile data via `clonePlacement()`
- AND the user copy MUST render the tile identically to the template

### Requirement: Only One Default Template (REQ-TMPL-008)

The system MUST enforce that at most one template is marked as the default at any time.

#### Scenario: Set a template as default when no default exists
- GIVEN no template has `isDefault: true`
- WHEN the admin creates or updates a template with `isDefault: true`
- THEN that template MUST become the default
- AND no other templates MUST be affected

#### Scenario: Set a template as default when another is already default
- GIVEN template "Company Dashboard" has `isDefault: true`
- WHEN the admin sets template "New Dashboard" as the default
- THEN "New Dashboard" MUST become the default
- AND "Company Dashboard" MUST have `isDefault` set to 0 (false)
- AND `clearDefaultTemplates()` MUST be called before setting the new default

#### Scenario: Remove default status from the only default template
- GIVEN template "Company Dashboard" has `isDefault: true`
- WHEN the admin sends PUT /api/admin/templates/1 with body `{"isDefault": false}`
- THEN the template MUST have `isDefault` set to 0 (false)
- AND no template MUST be the default (this is allowed)

### Requirement: Get Template with Placements (REQ-TMPL-009)

Administrators MUST be able to retrieve a specific template along with all its widget placements for editing.

#### Scenario: Get template with its placements
- GIVEN template id 1 has 6 widget placements
- WHEN the admin sends GET /api/admin/templates/1
- THEN the system MUST return the template object and an array of its 6 placements
- AND the response MUST include both the template entity and its placements as separate keys

#### Scenario: Get non-template dashboard via template endpoint
- GIVEN dashboard id 5 is a user dashboard (type: "user")
- WHEN the admin sends GET /api/admin/templates/5
- THEN the system MUST throw an exception indicating "Not an admin template"

#### Scenario: Get template with no placements
- GIVEN template id 2 exists but has no widget placements
- WHEN the admin sends GET /api/admin/templates/2
- THEN the system MUST return the template object with an empty placements array

### Requirement: Template Group Resolution (REQ-TMPL-010)

Template distribution MUST use Nextcloud's `IGroupManager` API to resolve user group memberships accurately.

#### Scenario: User added to a target group after template creation
- GIVEN template "Marketing Dashboard" targets groups ["marketing"]
- AND user "alice" was not in the "marketing" group when the template was created
- AND alice is later added to the "marketing" group
- WHEN alice opens LaunchPad for the first time
- THEN the system MUST distribute the "Marketing Dashboard" template to alice
- AND group membership MUST be checked at access time, not at template creation time

#### Scenario: User removed from a target group after receiving template
- GIVEN user "alice" received a copy of "Marketing Dashboard" while in the "marketing" group
- AND alice is later removed from the "marketing" group
- WHEN alice continues to use LaunchPad
- THEN alice's copy MUST continue to function normally
- AND the copy MUST NOT be deleted or revoked

#### Scenario: Template targets non-existent group
- GIVEN template "Test Dashboard" targets groups ["nonexistent-group"]
- WHEN any user opens LaunchPad
- THEN the template MUST NOT match any user (no user is in a non-existent group)
- AND the system MUST NOT throw errors during group resolution

### Requirement: Template Administration UI (REQ-TMPL-011)

The admin settings page MUST provide a UI for managing templates.

#### Scenario: Template list in admin settings
- GIVEN the admin opens the LaunchPad admin settings page
- THEN a template management section MUST be displayed
- AND all existing templates MUST be listed with their name, target groups, and default status

#### Scenario: Create template via admin UI
- GIVEN the admin clicks "Create Template" in the admin settings
- THEN a modal dialog MUST appear with fields for name, description, target groups, permission level, and default status
- AND the admin MUST be able to save the new template

#### Scenario: Group selection in template editor
- GIVEN the admin opens the template editor
- THEN a group selector MUST allow selecting from available Nextcloud groups
- NOTE: The current implementation uses `NcSelectTags` but `availableGroups` is hardcoded to an empty array. Groups are NOT fetched from the server.

### Requirement: Primary-group resolution for workspace routing (REQ-TMPL-012)

The system MUST expose a pure function `resolvePrimaryGroup(string $userId): string` that returns the Nextcloud group ID whose `group_shared` dashboards the user should see, OR the literal string `'default'` when no match is found. The algorithm MUST be:

1. Read the admin-configured ordered list of group IDs from `admin_settings.group_order` (JSON `string[]`, default `[]`).
2. Read the user's Nextcloud group memberships via `IGroupManager::getUserGroupIds($userId)`.
3. Walk `group_order` left-to-right and return the first group ID that also appears in the user's memberships.
4. If no match, return the literal string `'default'`.

The function MUST be deterministic and idempotent (no writes).

#### Scenario: First match wins by admin-configured priority

- GIVEN admin has set `group_order = ["engineering", "all-staff"]`
- AND user "alice" belongs to groups: `["all-staff", "engineering", "marketing"]`
- WHEN `resolvePrimaryGroup("alice")` is called
- THEN it MUST return `"engineering"` (because engineering appears first in group_order, even though all-staff is alphabetically earlier in alice's groups)

#### Scenario: User in no active group falls through to default sentinel

- GIVEN admin has set `group_order = ["engineering", "executives"]`
- AND user "carol" belongs only to groups: `["support"]`
- WHEN `resolvePrimaryGroup("carol")` is called
- THEN it MUST return `"default"`

#### Scenario: Empty group_order always returns default

- GIVEN admin has not configured any active groups (`group_order = []`)
- WHEN `resolvePrimaryGroup` is called for any user
- THEN it MUST return `"default"` regardless of the user's actual group memberships

#### Scenario: Configured group that the user is NOT in is skipped

- GIVEN `group_order = ["executives", "engineering"]`
- AND user "bob" belongs to: `["engineering", "support"]`
- WHEN `resolvePrimaryGroup("bob")` is called
- THEN it MUST skip "executives" and return `"engineering"`

#### Scenario: Configured group that no longer exists in Nextcloud is harmless

- GIVEN `group_order = ["deleted-group", "engineering"]`
- AND the Nextcloud group "deleted-group" has been removed
- AND user "alice" belongs to: `["engineering"]`
- WHEN `resolvePrimaryGroup("alice")` is called
- THEN it MUST return `"engineering"`
- AND MUST NOT raise an error
- NOTE: Cleanup of stale group IDs in `group_order` is the admin UI's responsibility; the resolver MUST be tolerant.

### Requirement: Resolver is the single routing authority (REQ-TMPL-013)

All workspace-rendering and dashboard-resolution code paths (REQ-DASH-013, REQ-DASH-018) MUST consult `resolvePrimaryGroup` for the user's primary group. There MUST NOT be parallel implementations of this lookup.

#### Scenario: Single source of truth

- GIVEN any future capability needs the user's primary workspace group
- WHEN it computes a group ID
- THEN it MUST go through `AdminTemplateService::resolvePrimaryGroup` (or its declared service interface)
- AND duplicating the algorithm inline is forbidden by code review

### Requirement: Template Gallery Endpoint (REQ-TMPL-014)

The system MUST expose a read-only gallery endpoint that lists all `admin_template` dashboards with metadata suitable for discovery and instantiation.

> NOTE (D2 — Index): The `WHERE type='admin_template' AND templateCategory=?` filter path MUST be backed by a composite index on `(type, templateCategory)`. The migration adding the three new metadata columns MUST also add this composite index to keep the optional category-filter query indexed at scale. The base `WHERE type='admin_template'` path already benefits from the existing index on `type`.

#### Scenario: List all templates in gallery

- GIVEN 3 admin templates exist with `templateCategory: 'marketing'`, `'engineering'`, and `null`
- WHEN a logged-in user sends `GET /api/templates/gallery`
- THEN the system MUST return HTTP 200 with an array of 3 template objects
- AND each object MUST include: `uuid`, `name`, `description`, `category` (nullable string), `previewImage` (nullable URL), `gridColumns`, `widgetCount` (count of widget placements), `lastUpdatedAt`
- AND the response MUST NOT include the widget tree or `isCompulsory` flag details (gallery is a list view, not a render)

#### Scenario: Filter gallery by category

- GIVEN 5 templates exist: 2 with `templateCategory: 'marketing'`, 2 with `templateCategory: 'engineering'`, 1 with `templateCategory: null`
- WHEN a logged-in user sends `GET /api/templates/gallery?category=marketing`
- THEN the system MUST return HTTP 200 with an array of 2 templates
- AND the response MUST contain only templates where `templateCategory = 'marketing'`

#### Scenario: Default sort order

- GIVEN multiple templates exist with various categories and names
- WHEN a user sends `GET /api/templates/gallery` (no sort parameter)
- THEN results MUST be sorted first by `templateCategory` (null last), then by `name` alphabetically
- AND this enables consistent ordering for pagination

#### Scenario: Sort by recency

- GIVEN 3 templates with `lastUpdatedAt` values: "2026-05-01 10:00:00", "2026-04-30 14:30:00", "2026-05-01 09:15:00"
- WHEN a user sends `GET /api/templates/gallery?sort=updatedAt`
- THEN the system MUST return results sorted by `lastUpdatedAt` descending (most recent first)
- AND HTTP 200 MUST be returned

#### Scenario: Gallery includes category null templates

- GIVEN a template has `templateCategory: null`
- WHEN a user calls `GET /api/templates/gallery` without category filter
- THEN the template MUST be included
- AND when calling `GET /api/templates/gallery?category=marketing`, the template with `null` category MUST NOT be included

### Requirement: Template Metadata Fields (REQ-TMPL-016)

Admin templates MUST support three new metadata fields for categorization and discovery: `templateCategory` (VARCHAR 64, nullable), `templateDescription` (TEXT, nullable), and `templatePreviewImage` (TEXT, nullable). The fields are stored as nullable columns on `oc_launchpad_dashboards` and only meaningful for rows with `type = 'admin_template'`.

#### Scenario: Template metadata in gallery response

- GIVEN a template with `templateCategory: 'marketing'`, `templateDescription: 'Use for campaign planning'`, `templatePreviewImage: 'https://example.com/img.png'`
- WHEN a user retrieves the template via `GET /api/templates/gallery`
- THEN the response MUST include all three metadata fields exactly as stored

#### Scenario: Metadata persists across updates

- GIVEN a template with `templateCategory: 'engineering'`
- WHEN an admin sends `PUT /api/admin/templates/{id}` to update the template name (via existing REQ-TMPL-003 endpoint)
- THEN `templateCategory` MUST remain `'engineering'` (unchanged)

#### Scenario: Update template metadata via save-as-template

- GIVEN user "alice" saves her dashboard as a template with `category: 'product'`
- THEN the new template's `templateCategory` MUST be set to `'product'`

#### Scenario: Template description field length

- GIVEN a user provides a `description` string of 500 characters when calling save-as-template
- THEN the system MUST accept and store the full 500 characters (unlike the regular `description` field, which may be shorter)
- NOTE: The `templateDescription` column stores longer text; validation MUST NOT truncate

#### Scenario: Metadata fields are nullable

- GIVEN a template with all metadata fields set to null
- WHEN the template is returned via any API endpoint
- THEN the response MUST include the fields with null values
- AND no error MUST be thrown

### Requirement: Preview Image Upload Endpoint (REQ-TMPL-017)

Administrators MUST be able to upload a preview image for a template, persisted using the existing resource-uploads ("custom-icon-upload") pipeline. The endpoint accepts a base64 data URL body so it shares MIME validation, SVG sanitisation, and the 5 MB size cap with `POST /api/resources`.

> NOTE (D4 — resource-uploads pipeline reuse): The `POST /api/admin/templates/{uuid}/preview-image` endpoint MUST delegate persistence to {@see ResourceService::upload()}. The body shape is `{base64: 'data:image/<type>;base64,<bytes>'}`. Allowed image types are PNG, JPG, GIF, WebP, SVG (sanitised). The persisted public URL is written to `templatePreviewImage` and returned as `{previewImage: '<url>'}`. Do not introduce a parallel image-persistence mechanism.

#### Scenario: Upload preview image for a template

- GIVEN an admin user and an admin template with UUID "abc123"
- WHEN the admin sends `POST /api/admin/templates/abc123/preview-image` with a JSON body containing `{base64: 'data:image/png;base64,<bytes>'}`
- THEN the system MUST save the image via the resource-uploads pipeline
- AND the image MUST be persisted under `<appdata>/resources/` with a high-entropy filename
- AND the template's `templatePreviewImage` field MUST be updated with the URL
- AND the response MUST return HTTP 200 with `{"status": "success", "previewImage": "/apps/launchpad/resource/<filename>"}`

#### Scenario: Non-admin cannot upload preview image

- GIVEN a regular user "alice"
- WHEN she sends `POST /api/admin/templates/abc123/preview-image` with a base64 image
- THEN the system MUST return HTTP 403
- AND the template's `templatePreviewImage` MUST NOT be modified

#### Scenario: Upload replaces previous preview image

- GIVEN a template with `templatePreviewImage: '/apps/launchpad/resource/old.png'`
- WHEN an admin uploads a new image via `POST /api/admin/templates/{uuid}/preview-image`
- THEN the system MUST overwrite the column with the new URL
- AND `templatePreviewImage` MUST point to the new image
- AND the old image file MAY be cleaned up (implementation-dependent)

#### Scenario: Invalid file format is rejected

- GIVEN an admin sends `POST /api/admin/templates/abc123/preview-image` with a `data:application/pdf;...` body
- THEN the system MUST return HTTP 400 with an `invalid_image` error code
- AND only image formats (PNG, JPG, GIF, WebP, SVG) MUST be accepted

#### Scenario: Preview image URL in gallery response

- GIVEN a template with a recently uploaded preview image
- WHEN a user calls `GET /api/templates/gallery`
- THEN the template object MUST include the `previewImage` URL
- AND the image MUST be immediately accessible (no delay)

> NOTE (D1 — Storage divergence): LaunchPad stores templates as `type='admin_template'` rows in `oc_launchpad_dashboards`. This is a deliberate and permanent divergence from the reference implementation's `/{lang}/_templates/` filesystem-folder convention. Reasons: (1) the existing REQ-TMPL-001..011 capability is already shipped — switching storage models would be a breaking change; (2) `WHERE type='admin_template'` is a single indexed query; the filesystem approach requires a full page-tree walk with path-segment string-matching; (3) DB enum cleanly separates kind from location; (4) LaunchPad supports DB-backed dashboards that have no GroupFolder and therefore no `_templates/` folder — a cross-backend representation requires the DB type column; (5) ACL equivalence is already provided by the `dashboard-sharing` capability. Do not attempt to converge on the filesystem-folder approach.

### Requirement: REQ-TMPL-012 Primary-group resolution for workspace routing

The system MUST expose a pure function `resolvePrimaryGroup(string $userId): string` that returns the Nextcloud group ID whose `group_shared` dashboards the user should see, OR the literal string `'default'` when no match is found. The algorithm MUST be:

1. Read the admin-configured ordered list of group IDs from `admin_settings.group_order` (JSON `string[]`, default `[]`).
2. Read the user's Nextcloud group memberships via `IGroupManager::getUserGroupIds($userId)`.
3. Walk `group_order` left-to-right and return the first group ID that also appears in the user's memberships.
4. If no match, return the literal string `'default'`.

The function MUST be deterministic and idempotent (no writes).

#### Scenario: First match wins by admin-configured priority

- GIVEN admin has set `group_order = ["engineering", "all-staff"]`
- AND user "alice" belongs to groups: `["all-staff", "engineering", "marketing"]`
- WHEN `resolvePrimaryGroup("alice")` is called
- THEN it MUST return `"engineering"` (because engineering appears first in group_order, even though all-staff is alphabetically earlier in alice's groups)

#### Scenario: User in no active group falls through to default sentinel

- GIVEN admin has set `group_order = ["engineering", "executives"]`
- AND user "carol" belongs only to groups: `["support"]`
- WHEN `resolvePrimaryGroup("carol")` is called
- THEN it MUST return `"default"`

#### Scenario: Empty group_order always returns default

- GIVEN admin has not configured any active groups (`group_order = []`)
- WHEN `resolvePrimaryGroup` is called for any user
- THEN it MUST return `"default"` regardless of the user's actual group memberships

#### Scenario: Configured group that the user is NOT in is skipped

- GIVEN `group_order = ["executives", "engineering"]`
- AND user "bob" belongs to: `["engineering", "support"]`
- WHEN `resolvePrimaryGroup("bob")` is called
- THEN it MUST skip "executives" and return `"engineering"`

#### Scenario: Configured group that no longer exists in Nextcloud is harmless

- GIVEN `group_order = ["deleted-group", "engineering"]`
- AND the Nextcloud group "deleted-group" has been removed
- AND user "alice" belongs to: `["engineering"]`
- WHEN `resolvePrimaryGroup("alice")` is called
- THEN it MUST return `"engineering"`
- AND MUST NOT raise an error
- NOTE: Cleanup of stale group IDs in `group_order` is the admin UI's responsibility; the resolver MUST be tolerant.

### Requirement: REQ-TMPL-013 Resolver is the single routing authority

All workspace-rendering and dashboard-resolution code paths (REQ-DASH-013, REQ-DASH-018) MUST consult `resolvePrimaryGroup` for the user's primary group. There MUST NOT be parallel implementations of this lookup.

#### Scenario: Single source of truth

- GIVEN any future capability needs the user's primary workspace group
- WHEN it computes a group ID
- THEN it MUST go through `AdminTemplateService::resolvePrimaryGroup` (or its declared service interface)
- AND duplicating the algorithm inline is forbidden by code review

### Requirement: REQ-RESYNC-001 Re-sync action pushes template updates to existing copies

Administrators MUST be able to push an updated admin template to its already-provisioned user copies via `POST /api/admin/templates/{id}/resync`, choosing a `strategy` of `overwrite` or `merge`. The action MUST be restricted to Nextcloud admins and MUST target only copies whose `basedOnTemplate` references the given template. Re-sync is an explicit, opt-in override of template copy independence (REQ-TMPL-006); first-access distribution (REQ-TMPL-005) MUST be unaffected.

#### Scenario: Admin re-syncs a template to existing copies

- GIVEN admin template id 1 has been provisioned to 12 users
- AND the admin has since added a widget and fixed a link on the template
- WHEN the admin sends `POST /api/admin/templates/1/resync` with body `{"strategy": "overwrite", "dryRun": false}`
- THEN the system MUST apply the template layout to all 12 provisioned copies
- AND the response MUST report the number of affected copies

#### Scenario: Non-admin cannot re-sync

- GIVEN admin template id 1 exists with provisioned copies
- WHEN regular user "alice" sends `POST /api/admin/templates/1/resync`
- THEN the system MUST return HTTP 403
- AND no user copy MUST be modified

#### Scenario: Re-sync rejects a non-template dashboard

- GIVEN dashboard id 5 is a user dashboard (`type: "user"`), not an admin template
- WHEN the admin sends `POST /api/admin/templates/5/resync`
- THEN the system MUST return an error indicating "Not an admin template"
- AND no dashboards MUST be modified

#### Scenario: Invalid strategy is rejected

- GIVEN admin template id 1 exists
- WHEN the admin sends `POST /api/admin/templates/1/resync` with body `{"strategy": "replace-all"}`
- THEN the system MUST return HTTP 400
- AND only `overwrite` and `merge` MUST be accepted

### Requirement: REQ-RESYNC-002 Dry-run reports the plan without mutating

The re-sync action MUST support a dry-run mode (`dryRun: true`) that computes and returns the planned changes — the set of affected copies and, per copy, the placements that would be added, updated, removed, and preserved — WITHOUT modifying any dashboard, placement, audit record, or notification.

#### Scenario: Dry-run reports affected copies without mutating

- GIVEN admin template id 1 has been provisioned to 8 users
- WHEN the admin sends `POST /api/admin/templates/1/resync` with body `{"strategy": "merge", "dryRun": true}`
- THEN the system MUST return HTTP 200 with a plan listing the 8 affected copies
- AND the plan MUST include, per copy, the counts of placements to add, update, remove, and preserve
- AND NO dashboard or widget placement MUST be modified
- AND NO audit record MUST be written and NO notification MUST be sent

#### Scenario: Dry-run on an up-to-date template reports no changes

- GIVEN admin template id 1 was already re-synced and has not changed since
- WHEN the admin sends `POST /api/admin/templates/1/resync` with `{"strategy": "overwrite", "dryRun": true}`
- THEN the plan MUST report zero placements to add, update, or remove for every copy

### Requirement: REQ-RESYNC-003 Merge strategy preserves user-added widgets

Under `strategy: "merge"`, the re-sync MUST reconcile template-origin placements onto each copy (add new template placements, update moved or changed template placements, remove placements the admin deleted from the template) while PRESERVING each user's personally-added widgets — placements the user added after provisioning that do not originate from the template. Under `strategy: "overwrite"`, the copy's layout MUST be replaced with the current template layout.

#### Scenario: Merge keeps user additions while applying template changes

- GIVEN user "alice" has a copy of template id 1 to which she added a personal "Notes" widget
- AND the admin added a new "Announcements" widget to the template and repositioned an existing one
- WHEN the admin re-syncs template id 1 with `{"strategy": "merge", "dryRun": false}`
- THEN alice's copy MUST gain the "Announcements" widget and reflect the repositioned template widget
- AND alice's personal "Notes" widget MUST remain on her copy unchanged

#### Scenario: Overwrite replaces the layout

- GIVEN user "bob" has a copy of template id 1 with a personally-added widget and a moved template widget
- WHEN the admin re-syncs template id 1 with `{"strategy": "overwrite", "dryRun": false}`
- THEN bob's copy layout MUST match the current template layout
- AND bob's personally-added widget MUST NOT be present after the overwrite

#### Scenario: Template widget removed by admin is removed under merge

- GIVEN template id 1 previously had a "Links" widget that all copies received
- AND the admin has since deleted the "Links" widget from the template
- WHEN the admin re-syncs with `{"strategy": "merge"}`
- THEN the template-origin "Links" widget MUST be removed from each copy
- AND user-added widgets on those copies MUST remain

### Requirement: REQ-RESYNC-004 Compulsory widgets are always reconciled

Regardless of the chosen strategy, re-sync MUST reconcile compulsory widgets against the template: a compulsory widget missing from a copy MUST be restored, and a compulsory widget's position and flags MUST be aligned to the template. Compulsory widgets are the org-controlled surface and MUST NOT be left stale by either strategy.

#### Scenario: Compulsory widget restored under merge

- GIVEN template id 1 has a compulsory "Company News" widget
- AND user "carol" managed to remove it from her copy
- WHEN the admin re-syncs template id 1 with `{"strategy": "merge"}`
- THEN the compulsory "Company News" widget MUST be restored to carol's copy at the template's position

#### Scenario: Compulsory widget position aligned under both strategies

- GIVEN template id 1 has a compulsory widget the admin has repositioned
- AND user "dave" has a copy where that compulsory widget is at the old position
- WHEN the admin re-syncs template id 1 (with either `overwrite` or `merge`)
- THEN the compulsory widget on dave's copy MUST match the template's position and flags

### Requirement: REQ-RESYNC-005 Re-sync is idempotent, audited, async-capable, and notifies users

A real (non-dry-run) re-sync MUST be idempotent — applying the same plan twice yields the same end state, and re-syncing an unchanged template is a no-op. Each per-copy apply MUST be transactional (partial placement failure rolls that copy back). Every real run MUST write an audit record (acting admin, template id, strategy, affected-copy count, timestamp) and MUST notify each affected user that an administrator updated their dashboard. For large target groups the apply MUST run asynchronously via `TemplateResyncJob` so the request returns promptly.

#### Scenario: Re-sync is idempotent

- GIVEN the admin re-synced template id 1 with `{"strategy": "overwrite"}` and the template has not changed
- WHEN the admin runs the same re-sync again
- THEN the resulting copies MUST be identical to the first run (no additional changes)
- AND the operation MUST NOT error

#### Scenario: Audit record is written on a real run

- GIVEN admin "admin1" re-syncs template id 1 with `{"strategy": "merge", "dryRun": false}` affecting 12 copies
- THEN the system MUST write an audit record capturing the acting admin, template id 1, strategy `merge`, an affected count of 12, and a timestamp

#### Scenario: Affected users are notified

- GIVEN a real re-sync of template id 1 modifies user "erin"'s copy
- WHEN the re-sync completes
- THEN erin MUST receive a notification that an administrator updated her dashboard
- AND the notification MUST be dispatched via the canonical `x-openregister-notifications` dialect when OpenRegister is present, otherwise via Nextcloud `INotification`

#### Scenario: Large groups apply asynchronously

- GIVEN admin template id 1 has been provisioned to 800 users
- WHEN the admin triggers a real re-sync
- THEN the system MUST enqueue `TemplateResyncJob` and return a prompt accepted response
- AND the job MUST apply the plan per copy and notify each affected user on completion

### Requirement: REQ-TMPL-018 Templates that ship with LaunchPad

LaunchPad MUST ship ready-made admin templates as data, one file per template at `data/templates/<id>.json`. A definition holds `templateId`, an integer `templateVersion`, a `language`, and one `dashboard` in the shape an export writes to `dashboards/<uuid>.json` (REQ-EXIM-001). The list of shipped ids is fixed in `ShippedTemplateService::SHIPPED_IDS`.

An administrator MUST be able to add a shipped template as an admin template from the Templates page (`POST /api/admin/templates/shipped/{id}/install`) and from the command line (REQ-CLI-012). Installing MUST go through the dashboard importer (REQ-EXIM-004), so a shipped template and an uploaded archive take one path. The installed template MUST keep every widget's configuration and its `isCompulsory` flag (REQ-EXIM-012).

Installing MUST NOT hand the template to anybody on its own. The definition ships with no target groups and is not the default. The administrator chooses the groups, or makes it the default, with the install options or afterwards on the Templates page (REQ-TMPL-003).

LaunchPad MUST remember the install per template: app config `shipped_template_<id>` holds the UUID and `shipped_template_version_<id>` the version. A second install MUST add nothing unless forced. A forced install MUST add a fresh copy and MUST leave the earlier one in place, because dashboards made from it point at it. Bringing an installed template to a newer shipped version is not a forced install: that is the update of REQ-TMPL-020, which keeps the template and its copies. When the recorded template was deleted, the shipped template MUST count as not installed.

A definition that is missing or malformed MUST fail the install with an error. It MUST NOT install an empty template.

The listing MUST name, per template, the Nextcloud dashboard widgets it shows that no app on the instance registers (`missingWidgets`). Those widgets are still placed, so the template has one shape on every instance.

The first shipped template is `mijn-werkdag`, a start page for a municipal employee, in Dutch. A shipped template MUST render as installed, with no further setting: it MUST NOT proxy a Nextcloud dashboard widget that has no items API, because LaunchPad paints those only when the legacy widget bridge is switched on (off by default), and every field its lists name MUST exist in the schema they read. `mijn-werkdag` holds a header, the "First today" list across apps (`attention-feed`, since template version 2), the employee's dossiq cases past their deadline, the employee's open dossiq cases and recent activity. The header and the "First today" list are compulsory. Its permission level is `add_only`.

#### Scenario: An administrator adds the shipped template and gives it to a group
- GIVEN LaunchPad ships the template `mijn-werkdag` and it is not installed
- WHEN an administrator adds it from the Templates page, then edits it and picks the group "medewerkers"
- THEN the template list MUST show "Mijn werkdag" with the group "medewerkers"
- AND a member of "medewerkers" who opens LaunchPad for the first time MUST get a dashboard made from it, with the header and the "First today" list marked compulsory

@e2e exclude No Playwright test was written for this. The install is pinned by ShippedTemplateServiceTest::testInstallAddsTheTemplateThroughTheImporter, the compulsory flags by ::testTheShippedTemplateSurvivesExportAndImport, and the page by TemplatesPage.shipped.spec.js. It has not been run in a browser.

#### Scenario: Installing twice adds one template
- GIVEN `mijn-werkdag` is installed
- WHEN an administrator installs it again without forcing
- THEN no second template MUST be added
- AND the response MUST say it was already installed and name the same UUID

@e2e exclude Service behaviour with no page of its own: pinned by ShippedTemplateServiceTest::testASecondInstallAddsNothing.

#### Scenario: A template deleted by the administrator can be added again
- GIVEN `mijn-werkdag` was installed and the administrator deleted that template
- WHEN the administrator installs it again
- THEN a new template MUST be added without forcing

@e2e exclude Pinned by ShippedTemplateServiceTest::testADeletedTemplateCountsAsNotInstalled.

#### Scenario: A broken definition fails loudly
- GIVEN the file for a shipped template is missing or has no widgets
- WHEN an administrator installs it
- THEN the install MUST fail with an error that names the file
- AND no template MUST be added

@e2e exclude A packaging fault cannot be staged in a browser: pinned by ShippedTemplateServiceTest::testAMalformedDefinitionFails.

#### Scenario: The listing names widgets no app registers here
- GIVEN no app on the instance registers the `activity` widget
- WHEN an administrator lists the shipped templates
- THEN `mijn-werkdag` MUST list `activity` under `missingWidgets`

@e2e exclude Needs an instance without the app: pinned by ShippedTemplateServiceTest::testTheListingNamesWidgetsNothingRegisters.

#### Scenario: The shipped template renders as installed
- GIVEN the shipped definition `data/templates/mijn-werkdag.json` and an instance with dossiq and default LaunchPad settings
- WHEN a member opens the dashboard made from it
- THEN no widget MUST show "This widget can only be shown on the Nextcloud dashboard itself." or a raw widget id as its heading
- AND the "Termijn" column of both case lists MUST show each case's deadline

@e2e exclude Needs dossiq with cases on the instance, which the e2e instance does not have. Pinned by ShippedTemplateServiceTest::testAShippedTemplateRendersAsInstalled, and seen in a browser on a test instance on 5 October 2026.

#### Scenario: Every widget in the shipped template is one LaunchPad can place
- GIVEN the shipped definition `data/templates/mijn-werkdag.json`
- WHEN its widgets are read
- THEN every `widgetId` MUST be on LaunchPad's own widget type list (`lib/widget-types.json`)
- AND no two widgets MUST share a grid cell
- AND every widget MUST fit inside the template's grid columns

@e2e exclude A check on shipped data, not a browser behaviour: pinned by ShippedTemplateServiceTest::testTheShippedDefinitionIsWellFormed.

### Requirement: REQ-TMPL-019 A member is shown their template on the first visit

A user who owns no personal dashboard and has no saved choice (no pinned default and no last-used dashboard that they can still see) MUST be shown the admin template that applies to them (REQ-TMPL-005: a template that targets one of their groups, else the default template). Both resolution chains MUST do this at the same place, directly after the saved-choice steps and before any group dashboard: the page shell's chain (REQ-DASH-018, step 1b) and `GET /api/dashboard` (REQ-DASH-009).

Precedence:

- A template that targets one of the user's groups MUST outrank every group dashboard, the user's own group's default included.
- The default template MUST outrank the dashboards of the `default` group (everyone), including the one seeded on install. It MUST step aside for a default dashboard of the user's own primary group, which is the more specific of the two.

What the user gets:

- With personal dashboards allowed (`allowUserDashboards`), the user MUST receive a personal copy (REQ-TMPL-005), once. The copy MUST be stored as their last-used dashboard, so later visits resolve it through the saved-choice step.
- With personal dashboards off, no copy MUST be made (REQ-ASET-003). The template itself MUST be shown, view only. Its compulsory flags have no effect then, because nothing can be removed.

A user who owns a personal dashboard, or has a saved choice, MUST NOT be affected by this requirement.

**Why this was written down.** Templates were only known to `GET /api/dashboard`, at the very end of its chain. The page decides what to show from the other chain, which did not know templates. So on an instance with the seeded dashboard for everyone, that dashboard always won; and without it the page said "No dashboards available" and never called the API that would have made the copy. Every unit test was green and no member of a targeted group had ever been shown a template. Found by a live check on 5 October 2026.

**What changes on an instance that already has templates.** A user who owns nothing and has no saved choice now lands on their template (and, with personal dashboards on, receives a copy) where they used to land on a group dashboard. Nobody else is moved.

**Known limit.** With personal dashboards off, the view-only template is not in the dashboard switcher. A member who opens another dashboard cannot switch back to it.

#### Scenario: A new member sees the template, although a dashboard for everyone exists
- GIVEN the instance has the seeded dashboard for everyone and personal dashboards are allowed
- AND the template "Mijn werkdag" targets the group "behandelaars"
- AND Sanne is in "behandelaars" and has never opened LaunchPad
- WHEN Sanne opens LaunchPad
- THEN she MUST see "Mijn werkdag", as a personal copy with the template's compulsory widgets
- AND a second visit MUST show the same copy and MUST NOT make another

#### Scenario: Without any other dashboard the page still shows the template
- GIVEN no group dashboard exists and the template targets Sanne's group
- WHEN Sanne opens LaunchPad for the first time
- THEN she MUST see the template's dashboard and MUST NOT see "No dashboards available"

@e2e exclude Needs an instance with no group dashboard at all, and the e2e instance is shared with other suites' fixtures. Pinned by DashboardServiceTemplateRungTest::testWithNoOtherDashboardThePageStillShowsTheTemplate.

#### Scenario: Personal dashboards off
- GIVEN personal dashboards are off and the template targets Sanne's group
- WHEN Sanne opens LaunchPad for the first time
- THEN she MUST see the template itself, view only
- AND no personal dashboard MUST be created for her

@e2e exclude Pinned by DashboardServiceTemplateRungTest::testWithPersonalDashboardsOffTheTemplateItselfIsShownViewOnly. Not run in a browser.

#### Scenario: The default template replaces the dashboard for everyone
- GIVEN the seeded dashboard for everyone exists and a default template exists
- AND Mark is in no group that a template targets and his primary group has no default dashboard
- WHEN Mark opens LaunchPad for the first time
- THEN he MUST get the default template, not the seeded dashboard

@e2e exclude Making a template the instance default changes what every other suite's fresh user lands on. Pinned by DashboardServiceTemplateRungTest::testTheDefaultTemplateReplacesTheSeededDashboard and ::testTheDefaultTemplateStepsAsideForTheUsersOwnGroupDefault.

#### Scenario: People already using LaunchPad are left alone
- GIVEN Pieter owns a personal dashboard, and Lotte has a last-used dashboard she can still see
- AND a template now targets a group both are in
- WHEN each opens LaunchPad
- THEN neither MUST be moved to the template and no copy MUST be made for them

@e2e exclude Pinned by DashboardServiceTemplateRungTest::testAUserWhoOwnsADashboardGetsNoTemplate and ::testASavedChoiceIsKept.

### Requirement: REQ-TMPL-020 Updating an installed shipped template in place

When LaunchPad ships a higher `templateVersion` of a template than the one installed (`shipped_template_version_<id>`), an administrator MUST be able to bring the installed template to that version with one action: the "Update to version N" button on the Templates page (`POST /api/admin/templates/shipped/{id}/update`) or `occ launchpad:template:install <id> --update` (REQ-CLI-012). The listing (REQ-TMPL-018) MUST say so per template with `updateAvailable`.

The update MUST keep the installed template: its id, UUID, name, description, target groups, default flag and permission level stay as they are. It MUST replace the template's widgets with the shipped version's. A widget an administrator added to the installed template by hand, or changed there, is replaced with it.

**A widget in both versions keeps its row.** A member's copy remembers, per widget, the id of the template widget it came from, and re-sync matches on that id (REQ-RESYNC-003). A definition carries no key per widget, so the update pairs the installed widgets with the new ones in three passes, each over what the pass before left:

1. every field a definition sets is equal: the widget is unchanged and is not written;
2. same widget type, same proxied Nextcloud widget and same title: the row is kept and changed;
3. same widget type, when exactly one such widget is left on each side: the row is kept and changed.

What is then left of the new version is added. What is left of the installed template is removed. Settings (`content`, `styleConfig`) are compared as data, so the same settings in another key order are unchanged.

**Members' copies follow.** After the template is written, the update MUST re-sync the copies with the `merge` strategy (REQ-RESYNC-001, -003, -004). So a compulsory widget the new version adds arrives in every copy, a widget the new version drops leaves every copy, and a widget a member added to their own copy stays. As with every merge re-sync, a template widget a member moved or changed is set back to the template's, and a template widget a member removed comes back. More than 50 copies are re-synced in the background (REQ-RESYNC-005). With personal dashboards off there are no copies: members see the template itself (REQ-TMPL-019), so they see the new version at once.

**Never silently.** The result MUST name every widget added, removed and changed, the number unchanged and the number of members' copies. A dry run (`dryRun=true`, `--dry-run`) MUST return the same lists and MUST write nothing: no widget, no version, no copy. The Templates page MUST show the dry run and ask for confirmation before it updates.

The widgets of the template MUST be written in one transaction. When a write fails, nothing MUST be kept and the recorded version MUST stay, so the next run tries again. The recorded version MUST be raised only after the widgets are written.

When the installed version is the shipped one or newer, the update MUST change nothing and MUST say so (`upToDate`). When the template is not installed, the endpoint MUST answer 409 and the command MUST exit 1; an unknown id answers 404.

#### Scenario: An administrator updates the installed template from the Templates page
- GIVEN `mijn-werkdag` is installed at version 2, targets the group "behandelaars", and LaunchPad ships version 3
- WHEN the administrator presses "Update to version 3" on the Templates page
- THEN a dialog MUST list the widgets that are added, removed and changed, and the number of members with a copy
- AND nothing MUST be written until the administrator confirms
- WHEN the administrator confirms
- THEN the same template MUST hold version 3's widgets, still target "behandelaars", and the button MUST be gone

@e2e exclude Staging "a newer version ships" needs a second definition file on the server, which a browser test cannot place. The page and the dialog are pinned by TemplatesPage.shippedUpdate.spec.js, the update by ShippedTemplateUpdateServiceTest::testTheUpdateReplacesTheWidgetsAndKeepsTheTemplate. Seen in a browser on a test instance on 5 October 2026.

#### Scenario: A member's copy follows and keeps what the member added
- GIVEN Pieter has a copy of the installed template and added a widget of his own to it
- WHEN the administrator updates the template to a version that adds a compulsory widget and drops another widget
- THEN Pieter's copy MUST hold the new compulsory widget
- AND the dropped widget MUST be gone from his copy
- AND the widget Pieter added MUST still be there

@e2e exclude Same reason as above. Pinned by ShippedTemplateUpdateServiceTest::testAMembersCopyFollowsAndKeepsTheirOwnWidget, which makes the copy with the service a first visit uses and re-syncs it with the real re-sync service.

#### Scenario: A dry run writes nothing
- GIVEN `mijn-werkdag` is installed at an older version
- WHEN an administrator asks for the update with `dryRun`
- THEN the answer MUST list the widgets added, removed and changed
- AND the template, the recorded version and every copy MUST be as before

@e2e exclude Pinned by ShippedTemplateUpdateServiceTest::testADryRunWritesNothing.

#### Scenario: A widget in both versions keeps its row
- GIVEN the installed template and the new version both hold the list "Mijn zaken", with other settings
- WHEN the template is updated
- THEN the list's row MUST keep its id and carry the new settings

@e2e exclude A database row id has no browser surface. Pinned by ShippedTemplateUpdateServiceTest::testTheUpdateReplacesTheWidgetsAndKeepsTheTemplate and ::testTheShippedDefinitionPairsWithItsOwnInstall.

#### Scenario: Nothing to do
- GIVEN the installed version is the version LaunchPad ships
- WHEN an administrator asks for the update
- THEN nothing MUST be written and the answer MUST say the template is up to date

@e2e exclude Pinned by ShippedTemplateUpdateServiceTest::testAMembersCopyFollowsAndKeepsTheirOwnWidget (second run) and TemplateInstallCommandTest::testAnUpToDateTemplateSaysSoAndExitsZero.

#### Scenario: A failed write keeps the recorded version
- GIVEN the database refuses a write halfway through the update
- THEN the update MUST fail with an error, roll back, and leave the recorded version unchanged

@e2e exclude A database fault cannot be staged in a browser. Pinned by ShippedTemplateUpdateServiceTest::testAFailedWriteKeepsTheRecordedVersion.

### Requirement: REQ-TMPL-021 One rule picks the template when several target the same group

When more than one admin template targets a group the user is in, `TemplateService::getApplicableTemplate()` MUST pick the same template on every instance and every visit, by this order:

1. the installed copy of a shipped template (the template `shipped_template_<id>` names) goes before a template made by hand, and among shipped templates the higher recorded version goes first;
2. then the lowest template id, which is the oldest template.

A template that targets one of the user's groups still goes before the default template (REQ-TMPL-005).

**Why.** The rule used to be "the first match", and the list was sorted by name, so two templates with the same name came out in the order the database happened to return them. After `occ launchpad:template:install <id> --force` an instance holds the old and the new copy of a shipped template for the same group, under the same name, and a new member could get either. The forced copy is the recorded one, so it now wins.

**What changes on an existing instance.** Only where two or more templates target the same group. There, a user without a dashboard of their own used to get the template whose name sorts first; they now get the installed shipped template if one of them is that, else the oldest. With personal dashboards off, where members are shown the template itself on every visit (REQ-TMPL-019), such a member can see another template after the upgrade than before. A user who already has a copy keeps it. An instance where every group has one template is not affected.

#### Scenario: The oldest hand-made template wins
- GIVEN the templates "Aanvragen" (id 9) and "Zaken" (id 4) both target a group Pieter is in
- WHEN Pieter opens LaunchPad for the first time
- THEN he MUST get "Zaken"

@e2e exclude Two templates for one group would change what every other suite's fresh user of that group lands on. Pinned by TemplateServiceApplicableTemplateTest::testTheLowestIdWinsAmongHandMadeTemplates.

#### Scenario: After a forced install the recorded copy wins
- GIVEN a forced install left the earlier "Mijn werkdag" (id 7) next to the recorded one (id 9), both for "behandelaars"
- WHEN a new member of "behandelaars" opens LaunchPad
- THEN they MUST get the template with id 9

@e2e exclude Pinned by TemplateServiceApplicableTemplateTest::testTheInstalledShippedTemplateGoesBeforeAnEarlierForcedCopy.

#### Scenario: A shipped template goes before a hand-made one
- GIVEN the hand-made template "Afdeling" (id 3) and the installed shipped template "Mijn werkdag" (id 9) both target a group Pieter is in
- WHEN Pieter opens LaunchPad for the first time
- THEN he MUST get "Mijn werkdag"

@e2e exclude Pinned by TemplateServiceApplicableTemplateTest::testAShippedTemplateGoesBeforeAHandMadeOneWithALowerId.

## Non-Functional Requirements

- **Performance**: Template distribution (copying placements) MUST complete within 2 seconds per user, even for templates with 20+ widget placements. The first-access check MUST add no more than 200ms to the initial dashboard load. `GET /api/templates/gallery` MUST return within 500ms even with 100+ templates; the gallery list SHOULD NOT fetch widget placements (use `WidgetPlacementMapper::countByDashboardId()` for the count, not `findByDashboardId()`).
- **Data integrity**: Template copies MUST be atomic -- if any placement fails to copy, the entire copy operation MUST be rolled back. The single-default invariant MUST be enforced at the database/service level. Save-as-template (REQ-TMPL-015) deep-copy MUST also be atomic — if placement copy fails, the entire operation MUST be rolled back.
- **Scalability**: Template distribution MUST work efficiently for organizations with 1000+ users. The system SHOULD NOT eagerly copy templates to all users; copies MUST be created on-demand at first access.
- **Security**: Only Nextcloud admin users MUST be able to create, update, or delete templates. Only dashboard owners can call `POST /api/dashboards/{uuid}/save-as-template`. Only admins can call `POST /api/admin/templates/{uuid}/preview-image`. Group membership checks MUST use Nextcloud's `IGroupManager` API.
- **Storage**: Preview images MUST be stored using the existing resource-uploads pipeline (same MIME validation, SVG sanitisation, 5 MB cap as `POST /api/resources`).
- **Localization**: Admin template management UI labels and error messages MUST support English and Dutch.

### Current Implementation Status

**Fully implemented:**
- REQ-TMPL-001 (Create Admin Template): `AdminTemplateService::createTemplate()` in `lib/Service/AdminTemplateService.php` creates dashboards with `type: "admin_template"`, `userId: null`, default `gridColumns: 12`. Default clearing via `clearDefaultTemplates()` is implemented.
- REQ-TMPL-002 (List Admin Templates): `AdminTemplateService::listTemplates()` calls `DashboardMapper::findAdminTemplates()`.
- REQ-TMPL-003 (Update Admin Template): `AdminTemplateService::updateTemplate()` with `applyTemplateUpdates()` handles name, description, targetGroups, permissionLevel, isDefault, gridColumns.
- REQ-TMPL-004 (Delete Admin Template): `AdminTemplateService::deleteTemplate()` deletes placements first via `placementMapper->deleteByDashboardId()`, then deletes the template.
- REQ-TMPL-005 (Template Distribution): `TemplateService::getApplicableTemplate()` checks group membership via `IGroupManager::getUserGroupIds()`. `createDashboardFromTemplate()` copies all placements including `isCompulsory` flags.
- REQ-TMPL-006 (Template Copy Independence): Copies are independent -- `buildDashboardFromTemplate()` creates a new Dashboard entity, `copyTemplatePlacements()` creates new WidgetPlacement entities.
- REQ-TMPL-007 (Template Widget Management): Templates share the same widget placement API as regular dashboards.
- REQ-TMPL-008 (Only One Default): `clearDefaultTemplates()` on DashboardMapper ensures single default.
- REQ-TMPL-009 (Get Template with Placements): `AdminTemplateService::getTemplateWithPlacements()` returns template + placements.
- REQ-TMPL-014 (Gallery endpoint): `AdminTemplateService::getGallery()` + `TemplateController::gallery()` expose `GET /api/templates/gallery`. Backed by `DashboardMapper::findAllTemplatesForGallery()` and the composite `(type, template_category)` index added in `Version001012Date20260503000000`.
- REQ-TMPL-015 (Save-as-template): `AdminTemplateService::saveAsTemplate()` + `TemplateController::saveAsTemplate()` expose `POST /api/dashboards/{uuid}/save-as-template`. Owner-only, transactional, uses `WidgetPlacementMapper::cloneToDashboard()` for the deep copy.
- REQ-TMPL-016 (Metadata fields): `templateCategory`, `templateDescription`, `templatePreviewImage` columns added to `oc_launchpad_dashboards`; serialised via `Dashboard::jsonSerialize()`.
- REQ-TMPL-017 (Preview image upload): `AdminTemplateService::uploadPreviewImage()` + `AdminController::uploadTemplatePreviewImage()` expose `POST /api/admin/templates/{uuid}/preview-image`. Reuses `ResourceService::upload()` for storage.

**Not yet implemented:**
- REQ-TMPL-001 validation: No server-side validation for `permissionLevel` values.
- REQ-TMPL-002 widget_count: Template list response does NOT include a widget placement count.
- REQ-TMPL-005 multi-template distribution: Only ONE template is distributed per first-access.
- REQ-TMPL-011 group fetching: `AdminSettings.vue` group selector has `availableGroups` hardcoded to empty array.

### Standards & References
- Nextcloud Group API: `OCP\IGroupManager::getUserGroupIds()`
- Nextcloud User API: `OCP\IUserManager::get()`
- WCAG 2.1 AA for the admin template management UI (modal dialogs, form fields)
- WAI-ARIA: Modal dialog accessibility via `NcModal` component
