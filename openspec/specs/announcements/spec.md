# announcements Specification

## Purpose
News items and notices an author publishes to the people they target: news shows in the announcements widget, where
readers like and comment through Nextcloud's comments and follow categories, and a notice shows as a banner above every
dashboard of the people it targets until its end time.

## Requirements

### Requirement: Authors publish targeted announcements (REQ-ANN-001)

Administrators and the people to whom Nextcloud's admin delegation gives Launchpad's announcements setting
(admin-settings REQ-ADM-034) MUST be able to write an announcement, on the Announcements tab of Launchpad's admin page
(REQ-ANN-006), with a title, text, optional category, target groups, an optional publish time and a required end time
("Show until", nl "Tonen tot"). The server MUST check that setting before every create, change, publish, end or delete.
The server MUST show a published announcement only to people in its target groups (everyone when none are set) between
its publish time and its end time, and MUST show drafts only to managers. The server MUST refuse a new announcement
without an end time, or with an end time before its publish time, with HTTP 400; an announcement stored before this rule
without an end time MUST keep showing until a manager sets one. The form MUST explain that the announcement disappears
from every dashboard after its end time, MUST name the end time in its preview ("zichtbaar tot 30 oktober 17:00"), and
its confirm button MUST read "Schedule" ("Inplannen") when the publish time lies in the future and "Publish" otherwise.

#### Scenario: News for one group

- **GIVEN** Karin holds the announcements setting
- **WHEN** she publishes "Inloopspreekuur privacy" in category "Privacy" for group "Medewerkers", shown until Friday 17:00
- **THEN** Pieter, a member of "Medewerkers", sees it in his Announcements widget
- **AND** Sanne, who is not a member, does not see it and gets 404 when she requests it by id

#### Scenario: Reader cannot author

- **GIVEN** Pieter is not an administrator and does not hold the announcements setting
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

- **GIVEN** Karin sets the publish time to next Monday 08:00 on the Announcements tab
- **THEN** the confirm button reads "Schedule"

### Requirement: Readers like and comment through Nextcloud comments (REQ-ANN-002)

Readers MUST be able to like an announcement that targets them and, when the author allowed it, comment on it, in the
announcement's own view (REQ-ANN-011), not in the widget. Likes and comments MUST be stored by Nextcloud's comments
service, not in Launchpad tables, and the view MUST show the like and comment counts.

#### Scenario: Pieter likes and comments

- **GIVEN** the announcement "Nieuwe werkplekken op de 3e verdieping" allows comments and targets everyone
- **WHEN** Pieter opens it from the widget, likes it and comments "Komen er ook sta-bureaus?"
- **THEN** the view shows 1 like and 1 comment
- **AND** his comment is visible to every reader of the announcement

### Requirement: Readers follow categories (REQ-ANN-003)

A reader MUST be able to follow and unfollow an announcement's category from the announcement's own view (REQ-ANN-011),
not from the widget. When an announcement in a followed category becomes visible to a follower, the follower MUST
receive one Nextcloud notification.

#### Scenario: Follow Privacy

- **GIVEN** Pieter follows "Privacy" from the view of a Privacy announcement
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

A notice MUST have an end time. While it is visible it MUST show as a banner above the dashboard grid on the Workspace
page of everyone it targets, whichever dashboard they open, with its level shown by icon and text, not colour alone, and
with the read row of REQ-ANN-007 (boards LpIntranet, LpVandaag), with the same meaning as in the widget: the check MUST
confirm the notice and keep the banner shown, marked read, until its end time, and the x MUST confirm it and hide the
banner for that reader. Every notice MUST show both; a stored "may not be dismissed" setting MUST be ignored.

#### Scenario: Maintenance banner

- **GIVEN** a published warning notice for "Burgerzaken" from Friday 17:00 to Saturday 12:00
- **WHEN** Pieter, in "Burgerzaken", opens any dashboard on Friday at 18:00
- **THEN** he sees the banner "Onderhoud zaaksysteem zaterdag 08:00 tot 12:00" with a warning icon, the check and the x
- **AND** on Saturday at 12:01 the banner is gone

#### Scenario: Notice without an end time

- **GIVEN** Karin writes a notice
- **WHEN** she tries to publish it without an end time
- **THEN** publishing fails with "A notice needs an end time"

#### Scenario: The check keeps the banner, the x hides it

- **GIVEN** a notice for everyone
- **WHEN** Sanne presses its check
- **THEN** the banner stays, marked read, and Sanne is counted as confirmed via check

#### Scenario: The x on a notice confirms it

- **GIVEN** a notice for everyone
- **WHEN** Pieter presses its x
- **THEN** the banner closes for him and the Announcements tab counts him as confirmed via x

### Requirement: The announcement form as LpAankondiging draws it (REQ-ANN-006)

The announcement form SHALL be a section of the Announcements tab of Launchpad's admin page (board LpAankondiging), not a
dialog and not part of the widget: the form on the left and "This is how it looks" on the right, a live preview of the
card that follows every field, and the note that only managers see it until the publication time. The form SHALL ask
Title, Text, Target group, the kind as two choice cards (News; Maintenance notice, shown at the top of every dashboard
until the end time), the read confirmation with its deadline, "Allow likes, comments and following", Publish on
(optional) and Show until. It SHALL end with Cancel, Save (Schedule or Publish for a new one, REQ-ANN-001), and for a
stored announcement End now and Delete; Delete asks first.

#### Scenario: Write a news item

- **GIVEN** Femke chose New announcement on the Announcements tab
- **WHEN** she types the title
- **THEN** the preview on the right shows it at once, without saving

#### Scenario: End an announcement now

- **GIVEN** an active announcement on the tab
- **WHEN** Femke chooses End now
- **THEN** its end time becomes now, it leaves every reader's dashboard and the list shows it as ended

### Requirement: An announcement asks for a read confirmation (REQ-ANN-007)

Every announcement SHALL show a read row to each reader in its audience, in the widget, on the banner and in its own
view: a check ("Read", nl "Gelezen") and an x, and, when the author asked for a confirmation, the date to confirm before.
Pressing the check or the x SHALL record a confirmation for that reader with which of the two they pressed; both count
as confirmed (Ruben's review of 27 Nov 2026, item 15). In the widget and on the banner alike, the check SHALL mark the item
read and keep it shown, and the x SHALL hide it for that reader (Ruben, 27 Nov 2026). The confirmations SHALL feed Seen and confirmed (REQ-ANN-009).

#### Scenario: Confirm before Friday

- **GIVEN** the announcement asks for confirmation before Friday 30 October
- **THEN** each reader sees the check, the x and "Confirm before 30 October"
- **AND** the preview on the tab says "Confirm before 30 October"

#### Scenario: The check confirms

- **GIVEN** Pieter sees the announcement in his widget
- **WHEN** he presses the check
- **THEN** the check is filled, the item stays, and he is counted as confirmed via check

### Requirement: The Announcements tab lists every announcement (REQ-ANN-008)

The Announcements tab of Launchpad's admin page (board LpAankondiging) SHALL list every announcement, news and notices,
active, scheduled and ended, each with its kind, its period, the number of people who have seen it and who have confirmed
it, and the actions Edit and Seen and confirmed; above the list New announcement. Below it, a second list "Widgets that
ask for confirmation" SHALL hold every widget placement that asks readers to confirm (dashboard-acknowledgements
REQ-ACK-008). It SHALL be reachable from Settings in
the More menu (entry Announcements) and from the widget's Manage link, for managers only. The tab's open announcement and
section SHALL be in the URL fragment.

#### Scenario: Femke opens the tab

- **GIVEN** Femke holds the announcements setting and is not an administrator
- **WHEN** she chooses Announcements under Settings in the More menu
- **THEN** the Announcements tab opens and lists the active, scheduled and ended announcements with their seen and
  confirmed counts

### Requirement: Seen and confirmed per announcement (REQ-ANN-009)

Seen and confirmed (board LpLeesbevestigingen) SHALL show, for one announcement, the audience size, how many have seen
it and not yet seen it, how many confirmed via the check and via the x, and how many have not confirmed; filters
Everyone, Confirmed, Not confirmed, Not seen; a list of people with their status and the time; Download (CSV with every
person, status, kind and time); and Send reminder, which sends one Nextcloud notification to every audience member who
has not confirmed, at most once a day per announcement. A person SHALL count as seen the first time the announcement was
on their screen, in the widget, on the banner or in its own view; recording it SHALL NOT delay the dashboard's first
render. Only managers SHALL read this (403 otherwise).

#### Scenario: 151 of 164 seen

- **GIVEN** "Nieuwe werkwijze Woo-verzoeken" was seen by 151 of 164 people and confirmed by 121 via the check and 7 via
  the x
- **WHEN** Femke opens Seen and confirmed
- **THEN** she sees 151 seen, 13 not yet seen, 128 confirmed (121 check, 7 x) and 36 not confirmed

#### Scenario: A reader cannot read the receipts

- **WHEN** Pieter requests the receipts of an announcement
- **THEN** the response is 403

### Requirement: The Announcements widget only shows (REQ-ANN-010)

The Announcements widget SHALL show the announcements that target the reader, each with its title, date, a short text,
the read row (REQ-ANN-007) and Open (REQ-ANN-011). It SHALL NOT offer New announcement, an edit form, likes, comments or
follow (Ruben's review of 27 Nov 2026, items 15 and 16). For a manager it SHALL show Manage, which opens the
Announcements tab on that announcement. The widget's settings SHALL only set what it shows (categories, how many).

#### Scenario: Pieter's widget has no management or social buttons

- **GIVEN** Pieter is a reader
- **WHEN** his dashboard shows the Announcements widget
- **THEN** each item shows the check, the x and Open, and no like, comment, follow, New or Edit button

#### Scenario: Femke manages from the widget

- **GIVEN** Femke holds the announcements setting
- **WHEN** she chooses Manage on an item
- **THEN** the Announcements tab of the admin page opens on that announcement

### Requirement: An announcement has its own view (REQ-ANN-011)

Open on a widget item or on the banner SHALL open the announcement's view, a modal with its full text, its read row,
and, when the author allowed them, likes, comments and follow for its category (REQ-ANN-002, REQ-ANN-003). For a manager
it SHALL also offer Manage.

#### Scenario: Open an announcement

- **GIVEN** an announcement that allows comments
- **WHEN** Pieter chooses Open on it in the widget
- **THEN** a modal shows the full text, the check and the x, the like button, the comments and Follow "Privacy"
