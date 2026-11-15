# Delta for widgets: picker search and automatic refresh

## ADDED Requirements

### Requirement: The widget picker narrows its list as you type (REQ-WDG-028)

The widget picker MUST show a search box above the list of widgets and apps you may add. Typing MUST narrow both lists to the entries whose title contains the typed text, ignoring case. Clearing the box MUST bring back the full lists. When nothing matches, the picker MUST say so instead of showing empty lists.

#### Scenario: Typing narrows the list

- **GIVEN** Pieter opens "Add widget" on his dashboard
- **WHEN** he types "cal" in "Search widgets"
- **THEN** the picker lists only the widgets and apps whose title contains "cal", such as "Calendar"

#### Scenario: Clearing the search

- **GIVEN** Pieter typed "cal" in "Search widgets"
- **WHEN** he clears the search box
- **THEN** the picker lists every widget and app again

#### Scenario: Nothing matches

- **GIVEN** Pieter opens "Add widget"
- **WHEN** he types "zzz"
- **THEN** the picker says that no widget matches
- **AND** it shows no empty list

### Requirement: A Nextcloud widget with a reload interval refreshes itself (REQ-WDG-029)

When a Nextcloud dashboard widget declares a reload interval above zero, LaunchPad MUST fetch its items again at that interval while the widget is on the page. The page MUST NOT reload. The timer MUST stop when the widget leaves the page. A widget without a reload interval MUST load its items once.

#### Scenario: Items refresh on the widget's interval

- **GIVEN** a widget on Pieter's dashboard declares a reload interval of 60 seconds
- **WHEN** 60 seconds pass with the dashboard open
- **THEN** the widget shows its items fetched again
- **AND** the page itself does not reload

#### Scenario: No interval, no refresh

- **GIVEN** a widget declares a reload interval of 0
- **WHEN** Pieter keeps the dashboard open
- **THEN** the widget loads its items once and does not fetch them again

#### Scenario: Leaving the dashboard stops the timer

- **GIVEN** a widget with a reload interval is on the page
- **WHEN** Pieter switches to another dashboard
- **THEN** the widget's refresh timer stops

### Requirement: Another app's widget shows that app's items for the viewer (REQ-WDG-030)

When a person places a widget that another Nextcloud app registers, such as the Mail app's mailbox widget or Deck's upcoming cards, LaunchPad MUST ask that app for the items it holds for the viewer and list them with their title, subtitle and icon. Each item MUST link to the item in its own app. When the app returns no items, the widget MUST show the app's own empty message.

#### Scenario: A mailbox preview

- **GIVEN** the Mail app is enabled and Pieter placed its mailbox widget on "Mijn werkplek"
- **WHEN** he opens the Workspace page
- **THEN** the widget lists his latest messages with sender and subject
- **AND** clicking a message opens it in Mail

#### Scenario: Open tasks from a planning app

- **GIVEN** Deck is enabled and Sanne placed its upcoming cards widget on her dashboard
- **WHEN** she opens the Workspace page
- **THEN** the widget lists the cards assigned to her with their due dates
- **AND** clicking a card opens it in Deck

#### Scenario: Nothing to show

- **GIVEN** Pieter's mailbox has no messages the Mail widget returns
- **WHEN** he opens the Workspace page
- **THEN** the widget shows the empty message the Mail app supplies
