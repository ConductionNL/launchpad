# URN-URL Resolution Flow

## Decision Diagram

```
Incoming reference (zaaktype, verzoektype, proces, document, zaak, taak)
                            |
                            v
              +---------------------------+
              | Parse URN and/or URL      |
              | Extract base + UUID       |
              +---------------------------+
                            |
            +---------------+---------------+
            |               |               |
            v               v               v
       URL only        URN only        Both provided
            |               |               |
            v               v               v
    Lookup URL base   Lookup URN base  Check UUID match
    in UrnMapping     in UrnMapping    (URN uuid == URL uuid?)
    Config            Config                |
            |               |          +----+----+
            v               v          |         |
    Found?          Found?         Match      No match
    |     |         |     |          |           |
    Y     N         Y     N          v           v
    |     |         |     |    Lookup both    REJECT
    v     |         v     |    mappings       "uuid mismatch"
  Auto-   |       Auto-   |         |
  fill    |       fill    |    +----+----+----+----+
  URN     |       URL     |    |    |    |         |
    |     |         |     |   Both  URN  URL     Neither
    |     v         |     v   found only only    found
    |  REQUIRE_     | REQUIRE_  |    |    |        |
    |  URL_URN_     | URN_URL_  v    v    v        v
    |  MAPPING?     | MAPPING? Check  ERR  ERR   Auto-
    |  |     |      | |     |  match                create
    |  Y     N      | Y     N                      mapping
    |  |     |      | |     |
    |  v     v      | v     v
    | REJECT OK     | REJECT OK
    | "no    |      | "no    |
    | mapping"|     | mapping"|
    |         |     |         |
    +---------+-----+---------+
              |
              v
         Save resolved
         URN + URL
```

## URN Format Breakdown

```
maykin:abc:ztc:zaaktype:99a8bd4f-4144-4105-9850-e477628852fc
|___| |_| |_| |______| |______________________________________|
  |    |   |     |                     |
  |    |   |     |                     UUID (resource identifier)
  |    |   |     resource type
  |    |   component
  |    system
  organisation

Base URN: maykin:abc:ztc:zaaktype
Maps to URL: https://gemeente-a.zgw.nl/zaaktypen
Full URL: https://gemeente-a.zgw.nl/zaaktypen/99a8bd4f-4144-4105-9850-e477628852fc
```

## Configuration

| Setting                  | Default | Effect                                              |
|--------------------------|---------|-----------------------------------------------------|
| REQUIRE_URN_URL_MAPPING  | True    | URN-only input rejected if no mapping exists         |
| REQUIRE_URL_URN_MAPPING  | False   | URL-only input accepted even without mapping         |
