# Proposal: work-packages-widget

## Why

Board LpWerkplek (design-system #1192) shows "Mijn werkpakketten": a person's open OpenProject work packages with a
status chip and a due date. Board LpAfdeling shows "Werkpakketten van het team": every open work package of one
project. Launchpad has no widget for OpenProject.

## Decision: a native widget over the official integration

The official Nextcloud app `integration_openproject` registers one Nextcloud dashboard widget,
`openproject_notifications`. It lists the person's unread OpenProject notifications, not their work packages, has no
due dates or statuses, and implements only `IWidget` (no item API), so launchpad's `nc-widget` support can only embed
it as is. Its HTTP API has no "assigned to me, due soon" query either: `GET /api/v1/work-packages` searches by text or
linked file.

So launchpad builds the widget itself, but reads through the integration: launchpad's backend asks the integration's
`OpenProjectAPIService::request()` for `work_packages` with the person's id. The integration holds the OpenProject
address and each person's own OAuth or OIDC token, refreshes it, and makes the call. Launchpad never sees a credential,
and OpenProject decides what each person may see. The service is looked up at runtime, so launchpad works without the
app.

## What changes

- `GET /apps/launchpad/api/work-packages?scope=mine|project&project=<identifier>&limit=n`, answering
  `{ state, workPackages, connectUrl? }` with `state` `ok`, `not-installed`, `not-connected` or `error`.
- A `work-packages` widget type, "My work packages" in the picker, offered only where `integration_openproject` is
  enabled for the viewer. It shows the work packages through `CnStatusList`: subject as a link into OpenProject,
  project, due date (overdue once passed) and status.
- A person who has not connected their OpenProject account gets a link to the integration's personal settings.
