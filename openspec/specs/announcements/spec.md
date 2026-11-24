# announcements Specification

## Purpose
News items and notices an author publishes to the people they target: news shows in the announcements widget, where
readers like and comment through Nextcloud's comments and follow categories, and a notice shows as a banner above every
dashboard of the people it targets until its end time.

## Requirements

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

### Requirement: Readers like and comment through Nextcloud comments (REQ-ANN-002)

Readers MUST be able to like an announcement that targets them and, when the author allowed comments, comment on it. Likes and comments MUST be stored by Nextcloud's comments service, not in LaunchPad tables, and the widget MUST show the like and comment counts.

#### Scenario: Pieter likes and comments

- **GIVEN** the announcement "Nieuwe werkplekken op de 3e verdieping" allows comments and targets everyone
- **WHEN** Pieter likes it and comments "Komen er ook sta-bureaus?"
- **THEN** the card shows 1 like and 1 comment
- **AND** his comment is visible to every reader of the announcement

### Requirement: Readers follow categories (REQ-ANN-003)

A reader MUST be able to follow and unfollow a category. When an announcement in a followed category becomes visible to a follower, the follower MUST receive one Nextcloud notification.

#### Scenario: Follow Privacy

- **GIVEN** Pieter follows "Privacy"
- **WHEN** Karin's scheduled announcement "Nieuwe privacyverklaring" in "Privacy" reaches its publish time
- **THEN** Pieter gets one notification "New announcement in Privacy: Nieuwe privacyverklaring"

### Requirement: Authors preview before publishing (REQ-ANN-004)

The editor MUST offer a preview that renders the announcement with the same component readers see, as a card for news and as a banner for a notice, together with the number of people the targeting reaches. Publishing MUST be a separate action after the preview.

#### Scenario: Preview a notice

- **GIVEN** Karin writes a notice "Onderhoud zaaksysteem zaterdag 08:00 tot 12:00" for group "Burgerzaken"
- **WHEN** she chooses "Preview"
- **THEN** she sees the warning banner as readers will and "Reaches 42 people"
- **AND** nobody else sees it until she chooses "Publish"

### Requirement: Notices show above every targeted dashboard for their period (REQ-ANN-005)

A notice MUST have an end time. While it is visible it MUST show as a banner above the dashboard grid on the Workspace page of everyone it targets, whichever dashboard they open, with its level shown by icon and text, not colour alone. A reader MUST be able to dismiss a dismissible notice for themselves; a non-dismissible notice MUST stay until its end time.

#### Scenario: Maintenance banner

- **GIVEN** a published, non-dismissible warning notice for "Burgerzaken" from Friday 17:00 to Saturday 12:00
- **WHEN** Pieter, in "Burgerzaken", opens any dashboard on Friday at 18:00
- **THEN** he sees the banner "Onderhoud zaaksysteem zaterdag 08:00 tot 12:00" with a warning icon and no close button
- **AND** on Saturday at 12:01 the banner is gone

#### Scenario: Notice without an end time

- **GIVEN** Karin writes a notice
- **WHEN** she tries to publish it without an end time
- **THEN** publishing fails with "A notice needs an end time"

### Requirement: The announcement form as LpAankondiging draws it (REQ-ANN-006)

New announcement SHALL open one dialog with the form on the left and "This is how it looks" on the right: a live preview of the card that follows every field, and the note that only the author sees it until the publication time. The form SHALL ask Title, Text, Target group, the kind as two choice cards (News; Maintenance notice, shown at the top of every dashboard until the end time), the read confirmation, "Allow comments and following", Publish on (optional) and Show until. The dialog SHALL end with Cancel and Schedule (Publish when Publish on is empty or past).

#### Scenario: Write a news item

- **WHEN** Pieter types the title
- **THEN** the preview on the right shows it at once, without saving

### Requirement: An announcement asks for a read confirmation (REQ-ANN-007)

An author SHALL be able to ask the audience to confirm they have read an announcement, with a date to confirm before. The card SHALL then show "I have read this" to each reader in the audience, and the confirmations SHALL feed the read receipts report.

#### Scenario: Confirm before Friday

- **GIVEN** the announcement asks for confirmation before Friday 30 October
- **THEN** each reader sees "I have read this" and the preview says "Confirm before 30 October"
