# Delta: people-widget

## ADDED Requirements

### Requirement: People listings honour the user-enumeration settings (REQ-PPL-015)

Every signed-in user SHALL be allowed to list people (`people-widget.get-users` is in the non-admin action baseline,
revision 9), so the People, Birthdays and New colleagues widgets work for everyone. What a listing shows SHALL follow
Nextcloud's user-enumeration settings (Administration > Sharing), for `GET /api/people` (browsing, searching and a
widget's group filter) and `GET /api/people/new` alike:

- enumeration open (Nextcloud's default): the whole directory, within the widget's own filters;
- enumeration restricted to the viewer's groups: only members of a group the viewer is in; a widget's group filter is
  narrowed to the viewer's groups, so a group the viewer is not in lists nobody;
- enumeration off: nobody;
- restricted to the phone book only: nobody, because a listing has no phone book to match.

An administrator who narrowed `people-widget.get-users` before revision 9 SHALL keep that setting.

#### Scenario: Every user may list people

- **GIVEN** an instance upgraded to action baseline revision 9 with enumeration open
- **WHEN** Pieter, an ordinary user, opens a dashboard with a People widget
- **THEN** the widget lists his colleagues instead of answering HTTP 403

#### Scenario: Listing restricted to own groups

- **GIVEN** Pieter and Anna are in the group "wonen" and Bram only in "zorg"
- **AND** user enumeration is restricted to the user's own groups
- **WHEN** Pieter searches the People widget
- **THEN** he finds Anna and himself, and not Bram

#### Scenario: Enumeration off lists nobody

- **GIVEN** user enumeration is off
- **WHEN** Pieter opens the People widget or the New colleagues list
- **THEN** nobody is listed

