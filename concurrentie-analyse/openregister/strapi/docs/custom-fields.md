
# Custom Fields
Source: https://docs.strapi.io/cms/features/custom-fields

# Custom Fields

Custom fields extend Strapi’s capabilities by adding new types of fields to content-types and components. Once created or added to Strapi via plugins, custom fields can be used in the Content-Type Builder and Content Manager just like built-in fields.

</IdentityCard>

## Configuration

Ready-made custom fields can be found on the [Marketplace](https://market.strapi.io/plugins?categories=Custom+fields). Once installed these, no other configuration is required, and you can start using them (see [usage](#usage)).

You can also develop your own custom field.

### Developing your own custom field

Though the recommended way to add a custom field is through creating a plugin, app-specific custom fields can also be registered within the global `register` [function](/cms/configurations/functions) found in `src/index` and `src/admin/app` files.

:::note Current limitations
* Custom fields can only be shared and distributed on the Marketplace using plugins.
* Custom fields cannot add new data types to Strapi and must use existing, built-in Strapi data types described in the [models' attributes](/cms/backend-customization/models#model-attributes) documentation. 
* You also cannot modify an existing data type.
* Special data types unique to Strapi, such as relation, media, component, or dynamic zone data types, cannot be used in custom fields.
:::

:::prerequisites

</Tabs>

The custom field could also be declared directly within the `strapi-server.js` file if you didn't have the plugin code scaffolded by the CLI generator:

</Tabs>

#### Registering a custom field in the admin panel

:::prerequisites

</Tabs>

##### Components

`app.customFields.register()` must pass a `components` object with an `Input` React component to use in the Content Manager's edit view.

**Example: Registering an Input component:**

In the following example, the `color-picker` plugin was created using the CLI generator (see [plugins development](/cms/plugins-development/developing-plugins.md)):

</Tabs>

<details>
<summary>Props passed to the custom field <code>Input</code> component:</summary>

| Prop             | Description                                                                                                                                                                                                                               | Type                                                                 |
| ---------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| `attribute`      | The attribute object with custom field's underlying Strapi type and options                                                                                                                                                               | `{ type: String, customField: String }`                              |
| `description`    | The field description set in [configure the view](/cms/features/content-manager#edit-view-settings)                                                                                                  | 

</Tabs>

:::tip
For a more detailed view of the props provided to the customFields and how they can be used check out the 

</Tabs>

<!-- TODO: replace these tip and links by proper documentation of all the possible shapes and parameters for `options` -->

:::tip
The Strapi codebase gives an example of how settings objects can be described: check the  file for the `base` settings and the  file for the `advanced` settings. The base form lists the settings items inline but the advanced form gets the items from an  file.
:::

## Usage

<br/>

### In the admin panel

Custom fields can be added to Strapi either by installing them from the [Marketplace](/cms/plugins/installing-plugins-via-marketplace) or by creating your own.

Once added to Strapi, custom fields can be added to any content type. Custom fields are listed in the _Custom_ tab when selecting a field for a content-type.

<!-- TODO: add screenshot of content-type builder with custom fields tab here -->

Each custom field type can have basic and advanced settings. The  lists available custom fields, and hosts dedicated documentation for each custom field, including specific settings.

### In the code

Once created and used, custom fields are defined like any other attribute in the model's schema. 

Custom fields are explicitly defined in the [attributes](/cms/backend-customization/models#model-attributes) of a model with `type: customField`.

As compared to how other types of models are defined, custom fields' attributes also show the following specificities:

- Custom field have a `customField` attribute. Its value acts as a unique identifier to indicate which registered custom field should be used, and follows one of these 2 formats:

  | Format               |  Origin |
  |----------------------|------------------|
  | `plugin::plugin-name.field-name` | The custom field was created through a plugin |
  | `global::field-name` | The custom field is specific to the current Strapi application and was created directly within the `register` [function](/cms/configurations/functions) |

- Custom fields can have additional parameters depending on what has been defined when registering the custom field (see [server registration](#registering-a-custom-field-on-the-server) and [admin panel registration](#registering-a-custom-field-in-the-admin-panel)).

**Example: A simple `color` custom field model definition:**

```json title="/src/api/[apiName]/[content-type-name]/content-types/schema.json"

{
// …
"attributes": {
  "color": { // name of the custom field defined in the Content-Type Builder
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


