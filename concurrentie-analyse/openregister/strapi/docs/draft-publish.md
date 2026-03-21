
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


# Draft & Publish
Source: https://docs.strapi.io/cms/features/draft-and-publish

# Draft & Publish

The Draft & Publish feature allows to manage drafts for your content.

</IdentityCard>

On the back-end server of Strapi, the Document Service API can also be used to interact with localized content:


