# Delta: widgets

## ADDED Requirements

### Requirement: The widgets on the first screen render in the first pass (REQ-WDG-047)

A widget whose top row starts within the first screen of the workspace SHALL mount in the dashboard's first render,
without waiting for it to be reported in view. As soon as the dashboard's placements are known, the renderer of every
widget type on the first screen SHALL start loading. Widgets further down SHALL keep loading when they come into view
(REQ-WDG-044).

#### Scenario: The first screen renders at once
@e2e exclude a timing property (no observer round, chunk requested early); pinned by src/utils/__tests__/firstViewport.spec.js and src/components/__tests__/WidgetWrapper.spec.js ('mounts a widget on the first screen at once and leaves one further down to the observer'); measured in the PR on the reference stack

- **GIVEN** Pieter's start page has tiles in its top rows and a chart far below
- **WHEN** he opens the start page
- **THEN** the tiles render in the first pass, with their renderer requested as soon as the dashboard is known
- **AND** the chart shows a loading state until it comes into view
