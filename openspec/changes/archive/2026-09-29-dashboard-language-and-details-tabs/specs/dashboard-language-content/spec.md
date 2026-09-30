# Delta for dashboard-language-content: settings tab and reader resolution

## ADDED Requirements

### Requirement: A dashboard owner manages language versions in the settings dialog (REQ-LANGUI-001)

The dashboard settings dialog MUST have a "Languages" tab, hidden while creating, listing each variant with its language and marking the primary. It MUST let the owner add a variant blank or copied from an existing language, edit its name and description, delete a non-primary variant, and make another variant primary.

#### Scenario: Add a Dutch version

- **GIVEN** Sanne owns "Intranet" whose only variant is English
- **WHEN** she opens Settings, Languages, chooses "Add language", picks Dutch and "Copy from English"
- **THEN** a Dutch variant is listed with the copied content and she can edit its name

#### Scenario: Primary switch

- **GIVEN** "Intranet" has English (primary) and Dutch
- **WHEN** she makes Dutch primary
- **THEN** Dutch is marked primary and English is a normal variant

### Requirement: The workspace page shows the reader's language (REQ-LANGUI-002)

When a dashboard has more than one variant, the workspace page MUST show the variant that matches the reader's Nextcloud language, and the primary with a visible note when none matches.

#### Scenario: Reader in Dutch

- **GIVEN** "Intranet" has Dutch and English variants and Jan's Nextcloud language is Dutch
- **WHEN** Jan opens "Intranet"
- **THEN** he sees the Dutch name and description

#### Scenario: Reader in German

- **GIVEN** the same dashboard and Petra's language is German
- **WHEN** Petra opens it
- **THEN** she sees the primary variant and a note "Shown in the primary language"

