---
status: draft
source: competitive-analysis
competitor: strapi
analyzed_date: 2026-03-14
---

# GraphQL API

## Overview

Strapi's GraphQL plugin (`@strapi/plugin-graphql`) auto-generates a complete GraphQL schema from content type definitions. It provides queries, mutations, and filters for all content types with the same capabilities as the REST API. The plugin uses a type registry, builder pattern, and extension system for customization.

## Auto-Generated Schema

For each content type, the plugin generates:

### Queries
- `{pluralName}(filters, pagination, sort, locale, status)` - List entries
- `{singularName}(documentId, locale, status)` - Get single entry

### Mutations
- `create{SingularName}(data, locale, status)` - Create entry
- `update{SingularName}(documentId, data, locale, status)` - Update entry
- `delete{SingularName}(documentId, locale)` - Delete entry

### Types
- `{SingularName}` - Main entity type with all fields
- `{SingularName}Input` - Input type for create/update
- `{SingularName}Filters` - Filter input type with operators
- `{SingularName}RelationResponseCollection` - Relation response wrapper

## Architecture

```
GraphQL Plugin
  +-- services/
  |   +-- builders/         # Schema builders per concern
  |   |   +-- type-registry # Global type registration
  |   |   +-- content-api/  # Auto-generated content types
  |   |   +-- filters/      # Filter input types
  |   |   +-- internals/    # Internal types (pagination, etc.)
  |   +-- content-api/      # Query/mutation resolver generation
  |   +-- extension/        # User extension system
  |   +-- format/           # Response formatting
  |   +-- utils/            # Helpers
  +-- config/               # Default configuration
```

## Type Registry

The type registry is a central store for all GraphQL types:
- Manages type definitions, resolvers, and configurations
- Prevents duplicate type registration
- Handles type dependencies and circular references
- Supports nexus-style type building

## Filtering in GraphQL

GraphQL filters mirror the REST API operators:

```graphql
query {
  articles(
    filters: {
      title: { containsi: "strapi" }
      publishedAt: { notNull: true }
      or: [
        { category: { name: { eq: "Tech" } } }
        { category: { name: { eq: "News" } } }
      ]
    }
    sort: ["createdAt:desc"]
    pagination: { page: 1, pageSize: 10 }
  ) {
    title
    content
    category {
      name
    }
  }
}
```

## Extension System

Users can extend the GraphQL schema in `src/index.ts`:

```typescript
export default {
  register({ strapi }) {
    const extensionService = strapi.plugin('graphql').service('extension');

    // Disable specific queries/mutations
    extensionService.shadowCRUD('api::article.article').disableAction('delete');

    // Add custom resolvers
    extensionService.use(({ nexus }) => ({
      typeDefs: `
        type Query {
          articleBySlug(slug: String!): Article
        }
      `,
      resolvers: {
        Query: {
          articleBySlug: {
            resolve: async (parent, args, ctx) => {
              return strapi.documents('api::article.article').findFirst({
                filters: { slug: args.slug }
              });
            }
          }
        }
      }
    }));
  }
};
```

## Configuration

```typescript
// config/plugins.ts
export default {
  graphql: {
    config: {
      endpoint: '/graphql',
      shadowCRUD: true,           // Auto-generate from content types
      playgroundAlways: false,
      depthLimit: 7,              // Query depth protection
      amountLimit: 100,           // Max items per query
      apolloServer: {
        tracing: false,
      },
    },
  },
};
```

## Relevance to OpenRegister

**Key differences:**
- Strapi offers GraphQL as a first-class alternative to REST; OpenRegister is REST-only with OAS
- Strapi's GraphQL is auto-generated from content types; no manual schema maintenance
- The extension system allows disabling specific operations per content type

**Features OpenRegister could consider:**
- GraphQL as an optional API layer (though REST + OAS may be sufficient)
- The shadowCRUD pattern of auto-generating API operations from schemas
- Query depth limiting for security
- Per-content-type operation disabling
