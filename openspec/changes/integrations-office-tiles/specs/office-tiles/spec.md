# Delta for office-tiles

## ADDED Requirements

### Requirement: The add flow offers an office tile pack built from what the server has (REQ-OFT-001)

The add flow MUST offer "Office tiles". Choosing it MUST place one container titled "Office" with a "New ..." tile for each file creator the server's office apps register, and tiles for Files, Mail, Calendar, Contacts and Talk when those apps are enabled for the user. It MUST NOT list an app the user cannot open.

#### Scenario: Pack on a server with Nextcloud Office

- **GIVEN** Nextcloud Office, Mail and Calendar are enabled and Contacts is not
- **WHEN** Pieter chooses "Office tiles" while editing "Mijn werkplek"
- **THEN** a container "Office" appears at the bottom with "New document", "New spreadsheet", "New presentation", "Files", "Mail" and "Calendar"
- **AND** there is no "Contacts" tile

### Requirement: A "New ..." tile creates a real document and never overwrites (REQ-OFT-002)

Clicking a "New ..." tile MUST ask for a name and folder, create the file from the office app's own blank template, choose a free name when the file exists, and open the new file in its editor.

#### Scenario: New document twice

- **GIVEN** Pieter's container "Office" has "New document"
- **WHEN** he clicks it, names the file "Notulen" and confirms
- **THEN** `Documents/Notulen.docx` is created from the editor's blank template and opens in the editor
- **AND WHEN** he does the same again, `Documents/Notulen (2).docx` is created and the first file is unchanged

#### Scenario: Unknown creator refused

- **GIVEN** a request to `POST /api/office-tiles/create` naming a creator id the server does not register
- **WHEN** the server handles it
- **THEN** the response is 400 and no file is created
