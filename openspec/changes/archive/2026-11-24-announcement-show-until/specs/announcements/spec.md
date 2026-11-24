# Delta: announcements

## MODIFIED Requirements

### Requirement: Authors publish targeted announcements (REQ-ANN-001)

Administrators and members of the announcement editor groups MUST be able to write an announcement with a title, text, optional category, target groups, an optional publish time and a required end time ("Show until", nl "Tonen tot", board LpAankondiging). The server MUST show a published announcement only to people in its target groups (everyone when none are set) between its publish time and its end time, and MUST show drafts only to authors and administrators. The server MUST refuse a new announcement without an end time, or with an end time before its publish time, with HTTP 400; an announcement stored before this rule without an end time MUST keep showing until an author sets one. The form MUST explain that the announcement disappears from every dashboard after its end time, MUST name the end time in its preview ("zichtbaar tot 30 oktober 17:00"), and its confirm button MUST read "Schedule" ("Inplannen") when the publish time lies in the future and "Publish" otherwise.

#### Scenario: News for one group

- **GIVEN** Karin is in the announcement editor group
- **WHEN** she publishes "Inloopspreekuur privacy" in category "Privacy" for group "Medewerkers", shown until Friday 17:00
- **THEN** Pieter, a member of "Medewerkers", sees it in his Announcements widget
- **AND** Sanne, who is not a member, does not see it and gets 404 when she requests it by id

#### Scenario: Reader cannot author

- **GIVEN** Pieter is not an administrator and not in the editor group
- **WHEN** he sends `POST /api/announcements`
- **THEN** the response is 403

#### Scenario: An end time is required

- **GIVEN** Karin writes an announcement without a "Show until"
- **WHEN** she sends `POST /api/announcements`
- **THEN** the response is 400 and names the missing end time

#### Scenario: The announcement disappears after its end time

- **GIVEN** an announcement shown until 30 October 17:00
- **WHEN** Pieter opens his dashboard on 30 October at 17:01
- **THEN** the announcement is not shown

#### Scenario: Scheduling for later

- **GIVEN** Karin sets the publish time to next Monday 08:00
- **THEN** the confirm button reads "Schedule"
