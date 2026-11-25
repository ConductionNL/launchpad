# Delta: widgets

## ADDED Requirements

### Requirement: A widget below the fold loads when it comes into view (REQ-WDG-044)

On a dashboard in view mode, a widget's content SHALL mount, and so ask its source, only when its card comes within one
viewport height of the visible area, or once the page has settled (2.5 seconds after the card appeared, at the browser's next
idle moment), or when the page is about to print, whichever comes first. Its card (frame, title, place in the grid) SHALL
show from the start. A widget whose content has mounted SHALL stay mounted. In edit mode, in the headless export render
(`?export=1`) and in a browser without IntersectionObserver every widget's content SHALL mount at once.

#### Scenario: The case list below the fold waits for the reader

- **GIVEN** Pieter's start page has the case list widget below the fold
- **WHEN** he opens the start page
- **THEN** the widgets on screen ask their sources and the case list does not yet
- **AND** when he scrolls towards the case list it asks its source and shows the cases

#### Scenario: Everything has loaded before a download

- **GIVEN** Pieter opened his start page and did not scroll
- **WHEN** the page has settled, or he chooses Download
- **THEN** every widget has mounted, so the printed dashboard shows every widget's content

#### Scenario: Edit mode shows every widget at once

- **GIVEN** Pieter edits his start page
- **WHEN** the dashboard shows in edit mode
- **THEN** every widget's content mounts at once
