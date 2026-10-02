---
kind: code
depends_on: []
---

# Ready-made tiles for the office suite

## Why

Every organisation wants the same first row of tiles: new document, new
spreadsheet, new presentation, files, mail, calendar, contacts. In LaunchPad
each is set up by hand, and a "new document" button today goes through the
link-button's `createFile` flow (`POST /api/files/create`, REQ-LBN-004), which
writes an empty file with a fixed name and overwrites a file of that name if it
exists. An empty `.docx` is not a valid document either.

Matrix row **int-office-tiles** (`openspec/parity/capabilities.json`), "Get
ready-made tiles for the office suite without setting each one up", rated
`no`, `built.state` `none`.

- Workspace 365, yes: https://support.workspace365.net/en/articles/175363-full-guide-for-user ready tiles for documents, email, calendar and address book from Microsoft 365.
- Microsoft Viva, yes: https://learn.microsoft.com/en-us/sharepoint/homesites/available-dashboard-cards "Microsoft apps cards are available out of the box when SharePoint app in Teams is enabled".

## What changes

- The add flow offers an "Office tiles" pack. One click places a container with a tile for each office action and app this server has: new document, new spreadsheet, new presentation (when Nextcloud Office, ONLYOFFICE or another editor registers them), and Files, Mail, Calendar, Contacts and Talk when those apps are enabled.
- "New ..." tiles ask for a name, create the file from the editor's own blank template in the person's chosen folder (default "Documents"), never overwrite, and open it in the editor.
- Administrators can add the pack to a template dashboard, so every new user starts with it.

## Capabilities

### New capabilities

- `office-tiles`: the office tile pack and the create-from-template action.

## Impact

- New `OfficeTilesService` over `OCP\Files\Template\ITemplateManager` (the creators Nextcloud's Files "New" menu uses) and `IAppManager`
- New `GET /api/office-tiles` and `POST /api/office-tiles/create`, with a new action in `lib/actions.seed.json`
- `src/modals/WidgetPickerModal.vue` (the pack), `src/components/TileWidget.vue` (a `create` link type that opens the name dialog)

## Out of scope

- Microsoft 365 tiles. LaunchPad links to Nextcloud's own office apps; a Microsoft 365 link is an ordinary web tile.
- Changing REQ-LBN-004's overwrite behaviour for the link-button widget. The office tiles use their own create path; the overwrite is noted as a follow-up for that widget.
