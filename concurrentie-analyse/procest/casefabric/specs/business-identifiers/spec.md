---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Business Identifiers (Custom Case Indexing)
category: querying
relevance: high
---

# Business Identifiers (Custom Case Indexing)

## Summary

CaseFabric's Business Identifiers feature allows marking Case File Item properties as indexed, queryable fields. This enables domain-specific filtering of cases and tasks beyond the standard metadata (state, type, assignment).

## How It Works

1. In the Case File Item Definition, mark properties as Business Identifiers
2. Engine detects changes to these properties at runtime
3. Values are stored in a `business_identifier` table (case ID, identifier name, value)
4. Cases and tasks can be queried using these identifiers

## Query API

### Basic Queries
```
GET /cases?identifiers=Nationality=Netherlands
GET /tasks?identifiers=CustomerLevel=Gold
```

### Operators
| Pattern | Meaning |
|---------|---------|
| `name=value` | Equality match |
| `name!=value` | Exclusion (inequality) |
| `name` | Existence (any value) |

### Combining Queries
- **Different identifiers** = intersection (AND):
  ```
  GET /cases?identifiers=Nationality=Netherlands,CustomerLevel=Gold
  ```
- **Same identifier, multiple values** = union (OR):
  ```
  GET /cases?identifiers=CustomerLevel=Gold,CustomerLevel=Silver
  ```
- **Combined**:
  ```
  GET /cases?identifiers=Nationality=Netherlands,CustomerLevel=Gold,CustomerLevel=Silver
  ```
  (Dutch customers with Gold OR Silver level)

### Exclusion
```
GET /cases?identifiers=Nationality!=Netherlands
```
(All non-Dutch customers)

## Cross-Case-Type Queries

Business Identifiers work across case definitions. If "Nationality" is defined in both TravelRequest and BusTrip cases, a query for Dutch nationality returns both types.

## Use Cases
- Filter cases by customer segment, location, status category
- Find tasks related to specific business entities
- Cross-reference cases by shared domain attributes
- Build dashboards with domain-specific filters

## Relevance to Procest

**High relevance.** The ability to query cases by domain-specific attributes is essential for any case management system. This maps directly to OpenRegister's faceting and search capabilities.

### What to adopt:
- Custom indexed fields for case/task querying is essential
- Cross-type queries (searching across different case types) is valuable
- Simple query syntax (equality, inequality, existence) is user-friendly
- Combination operators (AND between different fields, OR within same field)

### How Procest already handles this:
- OpenRegister provides schema-based object storage with custom properties
- Faceting configuration allows indexed, filterable fields
- OpenCatalogi search provides full-text and faceted search
- Procest can leverage existing OpenRegister query capabilities

### Gaps to address:
- Ensure Procest case objects have efficient indexing on key fields
- Support cross-case-type queries in list views
- Provide simple filter syntax for API consumers
