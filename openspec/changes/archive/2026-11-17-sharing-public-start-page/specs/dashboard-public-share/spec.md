# Delta for dashboard-public-share: public start page

## ADDED Requirements

### Requirement: Administrators choose a public link as the start page (REQ-PSP-001)

An administrator MUST be able to pick one existing public link and switch on the public start page. The server MUST refuse a link with a password or with an expiry in the past. When the chosen link is later revoked, protected or expired, the start page MUST switch itself off and the admin section MUST say why.

#### Scenario: Library start page

- **GIVEN** Noor has a public link for the dashboard "Bibliotheek Noord" without a password
- **WHEN** she picks it as the public start page on the Sharing tab and switches it on
- **THEN** the Sharing tab shows "Public start page: Bibliotheek Noord"

#### Scenario: Protected link refused

- **GIVEN** a public link protected by a password
- **WHEN** Noor tries to pick it as the start page
- **THEN** it is not offered, and a direct request to save it returns 400

### Requirement: Signed-out visitors land on the start page (REQ-PSP-002)

When the public start page is on, a visitor who is not signed in and opens the plain login page (no redirect address, no `direct` parameter) MUST be taken to the start page. A visitor following a deep link MUST still get the login form with the redirect kept.

#### Scenario: Visitor opens the address

- **GIVEN** the public start page is on
- **WHEN** a visitor who is not signed in opens `https://cloud.bibliotheek.nl/`
- **THEN** they see the dashboard "Bibliotheek Noord" read-only with a "Sign in" button

#### Scenario: Deep link keeps the login form

- **GIVEN** the public start page is on
- **WHEN** a signed-out staff member opens a link to `/apps/files/`
- **THEN** the login form shows, and after signing in they land in Files

### Requirement: The start page offers a way in for staff (REQ-PSP-003)

The start page MUST show a "Sign in" button that opens the login form with `direct=1`, so the visitor is not sent back to the start page and single sign-on setups keep working.

#### Scenario: Staff member signs in

- **GIVEN** Pieter is on the public start page
- **WHEN** he clicks "Sign in"
- **THEN** the login form opens at `/login?direct=1`
- **AND** after signing in he lands on his Workspace page
