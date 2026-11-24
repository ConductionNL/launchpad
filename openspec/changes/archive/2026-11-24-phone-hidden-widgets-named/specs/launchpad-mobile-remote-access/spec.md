# Delta: launchpad-mobile-remote-access

## ADDED Requirements

### Requirement: The phone notice names the hidden widgets (REQ-MRA-007)

When widgets are hidden on a phone (REQ-MRA-003), the notice at the top of the workspace SHALL say how many and name
them by title: "2 widgets do not work on a phone and are hidden: Snel beginnen (Word) and Assistent." (nl "2 widgets
werken niet op een telefoon en zijn verborgen: ..."), followed by a link "Which?" ("Welke?") that explains why a widget
can be hidden on a phone and that the person sees it on a larger screen. The notice SHALL replace the "view desktop
layout" link of REQ-MRA-003; there SHALL be no forced desktop layout on a phone. With more than three hidden widgets
the notice SHALL name three and count the rest.

#### Scenario: Pieter on his phone

- **GIVEN** Mijn werkplek holds Snel beginnen and Assistent, neither ready for a phone
- **WHEN** Pieter opens it at 390 px
- **THEN** the notice reads "2 widgets werken niet op een telefoon en zijn verborgen: Snel beginnen (Word) en Assistent." with the link "Welke?"
- **AND** no "view desktop layout" link is shown

#### Scenario: Many hidden widgets

- **GIVEN** five widgets on a dashboard are not ready for a phone
- **WHEN** it opens at 390 px
- **THEN** the notice names three of them and says "and 2 more"
