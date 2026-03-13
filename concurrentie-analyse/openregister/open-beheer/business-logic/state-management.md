# Open Beheer State Management & UI Patterns

## Frontend State Architecture

Open Beheer uses **no global state library** (no Redux, Zustand, MobX). State is managed through:

### 1. React Router Loaders (Data Fetching)

```
Route Definition                    Loader Function
-----------------                   ----------------
/:serviceSlug           ->          serviceLoader (redirects to first service)
/:serviceSlug/:catId    ->          (no loader, auto-redirect to zaaktypen)
.../zaaktypen           ->          zaaktypenLoader (fetches zaaktypen list)
.../zaaktypen/:uuid     ->          zaaktypeLoader (fetches zaaktype detail + expansions)
.../zaaktypen/create    ->          zaaktypeCreateLoader (fetches templates)
.../informatieobjecttypen ->        informatieobjecttypenLoader
.../informatieobjecttypen/:uuid ->  informatieobjecttypeLoader
/login                  ->          loginLoader (redirects if already authed)
/logout                 ->          logoutLoader (calls logout API)
```

Data flows: Loader -> useLoaderData() -> Component props

### 2. React Router Actions (Mutations)

```
User Interaction -> useSubmitAction() -> Route Action -> performAction()
                                              |
                                              v
                                    switch(action.type):
                                      CREATE_VERSION -> POST + redirect
                                      UPDATE_VERSION -> PATCH + redirect
                                      PUBLISH_VERSION -> multi-step PATCH/POST
                                      BATCH -> Promise.all(actions)
                                      ADD_RELATED_OBJECT -> POST
                                      EDIT_RELATED_OBJECT -> PUT
                                      DELETE_RELATED_OBJECT -> DELETE
                                      SELECT_VERSION -> redirect
                                      SET_TAB -> redirect (hash change)
```

### 3. Component-Level State

```
ZaaktypePage
  |
  |-- pendingUpdatesState: Partial<TargetType>     (scalar field changes)
  |-- actionsState: Record<tabKey, ZaaktypeAction[]>  (related object changes)
  |-- user state (from App.tsx useEffect)
  |
  +-- ZaaktypeTabs
        |-- activeTabIndex (from URL hash)
        |-- errors (from useErrorsState)
        |
        +-- ZaaktypeAttributeGridTab (for scalar tabs)
        |     |-- AttributeGrid with editable={isEditing}
        |     +-- onChange -> parent handleChange -> pendingUpdatesState
        |
        +-- ZaaktypeDataGridTab (for related object tabs)
              |-- RelatedObjectDataGrid
              |     |-- createActionsState[]
              |     |-- updateActionsState[]
              |     |-- deleteActionsState[]
              |     |-- objectListState[] (local copy of data)
              |     +-- onActionsChange -> parent handleTabActionsChange
              +-- isEditing (from URL ?editing=true)
```

## Edit Mode Flow

```
                     Published Zaaktype
                     (concept=false)
                            |
                    "Nieuwe versie"
                            |
                            v
                     +--------------+
                     | CREATE_VERSION|
                     | POST new     |
                     | concept      |
                     +--------------+
                            |
                     redirect to new UUID
                     ?editing=true
                            |
                            v
                  +-------------------+
                  | Editing Mode      |
                  |                   |
                  | pendingUpdates{}  |
                  | actionsState{}    |
                  +-------------------+
                    /        |        \
               "Annuleren"  "Opslaan"  "Publiceren"
                  |          |              |
                  v          v              v
            EDIT_CANCEL   BATCH         PUBLISH_VERSION
            redirect     1. DELETE actions  1. PATCH save
            no ?editing  2. PATCH zaaktype  2. PATCH old version
                         3. POST creates    3. PATCH begin date
                         redirect           4. POST publish
                         no ?editing        redirect
```

## Caching Strategy

```
Browser Level:
  - cacheMemo("getServiceChoices", ...) -> Service choices cached
  - cacheMemo("getCatalogiChoices", ...) -> Catalogi choices cached
  - cacheMemo("whoAmI", ...) -> User info cached
  - cacheSet("selectedCatalogusId:slug", ...) -> Last selected catalogus

Backend Level:
  - @cache decorator on ztc_client(), selectielijst_client(), objecttypen_client()
  - Cache invalidated on Service/APIConfig save/delete signals
  - Django session stored in Redis
  - Axes brute-force data in Redis
```

## URL-Driven State

Open Beheer encodes significant state in URLs:

```
/:serviceSlug/:catalogusId/zaaktypen/:uuid?editing=true#tab=statustypen
 ^              ^                      ^      ^              ^
 |              |                      |      |              |
 Service        Catalogus              Version  Edit mode    Active tab
 selection      selection              (UUID)   (boolean)    (hash param)
```

This means:
- Browser back/forward navigates between views correctly
- Bookmarking preserves full context
- Sharing URLs works
- No hidden state that can't be represented in the URL
