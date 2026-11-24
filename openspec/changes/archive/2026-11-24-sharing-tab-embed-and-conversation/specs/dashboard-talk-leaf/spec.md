# Delta: dashboard-talk-leaf

## ADDED Requirements

### Requirement: The conversation is switched on from the Sharing tab (REQ-LEAF-006)

The Sharing tab of a shared dashboard SHALL hold "Conversation beside this dashboard" with a switch "Show the conversation beside the dashboard". A dashboard that is not shared SHALL NOT show this part and SHALL say that the conversation appears once it is shared.

#### Scenario: A personal dashboard

- **GIVEN** My workspace is not shared
- **THEN** its Sharing tab says "This dashboard is yours alone. Share it with people or a group and the conversation appears here too."
