# Delta: image-widget

## ADDED Requirements

### Requirement: The Image content form (REQ-IMG-020)

The content part of an Image widget SHALL ask "Where does the image come from?" with Upload file (from Files or the computer, up to 5 MB) or Web address, SHALL show a chosen file with its name, size and dimensions and a way to choose another, SHALL require a text for who does not see the image, and SHALL offer an optional link on click.

#### Scenario: Alternative text is required

- **WHEN** the editor saves an image without alternative text
- **THEN** the form refuses and names the field
