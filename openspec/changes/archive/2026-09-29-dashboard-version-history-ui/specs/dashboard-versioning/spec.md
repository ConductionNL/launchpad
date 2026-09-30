# Delta for dashboard-versioning: history and restore screens

## ADDED Requirements

### Requirement: The owner sees the version history (REQ-VERSUI-001)

The dashboard menu MUST show "Version history" to the dashboard owner and to administrators when versioning is supported for the dashboard. It MUST list versions newest first with number, date, author and note.

#### Scenario: Owner opens the history

- **GIVEN** Sanne owns the personal dashboard "Mijn week" and it has four saved versions
- **WHEN** she chooses "Version history" from the dashboard menu
- **THEN** a dialog lists the four versions newest first with date and author

#### Scenario: Colleague sees no entry

- **GIVEN** Pieter can view but does not own "Mijn week" and is not an administrator
- **WHEN** he opens the dashboard menu
- **THEN** no "Version history" entry is shown

#### Scenario: Unsupported dashboard

- **GIVEN** a groupfolder-backed dashboard for which `modeSupported` is false
- **WHEN** the owner opens the dashboard menu
- **THEN** the entry is hidden

### Requirement: A person can save a named version (REQ-VERSUI-002)

The history dialog MUST offer "Save this version now" with an optional note, calling the explicit snapshot endpoint, and the new version MUST appear at the top of the list.

#### Scenario: Named snapshot

- **GIVEN** Sanne is about to rearrange her dashboard
- **WHEN** she types "before the reorganisation" and saves a version
- **THEN** the list shows a new top entry with that note

### Requirement: Restoring asks first and can be undone (REQ-VERSUI-003)

Restore MUST ask for confirmation naming the version date, restore through the restore endpoint, and reload the dashboard. The list MUST then show the `pre-restore` version so the restore can itself be reversed.

#### Scenario: Restore last week's layout

- **GIVEN** Sanne changed her dashboard on Monday and wants Friday's layout back
- **WHEN** she restores the Friday version and confirms
- **THEN** the dashboard reloads with the Friday layout and the list holds a new `pre-restore` entry with Monday's layout

