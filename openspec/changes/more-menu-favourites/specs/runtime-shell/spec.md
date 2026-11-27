# Delta: runtime-shell

## MODIFIED Requirements

### Requirement: REQ-SHELL-004 The dashboards are reached through More

The shell MUST NOT render a sidebar-toggle (hamburger) button, a title strip with the active dashboard's name, or a slide-in dashboards panel. Dashboards are switched in the More menu only (REQ-SHELL-016).

- The Dashboards group marks the open dashboard (`role="menuitemradio"`, `aria-checked="true"`) and ends in "More" (folded) or "Fewer" (unfolded).

#### Scenario: No dashboards panel
- **GIVEN** Pieter opens a dashboard
- **THEN** the page has no `.dashboard-switcher-sidebar` and no backdrop
- **AND** the header's only button is More

## ADDED Requirements

### Requirement: REQ-SHELL-016 The Dashboards column lists the favourites and unfolds in place

Folded, the More menu's Dashboards column MUST list the person's favourite dashboards (REQ-SHELL-017) in the order of the full list; a person without favourites sees the first eight dashboards. Its last item "More" MUST unfold the column in place, keeping the menu open, into a scrollable list of every dashboard the person can open, ending in "Fewer", which folds it again. Every opening of the menu MUST start folded.

#### Scenario: Favourites only
- **GIVEN** Pieter starred "Team Vergunningen" and can open five dashboards
- **WHEN** he opens More
- **THEN** the Dashboards column lists "Team Vergunningen" and "More"

#### Scenario: More unfolds in place
- **GIVEN** the menu is open and folded
- **WHEN** Pieter chooses "More"
- **THEN** the menu stays open and the column lists all five dashboards, each with a star, and ends in "Fewer"

### Requirement: REQ-SHELL-017 A star marks a dashboard as a favourite, per person

Every row of the unfolded list MUST carry a star button (`aria-pressed`), empty for a dashboard that is not a favourite and filled yellow for one that is. Clicking it MUST toggle the favourite without switching dashboards and store the person's list (preference `favourite-dashboards`, a JSON list of uuids). A failed write MUST put the previous list back. "Set as default" stays a separate action.

#### Scenario: Starring survives a reload
- **GIVEN** Pieter unfolds the list
- **WHEN** he clicks the empty star of "Intranet"
- **THEN** the star fills, the list is stored, and after a reload the folded column lists "Intranet"

### Requirement: REQ-SHELL-018 Powered by and Download live in the More menu

The More menu MUST end in a footer under both columns with the Documentation link and "Powered by" with the sendent and Conduction logos, each opening in a new tab (`rel="noopener noreferrer"`). The This dashboard group MUST offer "Download" (after "Copy link to this dashboard") to everyone who can open the dashboard; the page header MUST NOT carry its own Download button.

#### Scenario: Download from More
- **GIVEN** Pieter may only view a dashboard
- **WHEN** he chooses More > Download
- **THEN** his browser prints the dashboard as it is now
