# Open Beheer Data Model

## Local Database (PostgreSQL)

Open Beheer stores almost NO domain data locally. Its database contains only:

```
+---------------------+     +---------------------+
| accounts.User       |     | config.APIConfig    |
|---------------------|     |---------------------|
| username            |     | selectielijst_api_  |
| first_name          |     |   service (FK)      |
| last_name           |     | objecttypen_api_    |
| email               |     |   service (FK)      |
| is_staff            |     +---------------------+
| is_active           |              |
| date_joined         |              v
+---------------------+     +---------------------+
                             | zgw_consumers.      |
                             |   Service           |
+---------------------+     |---------------------|
| django_otp.*        |     | label               |
| (2FA tokens)        |     | slug                |
|---------------------|     | api_type (ztc/orc)  |
| user (FK)           |     | api_root            |
| key                 |     | client_id           |
| verified            |     | secret              |
+---------------------+     | auth_type           |
                             +---------------------+
+---------------------+
| mozilla_django_oidc_ |
|   db.OIDCClient     |
|---------------------|
| identifier          |
| enabled             |
| oidc_rp_client_id   |
| oidc_rp_client_     |
|   secret            |
+---------------------+
```

## External API Data Model (Managed via BFF)

All domain data lives in Open Zaak and related APIs:

```
                         OPEN ZAAK (Catalogi API)
+-------------------+
| Catalogus         |
|-------------------|
| url               |    1
| domein            |----+
| naam              |    |
| rsin              |    |  *
+-------------------+    +----> +-------------------+
                                | Zaaktype          |
                                |-------------------|
                                | url               |
                                | identificatie     |
                                | omschrijving      |
                                | concept (bool)    |
                                | beginGeldigheid   |
                                | eindeGeldigheid   |
                                | versiedatum       |
                                | vertrouwelijkheid |
                                | doel              |
                                | aanleiding        |
                                | doorlooptijd      |
                                | ...               |
                                +-------------------+
                                    |
            +-----------------------+-----------------------+
            |           |           |           |           |
            v           v           v           v           v
    +-----------+ +-----------+ +-----------+ +-----------+ +-----------+
    | StatusType| | ResultType| | RolType   | | Eigenschap| | BeslType  |
    |-----------|  |-----------|  |-----------|  |-----------|  |-----------|
    | volgnummer|  | omschr.  |  | omschr.  |  | naam     |  | omschr.  |
    | omschr.  |  | selectie-|  | omschr.- |  | definitie|  | categorie|
    | informeren|  |  lijst-  |  |  generiek|  | specifi- |  | reactie- |
    |          |  |  klasse  |  |          |  |  catie   |  |  termijn |
    +-----------+  +-----------+  +-----------+  +-----------+  +-----------+

                                    |
                        +-----------+-----------+
                        |                       |
                        v                       v
                +-----------+           +-----------+
                | ZaakObject|           | ZTIOT     |
                | Type      |           | (linking) |
                |-----------|           |-----------|
                | objecttype|           | informatieobject|
                | relatie-  |           |   type    |
                |  omschr.  |           | richting  |
                +-----------+           | volgnummer|
                    |                   +-----------+
                    v                       |
            +-----------+                   v
            | ObjectType|           +-----------+
            | (Obj. API)|           | InfoObject|
            |-----------|           | Type      |
            | name      |           |-----------|
            | url       |           | omschr.   |
            +-----------+           | vertrouw. |
                                    | concept   |
         SELECTIELIJST API          +-----------+
            +-----------+
            | ProcesType|
            |-----------|
            | naam      |
            | nummer    |
            | jaar      |
            | omschr.   |
            +-----------+
```

## Key Architectural Difference from OpenRegister

```
OPEN BEHEER:                         OPENREGISTER:

Frontend ---> BFF ---> Open Zaak     Frontend ---> API ---> Own Database
              |                                              |
              | NO domain data                               | ALL domain data
              | stored locally                               | stored locally
              |                                              |
              v                                              v
         Service config              +----------+    +----------+
         User accounts               | Register |    | Schema   |
         OIDC config                 |----------|    |----------|
         2FA tokens                  | name     |    | name     |
                                     | source   |    | version  |
                                     +----------+    | props    |
                                          |          +----------+
                                          |               |
                                          v               v
                                     +----------+
                                     | Object   |
                                     |----------|
                                     | uuid     |
                                     | register |
                                     | schema   |
                                     | data     |
                                     | created  |
                                     | updated  |
                                     +----------+
```
