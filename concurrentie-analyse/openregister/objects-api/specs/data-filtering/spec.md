---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# Data Attribute Filtering — Objects API (Documentation View)

## Purpose
Filter objects by arbitrary data attributes using a structured query syntax. Supports filtering on any attribute in the JSON data regardless of objecttype.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/api/usage.html

## API Reference
| Method | Path | Description |
|--------|------|-------------|
| GET | `/objects?data_attr=key__op__value` | Filter by data attribute |
| GET | `/objects?data_icontains=text` | Search all string values |
| GET | `/objects?type=<url>` | Filter by objecttype |
| GET | `/objects?ordering=-record__data__field` | Order by data attribute |

## Filter Syntax

Format: `data_attr=key__operator__value`

### Operators
| Operator | Description | Example |
|----------|-------------|---------|
| `exact` | Equal to | `height__exact__100` |
| `gt` | Greater than | `height__gt__50` |
| `gte` | Greater than or equal | `height__gte__50` |
| `lt` | Less than | `height__lt__200` |
| `lte` | Less than or equal | `height__lte__200` |
| `icontains` | Case-insensitive substring | `name__icontains__boom` |
| `in` | In list (pipe-separated) | `type__in__oak\|pine` |

### Nested Attributes
Use double underscore for nested JSON: `dimensions__height__exact__100`

### Multiple Filters
Chain multiple `data_attr` parameters: `?data_attr=height__gt__5&data_attr=type__exact__oak`

### Full Text Search
`data_icontains=boom` searches across ALL string values in the data.

### Ordering
`ordering=-record__data__height,record__index` sorts by nested data fields.

## Deprecated: data_attrs
The old `data_attrs` parameter used comma-separated filters but didn't support commas in values. Replaced by `data_attr`.

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| Arbitrary data filtering | Yes (data_attr parameter) | Yes (_search parameter) |
| Filter operators | exact, gt, gte, lt, lte, icontains, in | Various search modes |
| Nested attribute filtering | Yes (double underscore notation) | Dot notation |
| Full text data search | data_icontains parameter | Search endpoint |
| Ordering by data fields | Yes | Yes |
| Filter syntax | key__operator__value | Different syntax per search mode |

**Already in OpenRegister**: Data attribute filtering, search, ordering
**Not yet in OpenRegister**: Exact same operator syntax (key__op__value format), `in` operator, case-insensitive contains on arbitrary fields, full-text search across all string data values
