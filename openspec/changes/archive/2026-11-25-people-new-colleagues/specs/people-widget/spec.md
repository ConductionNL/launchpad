# Delta: people-widget

## ADDED Requirements

### Requirement: A profile field can hold a start date (REQ-PEX-005)

A custom profile field (REQ-PEX-001) MAY have the type date. An administrator MAY mark one date field as the start
date ("in dienst sinds"). The start date SHALL follow the field's source and visibility like any custom field, and SHALL
be read through the same visibility checks (REQ-PEX-004). Marking another field as the start date SHALL unmark the
previous one.

#### Scenario: Start date from LDAP
@e2e exclude needs an LDAP directory server and the user_ldap app, which the CI fixture does not provide; pinned by tests/Unit/Service/ProfileFieldServiceAccessGoldenTest.php::testAnLdapStartDateIsReadAsACalendarDate (generalized time and ISO dates read as 2026-10-12, an unreadable value read as none) and ::testOneDateFieldIsTheStartDate

- **GIVEN** the administrator adds a date field "In dienst sinds", source LDAP attribute `employeeStartDate`, marked as the start date
- **WHEN** Yasmina's LDAP entry holds 12 October 2026
- **THEN** her start date is 12 October 2026

### Requirement: The people widget can list new colleagues (REQ-PPL-014)

The people widget SHALL offer `selectionMode: 'new'` ("New colleagues", nl "Nieuwe collega's"). It SHALL list the
people whose start date lies between `newSinceDays` days ago (default 30, range 1 to 90) and `newUntilDays` days ahead
(default 14, range 0 to 30), newest start first, with photo, name, role and team, within the widget's other filters
(groups, disabled accounts). A person who has not started yet SHALL be marked with the day they start ("begint
maandag"). Only people whose start date the viewer may see SHALL be listed. When no field is marked as the start date,
the form SHALL not offer the mode and a placement that uses it SHALL say that the administrator has not set a start
date field.

#### Scenario: Three new colleagues on the intranet

- **GIVEN** Thomas started on 28 September, Lotte on 1 October and Yasmina starts on 12 October
- **AND** today is 5 October 2026 and the widget uses `selectionMode: 'new'` with the defaults
- **WHEN** Pieter opens Intranet Zuiddrecht
- **THEN** "Nieuwe collega's" lists Yasmina with "begint maandag", then Lotte, then Thomas, each with role and team

#### Scenario: No start date field

- **GIVEN** no profile field is marked as the start date
- **WHEN** an author opens the people widget form
- **THEN** the selection offers no "New colleagues"
