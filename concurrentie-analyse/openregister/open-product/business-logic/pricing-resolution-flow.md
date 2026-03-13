# Pricing Resolution Flow

## Current Price Resolution

```
ProductType.actuele_prijs
        |
        v
  +----------------------------------+
  | Query: prijzen                    |
  |   .filter(actief_vanaf <= today)  |
  |   .order_by("actief_vanaf")       |
  |   .last()                         |
  +----------------------------------+
        |
        v
  Returns the Prijs with the most recent
  actief_vanaf date that is not in the future.
  Returns None if no prices are active yet.
```

## Price Structure

```
ProductType
    |
    +-- Prijs (actief_vanaf: 2024-01-01)
    |       |
    |       +-- PrijsOptie (bedrag: 50.00, beschrijving: "normaal")
    |       +-- PrijsOptie (bedrag: 75.00, beschrijving: "spoed")
    |
    +-- Prijs (actief_vanaf: 2025-01-01)  <-- future price
    |       |
    |       +-- PrijsRegel (dmn_config -> Flowable, dmn_tabel_id: "parking-fee-v2")
    |
    +-- Prijs (actief_vanaf: 2023-06-15)  <-- past price (historical)
            |
            +-- PrijsOptie (bedrag: 45.00, beschrijving: "normaal")
            +-- PrijsOptie (bedrag: 70.00, beschrijving: "spoed")

If today = 2024-06-15:
  actuele_prijs = Prijs(2024-01-01) with opties [50.00, 75.00]
```

## Validation Rules

```
Prijs create/update
        |
        v
  +---------------------------+
  | Count prijsopties         |
  | Count prijsregels         |
  +---------------------------+
        |
  +-----+-----------+
  |     |           |
  v     v           v
 Both  Neither   One type
 > 0   are 0    has items
  |     |           |
  v     v           v
REJECT REJECT     OK
"niet  "moet
zowel  opties
opties of
als    regels
regels" hebben"
```

## Instance-Level vs Type-Level Pricing

| Aspect          | ProductType (Prijs)              | Product (prijs field)         |
|-----------------|----------------------------------|-------------------------------|
| Structure       | Date-scheduled, options/rules    | Simple decimal amount         |
| Purpose         | Catalog price / fee schedule     | Actual charged amount         |
| Frequency       | N/A                              | eenmalig/maandelijks/jaarlijks|
| DMN support     | Yes (PrijsRegel)                 | No                            |
