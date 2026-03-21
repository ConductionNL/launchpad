---
status: draft
source: competitive-analysis
competitor: directus
analyzed_date: 2026-03-14
---

# GraphQL API

## Overview

Directus auto-generates a GraphQL API from the database schema, providing type-safe queries, mutations, and subscriptions. The GraphQL schema is dynamically built based on collections, fields, relationships, and user permissions.

## Endpoints

- `POST /graphql` - Items scope (user collections)
- `POST /graphql/system` - System scope (directus_* collections)

Both endpoints accept standard GraphQL requests with `query`, `variables`, and `operationName`.

## Schema Generation

The GraphQL schema is generated at runtime by the `GraphQLService` which:

1. Reads all collections and their fields from the schema overview
2. Creates GraphQL types for each collection
3. Maps Directus field types to GraphQL scalars (String, Int, Float, Boolean, JSON, Date, etc.)
4. Generates relational fields as nested object types
5. Creates query/mutation resolvers that delegate to `ItemsService`
6. Applies permission filters to limit visible types/fields

## Queries

```graphql
# List items with filtering, sorting, pagination
query {
  articles(
    filter: { status: { _eq: "published" } }
    sort: ["-date_created"]
    limit: 10
    offset: 0
  ) {
    id
    title
    author {
      name
      avatar {
        id
        filename_download
      }
    }
    comments(limit: 5) {
      body
      user_created {
        first_name
      }
    }
  }
}

# Aggregation with grouping
query {
  articles_aggregated(
    groupBy: ["status", "year(publish_date)"]
  ) {
    group
    count {
      id
    }
    avg {
      rating
    }
    sum {
      views
    }
    countAll
  }
}

# Read single item
query {
  articles_by_id(id: "abc-123") {
    id
    title
    body
  }
}

# Content versioning query
query {
  posts_by_id(id: 1, version: "v1") {
    id
    title
  }
}

# Many-to-Any (M2A) with Union types
query {
  posts {
    sections {
      item {
        ... on headings {
          title
          level
        }
        ... on paragraphs {
          body
        }
        ... on videos {
          source
        }
      }
    }
  }
}
```

## Query Parameters in GraphQL

All REST query parameters have GraphQL equivalents:
- `filter` — Same filter rule syntax
- `sort` — Array of field names (prefix `-` for descending)
- `limit` — Integer
- `offset` — Integer
- `page` — Integer
- `search` — String (full-text search)
- `aggregate` — Via `_aggregated` query suffix
- `groupBy` — Array of field names (supports functions like `year()`)

Note: `deep` parameter is natively supported by GraphQL's nested query syntax.

## Mutations

```graphql
# Create
mutation {
  create_articles_item(data: {
    title: "New Article"
    body: "Content here"
    status: "draft"
  }) {
    id
    title
  }
}

# Update
mutation {
  update_articles_item(id: "abc-123", data: {
    status: "published"
  }) {
    id
    status
  }
}

# Delete
mutation {
  delete_articles_item(id: "abc-123") {
    id
  }
}

# Batch operations
mutation {
  create_articles_items(data: [...]) { ... }
  update_articles_items(ids: [...], data: { ... }) { ... }
  delete_articles_items(ids: [...]) { ... }
}
```

## Subscriptions (via WebSocket)

Directus supports GraphQL subscriptions using the `graphql-ws` protocol:

```js
import { createClient } from "graphql-ws";

const client = createClient({
    url: "ws://your-directus-url/graphql",
    keepAlive: 30000,
    connectionParams: async () => {
        return { access_token: "MY_TOKEN" };
    },
});
```

```graphql
subscription {
  articles_mutated {
    key
    event  # "create" | "update" | "delete"
    data {
      id
      title
      status
    }
  }
}

# Filter by event type
subscription {
  posts_mutated(event: create) {
    key
    data { text }
  }
}
```

The subscription controller listens for item mutations via the event emitter and pushes changes to subscribed clients, respecting their permission filters.

## Filter Operators in GraphQL

All 30+ filter operators work in GraphQL:
```graphql
query {
  posts(filter: {
    _or: [
      { status: { _eq: "published" } },
      { _and: [
        { user_created: { _eq: "$CURRENT_USER" } },
        { status: { _in: ["draft", "review"] } }
      ]}
    ]
  }) {
    id
    title
  }
}
```

Note: GraphQL attribute names cannot contain `:`. For M2A filtering, replace `:` with `__`. For function filters, append `_func` to field name:
```graphql
query {
  posts(filter: { date_published_func: { year: { _eq: 1968 } } }) { id }
}
```

## Schema Caching

The GraphQL schema is cached and invalidated when:
- Collections are created/modified/deleted
- Fields are created/modified/deleted
- Relations are created/modified/deleted
- Permissions change

A hash of the schema state is used to determine if regeneration is needed.

## Limitations
- File uploads not supported via GraphQL (use REST)
- Some advanced transformations only available via REST

## Gap Analysis (vs OpenRegister)

| Capability | Directus | OpenRegister |
|-----------|----------|-------------|
| GraphQL Queries | Auto-generated | Not available |
| GraphQL Mutations | Auto-generated | Not available |
| GraphQL Subscriptions | WebSocket-based | Not available |
| Schema Introspection | Yes | OAS spec instead |
| Union Types (M2A) | Native | N/A |
| Aggregation | Via `_aggregated` suffix | Limited |
| Type Safety | GraphQL types | JSON Schema |
| Content Versioning | `version` parameter | Not available |

## Competitive Impact
**High** — GraphQL is increasingly expected by modern frontend frameworks (Next.js, Nuxt, SvelteKit). The lack of GraphQL forces developers to use REST, which can lead to over-fetching or under-fetching data. Many headless CMS competitors (Strapi, Contentful, Hygraph) also offer GraphQL.

## Recommendation
Consider adding GraphQL support as a future enhancement:
1. A Nextcloud app generating GraphQL schema from OpenRegister schemas
2. An ExApp sidecar running a GraphQL server proxying to OpenRegister REST API
3. Integration with a GraphQL gateway like Apollo Federation
4. Libraries like `graphql-php` or `webonyx/graphql-php` could enable this
5. However, Nextcloud's ecosystem is REST-oriented, so this may be lower priority
