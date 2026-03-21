
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


