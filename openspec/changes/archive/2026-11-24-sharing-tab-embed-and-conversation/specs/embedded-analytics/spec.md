# Delta: embedded-analytics

## ADDED Requirements

### Requirement: Embed tokens on the Sharing tab (REQ-EMB-011)

The Sharing tab of a dashboard's settings SHALL hold "Embed on your website" for the dashboard's owner and administrators: a list of the dashboard's embed tokens with what they show (the whole dashboard or one widget), the allowed website, the state (Active with its views, or Revoked with the date and reason) and Withdraw; the code for the website of the selected token; and "Create new embed token". A new token SHALL be shown once with Copy and the note that it is not shown again.

#### Scenario: Create a token

- **WHEN** Pieter creates the token "Website Zuiddrecht" for the widget Open cases per case type and the site www.zuiddrecht.nl
- **THEN** the token is shown once with Copy, the table lists it as Active, and the code for the website shows the iframe
