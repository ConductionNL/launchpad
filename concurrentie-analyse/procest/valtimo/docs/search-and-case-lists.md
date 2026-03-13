# Valtimo Search Fields and Case Lists

Source: https://docs.valtimo.nl/features/case/for-developers/configuring-search-fields

## Overview

Search fields enable case filtering by allowing administrators to define searchable properties on document definitions.

## Configuration

### Creating Search Fields
API: `POST /api/v1/document-search/{documentDefinitionName}/fields`

### Field Properties
- **Key**: Unique identifier within the document definition
- **Path**: JSONPath with `doc:` prefix (e.g., `doc:person.lastName`), supports nested properties
- **Data type**: `boolean`, `date`, `datetime`, `number`, `text`
- **Field type**: Single input, dropdown selector, range input
- **Match type**: `exact` or `like` (contains)

### Display
- Custom title labels
- Falls back to key translation or key value
- Tooltip support via `searchFieldsTooltips` in translation config
- Supports case-level properties (creation date, assignee)

### Database Functions
- Database-agnostic function support (e.g., array sizing)

## Management
- Retrieve all configured search fields
- Update configurations while preserving ordering
- Delete fields by key reference
- Standard REST endpoints for CRUD operations
