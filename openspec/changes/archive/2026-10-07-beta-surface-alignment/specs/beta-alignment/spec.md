# Spec: Beta surface alignment

## ADDED Requirements

### Requirement: The four public surfaces SHALL agree on feature vocabulary
`appinfo/info.xml`, the shipped feature set in `src/manifest.json` / `lib/`,
the product page (`conduction-website/src/pages/apps/launchpad.mdx` + its
Dutch translation), and `docs/` SHALL describe the same canonical feature
list using the same feature names, and SHALL NOT assert a capability that
is not present in `lib/` or `src/` at HEAD.

#### Scenario: A feature bullet on the product page names a real capability
- **WHEN** the product page or docs describe a LaunchPad capability
- **THEN** that capability SHALL be traceable to a concrete file in `lib/`
  or `src/` (a controller, service, widget renderer, or registry entry)

#### Scenario: info.xml does not declare an install-time dependency the architecture forbids
- **WHEN** `docs/architecture.md` documents a "MUST NOT" install-time
  dependency rule for an app (e.g. OpenRegister)
- **THEN** `appinfo/info.xml`'s `<dependencies>` block SHALL NOT list that
  app

### Requirement: The license tag SHALL match the declared license text
`appinfo/info.xml`'s `<licence>` element and its top-of-file SPDX header
SHALL match the license asserted in both `<description>` CDATA blocks
("Free and open source under the EUPL-1.2 license") and every PHP file's
`@license` docblock tag.

#### Scenario: licence tag matches description text
- **WHEN** `info.xml`'s description states a license
- **THEN** the `<licence>` element SHALL declare the same license

### Requirement: A translated product page SHALL live at the same relative path as its source page
Docusaurus i18n resolves a translated page by matching the source page's
file path under the locale's content directory. A translation SHALL NOT be
left at a stale pre-rename filename after the English source page is
renamed.

#### Scenario: English product page is renamed
- **WHEN** `conduction-website/src/pages/apps/<old-slug>.mdx` is renamed to
  `<new-slug>.mdx`
- **THEN** every locale's translation under
  `i18n/<locale>/docusaurus-plugin-content-pages/apps/` SHALL be renamed to
  the same `<new-slug>.mdx`, and no stale `<old-slug>.mdx` file SHALL remain
