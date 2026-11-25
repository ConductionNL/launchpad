---
capability: personal-settings
status: done
---

# Personal settings Specification

## Purpose

Launchpad's own settings dialog: everything a person sets for themselves, in one place, without a page under
`/settings/user`.

## Requirements

### Requirement: One dialog holds every personal setting (REQ-PSET-001)

"Persoonlijke instellingen" in the Instellingen group of Meer (runtime-shell REQ-SHELL-013) SHALL open launchpad's
settings dialog over the current page. The dialog SHALL list its sections in a side list, in the order of board
LpPersoonlijk: Samenvatting per e-mail, Gebruikscijfers, Verborgen widgets, Mijn profiel, Inloggegevens; sections the
library adds (notifications, walkthrough, integrations) SHALL follow. A section whose feature is off SHALL be left
out: Gebruikscijfers when an administrator turned analytics off, Mijn profiel when no profile field is filled by the
person. Changes SHALL be saved at once ("Wijzigingen worden meteen bewaard"). Launchpad SHALL NOT register a section
in Nextcloud's personal settings.

#### Scenario: Pieter opens his settings

- **GIVEN** Pieter on the dashboard Intranet Zuiddrecht
- **WHEN** he opens Meer, unfolds Instellingen and chooses Persoonlijke instellingen
- **THEN** the dialog opens over the dashboard with the sections Samenvatting per e-mail, Gebruikscijfers, Verborgen widgets, Mijn profiel and Inloggegevens, in that order

#### Scenario: Analytics off for everyone

- **GIVEN** the administrator turned analytics off
- **WHEN** Pieter opens his settings
- **THEN** the dialog has no Gebruikscijfers section

### Requirement: A person chooses a summary email (REQ-PSET-002)

The section Samenvatting per e-mail SHALL explain "A mail with new announcements and what changed on your
dashboards" and offer "How often?": Off, Daily ("At 07:30, only when something changed") or Weekly ("On Monday at
07:30"). The choice SHALL be stored per person and SHALL be Off until the person changes it.

A background job SHALL send the summary at 07:30 in the person's time zone, daily or on Monday, to the person's email
address. It SHALL list the announcements published for the person since the last summary, and per dashboard the
person can open, what changed since then (the "What changed" notes of dashboard-versioning). A daily summary with
nothing in it SHALL NOT be sent. A person without an email address SHALL get no summary and the section SHALL say so.

#### Scenario: Pieter asks for a daily summary

- **GIVEN** Pieter set Samenvatting per e-mail to Daily
- **AND** yesterday an announcement was published for his group and the dashboard Team Vergunningen gained a widget
- **WHEN** it is 07:30 in his time zone
- **THEN** he gets one email naming the announcement and "Team Vergunningen: Widget Teamagenda added"

#### Scenario: Nothing changed

- **GIVEN** Pieter set Daily and nothing was published or changed since his last summary
- **WHEN** it is 07:30
- **THEN** no email is sent

#### Scenario: The default

- **GIVEN** Sanne never opened her settings
- **THEN** she gets no summary email

### Requirement: A person sees every widget they hid, on every dashboard (REQ-PSET-003)

The section Verborgen widgets SHALL say how many widgets the person hid on shared dashboards ("You have hidden 2
widgets on shared dashboards") and list each one with its title, its dashboard and "Show again". "Show all again"
SHALL show every hidden widget again. Both SHALL act on the person's own layer (dashboards-and-who-may-see-them REQ-DWMS-001) and SHALL
NOT change the shared dashboard for anyone else. With nothing hidden the section SHALL say so and offer no button.

#### Scenario: Pieter shows Verjaardagen again

- **GIVEN** Pieter hid Verjaardagen and Kantinemenu on Intranet Zuiddrecht
- **WHEN** he opens Verborgen widgets and chooses Show again next to Verjaardagen
- **THEN** Verjaardagen is back on his view of Intranet Zuiddrecht, and the section lists only Kantinemenu

#### Scenario: Show all again

- **GIVEN** Pieter hid two widgets on two dashboards
- **WHEN** he chooses Show all again
- **THEN** both widgets are back on his views, and the section says nothing is hidden
