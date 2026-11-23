# Delta: iframe-embed-widget

## ADDED Requirements

### Requirement: The outage state of an embedded report (REQ-IFRAME-005)

An embedded page or report SHALL show under its title where it comes from ("Embedded report · app.powerbi.com · signed in as <name>" when the connection signs in). When it cannot load, the card head SHALL show an "Outage" tag and the body SHALL say "The report could not be loaded", the cause and its time when the connection knows them ("gave an error today at 09:30: token expired"), with Reload and "Open in a new tab".

#### Scenario: Token expired

- **GIVEN** the Power BI connection failed at 09:30 with an expired token
- **THEN** the card shows Outage, the cause with 09:30, Reload and Open in a new tab
