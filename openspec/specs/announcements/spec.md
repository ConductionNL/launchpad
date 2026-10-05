---
status: building
---

# Announcements specification

## Purpose

Authors write news items and notices for the people in chosen groups. News shows in the Announcements widget with likes, comments and category follows; a notice shows as a banner above every dashboard of the people it targets, for its period. Targeting is checked on the server for every read. Likes and comments are stored by Nextcloud's comments service.

The decisions behind it are in `openspec/changes/engagement-announcements/design.md`.

## Requirements

### Requirement: Authors publish targeted announcements (REQ-ANN-001)

Administrators and members of the announcement editor groups MUST be able to write an announcement with a title, text, optional category, target groups, publish time and optional end time. The server MUST show a published announcement only to people in its target groups (everyone when none are set) between its publish time and its end time, and MUST show drafts only to authors and administrators.

#### Scenario: News for one group

- **GIVEN** Karin is in the announcement editor group
- **WHEN** she publishes "Inloopspreekuur privacy" in category "Privacy" for group "Medewerkers"
- **THEN** Pieter, a member of "Medewerkers", sees it in his Announcements widget
- **AND** Sanne, who is not a member, does not see it and gets 404 when she requests it by id

#### Scenario: Reader cannot author

- **GIVEN** Pieter is not an administrator and not in the editor group
- **WHEN** he sends `POST /api/announcements`
- **THEN** the response is 403

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
- **THEN** she sees the warning banner as readers will and "People reached: 42"
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
