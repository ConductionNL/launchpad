# Delta for dashboards: personal view of a shared dashboard

## ADDED Requirements

### Requirement: A person can hide a widget for themselves (REQ-PERSUI-001)

In view mode every widget that is not compulsory MUST offer "Hide for me" in its menu. Hiding MUST save the personal layer and reload the dashboard, and MUST NOT change what any other person sees.

#### Scenario: Hide one widget

- **GIVEN** Pieter views the shared dashboard "Team" that shows a weather widget he never uses
- **WHEN** he chooses "Hide for me" on the weather widget
- **THEN** the widget is gone for Pieter, and Sanne still sees it on the same dashboard

#### Scenario: Compulsory widget

- **GIVEN** the administrator marked the "Safety notice" widget compulsory
- **WHEN** Pieter opens its menu
- **THEN** no "Hide for me" item is offered

### Requirement: A person can bring hidden widgets back (REQ-PERSUI-002)

A "Hidden (n)" control MUST list the widgets the person hid, each with "Show again", and MUST be absent when nothing is hidden. "Reset my view" MUST delete the whole layer after confirmation.

#### Scenario: Show one again

- **GIVEN** Pieter hid two widgets
- **WHEN** he opens "Hidden (2)" and chooses "Show again" on one
- **THEN** that widget returns and the control now reads "Hidden (1)"

#### Scenario: Reset

- **GIVEN** Pieter hid two widgets and moved a third
- **WHEN** he confirms "Reset my view"
- **THEN** all three return to the shared layout

