---
status: draft
source: competitive-analysis
competitor: valtimo
analyzed_date: 2026-03-13
---
# Value Resolvers -- Valtimo

## Purpose
Pluggable dynamic data binding system that enables forms, process links, and plugins to read and write data from/to multiple sources using prefix-based expressions. Value resolvers decouple UI components from specific data storage locations, allowing a single form field to prefill from a ZGW zaak and write back to a case document.

## Architecture Overview
- **Backend module**: `value-resolver/` (core framework with resolver registry)
- **Pattern**: Strategy pattern -- resolvers registered by prefix, dispatched at runtime
- **Integration points**: Form prefill/submission, ProcessLink action parameters, plugin action inputs
- **Extensibility**: Custom resolvers can be registered by plugins or application code

## Data Model

### ValueResolver (interface)
| Method | Description |
|--------|-------------|
| `supportsPrefix(prefix)` | Returns true if this resolver handles the given prefix |
| `resolve(expression, context)` | Reads a value from the data source |
| `store(expression, value, context)` | Writes a value to the data source |

### Built-in Resolvers

| Prefix | Source | Example | Description |
|--------|--------|---------|-------------|
| `doc:` | Case document JSON | `doc:/person/firstName` | JSON path into the case document content |
| `pv:` | Process variables | `pv:approvalStatus` | Operaton process variable |
| `case:` | Case-level fields | `case:assigneeFullName` | Database fields on the case entity |
| `zaak:` | Zaken API | `zaak:startdatum` | Fields from the linked ZGW zaak |
| `zaakstatus:` | Zaak status | `zaakstatus:omschrijving` | Current zaak status fields |
| `zaakresultaat:` | Zaak result | `zaakresultaat:omschrijving` | Zaak result fields |
| `zaakobject:` | Linked objects | `zaakobject:adres/straat` | Objects linked to the zaak |
| (fixed) | Literal | `John` | No prefix = literal value |
| (env) | Environment | `env:API_KEY` | Environment variable (requires whitelisting) |

## Business Logic

### Resolution Flow (Read)
1. Expression received (e.g., `doc:/person/firstName`)
2. Prefix extracted (`doc:`)
3. Registry finds matching `ValueResolver` implementation
4. Resolver reads value from the appropriate source using context (document ID, process instance ID)
5. Value returned for form prefill or action parameter

### Resolution Flow (Write)
1. Form submitted with target expression (e.g., `doc:/person/lastName`)
2. Prefix extracted, resolver found
3. Resolver writes the submitted value to the target
4. For `doc:` -- updates the case document JSON at the specified path
5. For `pv:` -- sets the Operaton process variable

### Context
Resolvers receive a context object containing:
- Document ID (for `doc:` and `case:` resolvers)
- Process instance ID (for `pv:` resolver)
- ZaakInstanceLink (for `zaak:` resolvers)
- Current user information

### Environment Variable Whitelisting
- `env:` resolver requires explicit whitelisting of allowed variable names
- Prevents accidental exposure of sensitive environment variables
- Configured in application properties

### Custom Resolver Extension
1. Implement `ValueResolver` interface
2. Register with a unique prefix
3. Available immediately in forms, process links, and plugin actions
4. No frontend changes needed -- prefix-based dispatch is transparent

## Comparison Notes -- Valtimo vs Procest

### Procest approach
- OpenRegister schemas define data structure and access paths
- Data binding is schema-driven (direct field mapping)
- No prefix-based multi-source resolution system
- n8n expressions handle cross-source data access in workflows

### Valtimo advantages
- Unified syntax for accessing data from 8+ different sources
- Decouples forms from data storage -- same form works with different data sources
- Extensible via custom resolvers
- Two-way binding (read + write) with consistent API
- Environment variable access with security whitelisting

### Valtimo disadvantages
- Prefix syntax is Valtimo-specific -- not a standard
- No validation of resolver expressions at design time (runtime errors)
- Resolver context requires active process instance for `pv:` resolution
- Complex nested paths (`doc:/deeply/nested/array[0]/field`) can be fragile
- No expression builder UI -- users must know the syntax
