# Delta for dashboard-view-analytics: a personal opt-out switch

## ADDED Requirements

### Requirement: A person turns view counting off in their own settings (REQ-ANLT-UI-001)

LaunchPad's personal settings MUST show a "Count my visits" switch, on by default. Turning it off MUST store `analytics_optout = true` for that person, so REQ-ANLT-004 ignores their view and tile-click events. Turning it on again MUST store `false`. The switch MUST NOT show when an administrator has turned analytics off for everyone.

#### Scenario: A person stops their visits being counted

- **GIVEN** Eva is signed in and analytics is on for the organisation
- **WHEN** she opens her LaunchPad settings and turns "Count my visits" off
- **THEN** her user value `analytics_optout` is `true`
- **AND** her next dashboard visit adds nothing to the view counts

#### Scenario: A person counts again

- **GIVEN** Eva turned "Count my visits" off earlier
- **WHEN** she turns it on again
- **THEN** her user value `analytics_optout` is `false`
- **AND** her next dashboard visit is counted

#### Scenario: Analytics is off for everyone

- **GIVEN** an administrator turned analytics off for the organisation
- **WHEN** Eva opens her LaunchPad settings
- **THEN** the "Count my visits" switch is not shown
