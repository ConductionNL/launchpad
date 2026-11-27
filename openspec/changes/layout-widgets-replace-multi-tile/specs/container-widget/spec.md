# Delta: container-widget

## ADDED Requirements

### Requirement: A well leaves out tiles the viewer may not open (REQ-CONT-008)

Inside a well (and inside a tab of the Tabs widget), an App or link tile in app mode for an app the viewer may not open
SHALL NOT render for that viewer, and the remaining tiles SHALL close up in the well's sort order. Outside a well such a
tile keeps saying the app is not available (app-tile-widget REQ-APPTILE-002).

#### Scenario: A member without Deck

- **GIVEN** a group dashboard with a well holding tiles for Files, Deck and Talk
- **AND** a member for whom Deck is not enabled
- **WHEN** the member opens the dashboard
- **THEN** the well shows Files and Talk, side by side, and nothing for Deck
