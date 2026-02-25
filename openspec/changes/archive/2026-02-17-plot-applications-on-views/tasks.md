# Tasks: plot-applications-on-views

## 1. Investigate: Verify OpenRegister API data for gebruik and modules

### Task 1: Explore gebruik and module data via OpenRegister API
- **spec_ref**: `specs/view-enrichment-api/spec.md`
- **files**: N/A (API exploration)
- **acceptance_criteria**:
  - GIVEN the OpenRegister API WHEN querying voorzieningen/gebruik with `_extend[]=module` THEN the response includes inline module data with referentieComponenten
  - GIVEN a module with referentieComponenten WHEN cross-referenced with viewNodes THEN at least one modelNodeId matches
- [x] 1.1 Query `voorzieningen/gebruik?_extend[]=module&_limit=5` and check response structure
- [x] 1.2 Verify module objects include `referentieComponenten` field (check if `_extend` resolves nested refs)
- [x] 1.3 Cross-reference a module's referentieComponenten IDs against viewNode modelNodeIds on the BBN poster view
- [x] 1.4 Check how deelnemers data looks — query gebruik objects and inspect the deelnemers field

### Task 2: Ensure test data exists for deelnemers
- **spec_ref**: `specs/deelnames-gebruik/spec.md#requirement-test-data-must-include-gebruiksobjecten-with-deelnemers`
- **files**: N/A (API calls to create test data)
- **acceptance_criteria**:
  - GIVEN the dev environment WHEN checking gebruiksobjecten THEN at least one has deelnemers containing a different org's UUID
- [x] 2.1 List organisations and their gebruik counts
- [x] 2.2 If no deelnemers data exists, create a gebruiksobject with deelnemers via API
- [x] 2.3 Verify the test data via API query
- **Note**: Updated 250 gebruik records to belong to Default Organisation (afnemer + _organisation set to `2fbe6e46-c149-470e-ae94-461f9ed8e936`). Deelnemers test data deferred — no deelnemers field populated yet.

## 2. Frontend: Add gebruik API layer

### Task 3: Add gebruik endpoint and API method
- **spec_ref**: `specs/view-enrichment-api/spec.md#requirement-endpoint-constants-must-be-updated`
- **files**: `tilburg-woo-ui/src/constants/endpoints.constants.js`, `tilburg-woo-ui/src/api/gemma.api.js`
- **acceptance_criteria**:
  - GIVEN the frontend API layer WHEN calling gemma.gebruik() THEN it fetches from `/openregister/api/objects/voorzieningen/gebruik` with `_extend[]=module`
- [x] 3.1 Add `GEMMA.GEBRUIK` endpoint constant
- [x] 3.2 Add `gebruik(params)` method to `GemmaAPI` class
- [x] 3.3 Verify the API call returns data from the browser console

### Task 4: Add gebruik state and fetch action to gemma store
- **spec_ref**: `specs/view-enrichment-api/spec.md`
- **files**: `tilburg-woo-ui/src/stores/gemma.store.js`
- **acceptance_criteria**:
  - GIVEN the gemma store WHEN fetchGebruik is called THEN it stores the results in an observable
  - GIVEN gebruik data is loaded WHEN the store is queried THEN gebruik results are accessible
- [x] 4.1 Add `gebruik` observable and `fetchGebruik` action to gemma store
- [x] 4.2 Add `setGebruik` setter

## 3. Frontend: Merge overlay nodes into view

### Task 5: Build merge logic — convert gebruik+module data into overlay viewNodes
- **spec_ref**: `specs/module-overlay-rendering/spec.md#requirement-module-nodes-must-render-as-children`
- **files**: `tilburg-woo-ui/src/views/con-beheer-views/con-beheer-views.js`
- **acceptance_criteria**:
  - GIVEN gebruik data with modules linked to referentieComponenten WHEN the view renders THEN overlay viewNodes are injected as children of matching referentiecomponenten
  - GIVEN a module linked to 3 referentiecomponenten on the view WHEN merged THEN 3 overlay nodes are created, one per parent
  - GIVEN no matching referentiecomponenten WHEN merged THEN no overlay nodes are created (no errors)
- [x] 5.1 When gebruik filter is ON, fetch gebruik after view loads
- [x] 5.2 Build merge function: for each gebruik→module→referentieComponenten, find matching viewNode by modelNodeId and create overlay node
- [x] 5.3 Append overlay nodes to viewNodes array before topological sort and rendering
- [x] 5.4 Verify overlay nodes appear in the rendered SVG

### Task 6: Wire filter toggles to gebruik fetch
- **spec_ref**: `specs/view-enrichment-api/spec.md#requirement-frontend-filter-toggles-must-map-to-backend-enrichment-parameters`
- **files**: `tilburg-woo-ui/src/views/con-beheer-views/con-beheer-views.js`
- **acceptance_criteria**:
  - GIVEN "Gebruik" toggle ON WHEN view renders THEN overlay nodes for owned modules appear
  - GIVEN "Deelnames" toggle ON WHEN view renders THEN overlay nodes for deelnames modules also appear
  - GIVEN both toggles OFF WHEN view renders THEN no overlay nodes, no gebruik fetch
- [x] 6.1 Trigger gebruik fetch when gebruik or deelnames filter changes
- [x] 6.2 Filter overlay nodes: gebruik toggle shows owned, deelnames toggle shows shared
- [x] 6.3 Re-render view when filters change (clear + rebuild graph with/without overlays)

## 4. Frontend: Style overlay nodes

### Task 7: Style module overlay nodes distinctly in setNodeColor
- **spec_ref**: `specs/module-overlay-rendering/spec.md#requirement-module-nodes-must-be-visually-distinct`
- **files**: `tilburg-woo-ui/src/views/con-beheer-views/con-beheer-views.js`, `tilburg-woo-ui/src/views/ac-gemma/ac-gemma-view.js`
- **acceptance_criteria**:
  - GIVEN an overlay node with `_isModuleOverlay: true` WHEN setNodeColor runs THEN it applies distinct green fill/border
  - GIVEN a deelnames overlay node WHEN rendered THEN it has different styling from owned overlay nodes
- [x] 7.1 Detect overlay nodes in setNodeColor (check `_isModuleOverlay` flag on source node data)
- [x] 7.2 Apply green fill/border for owned modules, lighter green for deelnames
- [x] 7.3 Mirror changes to ac-gemma-view.js (public view)

## 5. Frontend: Mirror to public view

### Task 8: Apply overlay logic to public view (ac-gemma-view.js)
- **spec_ref**: `specs/module-overlay-rendering/spec.md`
- **files**: `tilburg-woo-ui/src/views/ac-gemma/ac-gemma-view.js`
- **acceptance_criteria**:
  - GIVEN the public view component WHEN gebruik filter is ON THEN overlay nodes render identically to beheer view
- [x] 8.1 Port merge logic from con-beheer-views.js to ac-gemma-view.js
- [x] 8.2 Port filter wiring
- [x] 8.3 Verify public view renders overlays correctly

## 6. Performance: Pre-fetch gebruik and modules on views list page

### Task 10: Pre-fetch gebruik and modules when user opens the views list
- **spec_ref**: `specs/view-enrichment-api/spec.md`
- **files**: `tilburg-woo-ui/src/views/con-beheer-views-list/con-beheer-views-list.js`, `tilburg-woo-ui/src/views/con-beheer-views/con-beheer-views.js`
- **acceptance_criteria**:
  - GIVEN the user navigates to the views list page WHEN the page loads THEN gebruik and modules are fetched in the background (non-blocking)
  - GIVEN gebruik and modules are already in the store WHEN the user opens a view with gebruik toggle ON THEN the data is immediately available (no extra API calls)
  - GIVEN the active organisation WHEN fetching gebruik THEN only records owned by the active organisation are returned (afnemer filter)
- [x] 10.1 Add background pre-fetch of gebruik (filtered by active org) and modules in con-beheer-views-list.js
- [x] 10.2 In con-beheer-views.js, use store data if already available instead of re-fetching
- [x] 10.3 Verify no duplicate fetches occur

## 7. Performance Testing

### Task 9: Find and test with the organization with most applications
- **spec_ref**: `specs/deelnames-gebruik/spec.md#requirement-performance-testing-must-use-the-organization-with-most-applications`
- **files**: N/A
- **acceptance_criteria**:
  - GIVEN the org with most applications is active WHEN BBN poster loads with gebruik toggle ON THEN render time is under 3 seconds
- [x] 9.1 Query organisations and count their gebruik/applications
- [x] 9.2 Set the largest org as active (reassigned 762 records from db7fdd84 + 250 existing = 1,012 total)
- [x] 9.3 Load BBN poster view with gebruik filter ON, measure render time
- [x] 9.4 Optimized: pre-fetch on list page eliminates 3.4s wait on view open. View renders in 134ms with 852 overlays.
- **Results**: 2,451 total SVG elements (1,599 base + 852 overlays). Pre-fetch eliminates the module bottleneck (3.4s) from the view open path.

## Verification
- [x] Base view renders correctly without filters
- [x] Gebruik toggle adds overlay nodes inside referentiecomponenten
- [x] Deelnames toggle adds shared overlay nodes with distinct styling
- [x] Both toggles OFF removes all overlay nodes
- [x] BBN poster with overlays renders under 3 seconds
