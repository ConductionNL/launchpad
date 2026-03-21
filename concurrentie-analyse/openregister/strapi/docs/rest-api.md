
# Content API
Source: https://docs.strapi.io/cms/api/content-api

# Strapi APIs to access your content

Once you've created and configured a Strapi project, created a content structure with the [Content-Type Builder](/cms/features/content-type-builder) and started adding data through the [Content Manager](/cms/features/content-manager), you likely would like to access your content.

From a front-end application, your content can be accessed through Strapi's Content API, which is exposed:

- by default through the [REST API](/cms/api/rest)
- and also through the [GraphQL API](/cms/api/graphql) if you installed the Strapi built-in [GraphQL plugin](/cms/plugins/graphql).

You can also use the [Strapi Client](/cms/api/client) library to interact with the REST API.

REST and GraphQL APIs represent the top-level layers of the Content API exposed to external applications. Strapi also provides 2 lower-level APIs:

- The [Document Service API](/cms/api/document-service), accessible through `strapi.documents`, is the recommended API to interact with your application's database within the [backend server](/cms/customization) or through [plugins](/cms/plugins-development/developing-plugins). The Document Service is the layer that handles **documents**




---


# REST API reference
Source: https://docs.strapi.io/cms/api/rest

# REST API reference

The REST API allows accessing the [content-types](/cms/backend-customization/models) through API endpoints. Strapi automatically creates [API endpoints](#endpoints) when a content-type is created. [API parameters](/cms/api/rest/parameters) can be used when querying API endpoints to refine the results.

This section of the documentation is for the REST API reference for content-types. We also have [guides](/cms/api/rest/guides/intro) available for specific use cases.

:::prerequisites
All content types are private by default and need to be either made public or queries need to be authenticated with the proper permissions. See the [Quick Start Guide](/cms/quick-start#step-4-set-roles--permissions), the user guide for the [Users & Permissions feature](/cms/features/users-permissions#roles), and [API tokens configuration documentation](/cms/features/api-tokens) for more details.
:::

:::note
By default, the REST API responses only include top-level fields and does not populate any relations, media fields, components, or dynamic zones. Use the [`populate` parameter](/cms/api/rest/populate-select) to populate specific fields. Ensure that the find permission is given to the field(s) for the relation(s) you populate.
:::

:::strapi Strapi Client
The [Strapi Client](/cms/api/client) library simplifies interactions with your Strapi back end, providing a way to fetch, create, update, and delete content.
:::

## Endpoints

For each Content-Type, the following endpoints are automatically generated:

<details>

<summary>Plural API ID vs. Singular API ID:</summary>

In the following tables:

- `:singularApiId` refers to the value of the "API ID (Singular)" field of the content-type,
- and `:pluralApiId` refers to the value of the "API ID (Plural)" field of the content-type.

These values are defined when creating a content-type in the Content-Type Builder, and can be found while editing a content-type in the admin panel (see [User Guide](/cms/features/content-type-builder#creating-content-types)). For instance, by default, for an "Article" content-type:

- `:singularApiId` will be `article`
- `:pluralApiId` will be `articles`

</Tabs>

<details>

<summary>Real-world examples of endpoints:</summary>

The following endpoint examples are taken from the 

</Tabs>
</details>

:::strapi Upload API
The Upload package (which powers the [Media Library feature](/cms/features/media-library)) has a specific API accessible through its [`/api/upload` endpoints](/cms/api/rest/upload).
:::

:::note
[Components](/cms/backend-customization/models#components-json) don't have API endpoints.
:::

## Requests

:::strapi Strapi 5 vs. Strapi v4
Strapi 5's Content API includes 2 major differences with Strapi v4:

- The response format has been flattened, which means attributes are no longer nested in a `data.attributes` object and are directly accessible at the first level of the `data` object (e.g., a content-type's "title" attribute is accessed with `data.title`).
- Strapi 5 now uses **documents** 

</ApiCall>

### Get a document {#get}

Returns a document by `documentId`.

:::strapi Strapi 5 vs. Strapi v4
In Strapi 5, a specific document is reached by its `documentId`.
:::

</ApiCall>

### Create a document {#create}

Creates a document and returns its value.

If the [Internationalization (i18n) plugin](/cms/features/internationalization) is installed, it's possible to use POST requests to the REST API to [create localized documents](/cms/api/rest/locale#rest-delete).

:::note
While creating a document, you can define its relations and their order (see [Managing relations through the REST API](/cms/api/rest/relations.md) for more details).
:::

</ApiCall>

### Update a document {#update}

Partially updates a document by `id` and returns its value.

Send a `null` value to clear fields.

:::note NOTES
* Even with the [Internationalization (i18n) plugin](/cms/features/internationalization) installed, it's currently not possible to [update the locale of a document](/cms/api/rest/locale#rest-update).
* While updating a document, you can define its relations and their order (see [Managing relations through the REST API](/cms/api/rest/relations) for more details).
:::

</ApiCall>

### Delete a document {#delete}

Deletes a document.

`DELETE` requests only send a 204 HTTP status code on success and do not return any data in the response body.

</ApiCall>




---


# Filters
Source: https://docs.strapi.io/cms/api/rest/filters

# REST API: Filters

The [REST API](/cms/api/rest) offers the ability to filter results found with its ["Get entries"](/cms/api/rest#get-all) method.<br/>
Using optional Strapi features can provide some more filters:

- If the [Internationalization (i18n) plugin](/cms/features/internationalization) is enabled on a content-type, it's possible to filter by locale.
- If the [Draft & Publish](/cms/features/draft-and-publish) is enabled, it's possible to filter based on a `published` (default) or `draft` status.

:::tip

<details>
<summary>JavaScript query (built with the qs library):</summary>

</ApiCall>

## Example: Find multiple restaurants with ids 3, 6,8

You can use the `$in` filter operator with an array of values to find multiple exact values.

<br />

<details>
<summary>JavaScript query (built with the qs library):</summary>

</ApiCall>

## Complex filtering

Complex filtering is combining multiple filters using advanced methods such as combining `$and` & `$or`. This allows for more flexibility to request exactly the data needed.

<br />

<details>
<summary>JavaScript query (built with the qs library):</summary>

</ApiCall>

## Deep filtering

Deep filtering is filtering on a relation's fields.

:::note
- Relations, media fields, components, and dynamic zones are not populated by default. Use the `populate` parameter to populate these content structures (see [`populate` documentation](/cms/api/rest/populate-select#population))
- You can filter what you populate, you can also filter nested relations, but you can't use filters for polymorphic content structures (such as media fields and dynamic zones).
:::

:::caution
Querying your API with deep filters may cause performance issues.  If one of your deep filtering queries is too slow, we recommend building a custom route with an optimized version of the query.
:::

<details>
<summary>JavaScript query (built with the qs library):</summary>

</ApiCall>




---


# REST API Guides
Source: https://docs.strapi.io/cms/api/rest/guides/intro

# REST API Guides

The [REST API reference](/cms/api/rest) documentation is meant to provide a quick reference for all the endpoints and parameters available.

## Guides

The following guides, officially maintained by the Strapi Documentation team, cover dedicated topics and provide detailed explanations (guides indicated with 🧠) or step-by-step instructions (guides indicated with 🛠️) for some use cases:

## Additional resources

:::strapi Want to help other users?
Some of the additional resources listed in this section have been created for Strapi v4 and might not fully work with Strapi 5. If you want to update one of the following articles for Strapi 5, feel free to  for the Write for the Community program.
:::

Additional tutorials and guides can be found in the following blog posts:




---


# Interactive Query Builder
Source: https://docs.strapi.io/cms/api/rest/interactive-query-builder

# Build your query URL with Strapi's interactive tool

A wide range of parameters can be used and combined to query your content with the [REST API](/cms/api/rest), which can result in long and complex query URLs.

Strapi's codebase uses  to parse and stringify nested JavaScript objects. It's recommended to use `qs` directly to generate complex query URLs instead of creating them manually.

You can use the following interactive query builder tool to generate query URLs automatically:

1. Replace the values in the _Endpoint_ and _Endpoint Query Parameters_ fields with content that fits your needs.
2. Click the **Copy to clipboard** button to copy the automatically generated _Query String URL_ which is updated as you type.

:::info Parameters usage
Please refer to the [REST API parameters table](/cms/api/rest/parameters) and read the corresponding parameters documentation pages to better understand parameters usage.
:::

<br />

<br />
 
<br />

:::note
The default endpoint path is prefixed with `/api/` and should be kept as-is unless you configured a different API prefix using [the `rest.prefix` API configuration option](/cms/configurations/api).<br/> For instance, to query the `books` collection type using the default API prefix, type `/api/books` in the _Endpoint_ field.
:::

:::caution Disclaimer
The `qs` library and the interactive query builder provided on this page:
- might not detect all syntax errors,
- are not aware of the parameters and values available in a Strapi project,
- and do not provide autocomplete features.

Currently, these tools are only provided to transform the JavaScript object in an inline query string URL. Using the generated query URL does not guarantee that proper results will get returned with your API.
:::




---


# Locale
Source: https://docs.strapi.io/cms/api/rest/locale

# REST API: `locale`

The [Internationalization (i18n) feature](/cms/features/internationalization) adds new abilities to the [REST API](/cms/api/rest).

:::prerequisites
To work with API content for a locale, please ensure the locale has been already [added to Strapi in the admin panel](/cms/features/internationalization#settings).
:::

The `locale` [API parameter](/cms/api/rest/parameters) can be used to work with documents only for a specified locale. `locale` takes a locale code as a value (see 

</Tabs>

### `GET` Get all documents in a specific locale {#rest-get-all}

</ApiCall>

### `GET` Get a document in a specific locale {#rest-get}

To get a specific document in a given locale, add the `locale` parameter to the query:

| Use case             | Syntax format and link for more information                                                    |
| -------------------- | ---------------------------------------------------------------------------------------------- |
| In a collection type | [`GET /api/content-type-plural-name/document-id?locale=locale-code`](#get-one-collection-type) |
| In a single type     | [`GET /api/content-type-singular-name?locale=locale-code`](#get-one-single-type)               |

#### Collection types {#get-one-collection-type}

To get a specific document in a collection type in a given locale, add the `locale` parameter to the query, after the `documentId`:

</ApiCall>

#### Single types {#get-one-single-type}

To get a specific single type document in a given locale, add the `locale` parameter to the query, after the single type name:

</ApiCall>

### `POST` Create a new localized document for a collection type {#rest-create}

To create a localized document from scratch, send a POST request to the Content API. Depending on whether you want to create it for the default locale or for another locale, you might need to pass the `locale` parameter in the query.

| Use case                      | Syntax format and link for more information                                               |
| ----------------------------- | --------------------------------------------------------------------------------------- |
| Create for the default locale | [`POST /api/content-type-plural-name`](#rest-create-default-locale) |
| Create for a specific locale  | [`POST /api/content-type-plural-name?locale=fr`](#rest-create-specific-locale)

#### For the default locale {#rest-create-default-locale}

If no locale has been passed in the request body, the document is created using the default locale for the application:

</ApiCall>

#### For a specific locale {#rest-create-specific-locale}

To create a localized entry for a locale different from the default one, add the `locale` parameter to the query URL of the POST request:

</ApiCall>

### `PUT` Create a new, or update an existing, locale version for an existing document {#rest-update}

With `PUT` requests sent to an existing document, you can:

- create another locale version of the document,
- or update an existing locale version of the document.

Send the `PUT` request to the appropriate URL, adding the `locale=your-locale-code` parameter to the query URL and passing attributes in a `data` object in the request's body:

| Use case             | Syntax format and link for more information                                               |
| -------------------- | --------------------------------------------------------------------------------------- |
| In a collection type | [`PUT /api/content-type-plural-name/document-id?locale=locale-code`](#rest-put-collection-type) |
| In a single type     | [`PUT /api/content-type-singular-name?locale=locale-code`](#rest-put-single-type)               |

:::caution
When creating a localization for existing localized entries, the body of the request can only accept localized fields.
:::

:::tip
The Content-Type should have the [`createLocalization` permission](/cms/features/rbac#collection-and-single-types) enabled, otherwise the request will return a `403: Forbidden` status.
:::

:::note
It is not possible to change the locale of an existing localized entry. When updating a localized entry, if you set a `locale` attribute in the request body it will be ignored.
:::

#### In a collection type {#rest-put-collection-type}

To create a new locale for an existing document in a collection type, add the `locale` parameter to the query, after the `documentId`, and pass data to the request's body:

</ApiCall>

#### In a single type {#rest-put-single-type}

To create a new locale for an existing single type document, add the `locale` parameter to the query, after the single type name, and pass data to the request's body:

</ApiCall>

<br/>

### `DELETE` Delete a locale version of a document {#rest-delete}

To delete a locale version of a document, send a `DELETE` request with the appropriate `locale` parameter.

`DELETE` requests only send a 204 HTTP status code on success and do not return any data in the response body.

#### In a collection type {#rest-delete-collection-type}

To delete only a specific locale version of a document in a collection type, add the `locale` parameter to the query after the `documentId`:

#### In a single type {#rest-delete-single-type}

To delete only a specific locale version of a single type document, add the `locale` parameter to the query after the single type name:




---


# Parameters
Source: https://docs.strapi.io/cms/api/rest/parameters

# REST API parameters

API parameters can be used with the [REST API](/cms/api/rest) to filter, sort, and paginate results and to select fields and relations to populate. Additionally, specific parameters related to optional Strapi features can be used, like the publication state and locale of a content-type.

The following API parameters are available:

| Operator           | Type          | Description                                           |
| ------------------ | ------------- | ----------------------------------------------------- |
| `filters`          | Object        | [Filter the response](/cms/api/rest/filters) |
| `locale`           | String        | [Select a locale](/cms/api/rest/locale) |
| `status`           | String        | [Select the Draft & Publish status](/cms/api/rest/status) |
| `populate`         | String or Object | [Populate relations, components, or dynamic zones](/cms/api/rest/populate-select#population) |
| `fields`           | Array         | [Select only specific fields to display](/cms/api/rest/populate-select#field-selection) |
| `sort`             | String or Array  | [Sort the response](/cms/api/rest/sort-pagination.md#sorting) |
| `pagination`       | Object        | [Page through entries](/cms/api/rest/sort-pagination.md#pagination) |

Query parameters use the  (i.e. they are encoded using square brackets `[]`).

:::tip
A wide range of REST API parameters can be used and combined to query your content, which can result in long and complex query URLs.<br/>👉 You can use Strapi's [interactive query builder](/cms/api/rest/interactive-query-builder) tool to build query URLs more conveniently. 🤗
:::




---


# Populate and Select
Source: https://docs.strapi.io/cms/api/rest/populate-select

# REST API: Population & Field Selection

The [REST API](/cms/api/rest) by default does not populate any relations, media fields, components, or dynamic zones. Use the [`populate` parameter](#population) to populate specific fields. Use the [`fields` parameter](#field-selection) to return only specific fields with the query results.

:::tip

</ApiCall>

## Population

The REST API by default does not populate any type of fields, so it will not populate relations, media fields, components, or dynamic zones unless you pass a `populate` parameter to populate various field types. Populated relations always return full objects; the REST API currently cannot return just an array of IDs.

:::prerequisites
The `find` permission must be enabled for the content-types that are being populated. If a role does not have access to a content-type, the content-type will not be populated (see [Users & Permissions](/cms/features/users-permissions#editing-a-role) for additional information on how to enable `find` permissions for content-types).
:::

You can use the `populate` parameter alone or [in combination with multiple operators](#combining-population-with-other-operators) for more control over the population.

:::caution 
`populate=deep` plugins are [not recommended in Strapi](https://support.strapi.io/articles/8544110758-why-populate-deep-plugins-are-not-recommended-in-strapi).
:::

The following table lists populate use cases with example syntax. Each row links to the Understanding populate guide for details:

| Use case  | Example parameter syntax | Detailed explanations to read |
|-----------| ---------------|-----------------------|
| Populate everything, 1 level deep, including media fields, relations, components, and dynamic zones | `populate=*`| [Populate all relations and fields, 1 level deep](/cms/api/rest/guides/understanding-populate#populate-all-relations-and-fields-1-level-deep) |
| Populate one relation,<br/>1 level deep | `populate=a-relation-name`| [Populate 1 level deep for specific relations](/cms/api/rest/guides/understanding-populate#populate-1-level-deep-for-specific-relations) |
| Populate several relations,<br/>1 level deep | `populate[0]=relation-name&populate[1]=another-relation-name&populate[2]=yet-another-relation-name`| [Populate 1 level deep for specific relations](/cms/api/rest/guides/understanding-populate#populate-1-level-deep-for-specific-relations) |
| Populate some relations, several levels deep | `populate[root-relation-name][populate][0]=nested-relation-name`| [Populate several levels deep for specific relations](/cms/api/rest/guides/understanding-populate#populate-several-levels-deep-for-specific-relations) |
| Populate a component | `populate[0]=component-name`| [Populate components](/cms/api/rest/guides/understanding-populate#populate-components) |
| Populate a component and one of its nested components | `populate[0]=component-name&populate[1]=component-name.nested-component-name`| [Populate components](/cms/api/rest/guides/understanding-populate#populate-components) |
| Populate a dynamic zone (only its first-level elements) | `populate[0]=dynamic-zone-name`| [Populate dynamic zones](/cms/api/rest/guides/understanding-populate#populate-dynamic-zones) |
| Populate a dynamic zone and its nested elements and relations, using a precisely defined, detailed population strategy | `populate[dynamic-zone-name][on][component-category.component-name][populate][relation-name][populate][0]=field-name`| [Populate dynamic zones](/cms/api/rest/guides/understanding-populate#populate-dynamic-zones) |

:::tip
To build complex queries with multiple-level population, use the [interactive query builder](/cms/api/rest/interactive-query-builder) tool. For more detailed explanations and examples, see the [REST API guides](/cms/api/rest/guides/intro).
:::

### Combining population with other operators

You can combine the `populate` operator with other operators such as [field selection](/cms/api/rest/populate-select#field-selection), [filters](/cms/api/rest/filters), and [sort](/cms/api/rest/sort-pagination) in the population queries.

:::note
The population and pagination operators cannot be combined.
:::

#### Populate with field selection

`fields` and `populate` can be combined.

</ApiCall>

#### Populate with filtering

`filters` and `populate` can be combined.

</ApiCall>




---


# Relations
Source: https://docs.strapi.io/cms/api/rest/relations

# Managing relations with API requests

Defining relations between content-types (that are designated as entities in the database layers) is connecting entities with each other.

Relations between content-types can be managed through the [admin panel](/cms/features/content-manager#relational-fields) or through [REST API](/cms/api/rest) or [Document Service API](/cms/api/document-service) requests.

Relations can be connected, disconnected or set through the Content API by passing parameters in the body of the request. These payloads work for both single-entry relations and multi relations (one-to-many, many-to-one, many-to-many, and many-way). When a relational field allows multiple links, the API expects arrays of relation IDs and returns arrays in responses.

|  Parameter name         | Description | Type of update |
|-------------------------|-------------|----------------|
| [`connect`](#connect)   | Connects new entities.<br /><br />Can be used in combination with `disconnect`.<br /><br />Can be used with [positional arguments](#relations-reordering) to define an order for relations.    | Partial |
| [`disconnect`](#disconnect)    | Disconnects entities.<br /><br />Can be used in combination with `connect`. | Partial |
| [`set`](#set)           | Set entities to a specific set. Using `set` will overwrite all existing connections to other entities.<br /><br />Cannot be used in combination with `connect` or `disconnect`.  | Full |

:::note
Multi relations can be managed from the REST API and the [GraphQL API](/cms/api/graphql#fetch-relations): the  `connect`, `disconnect`, and `set` operations are available across both APIs. However, the [Document Service API](/cms/api/document-service) does not handle relations.
:::

:::note
When [Internationalization (i18n)](/cms/features/internationalization) is enabled on the content-type, you can also pass a locale to set relations for a specific locale, as in this Document Service API example:

```js
await strapi.documents('api::restaurant.restaurant').update({ 
  documentId: 'a1b2c3d4e5f6g7h8i9j0klm',
  locale: 'fr',
  data: { 
    category: {
      connect: ['z0y2x4w6v8u1t3s5r7q9onm', 'j9k8l7m6n5o4p3q2r1s0tuv']
    }
  }
})
```

If no locale is passed, the default locale will be assumed.
:::

## `connect`

Using `connect` in the body of a request performs a partial update, connecting the specified relations.

`connect` accepts either a shorthand or a longhand syntax:

| Syntax type | Syntax example |
| ------------|----------------|
| shorthand   | `connect: ['z0y2x4w6v8u1t3s5r7q9onm', 'j9k8l7m6n5o4p3q2r1s0tuv']` |
| longhand    | ```connect: [{ documentId: 'z0y2x4w6v8u1t3s5r7q9onm' }, { documentId: 'j9k8l7m6n5o4p3q2r1s0tuv' }]``` |

You can also use the longhand syntax to [reorder relations](#relations-reordering).

`connect` can be used in combination with [`disconnect`](#disconnect).

:::caution
`connect` is not officially supported for media attributes. Advanced users can technically connect media entries by targeting upload file IDs, but this workaround isn't recommended or supported by Strapi and can easily break (e.g. when Draft & Publish uses mismatched IDs). Proceed with caution.
:::

</MultiLanguageSwitcher>

</TabItem>

</MultiLanguageSwitcher>

</TabItem>
</Tabs>

### Relations reordering

</TabItem>

Omitting the `position` argument (as in `documentId: 'srkvrr77k96o44d9v6ef1vu9'`) defaults to `position: { end: true }`. All other relations are positioned relative to another existing `id` (using `after` or `before`) or relative to the list of relations (using `start` or `end`). Operations are treated sequentially in the order defined in the `connect` array, so the resulting database record will be the following:

```js
categories: [
  { id: 'nyk7047azdgbtjqhl7btuxw' },
  { id: 'j9k8l7m6n5o4p3q2r1s0tuv' },
  { id: '6u86wkc6x3parjd4emikhmx6' },
  { id: '3r1wkvyjwv0b9b36s7hzpxl7' },
  { id: 'a1b2c3d4e5f6g7h8i9j0klm' },
  { id: 'rkyqa499i84197l29sbmwzl' },
  { id: 'srkvrr77k96o44d9v6ef1vu9' }
]
```

</TabItem>

</Tabs>

### Edge cases: Draft & Publish or i18n disabled

When some built-in features of Strapi 5 are disabled for a content-type, such as [Draft & Publish](/cms/features/draft-and-publish) and [Internationalization (i18)](/cms/features/internationalization), the `connect` parameter might be used differently:

**Relation from a `Category` with i18n _off_ to an `Article` with i18n _on_:**

In this situation you can select which locale you are connecting to:

```js
data: {
    categories: {
      connect: [
        { documentId: 'z0y2x4w6v8u1t3s5r7q9onm', locale: 'en' },
        // Connect to the same document id but with a different locale 👇
        { documentId: 'z0y2x4w6v8u1t3s5r7q9onm', locale: 'fr' },
      ]
   }
}
```

**Relation from a `Category` with Draft & Publish _off_ to an `Article` with Draft & Publish _on_:**

```js
data: {
  categories: {
    connect: [
      { documentId: 'z0y2x4w6v8u1t3s5r7q9onm', status: 'draft' },
      // Connect to the same document id but with different publication states 👇
      { documentId: 'z0y2x4w6v8u1t3s5r7q9onm', status: 'published' },
    ]
  }
}
```

## `disconnect`

Using `disconnect` in the body of a request performs a partial update, disconnecting the specified relations.

`disconnect` accepts either a shorthand or a longhand syntax:

| Syntax type | Syntax example |
| ------------|----------------|
| shorthand   | `disconnect: ['z0y2x4w6v8u1t3s5r7q9onm', 'j9k8l7m6n5o4p3q2r1s0tuv']`
| longhand    | ```disconnect: [{ documentId: 'z0y2x4w6v8u1t3s5r7q9onm' }, { documentId: 'j9k8l7m6n5o4p3q2r1s0tuv' }]``` |

`disconnect` can be used in combination with [`connect`](#connect).

<br />

</TabItem>

</TabItem>
</Tabs>

## `set`

Using `set` performs a full update, replacing all existing relations with the ones specified, in the order specified.

`set` accepts a shorthand or a longhand syntax:

| Syntax type | Syntax example                  |
| ----------- | ------------------------------- |
| shorthand   | `set: ['z0y2x4w6v8u1t3s5r7q9onm', 'j9k8l7m6n5o4p3q2r1s0tuv']`                   |
| longhand    | ```set: [{ documentId: 'z0y2x4w6v8u1t3s5r7q9onm' }, { documentId: 'j9k8l7m6n5o4p3q2r1s0tuv' }]``` |

As `set` replaces all existing relations, it should not be used in combination with other parameters. To perform a partial update, use [`connect`](#connect) and [`disconnect`](#disconnect).

:::note Omitting set
Omitting any parameter is equivalent to using `set`.<br/>For instance, the following 3 syntaxes are all equivalent:

- `data: { categories: set: [{ documentId: 'z0y2x4w6v8u1t3s5r7q9onm' }, { documentId: 'j9k8l7m6n5o4p3q2r1s0tuv' }] }}`
- `data: { categories: set: ['z0y2x4w6v8u1t3s5r7q9onm2', 'j9k8l7m6n5o4p3q2r1s0tuv'] }}`
- `data: { categories: ['z0y2x4w6v8u1t3s5r7q9onm2', 'j9k8l7m6n5o4p3q2r1s0tuv'] }`

:::

</TabItem>

</TabItem>
</Tabs>




---


# Sort and Pagination
Source: https://docs.strapi.io/cms/api/rest/sort-pagination

# REST API: Sort & Pagination

Entries that are returned by queries to the [REST API](/cms/api/rest) can be sorted and paginated.

:::tip

<details>
<summary>JavaScript query (built with the qs library):</summary>

</ApiCall>

### Example: Sort using 2 fields and set the order

Using the `sort` parameter and defining `:asc` or  `:desc` on sorted fields, you can get results sorted in a particular order.

<br />

<details>
<summary>JavaScript query (built with the qs library):</summary>

</ApiCall>

## Pagination

Queries can accept `pagination` parameters. Results can be paginated:

- either by [page](#pagination-by-page) (i.e., specifying a page number and the number of entries per page)
- or by [offset](#pagination-by-offset) (i.e., specifying how many entries to skip and to return)

:::note
Pagination methods can not be mixed. Always use either `page` with `pageSize` **or** `start` with `limit`.
:::

### Pagination by page

To paginate results by page, use the following parameters:

| Parameter               | Type    | Description                                                               | Default |
| ----------------------- | ------- | ------------------------------------------------------------------------- | ------- |
| `pagination[page]`      | Integer | Page number                                                               | 1       |
| `pagination[pageSize]`  | Integer | Page size                                                                 | 25      |
| `pagination[withCount]` | Boolean | Adds the total numbers of entries and the number of pages to the response | True    |

<details>
<summary>JavaScript query (built with the qs library):</summary>

</ApiCall>

### Pagination by offset

To paginate results by offset, use the following parameters:

| Parameter               | Type    | Description                                                    | Default |
| ----------------------- | ------- | -------------------------------------------------------------- | ------- |
| `pagination[start]`     | Integer | Start value (i.e. first entry to return)                      | 0       |
| `pagination[limit]`     | Integer | Number of entries to return                                    | 25      |
| `pagination[withCount]` | Boolean | Toggles displaying the total number of entries to the response | `true`  |

:::tip
The default and maximum values for `pagination[limit]` can be [configured in the `./config/api.js`](/cms/configurations/api) file with the `api.rest.defaultLimit` and `api.rest.maxLimit` keys.
:::

<details>
<summary>JavaScript query (built with the qs library):</summary>

</ApiCall>




---


# Status
Source: https://docs.strapi.io/cms/api/rest/status

# REST API: `status`

The [REST API](/cms/api/rest) offers the ability to filter results based on their status, draft or published.

:::prerequisites
The [Draft & Publish](/cms/features/draft-and-publish) feature should be enabled.
:::

Queries can accept a `status` parameter to fetch documents based on their status:

- `published`: returns only the published version of documents (default)
- `draft`: returns only the draft version of documents

:::tip
In the response data, the `publishedAt` field is `null` for drafts.
:::

:::note
Since published versions are returned by default, passing no status parameter is equivalent to passing `status=published`.
:::

<br /><br />

<details>
<summary>JavaScript query (built with the qs library):</summary>

</ApiCall>




---


# Upload files
Source: https://docs.strapi.io/cms/api/rest/upload

# REST API: Upload files

The [Media Library feature](/cms/features/media-library) is powered in the back-end server of Strapi by the `upload` package. To upload files to Strapi, you can either use the Media Library directly from the admin panel, or use the [REST API](/cms/api/rest), with the following available endpoints :

| Method | Path                    | Description         |
| :----- | :---------------------- | :------------------ |
| GET    | `/api/upload/files`     | Get a list of files |
| GET    | `/api/upload/files/:id` | Get a specific file |
| POST   | `/api/upload`           | Upload files        |
| POST   | `/api/upload?id=x`      | Update fileInfo     |
| DELETE | `/api/upload/files/:id` | Delete a file       |

:::note Notes
- [Folders](/cms/features/media-library#organizing-assets-with-folders) are an admin panel-only feature and are not part of the Content API (REST or GraphQL). Files uploaded through REST are located in the automatically created "API Uploads" folder.
- The GraphQL API does not support uploading media files. To upload files, use the REST API or directly add files from the [Media Library](/cms/features/media-library) in the admin panel. Some GraphQL mutations to update or delete uploaded media files are still possible (see [GraphQL API documentation](/cms/api/graphql#mutations-on-media-files) for details).
:::

## Upload files

Upload one or more files to your application.

`files` is the only accepted parameter, and describes the file(s) to upload. The value(s) can be a Buffer or Stream.

:::tip
When uploading an image, include a `fileInfo` object to set the file name, alt text, and caption.
:::

</Tabs>

:::caution
You have to send FormData in your request body.
:::

## Upload entry files

Upload one or more files that will be linked to a specific entry.

The following parameters are accepted:

| Parameter | Description |
| --------- | ----------- |
|`files`    | The file(s) to upload. The value(s) can be a Buffer or Stream. |
|`path` (optional) | The folder where the file(s) will be uploaded to (only supported on strapi-provider-upload-aws-s3). |
| `refId` | The ID of the entry which the file(s) will be linked to. |
| `ref` | The unique ID (uid) of the model which the file(s) will be linked to (see more below). |
| `source` (optional) | The name of the plugin where the model is located. |
| `field` | The field of the entry which the file(s) will be precisely linked to. |

For example, given the `Restaurant` model attributes:

```json title="/src/api/restaurant/content-types/restaurant/schema.json"
{
  // ...
  "attributes": {
    "name": {
      "type": "string"
    },
    "cover": {
      "type": "media",
      "multiple": false,
    }
  }
// ...
}
```

The following is an example of a corresponding front-end code:

```html
<form>
  <!-- Can be multiple files if you setup "collection" instead of "model" -->
  <input type="file" name="files" />
  <input type="text" name="ref" value="api::restaurant.restaurant" />
  <input type="text" name="refId" value="5c126648c7415f0c0ef1bccd" />
  <input type="text" name="field" value="cover" />
  <input type="submit" value="Submit" />
</form>

<script type="text/javascript">
  const form = document.querySelector('form');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    await fetch('/api/upload', {
      method: 'post',
      body: new FormData(e.target)
    });
  });
</script>
```

:::caution
You have to send FormData in your request body.
:::

## Update fileInfo

Update a file in your application.

`fileInfo` is the only accepted parameter, and describes the fileInfo to update:

```js

const fileId = 50;
const newFileData = {
  alternativeText: 'My new alternative text for this image!',
};

const form = new FormData();

form.append('fileInfo', JSON.stringify(newFileData));

const response = await fetch(`http://localhost:1337/api/upload?id=${fileId}`, {
  method: 'post',
  body: form,
});

```

## Models definition

Adding a file attribute to a [model](/cms/backend-customization/models) (or the model of another plugin) is like adding a new association.

The following example lets you upload and attach one file to the `avatar` attribute:

```json title="/src/api/restaurant/content-types/restaurant/schema.json"

{
  // ...
  {
    "attributes": {
      "pseudo": {
        "type": "string",
        "required": true
      },
      "email": {
        "type": "email",
        "required": true,
        "unique": true
      },
      "avatar": {
        "type": "media",
        "multiple": false,
      }
    }
  }
  // ...
}

```

The following example lets you upload and attach multiple pictures to the `restaurant` content-type:

```json title="/src/api/restaurant/content-types/restaurant/schema.json"
{
  // ...
  {
    "attributes": {
      "name": {
        "type": "string",
        "required": true
      },
      "covers": {
        "type": "media",
        "multiple": true,
      }
    }
  }
  // ...
}
```


