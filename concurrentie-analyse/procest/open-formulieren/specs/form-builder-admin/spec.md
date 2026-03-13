# Form Builder Admin Interface

## What Open Forms Does

### Django Admin + React
- Backend: Django admin with custom views and inline editors
- Frontend: React-based form builder embedded in admin
- Form.io Builder component for visual drag-and-drop form design

### Form Builder Features
- Drag-and-drop component placement
- Component configuration panels (validation, conditional logic, prefill, registration attribute mapping)
- Preview mode
- Multi-step form design with step ordering
- Logic rule builder (simple mode with dropdowns, advanced mode with raw JSON)
- Variable management panel
- Registration backend configuration per form
- Auth backend selection per form
- Payment backend configuration

### Custom Components
Beyond standard Form.io components, Open Forms adds:
- `addressNL` -- Dutch address with postcode lookup
- `bsn` -- BSN validation
- `iban` -- IBAN validation
- `licenseplate` -- Dutch license plate format
- `postcode` -- Dutch postcode format
- `cosign` -- Co-sign trigger (v1 and v2)
- `np-family-members` -- Family member data entry (partners/children)
- `currency` -- Currency input
- `map` -- Leaflet map for location selection
- `editGrid` -- Repeating group of fields
- `customerProfile` -- Customer profile component

### Form Management
- Form categories for organization
- Form versioning (automatic on save)
- Form import/export (ZIP format)
- Form copying/duplication
- Submission statistics tracking

### Admin Digest Emails
- Periodic digest email to admins with:
  - Failed registrations
  - Failed emails
  - Failed prefill plugins
  - Invalid configurations (certificates, logic rules, backends)
  - Expiring reference lists

## Already in Procest

- Basic admin interfaces in Nextcloud for case/pipeline management
- OpenRegister schema editor

## Not Yet in Procest

- **Visual form builder** -- No drag-and-drop form designer
- **Form.io component library** -- No rich component palette (address, IBAN, BSN, etc.)
- **Form preview** -- No live preview of form being built
- **Logic rule builder UI** -- No visual interface for defining conditional logic
- **Variable management panel** -- No dedicated UI for managing form variables and their prefill/registration bindings
- **Form categories** -- No category-based form organization
- **Admin digest emails** -- No periodic admin health check emails
- **Form duplication** -- No one-click form copying
