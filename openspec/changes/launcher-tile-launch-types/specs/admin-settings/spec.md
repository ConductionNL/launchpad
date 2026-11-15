# admin-settings delta for launcher-tile-launch-types

## ADDED Requirements

### Requirement: Program schemes and single sign-on launch templates are admin settings (REQ-ASET-020)

The admin settings MUST hold `tileAllowedSchemes` (stored as `tile_allowed_schemes`), a list of lower-case URL schemes a program tile may use, and `ssoLaunchTemplates` (stored as `sso_launch_templates`), a list of `{key, name, urlTemplate}` where `urlTemplate` is an https address containing `{appId}` exactly once. Both MUST be empty by default. A write MUST be refused with HTTP 400, naming the entry, when a scheme is not lower-case or is one of `javascript`, `data`, `file`, `vbscript`, `about` or `blob`, or when a template is not https, lacks or repeats `{appId}`, carries another placeholder or credentials, or reuses a key; a refused write MUST leave both lists as they were. A stored value that no longer validates MUST read as empty. With these two, `GET /api/admin/settings` (REQ-ASET-001) returns fourteen keys; the REQ-ASET-001 list is updated when this change is archived.

#### Scenario: A script scheme cannot be allowed

- **GIVEN** an administrator on the LaunchPad admin page
- **WHEN** they save `javascript` as an allowed scheme
- **THEN** the response is 400 naming `javascript` and the allowed list is unchanged

#### Scenario: A launch template must be https with the app id

- **GIVEN** an administrator defining a launch template
- **WHEN** they save `http://idp.example.nl/{appId}`
- **THEN** the response is 400 and no template is stored
