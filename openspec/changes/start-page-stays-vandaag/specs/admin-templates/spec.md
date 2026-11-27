# Delta: admin-templates

## MODIFIED Requirements

### Requirement: REQ-TMPL-019 A member is shown their template on the first visit

A user who owns no personal dashboard and has no chosen start dashboard (`default_dashboard_uuid` naming a dashboard
they can still see) MUST be shown the admin template that applies to them (REQ-TMPL-005: a template that targets one of
their groups, else the default template). Both resolution chains MUST do this at the same place, directly after the
start-dashboard step and before any group dashboard: the page shell's chain (REQ-DASH-018, step 1b) and
`GET /api/dashboard` (REQ-DASH-009).

Precedence:

- A template that targets one of the user's groups MUST outrank every group dashboard, the user's own group's default
  included.
- The default template MUST outrank the dashboards of the `default` group (everyone), including the start page
  Vandaag. It MUST step aside for a default dashboard of the user's own primary group, which is the more specific of
  the two.

What the user gets:

- With personal dashboards allowed (`allowUserDashboards`), the user MUST receive a personal copy (REQ-TMPL-005), once.
  The copy MUST be stored as their start dashboard (`default_dashboard_uuid`), so later visits resolve it through step
  1 of REQ-DASH-018. The person can change it with Set as default.
- With personal dashboards off, no copy MUST be made (REQ-ASET-003). The template itself MUST be shown, view only. Its
  compulsory flags have no effect then, because nothing can be removed.

A user who owns a personal dashboard, or has a chosen start dashboard, MUST NOT be affected by this requirement. Which
dashboard the user looked at last MUST NOT count.

There is no migration of the last-used dashboard: a member whose copy was only stored as their last-used dashboard
before this rule lands on their group default or Vandaag until they choose Set as default.

#### Scenario: A new member sees the template, although a dashboard for everyone exists

- **GIVEN** the instance has the start page Vandaag for everyone and personal dashboards are allowed
- **AND** the template "Mijn werkdag" targets the group "behandelaars"
- **AND** Sanne is in "behandelaars" and has never opened launchpad
- **WHEN** Sanne opens launchpad
- **THEN** she MUST see "Mijn werkdag", as a personal copy with the template's compulsory widgets
- **AND** her `default_dashboard_uuid` MUST name that copy, and a second visit MUST show the same copy without making
  another

#### Scenario: Without any other dashboard the page still shows the template

- **GIVEN** no group dashboard exists and the template targets Sanne's group
- **WHEN** Sanne opens launchpad for the first time
- **THEN** she MUST see the template's dashboard and MUST NOT see "No dashboards available"
@e2e exclude needs an instance with no group dashboard at all, and the e2e instance is shared with other suites' fixtures; pinned by DashboardServiceTemplateRungTest::testWithNoOtherDashboardThePageStillShowsTheTemplate

#### Scenario: The default template replaces the dashboard for everyone

- **GIVEN** the start page Vandaag exists for everyone and a default template exists
- **AND** Mark is in no group that a template targets and his primary group has no default dashboard
- **WHEN** Mark opens launchpad for the first time
- **THEN** he MUST get the default template, not Vandaag

#### Scenario: Personal dashboards off

- **GIVEN** personal dashboards are off and the template targets Sanne's group
- **WHEN** Sanne opens launchpad for the first time
- **THEN** she MUST see the template itself, view only, and no personal dashboard MUST be created for her

#### Scenario: People already using LaunchPad are left alone

- **GIVEN** Pieter owns a personal dashboard, and Lotte chose a group dashboard as her start dashboard
- **AND** a template now targets a group both are in
- **WHEN** each opens launchpad
- **THEN** neither MUST be moved to the template and no copy MUST be made for them
