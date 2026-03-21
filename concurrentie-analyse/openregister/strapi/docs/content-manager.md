
# Content Manager
Source: https://docs.strapi.io/cms/features/content-manager

# Content Manager

From the 
  
</IdentityCard>

## Overview

<!--

</Tabs>

### Configuring the edit view {#edit-view-settings}

</Tabs>

## Usage

<br/>

### Creating & Writing content

In Strapi, writing content consists in filling up fields, which are meant to contain specific content (e.g. text, numbers, media, etc.). These fields were configured for the collection or single type beforehand, through the [Content-type Builder](/cms/features/content-type-builder).

</Tabs>

#### Dynamic zones

Dynamic zones are a combination of components, which themselves are composed of several fields. Writing the content of a dynamic zone requires additional steps in order to access the fields.

</Tabs>

:::tip
- Not all entries are listed by default: more can be displayed by clicking on the **Load more** button. Also, instead of choosing an entry by scrolling the list, you can click any relational field drop-down list and type to search a specific entry.

- Click on the name of an entry to open it. The behavior depends on the **Relation open behavior** setting configured in the edit view: the entry opens in a modal (default), navigates to its full edit page, or opens in a new browser tab. See [Configuring the edit view](#edit-view-settings) to change this setting.
:::

:::note
- If the [Draft & Publish feature](/cms/features/draft-and-publish) is activated for the content-type the relational field belongs to, you will notice blue or green dots next to the entries names in the drop-down list. They indicate the status of the entry, respectively draft or published content.
- If the [Internationalization (i18n) feature](/cms/features/internationalization) is enabled for the content-type, the list of entries may be limited or differ from one locale to another. Only relevant entries that can possibly be chosen for a relational field will be listed.
:::

<!-- Add a section "Managing entries" here with the explanations of the list view interface? Or before "Creating & Writing content"? Or maybe have 1. "Creating & managing entries" 2. "Writing content"? Or just use a Guideflow? -->

### Deleting content

You can delete content by deleting any entry of a collection type, or the default entry of a single type.

1. In the edit view of the entry, click on  at the top right of the interface, and click the **Delete document** button.<br/>If Internationalization is enabled for the content-type, you can also choose to delete only the currently selected locale by clicking on the **Delete locale** button.
2. In the window that pops up, click on the **Confirm** button to confirm the deletion.

:::tip
You can delete entries from the list view of a collection type, by clicking on   on the right side of the entry's record in the table, then choosing the  **Delete document** button.<br/>If [Internationalization](/cms/features/internationalization) is enabled for the content-type, **Delete document** deletes all locales while **Delete locale** only deletes the currently listed locale.


