# Delta: runtime-shell

## MODIFIED Requirements

### Requirement: The admin area has its own first-visit tour (REQ-SHELL-011)

LaunchPad MUST declare a second guided tour, `launchpad:admin-getting-started`, for the LaunchPad admin settings page. It MUST start on an administrator's first visit to that page and walk through six stops, in this order, as board LpBeheerAlgemeen draws it ("Rondleiding door het beheer, stap 1 van 6"): the tabs General ("Algemeen"), Templates ("Sjablonen"), Roles and rights ("Rollen en rechten"), Sharing ("Delen"), Kiosk and Demo data ("Demogegevens"). Each stop MUST name its tab and say in one line what is set there; the first stop MUST say the tour is shown once. Each stop MUST offer Skip and Next. Completion MUST be stored per user like the Workspace tour (REQ-SHELL-010). A person who is not an administrator never sees it.

#### Scenario: An administrator's first visit to the admin page

- **GIVEN** Noor is a Nextcloud administrator who never opened the LaunchPad admin page
- **WHEN** she opens it
- **THEN** the admin tour starts on the General tab with "step 1 of 6"

#### Scenario: The tour visits the six tabs in order

- **GIVEN** Noor is on the first stop of the admin tour
- **WHEN** she chooses Next five times
- **THEN** the tour has visited General, Templates, Roles and rights, Sharing, Kiosk and Demo data, in that order

#### Scenario: A finished admin tour stays closed

- **GIVEN** Noor completed the admin tour
- **WHEN** she opens the admin page again
- **THEN** the admin tour does not start
