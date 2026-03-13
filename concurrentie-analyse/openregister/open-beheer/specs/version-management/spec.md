# Version Management

## Feature Summary

Open Beheer surfaces the Catalogi API's version system for zaaktypen. Each zaaktype
can have multiple versions distinguished by beginGeldigheid and eindeGeldigheid dates.
The VersionSelector component allows users to browse versions and create new ones.

## How It Works in Open Beheer

### Version Timeline

- Each zaaktype version has `beginGeldigheid` and `eindeGeldigheid` dates
- Versions with `concept=true` are drafts; `concept=false` are published
- The VersionSelector component shows all versions as a timeline
- Users can click any version to view its details
- The "current" version is typically the one with the latest beginGeldigheid

### Creating a New Version

1. User clicks "Nieuwe versie" on a published zaaktype
2. BFF creates a new concept version via POST to Open Zaak
3. The new version copies field values from the current version
4. Related objects are also copied to the new version
5. User edits the concept version
6. On publish, the old version's eindeGeldigheid is set to yesterday

### Publishing Flow

1. PATCH old version: `eindeGeldigheid = today - 1`
2. PATCH new version: `beginGeldigheid = today` (if not set or in the past)
3. POST to `/zaaktypen/{uuid}/publish/` to mark as non-concept

### Version Comparison

The VersionSelector shows metadata for each version:
- Begin and end geldigheid dates
- Whether it's a concept or published
- Version date (versiedatum)

### Detail View with Versions

The `DetailWithVersions` protocol extends `DetailView` to add:
- `get_versions()`: Fetches all versions of the same zaaktype
- `get_version_fields()`: Fields displayed in version selector
- Version data included in the `DetailResponse` envelope

### API Pattern

The BFF resolves versions by fetching all zaaktypen with the same identificatie
within the same catalogus. The Catalogi API returns separate objects for each version.

## Already in OpenRegister

- **Audit logging**: OpenRegister tracks all changes with timestamps
- **Time-travel queries**: Can query object state at any point in time
- **Soft deletes**: Objects are never truly deleted

## Not Yet in OpenRegister

- **Geldigheid-based version management**: No begin/eindeGeldigheid date model for managing validity periods of type definitions. OpenRegister's versioning is change-based (every edit creates a version), not period-based.
- **Version selector UI component**: No visual timeline or version picker in the admin UI
- **Draft/published version workflow**: No concept of draft vs published versions. All saves are immediately active.
- **Version copying with related objects**: No mechanism to create a new version by copying an existing object + all its related objects
- **Publish action with automatic date management**: No publish endpoint that sets validity dates on old and new versions automatically
