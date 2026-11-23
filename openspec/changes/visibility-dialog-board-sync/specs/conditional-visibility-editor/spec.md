# Delta: conditional-visibility-editor

## ADDED Requirements

### Requirement: The rules dialog as LpZichtbaarheid draws it (REQ-CVUI-006)

The dialog SHALL be titled "Who sees this widget?" under "<widget> · <dashboard>", SHALL offer "If all rules match" and "If one rule matches" as choice cards, SHALL show one card per rule with its kind (Groups, Times, Period, Profile field), its value and Edit and delete buttons, then "Add rule", and SHALL end with "View as": a person and a moment, and a sentence that says whether that person sees the widget and why.

#### Scenario: View as Sanne

- **GIVEN** the rules ask for group KCC and Monday to Friday 08:00 to 17:00
- **WHEN** the editor picks Sanne de Vries and Tuesday 13 October 10:00
- **THEN** the sentence reads that Sanne sees this widget, because she is in KCC and the moment is within the times

### Requirement: The dialog is in the widget's menu (REQ-CVUI-007)

In edit mode the widget's menu SHALL hold "Who sees this widget?", which opens the dialog, so it is reachable by keyboard and not only by right-click.

#### Scenario: From the menu

- **WHEN** the editor opens the menu of the News widget and chooses Who sees this widget?
- **THEN** the dialog opens for that widget
