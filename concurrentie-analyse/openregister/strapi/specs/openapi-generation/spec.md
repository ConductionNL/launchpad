---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# OpenAPI Specification Generation

## Overview

Strapi v5 includes an `@strapi/openapi` package that auto-generates OpenAPI 3.x specifications from route definitions and Zod validation schemas. This is separate from the `@strapi/plugin-documentation` (Swagger UI) and represents a significant architectural investment in type-safe API contracts.

## Architecture

```
@strapi/openapi/src/
  assemblers/          # Convert internal route defs to OpenAPI components
  context/             # Build context for spec generation
  generator/           # Main spec generator
  pre-processor/       # Transform routes before generation
  post-processor/      # Clean up generated spec
  registries/          # Type registries for reusable schemas
  routes/              # Route definition scanning
  utils/               # Helper utilities
  constants.ts         # OpenAPI constants
  types.ts             # TypeScript types
```

## Route-Level Validation with Zod

Strapi v5 routes define their API contracts using Zod schemas:

```typescript
// From core-api routes
{
  find: {
    method: 'GET',
    path: `/${info.pluralName}`,
    handler: `${uid}.find`,
    request: {
      query: validator.queryParams([
        'fields', 'filters', '_q', 'pagination', 'sort', 'populate',
        ...conditionalQueryParams,  // locale, status based on content type
      ]),
    },
    response: z.object({ data: validator.documents }),
  },
  create: {
    method: 'POST',
    path: `/${info.pluralName}`,
    handler: `${uid}.create`,
    request: {
      query: validator.queryParams(['fields', 'populate', ...conditionalQueryParams]),
      body: { 'application/json': validator.body },
    },
    response: z.object({ data: validator.document }),
  },
}
```

## Route Validator

The `CoreContentTypeRouteValidator` generates Zod schemas from content type definitions:

- `validator.document` - Full document schema (for responses)
- `validator.documents` - Array of documents schema
- `validator.body` - Create input schema
- `validator.partialBody` - Update input schema (all fields optional)
- `validator.documentID` - Document ID validation
- `validator.queryParams([...])` - Query parameter validation with selective params

### Conditional Parameters
Query parameters are conditionally included based on content type features:
- `locale` - Only for localized content types
- `status` / `hasPublishedVersion` - Only for content types with draft/publish enabled

This means the generated OpenAPI spec accurately reflects each content type's capabilities.

## Generation Pipeline

1. **Route scanning**: Collect all registered routes (content API, admin API, plugin routes)
2. **Pre-processing**: Normalize route definitions, resolve references
3. **Context building**: Gather content type metadata, field types, relations
4. **Assembly**: Convert routes + schemas to OpenAPI path items, components, and schemas
5. **Post-processing**: Clean up, deduplicate, validate the generated spec

## Dual-Purpose Validation

The Zod schemas serve two purposes:
1. **Runtime validation**: Request/response validation during API calls (when `strictParams` is enabled)
2. **Spec generation**: Zod schemas are converted to JSON Schema for OpenAPI spec output

This means the API documentation is always in sync with the actual validation rules.

## Relevance to OpenRegister

**Key differences:**
- Strapi generates OpenAPI from Zod schemas on routes; OpenRegister generates OAS from JSON Schema definitions
- Strapi's approach ties validation to documentation; OpenRegister's schema IS the documentation
- Strapi conditionally includes parameters per content type; OpenRegister could do the same per register/schema

**Features OpenRegister could adopt:**
- Conditional query parameter documentation (only show locale param for localized schemas)
- Zod-style validation that doubles as spec generation
- Per-route request/response schema definitions
- The `CoreContentTypeRouteValidator` pattern of generating validators from schema metadata
- Distinguishing between `body` (create, all required) and `partialBody` (update, all optional)
