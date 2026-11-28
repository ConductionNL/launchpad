# Delta: runtime-shell

## MODIFIED Requirements

### Requirement: REQ-SHELL-009 No navigation panel; the destinations are in the More menu

On every page other than the dashboard view, `App.vue` renders the More menu through `CnAppRoot`'s `#header-actions`
slot. When the page header draws an action row (the store page's, an index page's header buttons), More SHALL be that
row's last button, after the page's own actions such as Publish (board LpStore). Without such a row it SHALL sit at the
top end of the content, with that column kept free so a page's own header actions are never covered.

#### Scenario: More is the last button of the store page header

- **GIVEN** a member on the Marketplace page, whose header draws Publish
- **WHEN** the page renders
- **THEN** the More menu MUST sit in the page header's action row, after Publish
- **AND** it MUST NOT be placed outside the content column
