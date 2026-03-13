# UPL Enforcement Flow

## Decision Diagram

```
                    ProductType Create/Update
                            |
                            v
                   +------------------+
                   | Get doelgroep    |
                   +------------------+
                            |
              +-------------+-------------+
              |                           |
              v                           v
    doelgroep is              doelgroep is
    "burgers" or              "interne_organisatie" or
    "bedrijven_en_            "samenwerkingspartners"
    instellingen"                     |
              |                       v
              v                 UPL is optional
    +-------------------+       (no validation)
    | UPL provided?     |
    +-------------------+
         |          |
         v          v
        YES        NO
         |          |
         v          v
    Validate    REJECT with error:
    UPL exists  "Bij de doelgroep Burgers of
    in DB       Bedrijven en instellingen is
         |       een uniforme product naam verplicht"
         v
    Continue with
    create/update
```

## UPL Import Flow

```
load_upl command
      |
      v
  +------------------+
  | Read CSV         |
  | (file or URL)    |
  +------------------+
      |
      v
  +------------------+
  | Validate columns |
  | URI,             |
  | UniformeProductnaam |
  +------------------+
      |
      v
  For each row:
      |
      v
  +------------------+
  | update_or_create |
  | by naam          |
  | set uri,         |
  | is_verwijderd=   |
  |   False          |
  +------------------+
      |
      v
  After all rows:
      |
      v
  +------------------+
  | Mark all UPN NOT |
  | in import as     |
  | is_verwijderd=   |
  |   True           |
  +------------------+
      |
      v
  Report: created N, updated M, removed K
```

## Key Points
- UPL entries are NEVER hard-deleted (existing ProductTypes may reference them)
- `is_verwijderd=True` marks entries no longer in the official government list
- The constraint is enforced at both model.clean() and serializer validator levels
- UPL naam is used as the natural key and API identifier (not UUID/ID)
