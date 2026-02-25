# Design: plot-applications-on-views

## Architecture Overview

The frontend already calls OpenRegister directly for views (`/openregister/api/objects/vng-gemma/view/{id}`). It can use the same pattern to fetch applications and gebruik data, then merge everything client-side.

**Decision: Frontend-driven enrichment via direct OpenRegister calls**

No backend changes needed. The frontend fetches views, applications, and gebruik as separate OpenRegister queries, then merges them into overlay viewNodes before rendering.

```
Frontend (filter toggles)
  → GET /openregister/api/objects/vng-gemma/view/{id}          (base view — already working)
  → GET /openregister/api/objects/voorzieningen/gebruik?_extend[]=module  (when gebruik toggle ON)
  → Frontend merges: match module.referentieComponenten against view's viewNodes by modelNodeId
  → Inject module overlay nodes as children of matching referentiecomponenten
  → Render all viewNodes through existing pipeline (topological sort → renderToGraph → setNodeColor)
```

The softwarecatalog ViewService/ViewController enrichment API is dead code — the frontend never used it. It should be removed (tracked separately).

## API Design

### Existing: View (no changes)

```
GET /openregister/api/objects/vng-gemma/view/{id}
```

Returns the base GEMMA view with `viewNodes` and `viewRelationships`. No changes needed.

### New frontend call: Gebruik with extended module data

```
GET /openregister/api/objects/voorzieningen/gebruik?_extend[]=module&_limit=1000
```

**Response (abbreviated):**
```json
{
  "results": [
    {
      "id": "uuid-gebruik-1",
      "afnemer": "uuid-org-a",
      "module": {
        "id": "uuid-module-1",
        "naam": "Exact Online",
        "referentieComponenten": [
          { "id": "id-abc123", "name": "Financieel component" }
        ]
      },
      "deelnemers": ["uuid-org-b", "uuid-org-c"],
      "@self": { "organisation": "uuid-org-a" }
    }
  ]
}
```

Using `_extend[]=module` inlines the full module object (including its `referentieComponenten`). This avoids a second API call to fetch modules separately.

### Frontend Merge Logic

For each gebruik result:
1. Extract `module.referentieComponenten` — array of GEMMA element references
2. For each referentiecomponent, find matching `viewNode` by `modelNodeId`
3. Create an overlay viewNode as a child of that viewNode:

```javascript
{
  viewNodeId: `gebruik-${gebruik.id}-${refComp.id}`,
  modelNodeId: module.id,
  name: module.naam,
  type: 'module',
  parent: matchingViewNode.viewNodeId,
  x: 5,       // small offset inside parent
  y: matchingViewNode.height - 20,
  width: matchingViewNode.width - 10,
  height: 18,
  color: 'rgb(200, 255, 200)',
  borderColor: 'rgba(0, 150, 0, 0.6)',
  _isModuleOverlay: true,
  _gebruikId: gebruik.id,
  _isDeelname: gebruik['@self'].organisation !== activeOrgId
}
```

4. Append overlay nodes to viewNodes array before topological sort

### Filter Toggle Behavior

| Frontend filter | Action |
|----------------|--------|
| `gebruik` ON | Fetch gebruik, create overlay nodes for modules owned by active org |
| `deelnames` ON | Same fetch, also include overlay nodes where `_isDeelname: true` |
| Both OFF | No gebruik fetch, render base view only |
| `product` ON | Future — not implemented in this change |

## Database Changes

None. All data already exists in OpenRegister.

## Nextcloud Integration

No backend changes. The softwarecatalog ViewService enrichment API is unused dead code.

## File Structure

Frontend-only changes:

```
tilburg-woo-ui/
  src/
    stores/
      gemma.store.js           # Add fetchGebruik action, store gebruik data
    api/
      gemma.api.js             # Add gebruik() API method
    constants/
      endpoints.constants.js   # Add GEMMA.GEBRUIK endpoint
    views/
      con-beheer-views/
        con-beheer-views.js    # Merge gebruik→module overlay nodes, style in setNodeColor
      ac-gemma/
        ac-gemma-view.js       # Same overlay logic for public view
```

## Security Considerations

- **RBAC**: OpenRegister applies RBAC on the gebruik query — only returns objects for the active organization. No special handling needed.
- **Deelnames**: Gebruik objects where the active org is in `deelnemers` may need the softwarecatalog `/api/aangeboden-gebruik/deelnemers` endpoint since OpenRegister RBAC would filter them out. Investigate during implementation — if OpenRegister's `deelnemers` filter works without RBAC bypass, use it directly.

## NL Design System

- Module overlay nodes use a distinct color scheme (green tones) to differentiate from GEMMA reference components
- Deelnames overlay nodes use a different shade (lighter green / different opacity)
- No new NL Design System components needed

## Trade-offs

### Decision 1: Frontend-driven merge vs. backend enrichment API

**Chosen: Frontend-driven (direct OpenRegister calls)**
- Pro: Simple — no backend code to maintain, same pattern as existing view fetching
- Pro: OpenRegister already handles RBAC, pagination, `_extend` joins
- Pro: Avoids the buggy, untested ViewService enrichment code path
- Con: Multiple API calls (view + gebruik), but they can run in parallel
- Con: Merge logic lives in frontend JavaScript

### Decision 2: Copy module nodes per referentiecomponent vs. single node with links

**Chosen: Copy per referentiecomponent**
- Pro: Works naturally with parent-child hierarchy in ViewRenderer
- Con: An application with 5 referentiecomponenten appears 5 times
- This is correct behavior — it shows which referentiecomponenten the application fulfills

### Decision 3: Module node positioning

**Chosen: Small bar inside bottom of parent referentiecomponent**
- Module node rendered as a narrow strip at the bottom of the parent
- Multiple modules stack vertically
- Keeps the overall layout stable (no overflow outside parent bounds)

## Risks and Mitigations

| Risk | Mitigation |
|------|------------|
| Large orgs (1000+ apps) → large gebruik response | `_limit=1000` with pagination if needed; only fetch when filter is ON |
| Module positioning overlaps | Stack vertically inside parent with small height |
| Deelnames RBAC filtering | Test if OpenRegister returns deelnemers-matched objects; fall back to softwarecatalog endpoint if not |
| `_extend[]=module` may not inline `referentieComponenten` | Test and verify; may need `_extend[]=module.referentieComponenten` or separate fetch |

## Open Questions

1. Does `_extend[]=module` recursively include `referentieComponenten` on the module object, or do we need a nested extend?
2. Should module overlay nodes be clickable (navigate to application detail)?
3. For deelnames, does OpenRegister's search support `deelnemers=<orgUuid>` filter without RBAC bypass?
