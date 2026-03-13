# Open Beheer Request Flow

## BFF Proxy Request Lifecycle

```
                                     OPEN BEHEER
                    +--------------------------------------------+
                    |                                            |
 React Frontend     |  Django BFF (Backend-for-Frontend)         |     External APIs
                    |                                            |
 +-------------+    |  +----------------+    +--------------+    |    +---------------+
 |             |    |  |                |    |              |    |    |               |
 | Browser     |--->|->| DRF View      |--->| ztc_client() |--->|--->| Open Zaak     |
 | (fetch)     |    |  | (ListView/    |    | (ape-pie)    |    |    | Catalogi API  |
 |             |    |  |  DetailView)  |    |              |    |    |               |
 |             |    |  |               |    +--------------+    |    +---------------+
 |             |    |  |               |                        |
 |             |    |  |               |    +--------------+    |    +---------------+
 |             |    |  |               |--->| selectie-    |--->|--->| Selectielijst |
 |             |    |  |               |    | lijst_client |    |    | API           |
 |             |    |  |               |    +--------------+    |    +---------------+
 |             |    |  |               |                        |
 |             |    |  |               |    +--------------+    |    +---------------+
 |             |<---|<-| + OBField     |--->| objecttypen_ |--->|--->| Objecttypen   |
 |             |    |  |   metadata    |    | client()     |    |    | API           |
 |             |    |  |   generation  |    +--------------+    |    +---------------+
 +-------------+    |  +----------------+                       |
                    |                                            |
                    +--------------------------------------------+
```

## GET Request Flow (Detail View)

```
1. Frontend: GET /api/v1/service/{slug}/zaaktypen/{uuid}/
       |
2. DRF Router -> ZaakTypeDetailView.get()
       |
3. ztc_client(slug) -> cached APIClient for this Open Zaak instance
       |
4. client.get("zaaktypen/{uuid}") -> Open Zaak HTTP request
       |
5. msgspec.json.decode(response.content, type=ExpandableZaakType)
       |
6. expand_one(client, expansions, zaaktype)
       |  |-- fetch_all(client, "statustypen", {zaaktype: url, status: alles})
       |  |-- fetch_all(client, "resultaattypen", {zaaktype: url, status: alles})
       |  |-- fetch_all(client, "roltypen", {zaaktype: url, status: alles})
       |  |-- fetch_all(client, "eigenschappen", {zaaktype: url, status: alles})
       |  |-- fetch_all(client, "informatieobjecttypen", {zaaktype: url, status: alles})
       |  |-- fetch_all(client, "besluittypen", {zaaktypen: url, status: alles})
       |  |-- expand_zaakobjecttypen(client, [zaaktype])
       |  |-- expand_selectielijstprocestype(client, [zaaktype])
       |  +-- expand_zaaktype_informatieobjecttype(client, [zaaktype])
       |
7. get_item_versions(slug, zaaktype) -> all versions with same identificatie
       |
8. get_fields(zaaktype) -> OBField[] with type/options/editability
       |
9. get_fieldsets() -> named field groupings
       |
10. Return DetailResponse { result, fields, fieldsets, versions }
```

## POST Request Flow (Create Zaaktype with Related Objects)

```
1. Frontend: POST /api/v1/service/{slug}/zaaktypen/
   Body: { ...zaaktype_fields, _expand: { statustypen: [...], roltypen: [...], ... } }
       |
2. ZaakTypeListView.post()
       |
3. client.post("zaaktypen", json=request.data)  -- Create zaaktype in Open Zaak
       |
4. create_related(client, zaaktype, request.data)
       |  |
       |  |-- For each relation type (besluittypen, statustypen, resultaattypen,
       |  |   eigenschappen, informatieobjecttypen, roltypen, zaakobjecttypen):
       |  |
       |  |   inject_foreignkeys(key):
       |  |     - Add catalogus from parent zaaktype
       |  |     - Add zaaktype URL from parent
       |  |
       |  |   create_many(client, endpoint, type, data):
       |  |     - POST each item to Open Zaak
       |  |     - Collect results and errors
       |  |
       |  |-- Patch M2M relations back to zaaktype:
       |  |   zaaktype.besluittypen = [new_urls] + [existing_urls]
       |  |   zaaktype.statustypen = [new_urls]
       |  |   etc.
       |  |
       |  +-- Return (zaaktype_with_expand, errors)
       |
5. If errors: Return 400/500 with error list
6. Else: Return 201 with created zaaktype + expanded relations
```

## Authentication Flow

```
1. Frontend loads -> LoginPage checks auth
       |
2. GET /api/v1/auth/ensure-csrf-token/
   Response: 204 + Set-Cookie: csrftoken=...
       |
3. POST /api/v1/auth/login/
   Headers: X-CSRFToken: {csrftoken}
   Body: { username, password }
       |
4. Django authenticates -> creates session
   Response: 204 + Set-Cookie: openbeheer_sessionid=...
       |
5. GET /api/v1/whoami/
   Cookies: openbeheer_sessionid=...
   Response: { username, firstName, lastName, email }
       |
6. All subsequent requests include both cookies
   Every mutating request: first ensure-csrf-token, then the actual request
```

## Publish Zaaktype Flow

```
1. Frontend: "Publiceren" button clicked
       |
2. PATCH /service/{slug}/zaaktypen/{conceptUUID}/
   Body: { ...pendingUpdates }  -- Save any unsaved changes first
       |
3. If active version exists:
   PATCH /service/{slug}/zaaktypen/{activeUUID}/
   Body: { eindeGeldigheid: "2026-03-12" }  -- Yesterday
       |
4. If beginGeldigheid not set or in the past:
   PATCH /service/{slug}/zaaktypen/{conceptUUID}/
   Body: { beginGeldigheid: "2026-03-13" }  -- Today
       |
5. POST /service/{slug}/zaaktypen/{conceptUUID}/publish/
   -> BFF proxies to Open Zaak: POST zaaktypen/{uuid}/publish
   -> Open Zaak sets concept=false
       |
6. Redirect to zaaktype detail (editing mode off)
```
