# Proposal: no-buildiq-edit-control

## Why

The boards of design-system #1192 (DEMO-SEED, "Rules that every board follows") hold only launchpad's own actions in
the page header: Edit (or Customise start page), Add widget where the role allows it, and More. Where Buildiq is
enabled, `CnAppRoot` adds Buildiq's in-app edit button to every page's action row, so launchpad's pages showed a
control the boards do not draw. Launchpad dashboards are edited in launchpad.

## What changes

- `src/manifest.json` sets `openbuildEditable: false`, the manifest's opt-out of the Buildiq edit button.
