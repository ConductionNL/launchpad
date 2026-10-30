---
kind: config
depends_on: []
---

# Platform baseline: what LaunchPad takes from Nextcloud

## Why

Seven rows of the LaunchPad parity matrix (`openspec/parity/capabilities.json`) are built because LaunchPad runs inside Nextcloud, but no spec says so. They carried `"spec": "none"`, so nothing held LaunchPad to them. A change that adds a login form, a hosted call, a hard-coded colour or a private copy of group membership would break them without failing a spec.

Rows covered, all `built`:

- **p-sso**, "Sign in once with the organisation's identity provider."
- **p-own-servers**, "Run the dashboard on your own servers."
- **p-theme**, "The dashboard takes on the organisation's colours, including government theming."
- **p-languages**, "Use the dashboard in Dutch and many other languages."
- **d-idp-groups**, "Group membership follows the groups sent by the identity provider at sign-in."
- **d-pwa**, "Install the dashboard on a phone's home screen as an app."
- **d-dark-mode**, "The dashboard follows the device's light or dark mode."

## What changes

- A new capability `platform-baseline` with one requirement per row (REQ-PLB-001 to REQ-PLB-007). Each states what LaunchPad must do so the Nextcloud behaviour reaches every LaunchPad page.
- The seven rows cite the requirement in `built.spec` and `built.requirement`.
- `screens.md` names board LpMobiel for d-pwa and a one-line reason for the six rows without a screen.

## Capabilities

### New capabilities

- `platform-baseline`

## Impact

- Spec only. No code changes: every requirement describes behaviour that is on `development` today (evidence in `tasks.md`).
- `specs/launchpad-mobile-remote-access` keeps PWA out of its own scope; REQ-PLB-006 is the home for it.

## Out of scope

- Per-dashboard themes and custom CSS (`platform-dashboard-theme-and-css`).
