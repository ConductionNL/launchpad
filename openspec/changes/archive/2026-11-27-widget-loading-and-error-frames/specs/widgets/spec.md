# Delta: widgets

## ADDED Requirements

### Requirement: Every widget shows its own loading and error state (REQ-WDG-046)

No widget SHALL hold up the first render of a dashboard. Every widget's card (frame, title, place in the grid) SHALL
render at once, with a loading state (a busy status, "Loading…") in place of its content until the content mounts and
asks its source (REQ-WDG-044). A widget whose content fails to show (an error while it sets up, renders or runs a
lifecycle hook) SHALL show "Widget not available" with Retry inside its own card, and every other widget SHALL render
as if it were not there. Retry SHALL mount the content afresh. An error in a widget's event handler or watcher SHALL
NOT replace the widget.

#### Scenario: A widget's card shows a loading state first

- **GIVEN** Pieter's start page has a widget below the fold
- **WHEN** he opens the start page
- **THEN** that widget's card is in the grid with a loading state until it comes into view

#### Scenario: A failing widget stays in its own card
@e2e exclude no shipped widget can be made to throw while it renders without changing its code; pinned by src/components/__tests__/WidgetErrorBoundary.spec.js ('keeps a widget that fails to render inside its card, beside a working one', 'mounts the widget afresh on Retry')

- **GIVEN** Pieter's start page has a weather widget whose content throws while it renders, and a news widget
- **WHEN** he opens the start page
- **THEN** the weather widget's card says "Widget not available" with Retry
- **AND** the news widget shows its news

