# Catalogi & Service Selection

## Feature Summary

Multi-service and multi-catalogus selection system. Open Beheer can connect to multiple Open Zaak instances and each instance can have multiple catalogi. Users select a service (Open Zaak instance) and then a catalogus before they can manage zaaktypen or informatieobjecttypen.

## How It Works in Open Beheer

### Service Selection
- On login, the app fetches available ZTC services from `/api/v1/service/choices/`
- Services come from `zgw_consumers.Service` model filtered by `api_type=ztc`
- Auto-selects the first available service
- Service slug becomes part of the URL path: `/:serviceSlug/...`
- All subsequent API calls include the service slug for routing to the correct Open Zaak instance

### Catalogus Selection
- After service selection, fetches catalogi from `/api/v1/service/:slug/catalogi/choices/`
- Catalogi are fetched from Open Zaak's `catalogussen` endpoint via the BFF
- Displayed as a dropdown in the sidebar
- If only one catalogus exists, auto-selects it
- Selected catalogus ID cached in browser (via `cacheSet`)
- Catalogus UUID becomes part of the URL path: `/:serviceSlug/:catalogusId/...`

### URL Structure
- The full URL encodes both selections: `/:serviceSlug/:catalogusId/zaaktypen`
- The BFF translates catalogus UUIDs back to full Open Zaak URLs for API calls

## Technical Implementation

### Backend
- `ServiceChoicesView`: Returns `OBOption[str]` list (label=service.label, value=service.slug)
- `CatalogChoicesView`: Fetches from Open Zaak, returns `OBOption[str]` list (label=naam or domein, value=catalogus URL)
- Service configuration managed via Django admin (zgw-consumers Service model)

### Frontend
- `useService` hook: Fetches services after user is authenticated
- `useCatalogi` hook: Fetches catalogi when service changes, handles auto-navigation
- `serviceLoader`: Redirects to first service on initial page load
- App.tsx sidebar: Renders catalogus dropdown + navigation items

## Already in OpenRegister

- **Multi-register support**: OpenRegister has registers as first-class entities, similar to catalogi
- **Register selection**: Users can switch between registers in the admin UI
- **Schema-scoped views**: Objects are always viewed within a register + schema context

## Not Yet in OpenRegister

- **Multi-backend support**: OpenRegister doesn't connect to multiple external backend instances. It IS the backend. Open Beheer's multi-service support is specific to the proxy architecture.
- **Sidebar catalogus dropdown**: OpenRegister's register selection is different -- it's in the navigation rather than a dropdown within a sidebar.
- **Cached selection with auto-navigation**: The browser caching of last-selected catalogus with automatic re-navigation is a nice UX touch.
