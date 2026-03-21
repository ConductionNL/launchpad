
# Documents
Source: https://docs.strapi.io/cms/api/document

<div className="document-concept-page custom-mermaid-layout">

# Documents

A **document** in Strapi 5 is an API-only concept. A document represents all the different variations of content for a given entry of a content-type.

A single type contains a unique document, and a collection type can contain several documents.

When you use the admin panel, the concept of a document is never mentioned and not necessary for the end user. Users create and edit **entries** in the [Content Manager](/cms/features/content-manager). For instance, as a user, you either list the entries for a given locale, or edit the draft version of a specific entry in a given locale.

However, at the API level, the value of the fields of an entry can actually have:

- different content for the English and the French locale,
- and even different content for the draft and published version in each locale.

The bucket that includes the content of all the draft and published versions for all the locales is a document.

Manipulating documents with the [Document Service API](/cms/api/document-service) will help you create, retrieve, update, and delete documents or a specific subset of the data they contain.

The following diagrams represent all the possible variations of content depending on which features, such as [Internationalization (i18n)](/cms/features/internationalization) and [Draft & Publish](/cms/features/draft-and-publish), are enabled for a content-type:

</Tabs>

- If the Internationalization (i18n) feature is enabled on the content-type, a document can have multiple **document locales**.
- If the Draft & Publish feature is enabled on the content-type, a document can have a **published** and a **draft** version.

:::strapi APIs to query documents data
To interact with documents or the data they represent:

  - From the back-end server (for instance, from controllers, services, and the back-end part of plugins), use the [Document Service API](/cms/api/document-service).
  - From the front-end part of your application, query your data using the [REST API](/cms/api/rest) or the [GraphQL API](/cms/api/graphql).

For additional information about the APIs, please refer to the [Content API introduction](/cms/api/content-api).
:::

:::info Default version in returned results
An important difference between the back-end and front-end APIs is about the default version returned when no parameter is passed:
- The Document Service API returns the draft version by default,
- while REST and GraphQL APIs return the published version by default.
:::

</div>




---


# Document Service API
Source: https://docs.strapi.io/cms/api/document-service

# Document Service API

The Document Service API is built on top of the **Query Engine API**  and is used to perform CRUD ([create](#create), [retrieve](#findone), [update](#update), and [delete](#delete)) operations on **documents** 

:::strapi Entity Service API is deprecated in Strapi 5
The Document Service API replaces the Entity Service API used in Strapi v4 (

</ApiCall>

The `findOne()` method returns the matching document if found, otherwise returns `null`.

### `findFirst()`

Find the first document matching the parameters.

Syntax:  `findFirst(parameters: Params) => Document`

#### Parameters

| Parameter | Description | Default | Type |
|-----------|-------------|---------|------|
| [`locale`](/cms/api/document-service/locale#find-first) |  Locale of the documents to find. | Default locale | String or `undefined` |
| [`status`](/cms/api/document-service/status#find-first) | _If [Draft & Publish](/cms/features/draft-and-publish) is enabled for the content-type_:<br/>Publication status, can be: <ul><li>`'published'` to find only published documents</li><li>`'draft'` to find only draft documents</li></ul> | `'draft'` | `'published'` or `'draft'` |
| [`filters`](/cms/api/document-service/filters) | [Filters](/cms/api/document-service/filters) to use | `null` | Object |
| [`fields`](/cms/api/document-service/fields#findfirst)   | [Select fields](/cms/api/document-service/fields#findfirst) to return   | All fields<br/>(except those not populate by default)  | Object |
| [`populate`](/cms/api/document-service/populate) | [Populate](/cms/api/document-service/populate) results with additional fields. | `null` | Object |

#### Examples

<br />

##### Generic example

By default, `findFirst()` returns the draft version, in the default locale, of the first document for the passed unique identifier (collection type id or single type id):

</ApiCall>

##### Find the first document matching parameters

Pass some parameters to `findFirst()` to return the first document matching them.

If no `locale` or `status` parameters are passed, results return the draft version for the default locale:

</ApiCall>

### `findMany()`

Find documents matching the parameters.

Syntax: `findMany(parameters: Params) => Document[]`

#### Parameters

| Parameter | Description | Default | Type |
|-----------|-------------|---------|------|
| [`locale`](/cms/api/document-service/locale#find-many) |  Locale of the documents to find. | Default locale | String or `undefined` |
| [`status`](/cms/api/document-service/status#find-many) | _If [Draft & Publish](/cms/features/draft-and-publish) is enabled for the content-type_:<br/>Publication status, can be: <ul><li>`'published'` to find only published documents</li><li>`'draft'` to find only draft documents</li></ul> | `'draft'` | `'published'` or `'draft'` |
| [`filters`](/cms/api/document-service/filters) | [Filters](/cms/api/document-service/filters) to use | `null` | Object |
| [`fields`](/cms/api/document-service/fields#findmany)   | [Select fields](/cms/api/document-service/fields#findmany) to return   | All fields<br/>(except those not populate by default)  | Object |
| [`populate`](/cms/api/document-service/populate) | [Populate](/cms/api/document-service/populate) results with additional fields. | `null` | Object |
| [`pagination`](/cms/api/document-service/sort-pagination#pagination) | [Paginate](/cms/api/document-service/sort-pagination#pagination) results |
| [`sort`](/cms/api/document-service/sort-pagination#sort) | [Sort](/cms/api/document-service/sort-pagination#sort) results | | | 

#### Examples

<br />

##### Generic example

When no parameter is passed, `findMany()` returns the draft version in the default locale for each document:

</ApiCall>

##### Find documents matching parameters

Available filters are detailed in the [filters](/cms/api/document-service/filters) page of the Document Service API reference.

If no `locale` or `status` parameters are passed, results return the draft version for the default locale:

</ApiCall>

<!-- TODO: To be completed post v5 GA -->
<!-- #### Find ‘fr’ version of all documents with fallback on default (en)

```js
await documents('api:restaurant.restaurant').findMany({ locale: 'fr', fallbackLocales: ['en'] } );
``` -->

<!-- TODO: To be completed post v5 GA -->
<!-- #### Find sibling locales for one or many documents

```js
await documents('api:restaurant.restaurant').findMany({ locale: 'fr', populateLocales: ['en', 'it'] } );
// Option of response forma for this case 
{
  data: {
		title: { "Wonderful" }
  },
  localizations: [
    { enLocaleData },
    { itLocaleData }
  ]
}

await documents('api:restaurant.restaurant').findMany({ locale: ['en', 'it'] } );
// Option of response format for this case 
{
  data: {
		title: {
			"en": "Wonderful",
			"it": "Bellissimo"
		}
  },
}
```

</Request> -->

### `create()`

Creates a drafted document and returns it.

Pass fields for the content to create in a `data` object.

Syntax: `create(parameters: Params) => Document`

#### Parameters

| Parameter | Description | Default | Type |
|-----------|-------------|---------|------|
| [`locale`](/cms/api/document-service/locale#create) | Locale of the documents to create. | Default locale | String or `undefined` |
| [`fields`](/cms/api/document-service/fields#create)   | [Select fields](/cms/api/document-service/fields#create) to return   | All fields<br/>(except those not populated by default)  | Object |
| [`status`](/cms/api/document-service/status#create) | _If [Draft & Publish](/cms/features/draft-and-publish) is enabled for the content-type_:<br/>Can be set to `'published'` to automatically publish the draft version of a document while creating it  | -| `'published'` |
| [`populate`](/cms/api/document-service/populate) | [Populate](/cms/api/document-service/populate) results with additional fields. | `null` | Object |

#### Example

If no `locale` parameter is passed, `create()` creates the draft version of the document for the default locale:

</ApiCall>

:::tip
If the [Draft & Publish](/cms/features/draft-and-publish) feature is enabled on the content-type, you can automatically publish a document while creating it (see [`status` documentation](/cms/api/document-service/status#create)).
:::

### `update()`

Updates document versions and returns them.

Syntax: `update(parameters: Params) => Promise

</ApiCall>

<!-- ! not working -->
<!-- #### Update many document locales

```js
// Updates the default locale by default
await documents('api:restaurant.restaurant').update(documentId, {locale: ['es', 'en'], data: {name: "updatedName" }}
``` -->

### `delete()`

Deletes one document, or a specific locale of it.

Syntax: `delete(parameters: Params): Promise<{ documentId: ID, entries: Number }>`

#### Parameters

| Parameter | Description | Default | Type |
|-----------|-------------|---------|------|
| `documentId`| Document id | | `ID`|
| [`locale`](/cms/api/document-service/locale#delete) | Locale version of the document to delete. | `null`<br/>(deletes only the default locale) | String, `'*'`, or `null` |
| [`filters`](/cms/api/document-service/filters) | [Filters](/cms/api/document-service/filters) to use | `null` | Object |
| [`fields`](/cms/api/document-service/fields#delete)   | [Select fields](/cms/api/document-service/fields#delete) to return   | All fields<br/>(except those not populate by default)  | Object |
| [`populate`](/cms/api/document-service/populate) | [Populate](/cms/api/document-service/populate) results with additional fields. | `null` | Object |

#### Example

If no `locale` parameter is passed, `delete()` only deletes the default locale version of a document. This deletes both the draft and published versions:

<!-- ! not working -->
<!-- #### Delete a document with filters

To delete documents matching parameters, pass these parameters to `delete()`.

If no `locale` parameter is passed, it will delete only the default locale version:

 -->

### `publish()`

Publishes one or multiple locales of a document.

This method is only available if [Draft & Publish](/cms/features/draft-and-publish) is enabled on the content-type.

Syntax: `publish(parameters: Params): Promise<{ documentId: ID, entries: Number }>`

#### Parameters

| Parameter | Description | Default | Type |
|-----------|-------------|---------|------|
| `documentId`| Document id | | `ID`|
| [`locale`](/cms/api/document-service/locale#publish) | Locale of the documents to publish. | Only the default locale | String, `'*'`, or `null` |
| [`filters`](/cms/api/document-service/filters) | [Filters](/cms/api/document-service/filters) to use | `null` | Object |
| [`fields`](/cms/api/document-service/fields#publish)   | [Select fields](/cms/api/document-service/fields#publish) to return   | All fields<br/>(except those not populate by default)  | Object |
| [`populate`](/cms/api/document-service/populate) | [Populate](/cms/api/document-service/populate) results with additional fields. | `null` | Object |

#### Example

If no `locale` parameter is passed, `publish()` only publishes the default locale version of the document:

</ApiCall>

<!-- ! not working -->
<!-- #### Publish document locales with filters

```js
// Only publish locales with title is "Ready to publish"
await strapi.documents('api::restaurant.restaurant').publish(
  { filters: { title: 'Ready to publish' }}
);
``` -->

### `unpublish()`

Unpublishes one or all locale versions of a document, and returns how many locale versions were unpublished.

This method is only available if [Draft & Publish](/cms/features/draft-and-publish) is enabled on the content-type.

Syntax: `unpublish(parameters: Params): Promise<{ documentId: ID, entries: Number }>`

#### Parameters

| Parameter | Description | Default | Type |
|-----------|-------------|---------|------|
| `documentId`| Document id | | `ID`|
| [`locale`](/cms/api/document-service/locale#unpublish) | Locale of the documents to unpublish. | Only the default locale | String, `'*'`, or `null` |
| [`filters`](/cms/api/document-service/filters) | [Filters](/cms/api/document-service/filters) to use | `null` | Object |
| [`fields`](/cms/api/document-service/fields#unpublish)   | [Select fields](/cms/api/document-service/fields#unpublish) to return   | All fields<br/>(except those not populate by default)  | Object |
| [`populate`](/cms/api/document-service/populate) | [Populate](/cms/api/document-service/populate) results with additional fields. | `null` | Object |

#### Example

If no `locale` parameter is passed, `unpublish()` only unpublishes the default locale version of the document:

</ApiCall>

### `discardDraft()`

Discards draft data and overrides it with the published version.

This method is only available if [Draft & Publish](/cms/features/draft-and-publish) is enabled on the content-type.

Syntax: `discardDraft(parameters: Params): Promise<{ documentId: ID, entries: Number }>`

#### Parameters

| Parameter | Description | Default | Type |
|-----------|-------------|---------|------|
| `documentId`| Document id | | `ID`|
| [`locale`](/cms/api/document-service/locale#discard-draft) | Locale of the documents to discard. | Only the default locale. | String, `'*'`, or `null` |
| [`filters`](/cms/api/document-service/filters) | [Filters](/cms/api/document-service/filters) to use | `null` | Object |
| [`fields`](/cms/api/document-service/fields#discarddraft)   | [Select fields](/cms/api/document-service/fields#discarddraft) to return   | All fields<br/>(except those not populate by default)  | Object |
| [`populate`](/cms/api/document-service/populate) | [Populate](/cms/api/document-service/populate) results with additional fields. | `null` | Object |

#### Example

If no `locale` parameter is passed, `discardDraft()` discards draft data and overrides it with the published version only for the default locale:

</ApiCall>

### `count()`

Count the number of documents that match the provided parameters.

Syntax: `count(parameters: Params) => number`

#### Parameters

| Parameter | Description | Default | Type |
|-----------|-------------|---------|------|
| [`locale`](/cms/api/document-service/locale#count) | Locale of the documents to count | Default locale | String or `null` |
| [`status`](/cms/api/document-service/status#count) | _If [Draft & Publish](/cms/features/draft-and-publish) is enabled for the content-type_:<br/>Publication status, can be: <ul><li>`'published'` to find only published documents </li><li>`'draft'` to find draft documents (will return all documents)</li></ul> | `'draft'` | `'published'` or `'draft'` |
| [`filters`](/cms/api/document-service/filters) | [Filters](/cms/api/document-service/filters) to use | `null` | Object |

:::note
Since published documents necessarily also have a draft counterpart, a published document is still counted as having a draft version.

This means that counting with the `status: 'draft'` parameter still returns the total number of documents matching other parameters, even if some documents have already been published and are not displayed as "draft" or "modified" in the Content Manager anymore. There currently is no way to prevent already published documents from being counted.
:::

#### Examples

<br />

##### Generic example

If no parameter is passed, the `count()` method the total number of documents for the default locale:

</ApiCall>

##### Count published documents

To count only published documents, pass `status: 'published'` along with other parameters to the `count()` method.

If no `locale` parameter is passed, documents are counted for the default locale.

##### Count documents with filters

Any [filters](/cms/api/document-service/filters) can be passed to the `count()` method.

If no `locale` and no `status` parameter is passed, draft documents (which is the total of available documents for the locale since even published documents are counted as having a draft version) are counted only for the default locale:

```js
/**
 * Count number of draft documents (default if status is omitted) 
 * in English (default locale) 
 * whose name starts with 'Pizzeria'
 */
strapi.documents('api::restaurant.restaurant').count({ filters: { name: { $startsWith: "Pizzeria" }}})`
```




---


# Using fields with the Document Service API
Source: https://docs.strapi.io/cms/api/document-service/fields

# Document Service API: Selecting fields

By default the [Document Service API](/cms/api/document-service) returns all the fields of a document but does not populate any fields. This page describes how to use the `fields` parameter to return only specific fields with the query results.

:::tip
You can also use the `populate` parameter to populate relations, media fields, components, or dynamic zones (see the [`populate` parameter](/cms/api/document-service/populate) documentation).
:::

</ApiCall>

## Select fields with `findFirst()` queries {#findfirst}

To select fields to return while [finding the first document](/cms/api/document-service#findfirst) matching the parameters with the Document Service API:

</ApiCall>

## Select fields with `findMany()` queries {#findmany}

To select fields to return while [finding documents](/cms/api/document-service#findmany) with the Document Service API:

</ApiCall>

## Select fields with `create()` queries {#create}

To select fields to return while [creating documents](/cms/api/document-service#create) with the Document Service API:

</ApiCall>

## Select fields with `update()` queries {#update}

To select fields to return while [updating documents](/cms/api/document-service#update) with the Document Service API:

</ApiCall>

## Select fields with `delete()` queries {#delete}

To select fields to return while [deleting documents](/cms/api/document-service#delete) with the Document Service API:

</ApiCall>

## Select fields with `publish()` queries {#publish}

To select fields to return while [publishing documents](/cms/api/document-service#publish) with the Document Service API:

</ApiCall>

## Select fields with `unpublish()` queries {#unpublish}

To select fields to return while [unpublishing documents](/cms/api/document-service#unpublish) with the Document Service API:

</ApiCall>

## Select fields with `discardDraft()` queries {#discarddraft}

To select fields to return while [discarding draft versions of documents](/cms/api/document-service#discarddraft) with the Document Service API:

</ApiCall>




---


# Using filters with the Document Service API
Source: https://docs.strapi.io/cms/api/document-service/filters

# Document Service API: Filters

The [Document Service API](/cms/api/document-service) offers the ability to filter results.

The following operators are available:

| Operator                         | Description                              |
| -------------------------------- | ---------------------------------------- |
| [`$eq`](#eq)                     | Equal                                    |
| [`$eqi`](#eqi)                   | Equal (case-insensitive)                 |
| [`$ne`](#ne)                     | Not equal                                |
| [`$nei`](#nei)                   | Not equal (case-insensitive)             |
| [`$lt`](#lt)                     | Less than                                |
| [`$lte`](#lte)                   | Less than or equal to                    |
| [`$gt`](#gt)                     | Greater than                             |
| [`$gte`](#gte)                   | Greater than or equal to                 |
| [`$in`](#in)                     | Included in an array                     |
| [`$notIn`](#notin)               | Not included in an array                 |
| [`$contains`](#contains)         | Contains                                 |
| [`$notContains`](#notcontains)   | Does not contain                         |
| [`$containsi`](#containsi)       | Contains (case-insensitive)              |
| [`$notContainsi`](#notcontainsi) | Does not contain (case-insensitive)      |
| [`$null`](#null)                 | Is null                                  |
| [`$notNull`](#notnull)           | Is not null                              |
| [`$between`](#between)           | Is between                               |
| [`$startsWith`](#startswith)     | Starts with                              |
| [`$startsWithi`](#startswithi)   | Starts with (case-insensitive)           |
| [`$endsWith`](#endswith)         | Ends with                                |
| [`$endsWithi`](#endswithi)       | Ends with (case-insensitive)             |
| [`$or`](#or)                     | Joins the filters in an "or" expression  |
| [`$and`](#and)                   | Joins the filters in an "and" expression |
| [`$not`](#not)                   | Joins the filters in an "not" expression |

## Attribute operators

<br/>

### `$not`

Negates the nested condition(s).

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $not: {
        $contains: 'Hello World',
      },
    },
  },
});
```

### `$eq`

Attribute equals input value.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $eq: 'Hello World',
    },
  },
});
```

`$eq` can be omitted:

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: 'Hello World',
  },
});
```

### `$eqi`

Attribute equals input value (case-insensitive).

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $eqi: 'HELLO World',
    },
  },
});
```

### `$ne`

Attribute does not equal input value.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $ne: 'ABCD',
    },
  },
});
```

### `$nei`

Attribute does not equal input value (case-insensitive).

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $nei: 'abcd',
    },
  },
});
```

### `$in`

Attribute is contained in the input list.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $in: ['Hello', 'Hola', 'Bonjour'],
    },
  },
});
```

`$in` can be omitted when passing an array of values:

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: ['Hello', 'Hola', 'Bonjour'],
  },
});
```

### `$notIn`

Attribute is not contained in the input list.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $notIn: ['Hello', 'Hola', 'Bonjour'],
    },
  },
});
```

### `$lt`

Attribute is less than the input value.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    rating: {
      $lt: 10,
    },
  },
});
```

### `$lte`

Attribute is less than or equal to the input value.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    rating: {
      $lte: 10,
    },
  },
});
```

### `$gt`

Attribute is greater than the input value.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    rating: {
      $gt: 5,
    },
  },
});
```

### `$gte`

Attribute is greater than or equal to the input value.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    rating: {
      $gte: 5,
    },
  },
});
```

### `$between`

Attribute is between the 2 input values, boundaries included (e.g., `$between[1, 3]` will also return `1` and `3`).

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    rating: {
      $between: [1, 20],
    },
  },
});
```

### `$contains`

Attribute contains the input value (case-sensitive).

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $contains: 'Hello',
    },
  },
});
```

### `$notContains`

Attribute does not contain the input value (case-sensitive).

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $notContains: 'Hello',
    },
  },
});
```

### `$containsi`

Attribute contains the input value. `$containsi` is not case-sensitive, while [$contains](#contains) is.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $containsi: 'hello',
    },
  },
});
```

### `$notContainsi`

Attribute does not contain the input value. `$notContainsi` is not case-sensitive, while [$notContains](#notcontains) is.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $notContainsi: 'hello',
    },
  },
});
```

### `$startsWith`

Attribute starts with input value (case-sensitive).

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $startsWith: 'ABCD',
    },
  },
});
```

### `$startsWithi`

Attribute starts with input value (case-insensitive).

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $startsWithi: 'ABCD', // will return the same as filtering with 'abcd'
    },
  },
});
```

### `$endsWith`

Attribute ends with input value (case-sensitive).

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $endsWith: 'ABCD',
    },
  },
});
```

### `$endsWithi`

Attribute ends with input value (case-insensitive).

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $endsWith: 'ABCD', // will return the same as filtering with 'abcd'
    },
    },
  },
});
```

### `$null`

Attribute is `null`.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $null: true,
    },
  },
});
```

### `$notNull`

Attribute is not `null`.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: {
      $notNull: true,
    },
  },
});
```

## Logical operators

### `$and`

All nested conditions must be `true`.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    $and: [
      {
        title: 'Hello World',
      },
      {
        createdAt: { $gt: '2021-11-17T14:28:25.843Z' },
      },
    ],
  },
});
```

`$and` will be used implicitly when passing an object with nested conditions:

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    title: 'Hello World',
    createdAt: { $gt: '2021-11-17T14:28:25.843Z' },
  },
});
```

### `$or`

One or many nested conditions must be `true`.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    $or: [
      {
        title: 'Hello World',
      },
      {
        createdAt: { $gt: '2021-11-17T14:28:25.843Z' },
      },
    ],
  },
});
```

### `$not`

Negates the nested conditions.

**Example**

```js
const entries = await strapi.documents('api::article.article').findMany({
  filters: {
    $not: {
      title: 'Hello World',
    },
  },
});
```

:::note
`$not` can be used as:

- a logical operator (e.g. in `filters: { $not: { // conditions… }}`)
- [an attribute operator](#not) (e.g. in `filters: { attribute-name: $not: { … } }`).
:::

:::tip
`$and`, `$or` and `$not` operators are nestable inside of another `$and`, `$or` or `$not` operator.
:::




---


# Using the locale parameter with the Document Service API
Source: https://docs.strapi.io/cms/api/document-service/locale

# Document Service API: Using the `locale` parameter

By default the [Document Service API](/cms/api/document-service) returns the default locale version of documents (which is 'en', i.e. the English version, unless another default locale has been set for the application, see [Internationalization (i18n) feature](/cms/features/internationalization)). This page describes how to use the `locale` parameter to get or manipulate data only for specific locales.

## Get a locale version with `findOne()` {#find-one}

If a `locale` is passed, the [`findOne()` method](/cms/api/document-service#findone) of the Document Service API returns the version of the document for this locale:

</ApiCall>

If no `status` parameter is passed, the `draft` version is returned by default.

## Get a locale version with `findFirst()` {#find-first}

To return a specific locale while [finding the first document](/cms/api/document-service#findfirst) matching the parameters with the Document Service API:

</ApiCall>

If no `status` parameter is passed, the `draft` version is returned by default.

## Get locale versions with `findMany()` {#find-many}

When a `locale` is passed to the [`findMany()` method](/cms/api/document-service#findmany) of the Document Service API, the response will return all documents that have this locale available.

If no `status` parameter is passed, the `draft` versions are returned by default.

</ApiCall>

<details>
<summary>Explanation:</summary>

Given the following 4 documents that have various locales:

- Document A:
  - en
  - `fr`
  - it
- Document B:
  - en
  - it
- Document C:
  - `fr`
- Document D:
  - `fr`
  - it

`findMany({ locale: 'fr' })` would only return the draft version of the documents that have a `‘fr’` locale version, that is documents A, C, and D.

</details>

## `create()` a document for a locale {#create}

To create a document for specific locale, pass the `locale` as a parameter to the [`create` method](/cms/api/document-service#create) of the Document Service API:

</ApiCall>

## `update()` a locale version {#update}

To update only a specific locale version of a document, pass the `locale` parameter to the [`update()` method](/cms/api/document-service#update) of the Document Service API:

</ApiCall>

## `delete()` locale versions {#delete}

Use the `locale` parameter with the [`delete()` method](/cms/api/document-service#delete) of the Document Service API to delete only some locales. Unless a specific `status` parameter is passed, this deletes both the draft and published versions.

### Delete a locale version

To delete a specific locale version of a document:

### Delete all locale versions

The `*` wildcard is supported by the `locale` parameter and can be used to delete all locale versions of a document:

</ApiCall>

## `publish()` locale versions {#publish}

To publish only specific locale versions of a document with the [`publish()` method](/cms/api/document-service#publish) of the Document Service API, pass `locale` as a parameter:

### Publish a locale version

To publish a specific locale version of a document:

</ApiCall>

### Publish all locale versions

The `*` wildcard is supported by the `locale` parameter to publish all locale versions of a document:

</ApiCall>

## `unpublish()` locale versions {#unpublish}

To publish only specific locale versions of a document with the [`unpublish()` method](/cms/api/document-service#unpublish) of the Document Service API, pass `locale` as a parameter:

### Unpublish a locale version

To unpublish a specific locale version of a document, pass the `locale` as a parameter to `unpublish()`:

</ApiCall>

### Unpublish all locale versions

The `*` wildcard is supported by the `locale` parameter, to unpublish all locale versions of a document:

</ApiCall>

</ApiCall>

## `discardDraft()` for locale versions {#discard-draft}

To discard draft data only for some locales versions of a document with the [`discardDraft()` method](/cms/api/document-service#discarddraft) of the Document Service API, pass `locale` as a parameter:

### Discard draft for a locale version

To discard draft data for a specific locale version of a document and override it with data from the published version for this locale, pass the `locale` as a parameter to `discardDraft()`:

</ApiCall>

### Discard drafts for all locale versions

The `*` wildcard is supported by the `locale` parameter, to discard draft data for all locale versions of a document and replace them with the data from the published versions:

</ApiCall>

## `count()` documents for a locale {#count}

To count documents for a specific locale, pass the `locale` along with other parameters to the [`count()` method](/cms/api/document-service#count) of the Document Service API.

If no `status` parameter is passed, draft documents are counted (which is the total of available documents for the locale since even published documents are counted as having a draft version):

```js
// Count number of published documents in French
strapi.documents('api::restaurant.restaurant').count({ locale: 'fr' });
```




---


# Extending the Document Service behavior
Source: https://docs.strapi.io/cms/api/document-service/middlewares

# Document Service API: Middlewares

The [Document Service API](/cms/api/document-service) offers the ability to extend its behavior thanks to middlewares.

Document Service middlewares allow you to perform actions before and/or after a method runs.

<figure style={{width: '100%', margin: '0'}}>
  <img src="/img/assets/backend-customization/diagram-controllers-services.png" alt="Simplified Strapi backend diagram with controllers highlighted" />
  <em><figcaption style={{fontSize: '12px'}}>The diagram represents a simplified version of how a request travels through the Strapi back end, with the Document Service highlighted. The backend customization introduction page includes a complete, <a href="/cms/backend-customization#interactive-diagram">interactive diagram</a>.</figcaption></em>
</figure>

## Registering a middleware

Syntax: `strapi.documents.use(middleware)`

### Parameters

A middleware is a function that receives a context and a next function.

Syntax: `(context, next) => ReturnType<typeof next>`

| Parameter | Description                           | Type       |
|-----------|---------------------------------------|------------|
| `context` | Middleware context                    | `Context`  |
| `next`    | Call the next middleware in the stack | `function` |

#### `context`

| Parameter     | Description                                                                          | Type          |
|---------------|--------------------------------------------------------------------------------------|---------------|
| `action`      | The method that is running ([see available methods](/cms/api/document-service)) | `string`      |
| `params`      | The method params ([see available methods](/cms/api/document-service))          | `Object`      |
| `uid`         | Content type unique identifier                                                       | `string`      |
| `contentType` | Content type                                                                         | `ContentType` |

<details>
<summary>Examples:</summary>

The following examples show what `context` might include depending on the method called:

</Tabs>
</details>

#### `next`

`next` is a function without parameters that calls the next middleware in the stack and return its response.

**Example**

```js
strapi.documents.use((context, next) => {
  return next();
});
```

### Where to register

Generaly speaking you should register your middlewares during the Strapi registration phase.

#### Users

The middleware must be registered in the general `register()` lifecycle method:

```js title="/src/index.js|ts"
module.exports = {
  register({ strapi }) {
    strapi.documents.use((context, next) => {
      // your logic
      return next();
    });
  },

  // bootstrap({ strapi }) {},
  // destroy({ strapi }) {},
};
```

#### Plugin developers

The middleware must be registered in the plugin's `register()` lifecycle method:

```js title="/(plugin-root-folder)/strapi-server.js|ts"
module.exports = {
  register({ strapi }) {
    strapi.documents.use((context, next) => {
      // your logic
      return next();
    });
  },

  // bootstrap({ strapi }) {},
  // destroy({ strapi }) {},
};
```

## Implementing a middleware

When implementing a middleware, always return the response from `next()`.
Failing to do this will break the Strapi application.

### Examples

```js
const applyTo = ['api::article.article'];

strapi.documents.use((context, next) => {
  // Only run for certain content types
  if (!applyTo.includes(context.uid)) {
    return next();
  }

  // Only run for certain actions
  if (['create', 'update'].includes(context.action)) {
    context.params.data.fullName = `${context.params.data.firstName} ${context.params.data.lastName}`;
  }

  const result = await next();

  // do something with the result before returning it
  return result
});
```

<br/>

:::strapi Lifecycle hooks
The Document Service API triggers various database lifecycle hooks based on which method is called. For a complete reference, see [Document Service API: Lifecycle hooks](/cms/migration/v4-to-v5/breaking-changes/lifecycle-hooks-document-service#table).
:::




---


# Using Populate with the Document Service API
Source: https://docs.strapi.io/cms/api/document-service/populate

# Document Service API: Populating fields

By default the [Document Service API](/cms/api/document-service) does not populate any relations, media fields, components, or dynamic zones. This page describes how to use the `populate` parameter to populate specific fields.

:::tip
You can also use the `select` parameter to return only specific fields with the query results (see the [`select` parameter](/cms/api/document-service/fields) documentation).
:::

:::caution
If the Users & Permissions plugin is installed, the `find` permission must be enabled for the content-types that are being populated. If a role doesn't have access to a content-type it will not be populated.
:::

<!-- TODO: add link to populate guides (even if REST API, the same logic still applies) -->

## Relations and media fields

Queries can accept a `populate` parameter to explicitly define which fields to populate, with the following syntax option examples.

### Populate 1 level for all relations

To populate one-level deep for all relations, use the `*` wildcard in combination with the `populate` parameter:

</ApiCall>

### Populate 1 level for specific relations

To populate specific relations one-level deep, pass the relation names in a `populate` array:

</ApiCall>

### Populate several levels deep for specific relations

To populate specific relations several levels deep, use the object format with `populate`:

</ApiCall>

## Components & Dynamic Zones

Components are populated the same way as relations:

</ApiCall>

Dynamic zones are highly dynamic content structures by essence. To populate a dynamic zone, you must define per-component populate queries using the `on` property.

</ApiCall>

## Populating with `create()`

To populate while creating documents:

</ApiCall>

## Populating with `update()`

To populate while updating documents:

</ApiCall>

## Populating with `publish()`

To populate while publishing documents (same behavior with `unpublish()` and `discardDraft()`):

</ApiCall>




---


# Using Sort & Pagination with the Document Service API
Source: https://docs.strapi.io/cms/api/document-service/sort-pagination

# Document Service API: Sorting and paginating results

The [Document Service API](/cms/api/document-service) offers the ability to sort and paginate query results.

## Sort

To sort results returned by the Document Service API, include the `sort` parameter with queries.

### Sort on a single field

To sort results based on a single field:

</ApiCall>

### Sort on multiple fields

To sort on multiple fields, pass them all in an array:

</ApiCall>

## Pagination

To paginate results, pass the `limit` and `start` parameters:

</ApiCall>




---


# Using Draft & Publish with the Document Service API
Source: https://docs.strapi.io/cms/api/document-service/status

# Document Service API: Usage with Draft & Publish

By default the [Document Service API](/cms/api/document-service) returns the draft version of a document when the [Draft & Publish](/cms/features/draft-and-publish) feature is enabled. This page describes how to use the `status` parameter to:

- return the published version of a document,
- count documents depending on their status, 
- and directly publish a document while creating it or updating it.

:::note
Passing `{ status: 'draft' }` to a Document Service API query returns the same results as not passing any `status` parameter.
:::

## Get the published version with `findOne()` {#find-one}

`findOne()` queries return the draft version of a document by default.

To return the published version while [finding a specific document](/cms/api/document-service#findone) with the Document Service API, pass `status: 'published'`:

</ApiCall>

## Get the published version with `findFirst()` {#find-first}

`findFirst()` queries return the draft version of a document by default.

To return the published version while [finding the first document](/cms/api/document-service#findfirst) with the Document Service API, pass `status: 'published'`:

</ApiCall>

## Get the published version with `findMany()` {#find-many}

`findMany()` queries return the draft version of documents by default.

To return the published version while [finding documents](/cms/api/document-service#findmany) with the Document Service API, pass `status: 'published'`:

</ApiCall>

## `count()` only draft or published versions {#count}

To take into account only draft or published versions of documents while [counting documents](/cms/api/document-service#count) with the Document Service API, pass the corresponding `status` parameter:

```js
// Count draft documents (also actually includes published documents)
const draftsCount = await strapi.documents("api::restaurant.restaurant").count({
  status: 'draft'
});
```

```js
// Count only published documents
const publishedCount = await strapi.documents("api::restaurant.restaurant").count({
  status: 'published'
});
```

:::note
Since published documents necessarily also have a draft counterpart, a published document is still counted as having a draft version.

This means that counting with the `status: 'draft'` parameter still returns the total number of documents matching other parameters, even if some documents have already been published and are not displayed as "draft" or "modified" in the Content Manager anymore. There currently is no way to prevent already published documents from being counted.
:::

## Create a draft and publish it {#create}

To automatically publish a document while creating it, add `status: 'published'` to parameters passed to `create()`:

</ApiCall>

## Update a draft and publish it {#update}

To automatically publish a document while updating it, add `status: 'published'` to parameters passed to `update()`:

</ApiCall>




---


# Models
Source: https://docs.strapi.io/cms/backend-customization/models

# Models

As Strapi is a headless Content Management System (CMS), creating a content structure for the content is one of the most important aspects of using the software. Models define a representation of the content structure.

There are 2 different types of models in Strapi:

- content-types, which can be collection types or single types, depending on how many entries they manage,
- and components that are content structures re-usable in multiple content-types.

If you are just starting out, it is convenient to generate some models with the [Content-type Builder](/cms/features/content-type-builder) directly in the admin panel. The user interface takes over a lot of validation tasks and showcases all the options available to create the content's content structure. The generated model mappings can then be reviewed at the code level using this documentation.

## Model creation

Content-types and components models are created and stored differently.

### Content-types

Content-types in Strapi can be created:

- with the [Content-type Builder in the admin panel](/cms/features/content-type-builder),
- or with [Strapi's interactive CLI `strapi generate`](/cms/cli#strapi-generate) command.

The content-types use the following files:

- `schema.json` for the model's [schema](#model-schema) definition. (generated automatically, when creating content-type with either method)
- `lifecycles.js` for [lifecycle hooks](#lifecycle-hooks). This file must be created manually.

These models files are stored in `./src/api/[api-name]/content-types/[content-type-name]/`, and any JavaScript or JSON file found in these folders will be loaded as a content-type's model (see [project structure](/cms/project-structure)).

:::note
In [TypeScript](/cms/typescript.md)-enabled projects, schema typings can be generated using the `ts:generate-types` command.
:::

### Components {#components-creation}

Component models can't be created with CLI tools. Use the [Content-type Builder](/cms/features/content-type-builder) or create them manually.

Components models are stored in the `./src/components` folder. Every component has to be inside a subfolder, named after the category the component belongs to (see [project structure](/cms/project-structure)).

## Model schema

The `schema.json` file of a model consists of:

- [settings](#model-settings), such as the kind of content-type the model represents or the table name in which the data should be stored,
- [information](#model-information), mostly used to display the model in the admin panel and access it through the REST and GraphQL APIs,
- [attributes](#model-attributes), which describe the content structure of the model,
- and [options](#model-options) used to defined specific behaviors on the model.

### Model settings

General settings for the model can be configured with the following parameters:

| Parameter                                          | Type   | Description                                                                                                            |
| -------------------------------------------- | ------ | ---------------------------------------------------------------------------------------------------------------------- |
| `collectionName`                                  | String | Database table name in which the data should be stored                                                    |
| `kind`<br /><br />_Optional,<br/>only for content-types_ | String | Defines if the content-type is:<ul><li>a collection type (`collectionType`)</li><li>or a single type (`singleType`)</li></ul> |

```json
// ./src/api/[api-name]/content-types/restaurant/schema.json

{
  "kind": "collectionType",
  "collectionName": "Restaurants_v1",
}
```

### Model information

The `info` key in the model's schema describes information used to display the model in the admin panel and access it through the Content API. It includes the following parameters:

<!-- ? with the new design system, do we still use FontAwesome?  -->

| Parameter            | Type   | Description                                                                                                                                 |
| -------------- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `displayName`  | String | Default name to use in the admin panel                                                                                                      |
| `singularName` | String | Singular form of the content-type name.<br />Used to generate the API routes and databases/tables collection.<br /><br />Should be kebab-case. |
| `pluralName`   | String | Plural form of the content-type name.<br />Used to generate the API routes and databases/tables collection.<br /><br />Should be kebab-case.    |
| `description`  | String | Description of the model                                                                                                                   |

```json title="./src/api/[api-name]/content-types/restaurant/schema.json"

  "info": {
    "displayName": "Restaurant",
    "singularName": "restaurant",
    "pluralName": "restaurants",
    "description": ""
  },
```

### Model attributes

The content structure of a model consists of a list of attributes. Each attribute has a `type` parameter, which describes its nature and defines the attribute as a simple piece of data or a more complex structure used by Strapi.

Many types of attributes are available:

- scalar types (e.g. strings, dates, numbers, booleans, etc.),
- Strapi-specific types, such as:
  - `media` for files uploaded through the [Media library](/cms/features/content-type-builder#media)
  - `relation` to describe a [relation](#relations) between content-types
  - `customField` to describe [custom fields](#custom-fields) and their specific keys
  - `component` to define a [component](#components-json) (i.e. a content structure usable in multiple content-types)
  - `dynamiczone` to define a [dynamic zone](#dynamic-zones) (i.e. a flexible space based on a list of components)
  - and the `locale` and `localizations` types, only used by the [Internationalization (i18n) plugin](/cms/features/internationalization)

The `type` parameter of an attribute should be one of the following values:

| Type categories | Available types |
|------|-------|
| String types | <ul><li>`string`</li> <li>`text`</li> <li>`richtext`</li><li>`enumeration`</li> <li>`email`</li><li>`password`</li><li>[`uid`](#uid-type)</li></ul> |
| Date types | <ul><li>`date`</li> <li>`time`</li> <li>`datetime`</li> <li>`timestamp`</li></ul> |
| Number types | <ul><li>`integer`</li><li>`biginteger`</li><li>`float`</li> <li>`decimal`</li></ul> |
| Other generic types |<ul><li>`boolean`</li><li>`json`</li></ul> |
| Special types unique to Strapi |<ul><li>`media`</li><li>[`relation`](#relations)</li><li>[`customField`](#custom-fields)</li><li>[`component`](#components-json)</li><li>[`dynamiczone`](#dynamic-zones)</li></ul> |
| Internationalization (i18n)-related types<br /><br />_Can only be used if the [i18n](/cms/features/internationalization) is enabled on the content-type_|<ul><li>`locale`</li><li>`localizations`</li></ul> |

#### Validations

Basic validations can be applied to attributes using the following parameters:

| Parameter | Type    | Description                                                                                               | Default |
| -------------- | ------- | --------------------------------------------------------------------------------------------------------- | ------- |
| `required`     | Boolean | If `true`, adds a required validator for this property                                                     | `false` |
| `max`          | Integer | Checks if the value is greater than or equal to the given maximum                                        | -       |
| `min`          | Integer | Checks if the value is less than or equal to the given minimum                                           | -       |
| `minLength`    | Integer | Minimum number of characters for a field input value                                                      | -       |
| `maxLength`    | Integer | Maximum number of characters for a field input value                                                      | -       |
| `private`      | Boolean | If `true`, the attribute will be removed from the server response.<br/><br/>💡 This is useful to hide sensitive data. | `false` |
| `configurable` | Boolean | If `false`, the attribute isn't configurable from the Content-type Builder plugin.                         | `true`  |

```json title="./src/api/[api-name]/content-types/restaurant/schema.json"

{
  // ...
  "attributes": {
    "title": {
      "type": "string",
      "minLength": 3,
      "maxLength": 99,
      "unique": true
    },
    "description": {
      "default": "My description",
      "type": "text",
      "required": true
    },
    "slug": {
      "type": "uid",
      "targetField": "title"
    }
    // ...
  }
}
```

#### Database validations and settings

:::caution 🚧 This API is considered experimental.
These settings should be reserved to an advanced usage, as they might break some features. There are no plans to make these settings stable.
:::

Database validations and settings are custom options passed directly onto the `tableBuilder` Knex.js function during schema migrations. Database validations allow for an advanced degree of control for setting custom column settings. The following options are set in a `column: {}` object per attribute:

| Parameter     | Type    | Description                                                                                   | Default |
| ------------- | ------- | --------------------------------------------------------------------------------------------- | ------- |
| `name`        | string  | Changes the name of the column in the database                                                | -       |
| `defaultTo`   | string  | Sets the database `defaultTo`, typically used with `notNullable`                              | -       |
| `notNullable` | boolean | Sets the database `notNullable`, ensures that columns cannot be null                          | `false` |
| `unsigned`    | boolean | Only applies to number columns, removes the ability to go negative but doubles maximum length | `false` |
| `unique`      | boolean | Enforces database-level uniqueness on published entries. Draft saves skip the check when Draft & Publish is enabled, so duplicates fail only at publish time. | `false` |
| `type`        | string  | Changes the database type, if `type` has arguments, you should pass them in `args`            | -       |
| `args`        | array   | Arguments passed into the Knex.js function that changes things like `type`                    | `[]`    |

:::caution Draft & Publish and `unique`
When [Draft & Publish](/cms/features/draft-and-publish) is enabled, Strapi intentionally skips `unique` validations while an entry is saved as a draft. Duplicates therefore remain undetected until publication, at which point the database constraint triggers an error even though the UI previously displayed “Saved document” for the drafts.

To avoid unexpected publication failures:

- disable Draft & Publish on content-types that must stay globally unique,
- or add custom validation (e.g. lifecycle hooks or middleware) that checks for draft duplicates before saving,
- or rely on automatically generated unique identifiers such as a `uid` field and document editorial conventions.
:::

```json title="./src/api/[api-name]/content-types/restaurant/schema.json"

{
  // ...
  "attributes": {
    "title": {
      "type": "string",
      "minLength": 3,
      "maxLength": 99,
      "unique": true,
      "column": {
        "unique": true // enforce database unique also
      }
    },
    "description": {
      "default": "My description",
      "type": "text",
      "required": true,
      "column": {
        "defaultTo": "My description", // set database level default
        "notNullable": true // enforce required at database level, even for drafts
      }
    },
    "rating": {
      "type": "decimal",
      "default": 0,
      "column": {
        "defaultTo": 0,
        "type": "decimal", // using the native decimal type but allowing for custom precision
        "args": [
          6,1 // using custom precision and scale
        ]
      }
    }
    // ...
  }
}
```

#### `uid` type

The `uid` type is used to automatically prefill the field value in the admin panel with a unique identifier (UID) (e.g. slugs for articles) based on 2 optional parameters:

- `targetField` (string): If used, the value of the field defined as a target is used to auto-generate the UID.
- `options` (string): If used, the UID is generated based on a set of options passed to 

</Tabs>

#### Custom fields

[Custom fields](/cms/features/custom-fields) extend Strapi’s capabilities by adding new types of fields to content-types. Custom fields are explicitly defined in the [attributes](#model-attributes) of a model with `type: customField`.

Custom fields' attributes also show the following specificities:

- a `customField` attribute whose value acts as a unique identifier to indicate which registered custom field should be used. Its value follows:
   - either the `plugin::plugin-name.field-name` format if a plugin created the custom field 
   - or the `global::field-name` format for a custom field specific to the current Strapi application
- and additional parameters depending on what has been defined when registering the custom field (see [custom fields documentation](/cms/features/custom-fields)).

```json title="./src/api/[apiName]/[content-type-name]/content-types/schema.json"

{
  // …
  "attributes": {
    "attributeName": { // attributeName would be replaced by the actual attribute name
      "type": "customField",
      "customField": "plugin::color-picker.color",
      "options": {
        "format": "hex"
      }
    }
  }
  // …
}
```

#### Components {#components-json}

Component fields create a relation between a content-type and a component structure. Components are explicitly defined in the [attributes](#model-attributes) of a model with `type: 'component'` and accept the following additional parameters:

| Parameter    | Type    | Description                                                                              |
| ------------ | ------- | ---------------------------------------------------------------------------------------- |
| `repeatable` | Boolean | Could be `true` or `false` depending on whether the component is repeatable or not       |
| `component`  | String  | Define the corresponding component, following this format:<br/>`<category>.<componentName>`  |

```json title="./src/api/[apiName]/restaurant/content-types/schema.json"

{
  "attributes": {
    "openinghours": {
      "type": "component",
      "repeatable": true,
      "component": "restaurant.openinghours"
    }
  }
}
```

#### Dynamic zones

Dynamic zones create a flexible space in which to compose content, based on a mixed list of [components](#components-json).

Dynamic zones are explicitly defined in the [attributes](#model-attributes)  of a model with `type: 'dynamiczone'`. They also accept a `components` array, where each component should be named following this format: `<category>.<componentName>`.

```json title="./src/api/[api-name]/content-types/article/schema.json"

{
  "attributes": {
    "body": {
      "type": "dynamiczone",
      "components": ["article.slider", "article.content"]
    }
  }
}
```

### Model options

The `options` key is used to define specific behaviors and accepts the following parameter:

| Parameter           | Type             | Description                                                                                                                                                                                                                                                                                                        |
|---------------------|------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `privateAttributes` | Array of strings | Allows treating a set of attributes as private, even if they're not actually defined as attributes in the model. It could be used to remove them from API responses timestamps. <br /><br /> The `privateAttributes` defined in the model are merged with the `privateAttributes` defined in the global Strapi configuration. |
| `draftAndPublish`   | Boolean          | Enables the draft and publish feature. <br /><br /> Default value: `true` (`false` if the content-type is created from the interactive CLI).                                                                                                                                                                                    |
| `populateCreatorFields` | Boolean | Populates `createdBy` and `updatedBy` fields in responses returned by the REST API (see [guide](/cms/api/rest/guides/populate-creator-fields) for more details).<br/><br/>Default value: `false`. |

```json title="./src/api/[api-name]/content-types/restaurant/schema.json"

{
  "options": {
    "privateAttributes": ["id", "createdAt"],
    "draftAndPublish": true
  }
}
```

### Plugin options

`pluginOptions` is an optional object allowing plugins to store configuration for a model or a specific attribute.

| Key                       | Value                         | Description                                            |
|---------------------------|-------------------------------|--------------------------------------------------------|
| `i18n`                    | `localized: true`             | Enables localization.                                  |
| `content-manager`         | `visible: false`              | Hides from Content Manager in the admin panel.         |
| `content-type-builder`    | `visible: false`              | Hides from Content-type Builder in the admin panel.    |

```json title="./src/api/[api-name]/content-types/[content-type-name]/schema.json"

{
  "attributes": {
    "name": {
      "pluginOptions": {
        "i18n": {
          "localized": true
        }
      },
      "type": "string",
      "required": true
    },
    "slug": {
      "pluginOptions": {
        "i18n": {
          "localized": true
        }
      },
      "type": "uid",
      "targetField": "name",
      "required": true
    }
    // …additional attributes
  }
}

```

## Lifecycle hooks

Lifecycle hooks are functions that get triggered when Strapi queries are called. They are triggered automatically when managing content through the administration panel or when developing custom code using `queries`·

Lifecycle hooks can be customized declaratively or programmatically.

:::caution
Lifecycles hooks are not triggered when using directly the 

</Tabs>

Using the database layer API, it's also possible to register a subscriber and listen to events programmatically:

```js title="./src/index.js"
module.exports = {
  async bootstrap({ strapi }) {
// registering a subscriber
    strapi.db.lifecycles.subscribe({
      models: [], // optional;

      beforeCreate(event) {
        const { data, where, select, populate } = event.params;

        event.state = 'doStuffAfterWards';
      },

      afterCreate(event) {
        if (event.state === 'doStuffAfterWards') {
        }

        const { result, params } = event;

        // do something to the result
      },
    });

    // generic subscribe for generic handling
    strapi.db.lifecycles.subscribe((event) => {
      if (event.action === 'beforeCreate') {
        // do something
      }
    });
  }
}
```




---


# Content-type Builder
Source: https://docs.strapi.io/cms/features/content-type-builder

# Content-type Builder

From the 
  
</IdentityCard>

## Overview

  </Tabs>
3. Click the **Finish** button in the dialog.
4. Click the **Save** button in the Content-Type Builder navigation.

#### Fields

From the table that lists the fields of your content-type, you can:
- Click on the 

</Tabs>

#### <img width="28" src="/img/assets/icons/v5/ctb_richtextblocks.svg" /> Rich Text (Blocks) {#rich-text-blocks}

The Rich Text (Blocks) field displays an editor with live rendering and various options to manage rich text. This field can be used for long written content, even including images and code.

</Tabs>

:::strapi React renderer
If using the Blocks editor, we recommend that you also use the 

</Tabs>

#### <img width="28" src="/img/assets/icons/v5/ctb_date.svg" /> Date {#date}

The Date field can display a date (year, month, day), time (hour, minute, second) or datetime (year, month, day, hour, minute, and second) picker.

</Tabs>
 
#### <img width="28" src="/img/assets/icons/v5/ctb_password.svg" /> Password

The Password field displays a password field that is encrypted.

</Tabs>

#### <img width="28" src="/img/assets/icons/v5/ctb_media.svg" /> Media {#media}

The Media field allows to choose one or more media files (e.g. image, video) from those uploaded in the Media Library of the application.

</Tabs>

#### <img width="28" src="/img/assets/icons/v5/ctb_relation.svg" /> Relation {#relation}

The Relation field allows to establish a relation with another content-type, that must be a collection type.

There are 6 different types of relations:

- <img width="25" src="/img/assets/icons/v5/ctb_relation_oneway.svg" /> One way: Content-type A *has one* Content-type B
- <img width="25" src="/img/assets/icons/v5/ctb_relation_1to1.svg" /> One-to-one: Content-type A *has and belong to one* Content-type B
- <img width="25" src="/img/assets/icons/v5/ctb_relation_1tomany.svg" /> One-to-many: Content-type A *belongs to many* Content-type B
- <img width="25" src="/img/assets/icons/v5/ctb_relation_manyto1.svg" /> Many-to-one: Content-type B *has many* Content-type A
- <img width="25" src="/img/assets/icons/v5/ctb_relation_manytomany.svg" /> Many-to-many: Content-type A *has and belongs to many* Content-type B
- <img width="25" src="/img/assets/icons/v5/ctb_relation_manyway.svg" /> Many way: Content-type A *has many* Content-type B

:::info Multi relations and single relations
Relations where at least one side can reference several entries are called multi relations. In the Content-type Builder, this includes one-to-many, many-to-one, many-to-many, and many-way relations. These relations appear as multi-select fields in the Content Manager and return arrays from the REST, GraphQL, and Document Service APIs; while single relations (one-way and one-to-one relations) return a single linked entry (see [Managing relations with API requests](/cms/api/rest/relations) for more information).
:::

</Tabs>

:::tip Modeling nested page hierarchies
To model a navigable tree of pages:
1. Add a `Page` collection type with a "Slug" (UID) and (optionally) an "Order" (Integer) field to control sibling ordering.
2. Create a Relation field from `Page` to `Page` and choose *Many-to-one* so each page can set its "Parent page". Strapi automatically provides the inverse "Children pages" relation.
3. When reading data, populate `children` recursively to load the tree. Keep the recursion depth small to avoid large responses.

<details>
<summary>Example</summary>
```json title="Populate nested children for a page tree"
{
  populate: {
    children: {
      fields: ['title', 'slug'],
      populate: {
        children: {
          fields: ['title', 'slug'],
        },
      },
    },
  },
}
```
</details>

The same populate pattern works with GraphQL or the Document Service API (see [Understanding populate guide](/cms/api/rest/guides/understanding-populate#populate-several-levels-deep-for-specific-relations)).
:::

#### <img width="28" src="/img/assets/icons/v5/ctb_boolean.svg" /> Boolean {#boolean}

The Boolean field displays a toggle button to manage boolean values (e.g. Yes or No, 1 or 0, True or False).

</Tabs>

#### <img width="28" src="/img/assets/icons/v5/ctb_json.svg" /> JSON {#json}

The JSON field allows to configure data in a JSON format, to store JSON objects or arrays.

</Tabs>

#### <img width="28" src="/img/assets/icons/v5/ctb_email.svg" /> Email {#email}

The Email field displays an email address field with format validation to ensure the email address is valid.

</Tabs>

#### <img width="28" src="/img/assets/icons/v5/ctb_password.svg" /> Password {#password}

The Password field displays a password field that is encrypted.

</Tabs>

#### <img width="28" src="/img/assets/icons/v5/ctb_enum.svg" /> Enumeration {#enum}

The Enumeration field allows to configure a list of values displayed in a drop-down list.

</Tabs>

:::caution
Enumeration values should always have an alphabetical character preceding any number as it could otherwise cause the server to crash without notice when the GraphQL plugin is installed.
:::

#### <img width="28" src="/img/assets/icons/v5/ctb_uid.svg" /> UID {#uid}

The UID field displays a field that sets a unique identifier, optionally based on an existing other field from the same content-type.

</Tabs>

:::tip
The UID field can be used to create a slug based on the Attached field.
:::

#### <img width="28" src="/img/assets/icons/v5/ctb_richtext.svg" /> Rich Text (Markdown) {#rich-text-markdown}

The Rich Text (Markdown) field displays an editor with basic formatting options to manage rich text written in Markdown. This field can be used for long written content.

</Tabs>

#### <img width="28" src="/img/assets/icons/v5/ctb_component.svg" /> Components {#components}

Components are a combination of several fields. Components allow to create reusable sets of fields, that can be quickly added to content-types, dynamic zones but also nested into other components.

When configuring a component through the Content-type Builder, it is possible to either:

- create a new component by clicking on *Create a new component* (see [Creating a new component](#new-component)),
- or use an existing one by clicking on *Use an existing component*.

</Tabs>

#### <img width="28" src="/img/assets/icons/v5/ctb_dz.svg" /> Dynamic zones {#dynamiczones}

Dynamic zones are a combination of components that can be added to content-types. They allow a flexible content structure as once in the Content Manager, administrators have the choice of composing and rearranging the components of the dynamic zone how they want.

</Tabs>

After configuring the settings of the dynamic zone, its components must be configured as well. It is possible to either choose an existing component or create a new one.

:::caution
When using dynamic zones, different components cannot have the same field name with different types (or with enumeration fields, different values).
:::

#### Custom fields

[Custom fields](/cms/features/custom-fields) are a way to extend Strapi’s capabilities by adding new types of fields to content-types or components. Once installed (see [Marketplace](/cms/plugins/installing-plugins-via-marketplace) documentation), custom fields are listed in the _Custom_ tab when selecting a field for a content-type.

Each custom field type can have basic and advanced settings. The  lists available custom fields, and hosts dedicated documentation for each custom field, including specific settings.

### Deleting content-types

Content types and components can be deleted through the Content-type Builder. Deleting a content-type automatically deletes all entries from the Content Manager that were based on that content-type. The same goes for the deletion of a component, which is automatically deleted from every content-type or entry where it was used.

1. In the  Content-type Builder sub navigation, click on the name of the content-type or component to delete.
2. In the edition interface of the chosen content-type or component, click on the  **Edit** button on the right side of the content-type's or component's name.
3. In the edition window, click on the **Delete** button.
4. In the confirmation window, confirm the deletion.
5. Click on the **Save** button in the Content-type Builder sub navigation.

:::caution
Deleting a content-type only deletes what was created and available from the Content-type Builder, and by extent from the admin panel of your Strapi application. All the data that was created based on that content-type is however kept in the database. For more information, please refer to the related .
:::


