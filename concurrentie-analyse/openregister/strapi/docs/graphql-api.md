
# GraphQL API
Source: https://docs.strapi.io/cms/api/graphql

# GraphQL API

The GraphQL API allows performing queries and mutations to interact with the [content-types](/cms/backend-customization/models#content-types) through Strapi's [GraphQL plugin](/cms/plugins/graphql). Results can be [filtered](#filters), [sorted](#sorting) and [paginated](#pagination).

:::prerequisites
To use the GraphQL API, install the [GraphQL](/cms/plugins/graphql) plugin:

</Tabs>
:::

Once installed, the GraphQL playground is accessible at the `/graphql` URL and can be used to interactively build your queries and mutations and read documentation tailored to your content-types:

</Tabs>

#### Fetch relations

You can ask to include relation data in your flat queries or in your 

</Columns>
</details>

:::

</TabItem>

</Tabs>

### Fetch media fields

Media fields content is fetched just like other attributes.

The following example fetches the `url` attribute value for each `cover` media field attached to each document from the "Restaurants" content-type:

```graphql
{
  restaurants {
    images {
      documentId
      url
    }
  }
}
```

For multiple media fields, you can use flat queries or 

</Tabs>

### Fetch components

Components content is fetched just like other attributes.

The following example fetches the `label`, `start_date`, and `end_date` attributes values for each `closingPeriod` component added to each document from the "Restaurants" content-type:

```graphql
{
  restaurants {
    closingPeriod {
      label
      start_date
      end_date
    }
  }
}
```

### Fetch dynamic zone data

Dynamic zones are union types in GraphQL so you need to use 

```graphql title="Simple examples for membership operators (in, notIn)"
# in - returns restaurants with category either "pizza" or "burger"
{
  restaurants(filters: { category: { in: ["pizza", "burger"] } }) {
    name
  }
}

# notIn - returns restaurants whose category is neither "pizza" nor "burger"
{
  restaurants(filters: { category: { notIn: ["pizza", "burger"] } }) {
    name
  }
}
```

```graphql title="Simple examples for null checks operators (null, notNull)"
# null - returns restaurants where description is null
{
  restaurants(filters: { description: { null: true } }) {
    name
  }
}

# notNull - returns restaurants where description is not null
{
  restaurants(filters: { description: { notNull: true } }) {
    name
  }
}
```

```graphql title="Simple examples for logical operators (and, or, not)"
# and - both category must be "pizza" AND averagePrice must be < 20
{
  restaurants(filters: {
    and: [
      { category: { eq: "pizza" } },
      { averagePrice: { lt: 20 } }
    ]
  }) {
    name
  }
}

# or - category is "pizza" OR category is "burger"
{
  restaurants(filters: {
    or: [
      { category: { eq: "pizza" } },
      { category: { eq: "burger" } }
    ]
  }) {
    name
  }
}

# not - category must NOT be "pizza"
{
  restaurants(filters: {
    not: { category: { eq: "pizza" } }
  }) {
    name
  }
}
```

```graphql title="Example with nested logical operators: use and, or, and not to find pizzerias under 20 euros"
{
  restaurants(
    filters: {
      and: [
        { not: { averagePrice: { gte: 20 } } }
        {
          or: [
            { name: { eq: "Pizzeria" } }
            { name: { startsWith: "Pizzeria" } }
          ]
        }
      ]
    }
  ) {
    documentId
    name
    averagePrice
  }
}
```

</ApiCall>

### Fetch a document in a specific locale {#locale-fetch}

To fetch a documents 

</ApiCall>

### Create a new localized document {#locale-create}

The `locale` field can be passed to create a localized document




---


# GraphQL plugin
Source: https://docs.strapi.io/cms/plugins/graphql

# GraphQL plugin

By default Strapi create [REST endpoints](/cms/api/rest#endpoints) for each of your content-types. The GraphQL plugin adds a GraphQL endpoint to fetch and mutate your content. With the GraphQL plugin installed, you can use the Apollo Server-based GraphQL Sandbox to interactively build your queries and mutations and read documentation tailored to your content types.

</IdentityCard>

</Tabs>

Once installed, the GraphQL sandbox is accessible at the `/graphql` URL and can be used to interactively build your queries and mutations and read documentation tailored to your content-types.

Once the plugin is installed, the **GraphQL Sandbox** is accessible at the `/graphql` route (e.g., 

</Tabs>

#### Dynamically enable Apollo Sandbox

You can use a function to dynamically enable Apollo Sandbox depending on the environment:

</Tabs>

#### CORS exceptions for Landing Page

If the landing page is enabled in production environments (which is not recommended), CORS headers for the Apollo Server landing page must be added manually.

To add them globally, you can merge the following into your middleware configuration:

```javascript title="/config/middlewares"
{
  name: "strapi::security",
  config: {
    contentSecurityPolicy: {
      useDefaults: true,
      directives: {
        "connect-src": ["'self'", "https:", "apollo-server-landing-page.cdn.apollographql.com"],
        "img-src": ["'self'", "data:", "blob:", "apollo-server-landing-page.cdn.apollographql.com"],
        "script-src": ["'self'", "'unsafe-inline'", "apollo-server-landing-page.cdn.apollographql.com"],
        "style-src": ["'self'", "'unsafe-inline'", "apollo-server-landing-page.cdn.apollographql.com"],
        "frame-src": ["sandbox.embed.apollographql.com"]
      }
    }
  }
}
```

To add these exceptions only for the `/graphql` path (recommended), you can create a new middleware to handle it. For example:

</Tabs>

#### Shadow CRUD

To simplify and automate the build of the GraphQL schema, we introduced the Shadow CRUD feature. It automatically generates the type definitions, queries, mutations and resolvers based on your models.

**Example:**

If you've generated an API called `Document` using [the interactive `strapi generate` CLI](/cms/cli#strapi-generate) or the administration panel, your model looks like this:

```json title="/src/api/[api-name]/content-types/document/schema.json"

{
  "kind": "collectionType",
  "collectionName": "documents",
  "info": {
    "singularName": "document",
    "pluralName": "documents",
    "displayName": "document",
    "name": "document"
  },
  "options": {
    "draftAndPublish": true
  },
  "pluginOptions": {},
  "attributes": {
    "name": {
      "type": "string"
    },
    "description": {
      "type": "richtext"
    },
    "locked": {
      "type": "boolean"
    }
  }
}
```

<details> 
<summary>Generated GraphQL type and queries</summary>

```graphql
# Document's Type definition
input DocumentFiltersInput {
  name: StringFilterInput
  description: StringFilterInput
  locked: BooleanFilterInput
  createdAt: DateTimeFilterInput
  updatedAt: DateTimeFilterInput
  publishedAt: DateTimeFilterInput
  and: [DocumentFiltersInput]
  or: [DocumentFiltersInput]
  not: DocumentFiltersInput
}

input DocumentInput {
  name: String
  description: String
  locked: Boolean
  createdAt: DateTime
  updatedAt: DateTime
  publishedAt: DateTime
}

type Document {
  name: String
  description: String
  locked: Boolean
  createdAt: DateTime
  updatedAt: DateTime
  publishedAt: DateTime
}

type DocumentEntity {
  id: ID
  attributes: Document
}

type DocumentEntityResponse {
  data: DocumentEntity
}

type DocumentEntityResponseCollection {
  data: [DocumentEntity!]!
  meta: ResponseCollectionMeta!
}

type DocumentRelationResponseCollection {
  data: [DocumentEntity!]!
}

# Queries to retrieve one or multiple restaurants.
type Query  {
  document(id: ID): DocumentEntityResponse
  documents(
    filters: DocumentFiltersInput
    pagination: PaginationArg = {}
    sort: [String] = []
    publicationState: PublicationState = LIVE
):DocumentEntityResponseCollection
}

# Mutations to create, update or delete a restaurant.
type Mutation {
  createDocument(data: DocumentInput!): DocumentEntityResponse
  updateDocument(id: ID!, data: DocumentInput!): DocumentEntityResponse
  deleteDocument(id: ID!): DocumentEntityResponse
}
```

</details>

#### Customization

Strapi provides a programmatic API to customize GraphQL, which allows:

* disabling some operations for the [Shadow CRUD](#shadow-crud)
* [using getters](#using-getters) to return information about allowed operations
* registering and using an `extension` object to [extend the existing schema](#extending-the-schema) (e.g. extend types or define custom resolvers, policies and middlewares)

<details> 
<summary>Example of GraphQL customizations</summary>

</Tabs>

</details>

##### Disabling operations in the Shadow CRUD

The `extension` service provided with the GraphQL plugin exposes functions that can be used to disable operations on Content-Types:

| Content-type function | Description                                    | Argument type    | Possible argument values |
| --------------------  | ---------------------------------------------- | ---------------- | ---------------------------------------------------------------------------------------------------------- |
| `disable()`           | Fully disable the Content-Type                 | -                | -                                                                                                          |
| `disableQueries()`    | Only disable queries for the Content-Type      | -                | -                                                                                                          |
| `disableMutations()`  | Only disable mutations for the Content-Type    | -                | -                                                                                                          |
| `disableAction()`     | Disable a specific action for the Content-Type | String           | One value from the list:<ul><li>`create`</li><li>`find`</li><li>`findOne`</li><li>`update`</li><li>`delete`</li></ul>   |
| `disableActions()`    | Disable specific actions for the Content-Type  | Array of Strings | Multiple values from the list: <ul><li>`create`</li><li>`find`</li><li>`findOne`</li><li>`update`</li><li>`delete`</li></ul>  |

Actions can also be disabled at the field level, with the following functions:

| Field function     | Description                      |
| ------------------ | -------------------------------- |
| `disable()`        | Fully disable the field          |
| `disableOutput()`  | Disable the output on a field    |
| `disableInput()`   | Disable the input on a field     |
| `disableFilters()` | Disable filters input on a field |

**Examples:**

```js
// Disable the 'find' operation on the 'restaurant' content-type in the 'restaurant' API
strapi
  .plugin('graphql')
  .service('extension')
  .shadowCRUD('api::restaurant.restaurant')
  .disableAction('find')

// Disable the 'name' field on the 'document' content-type in the 'document' API
strapi
  .plugin('graphql')
  .service('extension')
  .shadowCRUD('api::document.document')
  .field('name')
  .disable()
```

##### Using getters

The following getters can be used to retrieve information about operations allowed on content-types:

| Content-type getter        | Description                                                       | Argument type | Possible argument values                                                                                              |
| -------------------------- | ----------------------------------------------------------------- | ------------- | --------------------------------------------------------------------------------------------------------------------- |
| `isEnabled()`              | Returns whether a content-type is enabled                         | -             | -                                                                                                                     |
| `isDisabled()`             | Returns whether a content-type is disabled                        | -             | -                                                                                                                     |
| `areQueriesEnabled()`      | Returns whether queries are enabled on a content-type             | -             | -                                                                                                                     |
| `areQueriesDisabled()`     | Returns whether queries are disabled on a content-type            | -             | -                                                                                                                     |
| `areMutationsEnabled()`    | Returns whether mutations are enabled on a content-type           | -             | -                                                                                                                     |
| `areMutationsDisabled()`   | Returns whether mutations are disabled on a content-type          | -             | -                                                                                                                     |
| `isActionEnabled(action)`  | Returns whether the passed `action` is enabled on a content-type  | String        | One value from the list:<ul><li>`create`</li><li>`find`</li><li>`findOne`</li><li>`update`</li><li>`delete`</li></ul> |
| `isActionDisabled(action)` | Returns whether the passed `action` is disabled on a content-type | String        | One value from the list:<ul><li>`create`</li><li>`find`</li><li>`findOne`</li><li>`update`</li><li>`delete`</li></ul> |

The following getters can be used to retrieve information about operations allowed on fields:

| Field getter          | Description                                   |
| --------------------- | --------------------------------------------- |
| `isEnabled()`         | Returns whether a field is enabled            |
| `isDisabled()`        | Returns whether a field is disabled           |
| `hasInputEnabled()`   | Returns whether a field has input enabled     |
| `hasOutputEnabled()`  | Returns whether a field has output enabled    |
| `hasFiltersEnabled()` | Returns whether a field has filtering enabled |

###### Extending the schema

The schema generated by the Content API can be extended by registering an extension.

This extension, defined either as an object or a function returning an object, will be used by the `use()` function exposed by the `extension` [service](/cms/backend-customization/services) provided with the GraphQL plugin.

The object describing the extension accepts the following parameters:

| Parameter         | Type   | Description                                                                                  |
| ----------------- | ------ | -------------------------------------------------------------------------------------------- |
| `types`           | Array  | Allows extending the schema types using 

</Tabs>

</details>
:::

###### Custom configuration for resolvers

A resolver is a GraphQL query or mutation handler (i.e. a function, or a collection of functions, that generate(s) a response for a GraphQL query or mutation). Each field has a default resolver.

When [extending the GraphQL schema](#extending-the-schema), the `resolversConfig` key can be used to define a custom configuration for a resolver, which can include:

* [authorization configuration](#authorization-configuration) with the `auth` key
* [policies with the `policies`](#policies) key
* and [middlewares with the `middlewares`](#middlewares) key

:::tip
The [advanced queries](/cms/api/graphql/advanced-queries) guide might contain additional information suitable for your use case, including multi-level queries and custom resolvers examples.
:::

###### Authorization configuration

By default, the authorization of a GraphQL request is handled by the registered authorization strategy that can be either [API token](/cms/features/api-tokens) or through the [Users & Permissions plugin](#usage-with-the-users--permissions-plugin). The Users & Permissions plugin offers a more granular control.

<details>
<summary> Authorization with the Users & Permissions plugin</summary>

With the Users & Permissions plugin, a GraphQL request is allowed if the appropriate permissions are given.

For instance, if a 'Category' content-type exists and is queried through GraphQL with the `Query.categories` handler, the request is allowed if the appropriate `find` permission for the 'Categories' content-type is given.

To query a single category, which is done with the `Query.category` handler, the request is allowed if the the `findOne` permission is given.

Please refer to the user guide on how to [define permissions with the Users & Permissions plugin](/cms/features/rbac#editing-a-role).
</details>

To change how the authorization is configured, use the resolver configuration defined at `resolversConfig.[MyResolverName]`. The authorization can be configured:

* either with `auth: false` to fully bypass the authorization system and allow all requests,
* or with a `scope` attribute that accepts an array of strings to define the permissions required to authorize the request.

<details>
<summary> Examples of authorization configuration</summary>

</Tabs>
</details>

###### Policies

[Policies](/cms/backend-customization/policies) can be applied to a GraphQL resolver through the `resolversConfig.[MyResolverName].policies` key.

The `policies` key is an array accepting a list of policies, each item in this list being either a reference to an already registered policy or an implementation that is passed directly (see [policies configuration documentation](/cms/backend-customization/routes#policies)).

Policies directly implemented in `resolversConfig` are functions that take a `context` object and the `strapi` instance as arguments.
The `context` object gives access to:

* the `parent`, `args`, `context` and `info` arguments of the GraphQL resolver,
* Koa's 

</Tabs>

</details>

:::tip
The [advanced policies](/cms/api/graphql/advanced-policies) guide might contain additional information suitable for your use case.
:::

###### Middlewares

[Middlewares](/cms/backend-customization/middlewares) can be applied to a GraphQL resolver through the `resolversConfig.[MyResolverName].middlewares` key. The only difference between the GraphQL and REST implementations is that the `config` key becomes `options`.  

The `middlewares` key is an array accepting a list of middlewares, each item in this list being either a reference to an already registered middleware or an implementation that is passed directly (see [middlewares configuration documentation](/cms/backend-customization/routes#middlewares)).

Middlewares directly implemented in `resolversConfig` can take the GraphQL resolver's 

</Tabs>

</details>

##### Security

GraphQL is a query language allowing users to use a broader panel of inputs than traditional REST APIs. GraphQL APIs are inherently prone to security risks, such as credential leakage and denial of service attacks, that can be reduced by taking appropriate precautions.

### Disable introspection and Sandbox in production

In production environments, disabling the GraphQL Sandbox and the introspection query is strongly recommended.
If you haven't edited the [configuration file](#available-options), it is already disabled in production by default.

###### Limit max depth and complexity

A malicious user could send a query with a very high depth, which could overload your server. Use the `depthLimit` [configuration parameter](/cms/plugins/graphql#code-based-configuration) to limit the maximum number of nested fields that can be queried in a single request. By default, `depthLimit` is set to 10 but can be set to a higher value during testing and development.

:::tip
To increase GraphQL security even further, 3rd-party tools can be used. See the guide about 

You should see a new user is created in the `Users` collection type in your Strapi admin panel.

#### Authentication

To perform authorized requests, you must first get a JWT:

Then on each request, send along an `Authorization` header in the form of `{ "Authorization": "Bearer YOUR_JWT_GOES_HERE" }`. This can be set in the HTTP Headers section of your GraphQL Sandbox.

#### Usage with API tokens {#api-tokens}

To use API tokens for authentication, pass the token in the `Authorization` header using the format `Bearer your-api-token`.

:::note
Using API tokens in the the GraphQL Sandbox requires adding the authorization header with your token in the `HTTP HEADERS` tab:

```http
{
  "Authorization" : "Bearer


