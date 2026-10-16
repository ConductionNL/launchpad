# Delta for people-widget: expertise and custom fields

## ADDED Requirements

### Requirement: Administrators define custom profile fields (REQ-PEX-001)

An administrator MUST be able to define up to 10 custom profile fields, each with a label, a type (text or tags), a source (filled by the person, or read from an LDAP attribute), whether it is searchable, whether it shows in the people widget, and who may see it (everyone, or members of the person's groups).

#### Scenario: Office location from LDAP

- **GIVEN** Noor is an administrator and accounts come from LDAP
- **WHEN** she adds the field "Kantoorlocatie" of type text, source LDAP attribute `physicalDeliveryOfficeName`, shown in the widget
- **THEN** after Pieter next signs in, his people card shows "Kantoorlocatie: Stadhuis, 3e verdieping"
- **AND** Pieter sees the field read-only on his LaunchPad personal settings

### Requirement: People list their own expertise (REQ-PEX-002)

A person MUST be able to fill the fields sourced from themselves on their LaunchPad personal settings, including tags for expertise.

#### Scenario: Pieter adds his subjects

- **GIVEN** the field "Expertise" of type tags, filled by the person, searchable
- **WHEN** Pieter adds the tags "subsidies" and "Omgevingswet" on his personal settings and saves
- **THEN** his people card shows the two tags

### Requirement: Search finds people across the directory by profile and expertise (REQ-PEX-003)

With a query of 2 or more characters, the people widget MUST ask the server, which MUST match display name, email, role, headline, biography and every searchable custom field across the whole directory and return the matches a page at a time. Shorter queries MUST keep the current-page filter of REQ-PPL-011.

#### Scenario: Sanne finds the subsidies expert

- **GIVEN** Pieter has the tag "subsidies" and is not on the first page of the people widget
- **WHEN** Sanne types "subsidie" in the widget's search box
- **THEN** Pieter is in the results with the tag highlighted

#### Scenario: Clicking a tag searches for it

- **GIVEN** Pieter's card shows the tag "Omgevingswet"
- **WHEN** Sanne clicks the tag
- **THEN** the widget shows everyone tagged "Omgevingswet"

### Requirement: Search and display respect profile visibility (REQ-PEX-004)

A standard profile field MUST be matched and shown only when the person's Nextcloud visibility setting lets the viewer see it. A custom field MUST be matched and shown only to the audience its definition allows. Search results MUST NOT be served from a cache shared between viewers.

#### Scenario: Private biography is not searchable

- **GIVEN** Karin's biography mentions "subsidies" and its visibility is private
- **WHEN** Sanne searches "subsidies"
- **THEN** Karin is not in the results on the strength of her biography
- **AND** her card does not show the biography

#### Scenario: Group-only field

- **GIVEN** the field "Kostenplaats" is visible to members of the person's groups only, and Sanne shares no group with Pieter
- **WHEN** Sanne views Pieter's card or searches his cost centre
- **THEN** the field is not shown and does not match
