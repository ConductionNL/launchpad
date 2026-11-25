# Proposal: personal-settings-dialog

## Why

Board LpPersoonlijk draws launchpad's own settings dialog, opened from Meer, Instellingen, Persoonlijke instellingen.
Its sections, in order: Samenvatting per e-mail, Gebruikscijfers, Verborgen widgets, Mijn profiel and Inloggegevens.

On development the dialog is `CnAppRoot`'s user settings dialog with Mijn profiel (people-widget REQ-PEX-002),
Gebruikscijfers (dashboard-view-analytics REQ-ANLT-UI-001) and the library's credentials, notifications, walkthrough
and integrations sections. No requirement says what the dialog holds, and two of the board's sections do not exist:

- **Samenvatting per e-mail**: "Een mail met nieuwe aankondigingen en wat er op je dashboards veranderde", with "Hoe
  vaak?": Uit, Dagelijks ("Om 07:30, alleen als er iets veranderde") or Wekelijks ("Op maandag om 07:30").
- **Verborgen widgets**: "Je hebt 2 widgets verborgen op gedeelde dashboards", each with its dashboard and "Weer
  tonen", and "Alles weer tonen". Today hidden widgets can only be shown again per dashboard (dashboards-and-who-may-see-them REQ-DWMS-002,
  "Hidden (n)").

## What changes

- New capability `personal-settings`: the dialog and its sections in the board's order (REQ-PSET-001), the summary
  email (REQ-PSET-002), and the overview of hidden widgets across dashboards (REQ-PSET-003).
- No page under `/settings/user`: every per-person setting stays in this dialog.

## Decisions

- The summary email is launchpad's own, sent by a background job, so it can say what changed on the person's
  dashboards. Nextcloud's activity email (activity-feed-integration REQ-DIGE-001) stays as it is.
- The summary is off until the person turns it on.

## Impact

- `src/App.vue` (section order), two new section components under `src/views/settings/`, a background job and mail
  template for the summary, an endpoint pair for the hidden-widget overview over the personal layer of REQ-DWMS-001.
