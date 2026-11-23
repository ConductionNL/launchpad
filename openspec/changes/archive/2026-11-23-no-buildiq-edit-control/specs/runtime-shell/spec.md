# runtime-shell Delta

## ADDED Requirements

### Requirement: The page header holds only launchpad's own actions (REQ-SHELL-NOBQ)

Launchpad's pages SHALL NOT show the Buildiq in-app edit button, also where Buildiq is enabled. The manifest SHALL set
`openbuildEditable: false`.

#### Scenario: Buildiq is enabled

- **GIVEN** an instance with Buildiq enabled
- **WHEN** a person opens a launchpad dashboard
- **THEN** the page header shows no Buildiq edit button
