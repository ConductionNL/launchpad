---
capability: wiki-widget
status: draft
---

# Wiki widget

## Purpose

The organisation's wiki on a dashboard: search, the person's favourite pages and the recent pages.

## ADDED Requirements

### Requirement: The wiki widget shows favourites, recent pages and search (REQ-WIKI-001)

The `wiki-pages` widget SHALL read pages through OpenRegister's XWiki integration, never with credentials in the
browser. It SHALL show a search field, the person's favourites and the recent pages, at most `limit` each (default
3). Each page SHALL be a link into the wiki that opens in a new tab, with its space as detail. A page address that is
not http(s) SHALL NOT become a link. Typing SHALL search the wiki once typing pauses; a cleared search SHALL show the
favourites and recent pages again. When OpenRegister answers that no XWiki source is set up, the widget SHALL say the
wiki is not connected and ask the person to contact their administrator.

#### Scenario: Pieter's wiki

- **GIVEN** the wiki holds "Werkinstructie kapvergunningen", "Termijnen Omgevingswet" and "Legesverordening 2026"
- **WHEN** Pieter opens a dashboard with the Wiki widget
- **THEN** he sees his favourites and the recent pages as links into the wiki
- **AND** searching "leges" shows "Legesverordening 2026"

#### Scenario: The wiki is not connected

- **GIVEN** no `xwiki` source is set up in the connector app
- **WHEN** a person opens a dashboard with the Wiki widget
- **THEN** the widget says "The wiki is not connected yet. Ask your administrator to connect XWiki."

### Requirement: A person keeps favourite pages (REQ-WIKI-002)

A star on every page SHALL add it to or remove it from the person's favourites. A new favourite SHALL come first. The
favourites SHALL be kept in the person's launchpad preference `wiki-favourites`, at most 20, so they follow the person
across dashboards and devices. When saving fails, the favourites SHALL stay as they were.

#### Scenario: Starring a page

- **WHEN** Pieter stars "Termijnen Omgevingswet" and reloads the page
- **THEN** "Termijnen Omgevingswet" is the first of his favourites

### Requirement: A team wiki shows one space (REQ-WIKI-003)

With `space` set, the widget SHALL show only the pages of that space, in favourites, recent pages and search results.
The form SHALL offer the space and the number of pages per list, 1 to 20.

#### Scenario: Wiki of the team

- **GIVEN** a Wiki widget with space "Vergunningen" on the department page
- **THEN** only pages of the space "Vergunningen" show

### Requirement: The picker offers the wiki only where it can work (REQ-WIKI-004)

The picker SHALL offer the Wiki widget only when OpenRegister is enabled for the viewer, as the initial state's
`integrationApps` says. Outside edit mode a placed Wiki widget SHALL be left out for a viewer without OpenRegister; in
edit mode it SHALL render and explain that OpenRegister is needed.

#### Scenario: No OpenRegister

- **GIVEN** an instance where OpenRegister is disabled for the person
- **WHEN** they open the widget picker
- **THEN** "Wiki" is not offered
