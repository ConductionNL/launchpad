## ADDED Requirements

### Requirement: A placement that asks for confirmation looks like an announcement (REQ-ACK-011)

In the board look, a placement that asks its readers to confirm they read it SHALL be drawn as an announcement: a 4px
bar in the primary colour on the card's left edge, its title and content 24px in from the left and 20px from the
right, and a 17px title. It SHALL keep this frame after the reader confirmed and in edit mode.

#### Scenario: The Woo announcement on Vandaag
- **GIVEN** Pieter's Vandaag board holds "Nieuwe werkwijze Woo-verzoeken vanaf 1 november", which asks for a read confirmation
- **WHEN** he opens Vandaag
- **THEN** the card has a bar in the primary colour on its left edge and a 17px title

@e2e exclude A frame of the board look; pinned by src/components/__tests__/WidgetWrapper.announcement.spec.js and measured with compdiff on LpVandaag / LpIntranet.
