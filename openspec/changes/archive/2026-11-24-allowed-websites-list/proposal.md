# Proposal: allowed-websites-list

## Why

Board LpBeheerAlgemeen draws one block "Toegestane websites": "Widgets mogen alleen pagina's insluiten en gegevens
ophalen van deze sites", a list of hosts each marked "Insluiten", "Ophalen" or "Insluiten en ophalen"
(www.zuiddrecht.nl both, app.powerbi.com embed, kcc-api.zuiddrecht.nl fetch, www.youtube-nocookie.com embed), "Website
toevoegen" and Opslaan.

On development each feature keeps its own fail-closed allow-list in app config: the embed widget's hosts
(REQ-IFRAME-002), image hosts (REQ-IMG-006), `livetile_allowed_hosts` (live-data-tile-widget),
`healthping_allowed_hosts` (service-health-ping) and `calendar_widget_allowed_ics_hosts` (REQ-CAL-006). An
administrator has no one place that says which outside sites launchpad may embed or call, and parity row g-allowlists
is rated partial for that reason.

## What changes

- `admin-settings`: one list of allowed websites with a purpose per host, embed, fetch or both (REQ-ASET-021). Embed
  feeds the embed and image allow-lists; fetch feeds the live tile, health ping and calendar feed allow-lists. The
  checks themselves stay where they are and stay fail-closed.

## Impact

- An admin setting and its block on the General tab, a migration that merges the existing per-feature lists into it,
  the services that read their allow-list (they read the derived list), translations.
