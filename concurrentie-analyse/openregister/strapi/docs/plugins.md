
# Admin hooks
Source: https://docs.strapi.io/cms/plugins-development/admin-hooks

# Admin Panel API: Hooks

The Hooks API allows a plugin to create and register hooks, i.e. places in the application where plugins can add personalized behavior.

</Tabs>

:::note
For predictable interoperability between plugins, use stable namespaced hook IDs such as `my-plugin/my-hook`.
:::

## Subscribing to hooks

Subscribe to hooks with `registerHook()` during the [`bootstrap`](/cms/plugins-development/admin-panel-api#bootstrap) lifecycle, once all plugins are loaded. The callback receives arguments from the hook caller and should return the (optionally mutated) data.

</Tabs>

Async callbacks are also supported:

</Tabs>

## Running hooks

Hooks can be run in 3 modes:

| Mode | Function | Return value |
|---|---|---|
| Series | `runHookSeries` | Array of results from each function, in order |
| Parallel | `runHookParallel` | Array of resolved promise results, in order |
| Waterfall | `runHookWaterfall` | Single value after all transformations applied sequentially |

:::caution
For `runHookWaterfall`, each subscriber must return the transformed value so that the next subscriber in the chain receives it. Failing to return a value will break the chain.
:::

## Using predefined hooks

Strapi includes predefined hooks for the Content Manager's List and Edit views.

### `INJECT-COLUMN-IN-TABLE`

The `Admin/CM/pages/ListView/inject-column-in-table` hook can add or mutate columns in the List View of the [Content Manager](/cms/intro):

```tsx
runHookWaterfall(INJECT_COLUMN_IN_TABLE, {
  displayedHeaders: ListFieldLayout[],
  layout: ListFieldLayout,
});
```

The following example subscribes to this hook to add a custom "External id" column:

</Tabs>

**ListFieldLayout and ListLayout type definitions**:

### `MUTATE-EDIT-VIEW-LAYOUT`

The `Admin/CM/pages/EditView/mutate-edit-view-layout` hook can mutate the Edit View layout of the [Content Manager](/cms/intro).

The following example subscribes to this hook to force all fields to full width:

</Tabs>

**EditLayout and EditFieldLayout type definitions:**

:::note
The `EditLayout` and `ListLayout` shapes documented here come from the `useDocumentLayout` hook (see ). Internal package naming can vary, but plugin authors should rely on the `EditLayout` and `ListLayout` shapes exposed in this page.
:::




---


# Admin injection zones
Source: https://docs.strapi.io/cms/plugins-development/admin-injection-zones

# Admin Panel API: Injection zones

Plugins can extend and customize existing admin panel sections by injecting custom React components into predefined areas. This allows you to add functionality to Strapi's built-in interfaces without modifying core code.

</Tabs>

## Custom injection zones

Plugins can define their own injection zones to allow other plugins to extend their UI. Declare injection zones in the `registerPlugin` configuration:

</Tabs>

### Rendering injection zones in components

In Strapi 5, the `InjectionZone` component from `@strapi/helper-plugin` is removed and has no direct replacement export. To render injected components, create your own component with `useStrapiApp` from `@strapi/strapi/admin`.

</Tabs>

### Injecting into custom zones

Other plugins can inject components into your custom injection zones using the `injectComponent()` method in their `bootstrap` lifecycle:

</Tabs>

## Injection component parameters

The `injectComponent()` method accepts the following parameters:

| Parameter | Type | Description |
|---|---|---|
| `view` | `string` | The view name where the component should be injected |
| `zone` | `string` | The zone name within the view where the component should be injected |
| `component` | `object` | Configuration object with `name` (unique string) and `Component` (React component to inject) |

## Content Manager data access

When injecting components into Content Manager injection zones, you can access the Edit View data using the `useContentManagerContext` hook:

</Tabs>

:::caution
The `useContentManagerContext` hook is currently exported as `unstable_useContentManagerContext`. The `unstable_` prefix indicates the API may change in future releases. This hook replaces the [deprecated `useCMEditViewDataManager`](/cms/migration/v4-to-v5/additional-resources/helper-plugin#usecmeditviewdatamanager) from `@strapi/helper-plugin` which is not available in Strapi 5.
:::

## Best practices

- **Use descriptive zone names.** Choose clear names for your injection zones (e.g., `top`, `bottom`, `before`, `after`).

- **Check plugin availability.** Always verify that a plugin exists before injecting components into its zones:

  ```js
  bootstrap(app) {
    const targetPlugin = app.getPlugin('target-plugin');
    if (targetPlugin) {
      targetPlugin.injectComponent('view', 'zone', {
        name: 'my-component',
        Component: MyComponent,
      });
    }
  }
  ```

- **Use unique component names.** Ensure component names are unique to avoid conflicts with other plugins.

- **Handle missing zones gracefully.** Components should handle cases where injection zones might not be available.

- **Document your injection zones.** Clearly document which injection zones your plugin provides and their intended use.




---


# Admin localization
Source: https://docs.strapi.io/cms/plugins-development/admin-localization

# Admin Panel API: Localization

Plugins can provide translations for multiple languages to make the admin interface accessible to users worldwide. Strapi automatically loads and merges plugin translations with core translations, making them available throughout the admin panel.

</Tabs>

### Function parameters

The `registerTrads` function receives an object with the following property:

| Parameter | Type | Description |
|---|---|---|
| `locales` | `string[]` | Array of locale codes configured in the admin panel (e.g., `['en', 'fr', 'de']`) |

### Return value

The function must return a `Promise` that resolves to an array of translation objects. Each object has the following structure:

```ts
{
  data: Record<string, string>; // Translation key-value pairs
  locale: string; // Locale code (e.g., 'en', 'fr')
}
```

### Translation key prefixing

:::caution
Translation keys must be prefixed with your plugin ID to avoid conflicts with other plugins and core Strapi translations. For example, if your plugin ID is `my-plugin`, a key like `plugin.name` should become `my-plugin.plugin.name`.
:::

Use the `prefixPluginTranslations` utility function to automatically prefix all keys:

</Tabs>

For instance, if your translation file contains:

```json
{
  "plugin.name": "My Plugin",
  "settings.title": "Settings"
}
```

After prefixing with plugin ID `my-plugin`, these become:

- `my-plugin.plugin.name`
- `my-plugin.settings.title`

### Missing translation files

The `registerTrads` function should gracefully handle missing translation files by returning an empty object for that locale. The `.catch()` handler in the example above ensures that if a translation file does not exist, the plugin still returns a valid translation object:

```js
.catch(() => {
  return {
    data: {},
    locale,
  };
});
```

This allows plugins to provide translations for only some locales (e.g., only English) without breaking the admin panel for other locales.

## Translations in components

To use translations in your React components, use the `useIntl` hook from `react-intl`:

</Tabs>

### Helper function for translation keys

To avoid repeating the plugin ID prefix, create a helper function:

</Tabs>

Then use it in components:

</Tabs>

## Translations in configuration

Translation keys are also used when configuring menu links, settings sections, and other admin panel elements:

</Tabs>

## Plugin translation lifecycle

Strapi's admin panel automatically:

1. Calls `registerTrads` for all registered plugins during initialization
2. Merges translations from all plugins with core Strapi translations
3. Applies custom translations from the admin configuration (if any)
4. Makes translations available via `react-intl` throughout the admin panel

In practice, core admin translations are loaded first, plugin translations are merged on top, and project-level overrides in `config.translations` let you customize the labels displayed in the admin panel.

## Best practices

- **Always prefix translation keys.** Use `prefixPluginTranslations` or manually prefix keys with your plugin ID to avoid conflicts.
- **Provide default messages.** Always include `defaultMessage` when using `formatMessage` as a fallback if translations are missing.
- **Handle missing translations gracefully.** The `registerTrads` function should return empty objects for missing locales rather than throwing errors.
- **Use descriptive key names.** Choose clear, hierarchical key names (e.g., `settings.general.title` rather than `title1`).
- **Support at least English.** Providing English translations ensures your plugin works out of the box.
- **Verify behavior with multiple locales.** Test that your plugin works correctly when different locales are selected in the admin panel.

:::note
The `en` locale is always available in Strapi and serves as the fallback locale. If a translation is missing for a selected locale, Strapi uses the English translation.
:::

:::tip
To see which locales are available in your Strapi instance, check the `config.locales` array in your `src/admin/app.ts` or `src/admin/app.js` file. For programmatic access at runtime, see [Accessing the Redux store](/cms/plugins-development/admin-redux-store) (note that internal store structure may change between versions).
:::




---


# Admin navigation & settings
Source: https://docs.strapi.io/cms/plugins-development/admin-navigation-settings

# Admin Panel API: Navigation & settings

Plugins can customize the admin panel's navigation sidebar and settings pages to provide access to their features. All functions described on this page are called within the [`register`](/cms/plugins-development/admin-panel-api#register) or [`bootstrap`](/cms/plugins-development/admin-panel-api#bootstrap) lifecycle functions of your plugin's entry file.

</Tabs>

## Settings

The Settings API allows plugins to create new settings sections or add links to existing sections. Settings sections are organized groups of configuration pages accessible from the 

</Tabs>

The `createSettingSection()` function accepts the following parameters:

* the first argument is the section configuration:

  | Parameter | Type | Required | Description |
  |---|---|---|---|
  | `id` | `string` | ✅ | Unique identifier for the settings section |
  | `intlLabel` | `object` | ✅ | Localized label for the section, following the 

</Tabs>

`addSettingsLink` and `addSettingsLinks` both take a `sectionId` string as the first argument (e.g., `'global'` or `'permissions'`). The second argument is a single link object for `addSettingsLink` or an array of link objects for `addSettingsLinks`, using the same properties as the `links` array in [`createSettingSection()` (see table above)](#creating-a-new-settings-section).

### Available settings sections

Strapi provides built-in settings sections that plugins can extend:

- `global`: General application settings
- `permissions`: Administration panel settings

:::note
Creating a new settings section must be done in the `register` lifecycle function. Adding links to existing settings sections must be done in the `bootstrap` lifecycle function.
:::

### Path conventions for `to`

The `to` parameter behaves differently depending on the context:

| Context | `to` value | Final URL |
|---|---|---|
| `addMenuLink` | `/plugins/my-plugin` | `http://localhost:1337/admin/plugins/my-plugin` |
| `createSettingSection` link | `my-plugin/general` | `http://localhost:1337/admin/settings/my-plugin/general` |
| `addSettingsLink` | `my-plugin/documentation` | `http://localhost:1337/admin/settings/my-plugin/documentation` |

For menu links, the path is relative to the admin panel root (`/admin`). For settings links, the path is relative to the settings route (`/admin/settings`). Do not include the `settings/` prefix in settings link paths.

:::strapi Securing plugin routes
The `permissions` parameter on links only controls visibility in the navigation. To fully protect your plugin pages and register RBAC actions, see the [Admin permissions for plugins](/cms/plugins-development/guides/admin-permissions-for-plugins) guide.
:::




---


# Admin Panel API overview
Source: https://docs.strapi.io/cms/plugins-development/admin-panel-api

# Admin Panel API for plugins: An overview

A Strapi plugin can interact with both the back end and the front end of a Strapi application. The Admin Panel API covers the front-end part: it allows a plugin to customize Strapi's [admin panel](/cms/intro). The admin panel is a 

</Tabs>

#### registerPlugin() {#registerplugin}

**Type:** `Function`

Registers the plugin to make it available in the admin panel. This function is called within the [`register()`](#register) lifecycle function and returns an object with the following parameters:

| Parameter        | Type                     | Description                                                                                        |
| ---------------- | ------------------------ | -------------------------------------------------------------------------------------------------- |
| `id`             | String                   | Plugin id                                                                                          |
| `name`           | String                   | Plugin name                                                                                        |
| `apis`           | `Record<string, unknown>` | APIs exposed to other plugins                                                                      |
| `initializer`    | `React.ComponentType`   | Component for plugin initialization                                                                |
| `injectionZones` | Object                   | Declaration of available [injection zones](/cms/plugins-development/admin-injection-zones)          |
| `isReady`        | Boolean                  | Plugin readiness status (default: `true`)                                                          |

:::note
Some parameters can be imported from the `package.json` file.
:::

**Example:**

</Tabs>

### bootstrap() {#bootstrap}

**Type**: `Function`

Exposes the bootstrap function, executed after all the plugins are [registered](#register).

Within the `bootstrap()` function, a plugin can:

* extend another plugin using `getPlugin('plugin-name')`,
* register hooks (see [Hooks](/cms/plugins-development/admin-hooks)),
* [add links to a settings section](/cms/plugins-development/admin-navigation-settings#adding-links-to-existing-settings-sections),
* add actions and options to the Content Manager's List view and Edit view (see [Content Manager APIs](/cms/plugins-development/content-manager-apis)).

**Example:**

</Tabs>

## Available actions

The Admin Panel API provides several building blocks to customize the user interface, user experience, and behavior of the admin panel.

Use the following table to find which function to use and where to declare it. Click any function name for details:

| Action                                   | Function to use                                   | Related lifecycle function  |
| ---------------------------------------- | ------------------------------------------------- | --------------------------- |
| Add a new link to the main navigation    | [`addMenuLink()`](/cms/plugins-development/admin-navigation-settings#navigation-sidebar-menu-links)                      | [`register()`](#register)   |
| Create a new settings section            | [`createSettingSection()`](/cms/plugins-development/admin-navigation-settings#creating-a-new-settings-section) | [`register()`](#register)   |
| Add a single link to a settings section  | [`addSettingsLink()`](/cms/plugins-development/admin-navigation-settings#adding-links-to-existing-settings-sections)             | [`bootstrap()`](#bootstrap) |
| Add multiple links to a settings section | [`addSettingsLinks()`](/cms/plugins-development/admin-navigation-settings#adding-links-to-existing-settings-sections)           | [`bootstrap()`](#bootstrap) |
| Add panels, options, and actions to the Content Manager's Edit view and List view | <ul><li>[`addEditViewSidePanel()`](/cms/plugins-development/content-manager-apis#addeditviewsidepanel)</li><li>[`addDocumentAction()`](/cms/plugins-development/content-manager-apis#adddocumentaction)</li><li>[`addDocumentHeaderAction()`](/cms/plugins-development/content-manager-apis#adddocumentheaderaction)</li><li>[`addBulkAction()`](/cms/plugins-development/content-manager-apis#addbulkaction)</li></ul> | [`bootstrap()`](#bootstrap) |
| Declare an injection zone                | [`registerPlugin()`](#registerplugin)             | [`register()`](#register)   |
| Inject a component in an injection zone  | [`injectComponent()`](/cms/plugins-development/admin-injection-zones)           | [`bootstrap()`](#bootstrap)  |
| Add a reducer                            | [`addReducers()`](/cms/plugins-development/admin-redux-store#adding-custom-reducers)                      | [`register()`](#register)   |
| Create a hook                          | [`createHook()`](/cms/plugins-development/admin-hooks)                    | [`register()`](#register)   |
| Register a hook                          | [`registerHook()`](/cms/plugins-development/admin-hooks)                    | [`bootstrap()`](#bootstrap)   |
| Provide translations for the plugin admin interface | [`registerTrads()`](/cms/plugins-development/admin-localization#registertrads) | `registerTrads()` |

<br/>
Click on any of the following cards to get more details about a specific topic:

:::tip Replacing the WYSIWYG
The WYSIWYG editor can be replaced by taking advantage of [custom fields](/cms/features/custom-fields), for instance using the .
:::

:::info
The admin panel supports dotenv variables.

All variables defined in a `.env` file and prefixed by `STRAPI_ADMIN_` are available while customizing the admin panel through `process.env`.
:::




---


# Redux store & reducers
Source: https://docs.strapi.io/cms/plugins-development/admin-redux-store

# Admin Panel API: Redux store & reducers

Strapi's admin panel uses a global Redux store to manage application state. Plugins can access this store to read state, dispatch actions, and subscribe to state changes. This enables plugins to interact with core admin functionality like theme settings, language preferences, and authentication state.

</Tabs>

## Reading state with `useSelector`

The most common way to access Redux state in your plugin components is using the `useSelector` hook from `react-redux`:

</Tabs>

### Available state properties

The `admin_app` slice contains the following state properties:

| Property | Type | Description |
|---|---|---|
| `theme.currentTheme` | `string` | Current theme (`'light'`, `'dark'`, or `'system'`) |
| `theme.availableThemes` | `string[]` | Array of available theme names |
| `language.locale` | `string` | Current locale code (e.g., `'en'`, `'fr'`) |
| `language.localeNames` | `object` | Object mapping locale codes to display names |
| `token` | `string \| null` | Authentication token |
| `permissions` | `object` | User permissions object |

## Dispatching actions

To update the Redux store, use the `useDispatch` hook:

:::note
The examples below dispatch actions to core admin state (theme, locale) for illustration purposes. In practice, most plugins should dispatch actions to their own custom reducers rather than modifying global admin state.
:::

</Tabs>

### Available actions

<!-- TODO: Verify action types and slice name against the @strapi/admin Redux slice in the strapi/strapi codebase. -->

The `admin_app` slice provides the following actions:

| Action type | Payload type | Description |
|---|---|---|
| `admin/setAppTheme` | `string` | Set the theme (`'light'`, `'dark'`, or `'system'`) |
| `admin/setAvailableThemes` | `string[]` | Updates `theme.availableThemes` in `admin_app` |
| `admin/setLocale` | `string` | Set the locale (e.g., `'en'`, `'fr'`) |
| `admin/setToken` | `string \| null` | Set the authentication token |
| `admin/login` | `{ token: string, persist?: boolean }` | Login action with token and persistence option |
| `admin/logout` | `void` | Logout action (no payload) |

:::note
When dispatching actions, use the Redux Toolkit action type format: `'sliceName/actionName'`. The admin slice is named `'admin'`, so actions follow the pattern `'admin/actionName'`.
:::

## Accessing the store instance

For advanced use cases, you can access the store instance directly using the `useStore` hook:

</Tabs>

## Complete example

The following example combines all 3 patterns (useSelector, useDispatch, useStore) described on the present page:

            </Flex>
          </Box>

              {Object.keys(availableLocales).map((locale) => (
                
              ))}
            </Flex>
          </Box>

              {lastChange && (
                
              )}
            </Flex>
          </Box>
        </Flex>
      </Box>
    </Main>
  );
};

```

</ExpandableContent>

<br/>

</TabItem>

            </Flex>
          </Box>

              {Object.keys(availableLocales).map((locale) => (
                
              ))}
            </Flex>
          </Box>

              {lastChange && (
                
              )}
            </Flex>
          </Box>
        </Flex>
      </Box>
    </Main>
  );
};

```

</ExpandableContent>

<br/>

</TabItem>
</Tabs>

## Best practices

- **Use `useSelector` for reading state.** Prefer [`useSelector`](#reading-state-with-useselector) over direct store access. It automatically subscribes to updates and re-renders components when the selected state changes.
- **Clean up subscriptions.** Always unsubscribe from store subscriptions in `useEffect` cleanup functions to prevent memory leaks.
- **Consider type safety.** For Redux state access in plugins, use `react-redux` hooks (`useSelector`, `useDispatch`) with plugin-local typing (for example `RootState` and `AppDispatch`). If you use Strapi admin utilities, import them from `@strapi/admin/strapi-admin` (not `@strapi/admin`). Avoid relying on undocumented typed Redux hooks as part of Strapi's public API until they are explicitly documented as stable.
- **Avoid unnecessary dispatches.** Only dispatch actions when you need to update state. Reading state does not require dispatching actions.
- **Respect core state.** Be careful when modifying core admin state (like theme or locale) as it affects the entire admin panel. Consider whether your plugin should modify global state or maintain its own local state.

:::tip
To add your own state to the Redux store, see [Adding custom reducers](#adding-custom-reducers) above.
:::




---


# Content Manager APIs
Source: https://docs.strapi.io/cms/plugins-development/content-manager-apis

# Content Manager APIs

Content Manager APIs are part of the [Admin Panel API](/cms/plugins-development/admin-panel-api). They are a way for Strapi plugins to add content or options to the [Content Manager](/cms/features/content-manager). The Content Manager APIs allow you to extend the Content Manager by adding functionality from your own plugin, just like you can do it with [Injection zones](/cms/plugins-development/admin-injection-zones).

  </Tabs>

- Passing a function that receives the current elements and return the new ones. This is useful if, for example, you want to add something in a specific position in the list, like in the following code:

  </Tabs>

### Components

You need to pass components to the API in order to add things to the Content Manager.

Components are functions that receive some properties and return an object with some shape (depending on the function). Each component's return object is different based on the function you're using, but they receive similar properties, depending on whether you use a ListView or EditView API.

Properties include important information about the document(s) you are viewing or editing.

#### ListViewContext

```jsx
interface ListViewContext {
  /**
   * Will be either 'single-types' | 'collection-types'
   */
  collectionType: string;
  /**
   * The current selected documents in the table
   */
  documents: Document[];
  /**
   * The current content-type's model.
   */
  model: string;
}
```

#### EditViewContext

```jsx
interface EditViewContext {
  /**
   * This will only be null if the content-type
   * does not have draft & publish enabled.
   */
  activeTab: 'draft' | 'published' | null;
  /**
   * Will be either 'single-types' | 'collection-types'
   */
  collectionType: string;
  /**
   * Will be undefined if someone is creating an entry.
   */
  document?: Document;
  /**
   * Will be undefined if someone is creating an entry.
   */
  documentId?: string;
  /**
   * Will be undefined if someone is creating an entry.
   */
  meta?: DocumentMetadata;
  /**
   * The current content-type's model.
   */
  model: string;
}
```

:::tip
More information about types and APIs can be found in 

</Tabs>

## Available APIs

<br/>

### `addEditViewSidePanel`

Use this to add new panels to the Edit view sidebar, just like in the following example where something is added to the Releases panel:

![addEditViewSidePanel](/img/assets/content-manager-apis/add-edit-view-side-panel.png)

```jsx
addEditViewSidePanel(panels: DescriptionReducer<PanelComponent> | PanelComponent[])
```

#### PanelComponent

A `PanelComponent` receives the properties listed in [EditViewContext](#editviewcontext) and returns an object with the following shape:

```tsx
type PanelComponent = (props: PanelComponentProps) => {
  title: string;
  content: React.ReactNode;
};
```

`PanelComponentProps` extends the [EditViewContext](#editviewcontext).

### `addDocumentAction`

Use this API to add more actions to the Edit view or the List View of the Content Manager. There are 3 positions available:

- `header` of the Edit view:

    ![Header of the Edit view](/img/assets/content-manager-apis/add-document-action-header.png)
- `panel` of the Edit view:

    ![Panel of the Edit View](/img/assets/content-manager-apis/add-document-action-panel.png)
- `table-row` of the List view:

    ![Table-row in the List View](/img/assets/content-manager-apis/add-document-action-tablerow.png)

```jsx
addDocumentAction(actions: DescriptionReducer<DocumentActionComponent> | DocumentActionComponent[])
```

#### DocumentActionDescription

The interface and properties of the API look like the following: 

```jsx
interface DocumentActionDescription {
    label: string;
    onClick?: (event: React.SyntheticEvent) => Promise<boolean | void> | boolean | void;
    icon?: React.ReactNode;
    /**
     * @default false
     */
    disabled?: boolean;
    /**
     * @default 'panel'
     * @description Where the action should be rendered.
     */
    position?: DocumentActionPosition | DocumentActionPosition[];
    dialog?: DialogOptions | NotificationOptions | ModalOptions;
    /**
     * @default 'secondary'
     */
    variant?: ButtonProps['variant'];
    loading?: ButtonProps['loading'];
}

type DocumentActionPosition = 'panel' | 'header' | 'table-row' | 'preview' | 'relation-modal';

interface DialogOptions {
    type: 'dialog';
    title: string;
    content?: React.ReactNode;
    variant?: ButtonProps['variant'];
    onConfirm?: () => void | Promise<void>;
    onCancel?: () => void | Promise<void>;
}
interface NotificationOptions {
    type: 'notification';
    title: string;
    link?: {
        label: string;
        url: string;
        target?: string;
    };
    content?: string;
    onClose?: () => void;
    status?: NotificationConfig['type'];
    timeout?: number;
}
interface ModalOptions {
    type: 'modal';
    title: string;
    content: React.ComponentType<{
        onClose: () => void;
    }> | React.ReactNode;
    footer?: React.ComponentType<{
        onClose: () => void;
    }> | React.ReactNode;
    onClose?: () => void;
}
```

### `addDocumentHeaderAction`

Use this API to add more actions to the header of the Edit view of the Content Manager:

![addEditViewSidePanel](/img/assets/content-manager-apis/add-document-header-action.png)

```jsx
addDocumentHeaderAction(actions: DescriptionReducer<HeaderActionComponent> | HeaderActionComponent[])
```

#### HeaderActionDescription

The interface and properties of the API look like the following:

```jsx
interface HeaderActionDescription {
  disabled?: boolean;
  label: string;
  icon?: React.ReactNode;
  type?: 'icon' | 'default';
  onClick?: (event: React.SyntheticEvent) => Promise<boolean | void> | boolean | void;
  dialog?: DialogOptions;
  options?: Array<{
    disabled?: boolean;
    label: string;
    startIcon?: React.ReactNode;
    textValue?: string;
    value: string;
  }>;
  onSelect?: (value: string) => void;
  value?: string;
}

interface DialogOptions {
  type: 'dialog';
  title: string;
  content?: React.ReactNode;
  footer?: React.ReactNode;
}
```

### `addBulkAction`

Use this API to add buttons that show up when entries are selected on the List View of the Content Manager, just like the "Add to Release" button for instance:

![addEditViewSidePanel](/img/assets/content-manager-apis/add-bulk-action.png)

```jsx
addBulkAction(actions: DescriptionReducer<BulkActionComponent> | BulkActionComponent[])
```

#### BulkActionDescription

The interface and properties of the API look like the following: 

```jsx
interface BulkActionDescription {
  dialog?: DialogOptions | NotificationOptions | ModalOptions;
  disabled?: boolean;
  icon?: React.ReactNode;
  label: string;
  onClick?: (event: React.SyntheticEvent) => void;
  /**
   * @default 'default'
   */
  type?: 'icon' | 'default';
  /**
   * @default 'secondary'
   */
  variant?: ButtonProps['variant'];
}
```




---


# Plugin creation & setup
Source: https://docs.strapi.io/cms/plugins-development/create-a-plugin

# Plugin creation

There are many ways to create a Strapi 5 plugin, but the fastest and recommended way is to use the Plugin SDK.

The Plugin SDK is a set of commands orientated around developing plugins to use them as local plugins or to publish them on NPM and/or submit them to the Marketplace.

With the Plugin SDK, you do not need to set up a Strapi project before creating a plugin.

The present guide covers creating a plugin from scratch, linking it to an existing Strapi project, and publishing the plugin. If you already have an existing plugin, you can instead retrofit the plugin setup to utilise the Plugin SDK commands (please refer to the [Plugin SDK reference](/cms/plugins-development/plugin-sdk) for a full list of available commands).

:::note
This guide assumes you want to develop a plugin external to your Strapi project. However, the steps largely remain the same if you want to develop a plugin within your existing project. If you are not [using a monorepo](#monorepo) the steps are exactly the same.
:::

:::prerequisites

</Tabs>

The path `my-strapi-plugin` can be replaced with whatever you want to call your plugin, including the path to where it should be created (e.g., `code/strapi-plugins/my-new-strapi-plugin`).

You will be ran through a series of prompts to help you setup your plugin. If you selected yes to all options the final structure will be similar to the default [plugin structure](/cms/plugins-development/plugin-structure).

### Linking the plugin to your project

In order to test your plugin during its development, the recommended approach is to link it to a Strapi project.

Linking your plugin to a project is done with the `watch:link` command. The command will output explanations on how to link your plugin to a Strapi project.

In a new terminal window, run the following commands:

</Tabs>

:::note
In the above examples we use the name of the plugin (`my-strapi-plugin`) when linking it to the project. This is the name of the package, not the name of the folder.
:::

Because this plugin is installed via `node_modules` you won't need to explicity add it to your `plugins` [configuration file](/cms/configurations/plugins), so running the [`develop command`](/cms/cli#strapi-develop) to start your Strapi project will automatically pick up your plugin.

Now that your plugin is linked to a project, run `yarn develop` or `npm run develop` to start the Strapi application.

You are now ready to develop your plugin how you see fit! If you are making server changes, you will need to restart your server for them to take effect.

### Building the plugin for publishing

When you are ready to publish your plugin, you will need to build it. To do this, run the following command:

</Tabs>

The above commands will not only build the plugin, but also verify that the output is valid and ready to be published. You can then publish your plugin to NPM as you would any other package.

:::tip Upgrading from SDK Plugin v5
If you're upgrading from `@strapi/sdk-plugin` v5 to v6:
* Delete any `packup.config.ts` file from your plugin (it is no longer used).
* Rely on `package.json#exports` for build configuration (it is now derived automatically).
* Add `--sourcemap` to your build command if you need sourcemaps (they now default to off).

No other changes are required.
:::

## Working with the Plugin SDK in a monorepo environment {#monorepo}

If you are working with a monorepo environment to develop your plugin, you don't need to use the `watch:link` command because the monorepo workspace setup will handle the symlink. You can use the `watch` command instead.

However, if you are writing admin code, you might add an `alias` that targets the source code of your plugin to make it easier to work with within the context of the admin panel:

```ts

  config.resolve.alias = {
    ...config.resolve.alias,
    'my-strapi-plugin': path.resolve(
      __dirname,
      // We've assumed the plugin is local.
      '../plugins/my-strapi-plugin/admin/src'
    ),
  };

  return config;
};
```

:::caution
Because the server looks at the `server/src/index.ts|js` file to import your plugin code, you must use the `watch` command otherwise the code will not be transpiled and the server will not be able to find your plugin.
:::

### Configuration with a local plugin

Since the Plugin SDK is primarily designed for developing plugins, not locally, the configuration needs to be adjusted manually for local plugins.

When developing your plugin locally (using `@strapi/sdk-plugin`), your plugins configuration file looks like in the following example:

```js title="/config/plugins.js|ts"
myplugin: {
  enabled: true,
  resolve: `./src/plugins/local-plugin`,
},
```

However, this setup can sometimes lead to errors such as the following:

```js
Error: 'X must be used within StrapiApp';
```

This error often occurs when your plugin attempts to import core Strapi functionality, for example using:

```js

```

To resolve the issue, remove `@strapi/strapi` as a dev dependency from your plugin. This ensures that your plugin uses the same instance of Strapi's core modules as the main application, preventing conflicts and the associated errors.

## Setting a local plugin in a monorepo environment without the Plugin SDK

In a monorepo, you can configure your local plugin without using the Plugin SDK by adding 2 entry point files at the root of your plugin:

- server entry point: `strapi-server.js|ts`
- admin entry point: `strapi-admin.js|ts`

### Server entry point

The server entry point file initializes your plugin's server-side functionalities. The expected structure for `strapi-server.js` (or its TypeScript variant) is:

```js
module.exports = () => {
  return {
    register,
    config,
    controllers,
    contentTypes,
    routes,
  };
};
```

Here, you export a function that returns your plugin's core components such as controllers, routes, and configuration. For more details, please refer to the [Server API reference](/cms/plugins-development/server-api).

### Admin entry point

The admin entry point file sets up your plugin within the Strapi admin panel. The expected structure for `strapi-admin.js` (or its TypeScript variant) is:

```js

  register(app) {},
  bootstrap() {},
  registerTrads({ locales }) {},
};
```

This object includes methods to register your plugin with the admin application, perform bootstrapping actions, and handle translations. For more details, please refer to the [Admin Panel API reference](/cms/plugins-development/admin-panel-api).

:::tip
For a complete example of how to structure your local plugin in a monorepo environment, please check out our .
:::




---


# Developing plugins
Source: https://docs.strapi.io/cms/plugins-development/developing-plugins

# Developing Strapi plugins

Strapi allows the development of plugins that work exactly like the built-in plugins or 3rd-party plugins available from the 

:::strapi Custom fields plugins
Plugins can also be used to add [custom fields](/cms/features/custom-fields) to Strapi.
:::

## Guides

<br />

:::strapi Additional resources
The  can also include additional information useful while developing a Strapi plugin.
:::




---


# How to create admin permissions from plugins
Source: https://docs.strapi.io/cms/plugins-development/guides/admin-permissions-for-plugins

# How to create admin permissions from plugins

When [developing a Strapi plugin](/cms/plugins-development/developing-plugins), you might want to create admin permissions for your plugin. By doing that you can hook in to the [RBAC system](/cms/features/rbac) of Strapi to selectively grant permissions to certain pieces of your plugin.

To create admin permissions for your Strapi plugin, you'll need to register them on the server side before implementing them on the admin side.

## Register the permissions server side

Each individual permission has to registered in the bootstrap function of your plugin, as follows:

</Tabs>

## Implement permissions on the admin panel side

Before we can implement our permissions on the admin panel side we have to define them in a reusable configuration file. This file can be stored anywhere in your plugin admin code. You can do that as follows:

```js title="/src/plugins/my-plugin/admin/src/permissions.js|ts"
const pluginPermissions = {
  'accessOverview': [{ action: 'plugin::my-plugin.overview.access', subject: null }],
  'accessSidebar': [{ action: 'plugin::my-plugin.sidebar.access', subject: null }],
};

```

### Page permissions

Once you've created the configuration file you are ready to implement your permissions. If you've bootstrapped your plugin using the [plugin SDK init command](/cms/plugins-development/plugin-sdk#npx-strapisdk-plugin-init), you will have an example `HomePage.tsx` file. To implement page permissions you can do the following:

```js title="/src/plugins/my-plugin/admin/src/pages/HomePage.jsx|tsx" {2,5,12,16}

const HomePage = () => {
  const { formatMessage } = useIntl();

  return (
    
    </Page.Protect>
  );
};

```

You can see how we use our permissions configuration file together with the `<Page.Protect>` component to require specific permissions in order to view this page.

### Menu link permissions

The previous example makes sure that the permissions of a user that visits your page directly will be validated. However, you might want to remove the menu link to that page as well. To do that, you'll have to make a change to the `addMenuLink` implementation. You can do as follows:

```js title="/src/plugins/my-plugin/admin/src/index.js|ts" {21-23,5}

  register(app) {
    app.addMenuLink({
      to: `plugins/${PLUGIN_ID}`,
      icon: PluginIcon,
      intlLabel: {
        id: `${PLUGIN_ID}.plugin.name`,
        defaultMessage: PLUGIN_ID,
      },
      Component: async () => {
        const { App } = await import('./pages/App');

        return App;
      },
      permissions: [
        pluginPermissions.accessOverview[0],
      ],
    });

    app.registerPlugin({
      id: PLUGIN_ID,
      initializer: Initializer,
      isReady: false,
      name: PLUGIN_ID,
    });
  },
};

```

### Custom permissions with the `useRBAC` hook

To get even more control over the permission of the admin user you can use the `useRBAC` hook. With this hook you can use the permissions validation just like you want, as in the following example:

```js title="/src/plugins/my-plugin/admin/src/components/Sidebar.jsx|tsx" 

const Sidebar = () => {
  const {
    allowedActions: { canAccessSidebar },
  } = useRBAC(pluginPermissions);

  if (!canAccessSidebar) {
    return null;
  }

  return (
    <div>Sidebar component</div>
  );
};

```




---


# How to create components for Strapi plugins
Source: https://docs.strapi.io/cms/plugins-development/guides/create-components-for-plugins

# How to create components for Strapi plugins

When [developing a Strapi plugin](/cms/plugins-development/developing-plugins), you might want to create reusable components for your plugin. Components in Strapi are reusable data structures that can be used across different content-types.

To create components for your Strapi plugin, you'll need to follow a similar approach to creating content-types, but with some specific differences.

## Creating components

You can create components for your plugins in 2 different ways: using the Content-Type Builder (recommended way) or manually.

### Using the Content-Type Builder 

The recommended way to create components for your plugin is through the Content-Type Builder in the admin panel. 

The [Content-Type Builder documentation](/cms/features/content-type-builder#new-component) provides more details on this process.

### Creating components manually

If you prefer to create components manually, you'll need to:

1. Create a component schema in your plugin's structure.
2. Make sure the component is properly registered.

Components for plugins should be placed in the appropriate directory within your plugin structure. You would typically create them within the server part of your plugin (see [plugin structure documentation](/cms/plugins-development/plugin-structure)).

For more detailed information about components in Strapi, you can refer to the [Model attributes documentation](/cms/backend-customization/models#components-json).

## Reviewing the component structure

Components in Strapi follow the following format in their definition:

```javascript title="/my-plugin/server/components/category/component-name.json"
{
  "attributes": {
    "myComponent": {
      "type": "component",
      "repeatable": true,
      "component": "category.componentName"
    }
  }
}
```

## Component schema example

A component schema defines the structure of a reusable data fragment. Here is an example of a component schema for a plugin:

```json title="my-plugin/server/components/my-category/my-component.json"
{
  "collectionName": "components_my_category_my_components",
  "info": {
    "displayName": "My Component",
    "icon": "align-justify"
  },
  "attributes": {
    "name": {
      "type": "string",
      "required": true
    },
    "description": {
      "type": "text"
    }
  }
}
```

This configuration ensures your components will be available in both the Content-Type Builder and Content Manager when used in a content-type that has `pluginOptions` visibility enabled.




---


# How to pass data from server to admin panel with a Strapi plugin
Source: https://docs.strapi.io/cms/plugins-development/guides/pass-data-from-server-to-admin

# How to pass data from server to admin panel with a Strapi plugin

Strapi is **headless** . The admin panel is completely separate from the server.

When [developing a Strapi plugin](/cms/plugins-development/developing-plugins) you might want to pass data from the `/server` to the `/admin` folder. Within the `/server` folder you have access to the Strapi object and can do database queries whereas in the `/admin` folder you can't.

Passing data from the `/server` to the `/admin` folder can be done using the admin panel's Axios instance:

To pass data from the `/server` to `/admin` folder you would first [create a custom admin route](#create-a-custom-admin-route) and then [get the data returned in the admin panel](#get-the-data-in-the-admin-panel).

## Create a custom admin route

Admin routes are like the routes that you would have for any controller, except that the `type: 'admin'` declaration hides them from the general API router, and allows you to access them from the admin panel.

The following code will declare a custom admin route for the `my-plugin` plugin:

```js title="/my-plugin/server/routes/index.js"
module.exports = {
  'pass-data': {
    type: 'admin',
    routes: [
      {
        method: 'GET',
        path: '/pass-data',
        handler: 'myPluginContentType.index',
        config: {
          policies: [],
          auth: false,
        },
      },
    ]
  }
  // ...
};
```

This route will call the `index` method of the `myPluginContentType` controller when you send a GET request to the `/my-plugin/pass-data` URL endpoint.

Let's create a basic custom controller that simply returns a simple text:

```js title="/my-plugin/server/controllers/my-plugin-content-type.js"
'use strict';

module.exports = {
  async index(ctx) {
    ctx.body = 'You are in the my-plugin-content-type controller!';
  }
}
```

This means that when sending a GET request to the `/my-plugin/pass-data` URL endpoint, you should get the `You are in the my-plugin-content-type controller!` text returned with the response.

## Get the data in the admin panel

Any request sent from an admin panel component to the endpoint for which we defined the custom route `/my-plugin/pass-data` should now return the text message returned by the custom controller.

So for instance, if you create an `/admin/src/api/foobar.js` file and copy and paste the following code example:

```js title="/my-plugin/admin/src/api/foobar.js"

const foobarRequests = {
  getFoobar: async () => {
    const data = await axios.get(`/my-plugin/pass-data`);
    return data;
  },
};

```

You will be able to use `foobarRequests.getFoobar()` in the code of an admin panel component and have it return the `You are in the my-plugin-content-type controller!` text with the data.

For instance, within a React component, you could use `useEffect` to get the data after the component initializes:

```js title="/my-plugin/admin/src/components/MyComponent/index.js"

const [foobar, setFoobar] = useState([]);
// …
useEffect(() => {
  foobarRequests.getFoobar().then(res => {
    setFoobar(res.data);
  });
}, [setFoobar]);
// …
```

This would set the `You are in the my-plugin-content-type controller!` text within the `foobar` data of the component's state.




---


# How to store and access data from a Strapi plugin
Source: https://docs.strapi.io/cms/plugins-development/guides/store-and-access-data

# How to store and access data from a Strapi plugin

To store data with a Strapi [plugin](/cms/plugins-development/developing-plugins), use a plugin content-type. Plugin content-types work exactly like other [content-types](/cms/backend-customization/models). Once the content-type is [created](#create-a-content-type-for-your-plugin), you can start [interacting with the data](#interact-with-data-from-the-plugin).

## Create a content-type for your plugin

To create a content-type with the CLI generator, run the following command in a terminal within the `server/src/` directory of your plugin:

</Tabs>

The generator CLI is interactive and asks a few questions about the content-type and the attributes it will contain. Answer the first questions, then for the `Where do you want to add this model?` question, choose the `Add model to existing plugin` option and type the name of the related plugin when asked.

<figure style={{width: '100%', margin: '0' }}>
  <img src="/img/assets/development/generate-plugin-content-type.png" alt="Generating a content-type plugin with the CLI" />
  <em><figcaption style={{fontSize: '12px'}}>The <code>strapi generate content-type</code> CLI generator is used to create a basic content-type for a plugin.</figcaption></em>
</figure>

<br />

The CLI will generate some code required to use your plugin, which includes the following:

- the [content-type schema](/cms/backend-customization/models#model-schema)
- and a basic [controller](/cms/backend-customization/controllers), [service](/cms/backend-customization/services), and [route](/cms/backend-customization/routes) for the content-type

:::tip
You may want to create the whole structure of your content-types either entirely with the CLI generator or by directly creating and editing `schema.json` files. We recommend you first create a simple content-type with the CLI generator and then leverage the [Content-Type Builder](/cms/features/content-type-builder) in the admin panel to edit your content-type.

If your content-type is not visible in the admin panel, you might need to set the `content-manager.visible` and `content-type-builder.visible` parameters to `true` in the `pluginOptions` object of the content-type schema:

<details>
<summary>Making a plugin content-type visible in the admin panel:</summary>

The following highlighted lines in an example `schema.json` file show how to make a plugin content-type visible to the Content-Type Builder and Content-Manager:

```json title="/server/content-types/my-plugin-content-type/schema.json" {13-20} showLineNumbers
{
  "kind": "collectionType",
  "collectionName": "my_plugin_content_types",
  "info": {
    "singularName": "my-plugin-content-type",
    "pluralName": "my-plugin-content-types",
    "displayName": "My Plugin Content-Type"
  },
  "options": {
    "draftAndPublish": false,
    "comment": ""
  },
  "pluginOptions": {
    "content-manager": {
      "visible": true
    },
    "content-type-builder": {
      "visible": true
    }
  },
  "attributes": {
    "name": {
      "type": "string"
    }
  }
}

```

</details>
:::

### Ensure plugin content-types are imported

The CLI generator might not have imported all the related content-type files for your plugin, so you might have to make the following adjustments after the `strapi generate content-type` CLI command has finished running:

1. In the `/server/index.js` file, import the content-types:

  ```js {7,22} showLineNumbers title="/server/index.js"
  'use strict';

  const register = require('./register');
  const bootstrap = require('./bootstrap');
  const destroy = require('./destroy');
  const config = require('./config');
  const contentTypes = require('./content-types');
  const controllers = require('./controllers');
  const routes = require('./routes');
  const middlewares = require('./middlewares');
  const policies = require('./policies');
  const services = require('./services');

  module.exports = {
    register,
    bootstrap,
    destroy,
    config,
    controllers,
    routes,
    services,
    contentTypes,
    policies,
    middlewares,
  };

  ```

2. In the `/server/content-types/index.js` file, import the content-type folder:

  ```js title="/server/content-types/index.js"
  'use strict';

  module.exports = {
    // In the line below, replace my-plugin-content-type
    // with the actual name and folder path of your content type
    "my-plugin-content-type": require('./my-plugin-content-type'),
  };
  ```

3. Ensure that the `/server/content-types/[your-content-type-name]` folder contains not only the `schema.json` file generated by the CLI, but also an `index.js` file that exports the content-type with the following code:

  ```js title="/server/content-types/my-plugin-content-type/index.js
  'use strict';

  const schema = require('./schema');

  module.exports = {
    schema,
  };
  ```

## Interact with data from the plugin

Once you have created a content-type for your plugin, you can create, read, update, and delete data.

:::note
A plugin can only interact with data from the `/server` folder. If you need to update data from the admin panel, please refer to the [passing data guide](/cms/plugins-development/guides/pass-data-from-server-to-admin).
:::

To create, read, update, and delete data, you can use either the [Document Service API](/cms/api/document-service) or the [Query Engine API](/cms/api/query-engine). While it's recommended to use the Document Service API, especially if you need access to components or dynamic zones, the Query Engine API is useful if you need unrestricted access to the underlying database.

Use the `plugin::your-plugin-slug.the-plugin-content-type-name` syntax for content-type identifiers in Document Service and Query Engine API queries.

**Example:**

Here is how to find all the entries for the `my-plugin-content-type` collection type created for a plugin called `my-plugin`:

```js
// Using the Document Service API
let data = await strapi.documents('plugin::my-plugin.my-plugin-content-type').findMany();

// Using the Query Engine API
let data = await strapi.db.query('plugin::my-plugin.my-plugin-content-type').findMany();
````

:::tip
You can access the database via the `strapi` object which can be found in `middlewares`, `policies`, `controllers`, `services`, as well as from the `register`, `boostrap`, `destroy` lifecycle functions.
:::




---


# Plugin SDK reference
Source: https://docs.strapi.io/cms/plugins-development/plugin-sdk

# Plugin SDK reference

The Plugin SDK is set of commands provided by the package  orientated around developing plugins to use them as local plugins or to publish them on NPM and/or submit them to the Marketplace.

The present documentation lists the available Plugin SDK commands. The [associated guide](/cms/plugins-development/create-a-plugin) illustrates how to use these commands to create a plugin from scratch, link it to an existing project, and publish it.

## npx @strapi/sdk-plugin init

Create a new plugin at a given path.

```bash
npx @strapi/sdk-plugin init
```

| Arguments |  Type  | Description        | Default                   |
| --------- | :----: | ------------------ | ------------------------- |
| `path`    | string | Path to the plugin | `./src/plugins/my-plugin` |

| Option        | Type | Description                             | Default |
| ------------- | :--: | --------------------------------------- | ------- |
| `-d, --debug` |  -   | Enable debugging mode with verbose logs | false   |
| `--silent`    |  -   | Do not log anything                     | false   |

## strapi-plugin build

Bundle the Strapi plugin for publishing.

```bash
strapi-plugin build
```

| Option         |  Type  | Description                                                                                                       | Default |
| -------------- | :----: | ----------------------------------------------------------------------------------------------------------------- | ------- |
| `--force`      | string | Automatically answer "yes" to all prompts, including potentially destructive requests, and run non-interactively. | -       |
| `-d, --debug`  |   -    | Enable debugging mode with verbose logs                                                                           | false   |
| `--silent`     |   -    | Do not log anything                                                                                               | false   |
| `--minify`     |   -    | Minify the output                                                                                                 | false   |
| `--sourcemap`  |   -    | Produce sourcemaps                                                                                                | false   |

:::note
As of v6, the build configuration is automatically derived from your `package.json` exports field. No configuration file (such as `vite.config.ts` or `rollup.config.ts`) is needed.
:::

## strapi-plugin watch:link

Recompiles the plugin automatically on changes and runs `yalc push --publish`.

For testing purposes, it is very convenient to link your plugin to an existing application to experiment with it in real condition. This command is made to help you streamline this process.

```bash
strapi-plugin watch:link
```

| Option        | Type | Description                             | Default |
| ------------- | :--: | --------------------------------------- | ------- |
| `-d, --debug` |  -   | Enable debugging mode with verbose logs | false   |
| `--silent`    |  -   | Do not log anything                     | false   |

## strapi-plugin watch

Watch the plugin source code for any change and rebuild it everytime. Useful when implementing your plugin and testing it in an application.

```bash
strapi-plugin watch
```

| Option        | Type | Description                             | Default |
| ------------- | :--: | --------------------------------------- | ------- |
| `-d, --debug` |  -   | Enable debugging mode with verbose logs | false   |
| `--silent`    |  -   | Do not log anything                     | false   |

## strapi-plugin verify

Verify the output of the plugin before publishing it.

```bash
strapi-plugin verify
```

| Option        | Type | Description                             | Default |
| ------------- | :--: | --------------------------------------- | ------- |
| `-d, --debug` |  -   | Enable debugging mode with verbose logs | false   |
| `--silent`    |  -   | Do not log anything                     | false   |




---


# Plugin structure
Source: https://docs.strapi.io/cms/plugins-development/plugin-structure

# Plugin structure

When [creating a plugin with Plugin SDK](/cms/plugins-development/create-a-plugin), Strapi generates the following boilerplate structure for you in the `/src/plugins/my-plugin` folder:

A Strapi plugin is divided into 2 parts, each living in a different folder and offering a different API:

| Plugin part | Description | Folder       | API |
|-------------|-------------|--------------|-----|
| Admin panel | Includes what will be visible in the [admin panel](/cms/intro) (components, navigation, settings, etc.) | `admin/` |[Admin Panel API](/cms/plugins-development/admin-panel-api)|
| Backend server | Includes what relates to the [backend server](/cms/backend-customization) (content-types, controllers, middlewares, etc.) |`server/` |[Server API](/cms/plugins-development/server-api)|

<br />

:::note Notes about the usefulness of the different parts for your specific use case
- **Server-only plugin**: You can create a plugin that will just use the server part to enhance the API of your application. For instance, this plugin could have its own visible or invisible content-types, controller actions, and routes that are useful for a specific use case. In such a scenario, you don't need your plugin to have an interface in the admin panel.

- **Admin panel plugin vs. application-specific customization**: You can create a plugin to inject some components into the admin panel. However, you can also achieve this by creating a `/src/admin/index.js` file and invoking the `bootstrap` lifecycle function to inject your components. In this case, deciding whether to create a plugin depends on whether you plan to reuse and distribute the code or if it's only useful for a unique Strapi application.
:::

<br/>

:::strapi What to read next?
The next steps of your Strapi plugin development journey will require you to use any of the Strapi plugins APIs.

2 different types of resources help you understand how to use the plugin APIs:

- The reference documentation for the [Admin Panel API](/cms/plugins-development/admin-panel-api) and [Server API](/cms/plugins-development/server-api) give an overview of what is possible to do with a Strapi plugin.
- [Guides](/cms/plugins-development/developing-plugins#guides) cover some specific, use-case based examples.
:::




---


# Plugins extension
Source: https://docs.strapi.io/cms/plugins-development/plugins-extension

# Plugins extension

Strapi comes with plugins that can be installed from the [Marketplace](/cms/plugins/installing-plugins-via-marketplace#installing-marketplace-plugins-and-providers) or as npm packages. You can also create your own plugins (see [plugins development](/cms/plugins-development/developing-plugins)) or extend the existing ones.

:::warning
* Any plugin update could break this plugin's extensions.
* New versions of Strapi will be released with migration guides when required, but these guides never cover plugin extensions. Consider forking a plugin if extensive customizations are required.
* Currently, the admin panel part of a plugin can only be extended using , but please consider that doing so might break your plugin in future versions of Strapi.
:::

Plugin extensions code is located in the `./src/extensions` folder (see [project structure](/cms/project-structure)). Some plugins automatically create files there, ready to be modified.

<details> 
<summary>Example of extensions folder structure</summary>

```bash
/extensions
  /some-plugin-to-extend
    strapi-server.js|ts
    /content-types
      /some-content-type-to-extend
        schema.json
      /another-content-type-to-extend
        schema.json
  /another-plugin-to-extend
    strapi-server.js|ts
```
</details>

Plugins can be extended in 2 ways:

- [extending the plugin's content-types](#extending-a-plugins-content-types)
- [extending the plugin's interface](#extending-a-plugins-interface) (e.g. to add controllers, services, policies, middlewares and more)

## Extending a plugin's content-types

A plugin's Content-Types can be extended in 2 ways: using the programmatic interface within `strapi-server.js|ts` and by overriding the content-types schemas.

The final schema of the content-types depends on the following loading order:

1. the content-types of the original plugin,
2. the content-types overridden by the declarations in the [schema](/cms/backend-customization/models#model-schema) defined in `./src/extensions/plugin-name/content-types/content-type-name/schema.json`
3. the content-types declarations in the [`contentTypes` export from `strapi-server.js|ts`](/cms/plugins-development/server-content-types)
4. the content-types declarations in the [`register()` function](/cms/configurations/functions#register) of the Strapi application

To overwrite a plugin's [content-types](/cms/backend-customization/models):

1. _(optional)_ Create the `./src/extensions` folder at the root of the app, if the folder does not already exist.
2. Create a subfolder with the same name as the plugin to be extended.
3. Create a `content-types` subfolder.
4. Inside the `content-types` subfolder, create another subfolder with the same [singularName](/cms/backend-customization/models#model-information) as the content-type to overwrite.
5. Inside this `content-types/name-of-content-type` subfolder, define the new schema for the content-type in a `schema.json` file (see [schema](/cms/backend-customization/models#model-schema) documentation).
6. _(optional)_ Repeat steps 4 and 5 for each content-type to overwrite.

## Extending a plugin's interface

When a Strapi application is initializing, plugins, extensions and global lifecycle functions events happen in the following order:

1. Plugins are loaded and their interfaces are exposed.
2. Files in `./src/extensions` are loaded.
3. The `register()` and `bootstrap()` functions in `./src/index.js|ts` are called.

A plugin's interface can be extended at step 2 (i.e. within `./src/extensions`) or step 3 (i.e. inside `./src/index.js|ts`).

:::note
If your Strapi project is TypeScript-based, please ensure that the `index` file has a TypeScript extension (i.e., `src/index.ts`) otherwise it will not be compiled.
:::

### Within the extensions folder

To extend a plugin's server interface using the `./src/extensions` folder:

1. _(optional)_ Create the `./src/extensions` folder at the root of the app, if the folder does not already exist.
2. Create a subfolder with the same name as the plugin to be extended.
3. Create a `strapi-server.js|ts` file to extend a plugin's back end using the [Server API](/cms/plugins-development/server-api).
4. Within this file, define and export a function. The function receives the `plugin` interface as an argument so it can be extended.

<details>
<summary>Example of backend extension</summary>

```js title="./src/extensions/some-plugin-to-extend/strapi-server.js|ts"

module.exports = (plugin) => {
  plugin.controllers.controllerA.find = (ctx) => {};

  plugin.policies[newPolicy] = (ctx) => {};

  plugin.routes['content-api'].routes.push({
    method: 'GET',
    path: '/route-path',
    handler: 'controller.action',
  });

  return plugin;
};
```
</details>

:::note
The `strapi-server.js|ts` file is also where you can override the image function, by replacing the Upload plugin's `generateFileName()` function so that it generates custom image names.

<details>
<summary>Example of custom file-naming logic</summary>

```js title="./src/extensions/upload/strapi-server.js|ts"

module.exports = (plugin) => {
  plugin.services['image-manipulation'].generateFileName = (file) => {
    // Example: prefix a timestamp before the generated base name
    return `${Date.now()}_${name}`;
  };

  return plugin;
};

```
</details>

:::

`generateFileName()` belongs to the Upload plugin's `image-manipulation` service and expects a single `name: string` argument.

:::caution
This customization relies on an internal Upload plugin service (`image-manipulation`). Internal extension points are not part of Strapi's stable public API and can change between versions.
:::

### Within the register and bootstrap functions

To extend a plugin's interface within `./src/index.js|ts`, use the `bootstrap()` and `register()` [functions](/cms/configurations/functions) of the whole project, and access the interface programmatically with [getters](/cms/plugins-development/server-getters-usage).

<details>
<summary>Example of extending a plugin's content-type within ./src/index.js|ts</summary>

```js title="./src/index.js|ts"

module.exports = {
  register({ strapi }) {
    const contentTypeName = strapi.contentType('plugin::my-plugin.content-type-name')  
    contentTypeName.attributes = {
      // Spread previous defined attributes
      ...contentTypeName.attributes,
      // Add new, or override attributes
      'toto': {
        type: 'string',
      }
    }
  },
  bootstrap({ strapi }) {},
};
```
</details>




---


# Server API for plugins
Source: https://docs.strapi.io/cms/plugins-development/server-api

# Server API for plugins: An overview

A Strapi plugin can interact with both the back end and the front end of a Strapi application. The Server API covers the back-end part: it defines what the plugin registers, exposes, and executes on the Strapi server. The server part is defined in the entry file, which exports an object (or a function returning an object). That object describes what the plugin contributes to the server.

For more information on how plugins can customize the admin panel UI, see [Admin Panel API](/cms/plugins-development/admin-panel-api).

</Tabs>

All server code can technically live in the single entry file, but splitting each concern into its own folder, as generated by the Plugin SDK, is strongly recommended. The examples in this documentation follow that structure.

:::note Notes
* The entry file accepts either an object literal or a function that returns the same object shape. When the function form is used, Strapi calls it with `{ env }` (not `{ strapi }`) while loading the plugin module.
* `config` is a configuration object, not an executable lifecycle hook. Unlike `register()`, `bootstrap()`, or `destroy()`, it is not called as a function during the plugin lifecycle. It is loaded at startup and used to set defaults and validate user configuration. See [server lifecycle](/cms/plugins-development/server-lifecycle) for more information.
:::

## Available actions

The Server API lets a plugin take advantage of several building blocks to define its server-side behavior.

Use the following table to find which capability matches your goal:

| Goal | Parameter to use | When it runs |
| --- | --- | --- |
| Run code before the server starts | [`register()`](/cms/plugins-development/server-lifecycle#register) | Before database and routing initialization |
| Run code after all plugins are loaded | [`bootstrap()`](/cms/plugins-development/server-lifecycle#bootstrap) | After database, routes, and permissions are initialized |
| Clean up resources on shutdown | [`destroy()`](/cms/plugins-development/server-lifecycle#destroy) | On shutdown |
| Define plugin options with defaults and validation | [`config`](/cms/plugins-development/server-configuration) | Loaded at startup |
| Declare plugin content-types | [`contentTypes`](/cms/plugins-development/server-content-types) | Loaded at startup |
| Expose HTTP endpoints | [`routes`](/cms/plugins-development/server-routes) | Loaded at startup |
| Handle HTTP requests | [`controllers`](/cms/plugins-development/server-controllers-services#controllers) | Called per request |
| Implement business logic | [`services`](/cms/plugins-development/server-controllers-services#services) | Called from controllers or lifecycle hooks |
| Enforce access rules on routes | [`policies`](/cms/plugins-development/server-policies-middlewares#policies) | Evaluated per request, before controller |
| Intercept and modify request/response flow | [`middlewares`](/cms/plugins-development/server-policies-middlewares#middlewares) | Attached in `register()` or referenced in route config |
| Access plugin features at runtime | [Getters](/cms/plugins-development/server-getters-usage) | Any lifecycle or request handler |

<br/>

The following cards link directly to each dedicated page:

:::strapi Backend customization
Plugin routes, controllers, services, policies, and middlewares follow the same conventions as [backend customization](/cms/backend-customization) in a standard Strapi application. The Server API wraps these into the plugin namespace automatically (see [server content types](/cms/plugins-development/server-content-types#uids-and-naming-conventions) for details on UIDs and naming conventions).
:::




---


# Server configuration
Source: https://docs.strapi.io/cms/plugins-development/server-configuration

# Server API: Configuration

A plugin can expose a `config` object from its [server entry file](/cms/plugins-development/server-api#entry-file). This object defines default configuration values and validates any user-provided overrides loaded from the application's `config/plugins.js|ts` file.

</Tabs>

A user can override these values in the application's plugin configuration file:

</Tabs>

After deep-merging defaults with user overrides, the final config is `{ enabled: true, maxItems: 25, endpoint: 'https://api.production.example.com' }`.

## Runtime access

Once the plugin is loaded, its configuration is available anywhere the `strapi` object is accessible:

```js
// Read one key
const maxItems = strapi.plugin('my-plugin').config('maxItems');
```

```js
// Read the entire plugin config object
const pluginConfig = strapi.config.get('plugin::my-plugin');
```

Both `strapi.plugin().config()` and `strapi.config.get()` are typically used inside lifecycle functions, controllers, or services.

:::tip
Use `yarn strapi console` or `npm run strapi console` to inspect the live configuration of a running Strapi instance.
:::

## Best practices

- **Always provide a `default`.** A plugin with no defaults forces every user to supply all configuration values, which creates friction. Make every option optional with a sensible default.

- **Use the function form of `default` for environment-aware config.** The `({ env }) => ({...})` form lets users drive configuration from environment variables without any extra setup. The plain object form is fine for truly static defaults.

- **Keep validation simple and explicit.** The `validator` runs at startup, before any request is served. Throw descriptive errors so the operator knows exactly what is wrong. For example, `'"maxItems" must be a positive number'` is more useful than `'Invalid config'`.

- **Do not store secrets in plugin config.** Plugin configuration is accessible server-side via `strapi.config` and can be exposed unintentionally through logs, debug tooling, or custom endpoints if mishandled. Use environment variables directly in services, or read those values via the `env` helper in `default`, rather than embedding raw credentials in the config object.

- **Read config in services, not inline.** Accessing `strapi.plugin('my-plugin').config('key')` inside a service method rather than at module load time ensures the value is always the final merged value, not a snapshot taken before user overrides are applied.




---


# Server content-types
Source: https://docs.strapi.io/cms/plugins-development/server-content-types

# Server API: Content-types

A plugin can declare its own content-types by exporting a `contentTypes` object from the [server entry file](/cms/plugins-development/server-api#entry-file). Strapi registers these content-types under the plugin namespace at startup and makes them available through the Document Service API and the content-type registry.

</Tabs>

## UIDs and naming conventions

When a plugin content-type is registered, Strapi builds its runtime UID from the plugin namespace and the key used in the `contentTypes` export:

```
plugin::<plugin-name>.<content-types-key>
```

The recommended convention is to set `content-types-key === info.singularName`. Following this convention keeps the schema naming and runtime UID aligned and easier to read.

When the key matches `singularName` (recommended), the resulting UID follows this format:

```
plugin::<plugin-name>.<singular-name>
```

For example, a plugin named `my-plugin` with a content-type whose `singularName` is `article` and export key `article` has the UID `plugin::my-plugin.article`.

:::warning
If the `contentTypes` key and `info.singularName` diverge, getters and queries use the UID built from the registered key (not from `singularName`). This can introduce naming inconsistencies across your plugin code.
:::

This UID is used consistently across all APIs:

| Use case | Example |
| --- | --- |
| Query via Document Service | `strapi.documents('plugin::my-plugin.article').findMany()` |
| Access schema via getter | `strapi.contentType('plugin::my-plugin.article')` |
| Reference in route handler | `handler: 'article.find'` (short form, resolved via plugin registry) |
| Pass to sanitization API | `strapi.contentAPI.sanitize.output(data, schema, { auth })` |

:::note
Controllers, services, policies, and middlewares use the same `plugin::<plugin-name>.<resource-name>` UID format for global getters, but are referenced by their short registry key (e.g., `'article'`) within plugin-level APIs such as route `handler` and `policies`. See [Getters & usage](/cms/plugins-development/server-getters-usage) for details.
:::

## Access at runtime

### Querying with the Document Service API

Use the Document Service API to query plugin content-types from controllers, services, or lifecycle hooks:

</Tabs>

:::strapi Document Service API
For the full list of available methods and parameters, see the [Document Service API](/cms/api/document-service).
:::

### Accessing the schema

Use the content-type getter to retrieve the schema object, for example to pass it to the sanitization API:

</Tabs>

## Best practices

- **Match the export key to `info.singularName` exactly.** This keeps naming readable and consistent. At runtime, Strapi derives the plugin content-type UID from the key of the `contentTypes` map under the plugin namespace. A mismatch may create confusing UIDs and maintenance issues, even if registration still succeeds.

- **Use `collectionName` to avoid table name conflicts.** The `collectionName` field sets the database table name. Prefix it with the plugin name (e.g., `my_plugin_articles`) to avoid collisions with application content-types or other plugins.

- **Keep content-type schemas in their own files.** Define each schema in a dedicated `schema.json` file inside a subfolder named after the `singularName` (e.g., `content-types/article/schema.json`). This matches the structure generated by the Plugin SDK and keeps the index file readable.

- **Enable `draftAndPublish` only when needed.** Draft and Publish adds a publication workflow to the content-type. Enable it only if the plugin's use case requires it, as it adds complexity to queries and content management.




---


# Server controllers & services
Source: https://docs.strapi.io/cms/plugins-development/server-controllers-services

# Server API: Controllers & services

Controllers and services are the 2 building blocks that handle request processing and business logic in a plugin server. They work together in a clear separation of concerns: controllers own the HTTP layer, services own the domain layer:

| Goal | Use |
| --- | --- |
| Receive `ctx`, read the request, set the response | [Controller](#controllers) |
| Query the database or apply business rules | [Service](#services) |
| Reuse logic across multiple controllers or lifecycle hooks | [Service](#services) |
| Call an external API as part of a request | [Service](#services) |

</Tabs>

### Sanitization

When your plugin exposes Content API routes, sanitize query parameters and output data before returning them. This prevents leaking private fields or bypassing access rules.

Plugin controllers are plain factory functions and do not extend `createCoreController` like in the Strapi core (see [backend customization](/cms/backend-customization/controllers) for details). This means the `this.sanitizeQuery` and `this.sanitizeOutput` shorthands are not available. Use `strapi.contentAPI.sanitize` directly instead, passing the content-type schema explicitly:

</Tabs>

:::strapi Backend customization
For the full sanitization and validation reference, including `sanitizeInput`, `validateQuery`, and `validateInput`, see [Controllers](/cms/backend-customization/controllers#sanitize-validate-custom-controllers).
:::

## Services

A service is a factory function that receives `{ strapi }` and returns an object of named methods, or a plain object; like [controllers](#declaration), Strapi resolves both at runtime. Services hold business logic called from controllers, lifecycle hooks, or other services.

### Declaration

</Tabs>

:::caution TypeScript service typing
`services` is typed as `unknown` in the current `ServerObject` TypeScript interface (`@strapi/types`). This means `strapi.plugin('my-plugin').service('article')` returns `unknown` and requires a cast to call methods with type safety. For fully typed service calls, define and export the service type explicitly and cast at the call site.
:::

:::strapi Document Service API
Services interact with content-types through the [Document Service API](/cms/api/document-service), which documents the full list of available methods and parameters.
:::

## End-to-end example

The following example shows the complete request flow across routes, a controller, and a service for a simple article resource.

</Tabs>

</ExpandableContent>

## Best practices

- **Keep controllers thin.** A controller action should do 3 things: receive `ctx`, delegate to a service, and set the response. Business logic, database calls, and conditional branching all belong in services.

- **One service per resource.** Organize services by the resource they manage (e.g., `article`, `comment`, `settings`) rather than by action type. This keeps each file focused and easy to test.

- **Use the Document Service API in services, not in controllers.** Calling `strapi.documents(...)` directly in a controller bypasses the service layer and makes logic harder to reuse. Put all Document Service calls in services.

- **Sanitize Content API responses.** When exposing Content API routes, use `strapi.contentAPI.sanitize.output()` before returning data. Skipping sanitization can leak private fields to end users. Admin routes are not subject to the same content-type field visibility rules, but sanitizing them as well is harmless.

- **Cast service types explicitly in TypeScript.** Until `services` is strongly typed in `@strapi/types`, cast the return value of `strapi.plugin('my-plugin').service('my-service')` to the service interface at each call site. Avoid using `any` throughout the codebase.




---


# Server getters & usage
Source: https://docs.strapi.io/cms/plugins-development/server-getters-usage

# Server API: Getters & usage

Plugin server resources, such as controllers, services, policies, middlewares, and content-types, are accessible from any server-side location through the `strapi` instance: other plugins, lifecycle hooks, application controllers, or custom scripts. Routes and configuration use dedicated APIs — see the [getter reference](#full-getter-reference) below.

</Tabs>

### Calling a plugin service from bootstrap

Services called in `bootstrap()` have access to the full `strapi` instance, including other plugins' services:

</Tabs>

### Calling across plugins or from application code

From application-level controllers or services (outside the plugin), or when calling from another plugin, global getters using the full UID are often clearer:

</Tabs>

### Reading plugin configuration at runtime

```js
// Read a single key
const maxItems = strapi.plugin('todo').config('maxItems');
```

```js
// Read the full config object
const todoConfig = strapi.config.get('plugin::todo');
```

```js
// Read a nested key
const endpoint = strapi.config.get('plugin::todo.endpoint');
```

:::note
`strapi.plugin('my-plugin').config('key')` reads the merged configuration (user overrides applied on top of plugin defaults). It is the recommended way to read config inside plugin code. See [Server configuration](/cms/plugins-development/server-configuration) for how plugin configuration is declared and merged.
:::

### Accessing a content-type schema

Use the content-type getter when you need the schema object, for example to pass it to the sanitization API:

</Tabs>

## Common errors

- **Naming mismatch between route handler and controller key.** If your route declares `handler: 'task.find'`, your controllers index must export a key called `task` and that controller must have a method called `find`. A mismatch throws a runtime error when the route is matched.

- **Misusing the policy context argument.** The first argument to a policy function is a policy context object, not a raw Koa `ctx`. It wraps the request context but exposes a different interface. Naming it `ctx` in your code won't cause an error, but treating it as a Koa context (for example, calling `ctx.body` or `ctx.status`) will not work as expected. Use `policyContext.state` to access auth state, and call `return false` or throw a `PolicyError` to block the request.

- **Calling a service at module load time.** The `strapi` object is not initialized when modules are first loaded. Always call getters inside a function body. Never call them at the top level of a module file.

- **Using an incomplete UID in global getters.** `strapi.service('todo.task')` is not a valid plugin UID. Use the full `plugin::todo.task` form. Without the proper namespace, the service call fails or returns `undefined` at runtime.

  | Scope | Example UID |
  | --- | --- |
  | Plugin service | `plugin::todo.task` |
  | API service | `api::project.project` |

## Best practices

- **Prefer top-level getters inside your own plugin.** `strapi.plugin('my-plugin').service('task')` is more readable than the global form when both are inside the same plugin.

- **Use global getters in application code and cross-plugin calls.** When calling from `src/api/` or from another plugin, the full UID `plugin::todo.task` makes the dependency explicit and is easier to search for.

- **Access services in services, not at declaration time.** Avoid capturing service references in closures at module initialization. Always resolve them at call time using the getter, to ensure Strapi is fully loaded.




---


# Server lifecycle
Source: https://docs.strapi.io/cms/plugins-development/server-lifecycle

# Server API: Lifecycle

Lifecycle functions control when your plugin's server-side logic runs during the Strapi application startup and shutdown sequence. They are exported from the [server entry file](/cms/plugins-development/server-api#entry-file) alongside routes, controllers, services, and other server blocks.

</Tabs>

## bootstrap()

**Type:** `Function`

`bootstrap()` runs after module lifecycle registration (plugins/APIs), database initialization, route initialization, and Content API action registration.

Use `bootstrap()` to:
- Seed the database with initial data
- Register admin RBAC actions using `strapi.service('admin::permission').actionProvider.registerMany(...)`
- Register cron jobs
- Subscribe to database lifecycle events
- Call services from your plugin or other plugins
- Set up cross-plugin integrations that require other plugins to be registered first

</Tabs>

## destroy()

**Type:** `Function`

`destroy()` is called when the Strapi instance is shutting down. It is optional. Only implement it when your plugin holds resources that need explicit cleanup.

Use `destroy()` to:
- Close external connections (databases, message queues, WebSocket servers)
- Clear intervals or timeouts set in `bootstrap()`
- Remove event listeners registered during the plugin's lifetime

</Tabs>

## Best practices

- **Keep `register()` lightweight.** It runs before full initialization.

- **Use `bootstrap()` for database reads/writes.** The database is initialized during the bootstrap phase, not during register. Any call to `strapi.documents()` or a service that queries the database belongs in `bootstrap()`.

- **Register admin RBAC actions in `bootstrap()`.** Use `strapi.service('admin::permission').actionProvider.registerMany(...)` in `bootstrap()`. This is when the permission service is available. Content API actions are registered automatically by Strapi during the same phase.

- **Always pair resource creation with `destroy()`.** If your plugin opens a connection, registers a global interval, or attaches a process listener in `bootstrap()`, implement `destroy()` to clean up those resources. This prevents resource leaks during testing and graceful restarts.

- **Avoid hard dependencies between plugins in `register()`.** At registration time, the order in which other plugins have registered is not guaranteed. Cross-plugin calls that rely on another plugin being initialized belong in `bootstrap()`.

- **Prefer services over inline logic.** Move non-trivial bootstrap logic into a dedicated service method (e.g. `strapi.plugin('my-plugin').service('setup').initialize()`). This keeps lifecycle files readable and the logic testable.




---


# Server policies & middlewares
Source: https://docs.strapi.io/cms/plugins-development/server-policies-middlewares

# Server API: Policies & middlewares

Policies and middlewares are the two mechanisms for intercepting requests in a plugin server. Policies decide whether a request should proceed. Middlewares shape how it is processed.

</Tabs>

### Usage in routes

Once declared, reference a plugin policy from a route using the `plugin::my-plugin.policy-name` namespace:

</Tabs>

:::caution Policy return values
Returning `false` causes Strapi to send a `403 Forbidden` response. Returning nothing (`undefined`) is treated as permissive (allowed), not as a block. Always return `true` or `false` explicitly. Throwing an error causes Strapi to send a `500` response unless you throw a Strapi HTTP error class (e.g., `new errors.PolicyError(...)`, `new errors.ForbiddenError(...)`, or `new errors.UnauthorizedError(...)`).
:::

:::strapi Backend customization
For the full policy reference including GraphQL support and the `policyContext` API, see [Policies](/cms/backend-customization/policies).
:::

## Middlewares

A middleware is a Koa-style function that wraps the request/response cycle. Unlike [policies](#policies) (which are pass/fail guards), middlewares can read and modify the request before it reaches the controller, and modify the response after the controller has executed.

Plugins can export middlewares in 2 ways:

- as a **route-level middleware**, declared in the `middlewares` export of the server entry file and referenced in route `config.middlewares`
- as a **server-level middleware**, registered directly on the Strapi HTTP server via `strapi.server.use()` in `register()`

### Route-level middlewares

Route-level middlewares are scoped to a specific route and are declared like policies: as an object of named factory functions, then referenced in the route config.

Note the two-level signature: the outer function receives `(config, { strapi })` and returns the actual Koa middleware `async (ctx, next) => {}`. This allows Strapi to pass per-route configuration to the function.

:::note
- `middlewares` exports middleware functions from the plugin so they can be referenced and reused in route config.
- `strapi.server.use(...)` attaches a middleware to the global server pipeline.
- Middleware execution is request-based: once attached to a route or to the server pipeline, it runs for each matching request.
:::

</Tabs>

Reference a route-level middleware in a route using the same `plugin::my-plugin.middleware-name` namespace as policies:

</Tabs>

### Server-level middlewares

A server-level middleware is registered on the Strapi HTTP server directly and runs for every request, not just plugin routes. Register it in `register()` using `strapi.server.use()`:

</Tabs>

:::caution
Server-level middlewares affect all routes across all plugins and the application itself, not just your plugin's routes. A server-level middleware that throws or never calls `next()` will break every request on the server, not just your plugin's endpoints. Use route-level middlewares when the concern is specific to your plugin's endpoints.
:::

:::note Version/runtime behavior
For route declarations, validation accepts object entries shaped as `{ name, options }` for both `policies` and `middlewares` (see `services/server/routing.ts`).

At runtime, some internals still reference `{ resolve, config }` support in the middleware resolver (`services/server/middleware.ts`), but that shape is not accepted by route validation in standard route files.

To avoid validation errors, use `{ name, options }` in route configurations.
:::

:::strapi Backend customization
For the full middleware reference, see [Middlewares](/cms/backend-customization/middlewares).
:::

## Best practices

- **Use `policyContext`, not `ctx`, in policies.** The first argument to a policy is `policyContext`, a wrapper around the Koa context. Using it correctly ensures the policy works for both REST and GraphQL resolvers.

- **Return explicitly from policies.** A policy that returns `undefined` is treated as permissive (allowed). Always return `true` to allow or `false` to deny. Never return implicitly if the intent is to block the request.

- **Prefer route-level middlewares over server-level.** Server-level middlewares run on every request in the entire Strapi server. Scope middleware to plugin routes unless the behavior genuinely applies to all traffic.

- **Always call `await next()` in middlewares.** Forgetting `next()` means the request chain is interrupted and the controller never executes, resulting in a hanging request with no response.

- **Use `options` for reusable policies.** When the same policy logic needs different parameters per route (e.g., a required role name), pass them from the route's `{ name, options }` object. These values are received in the policy function's `config` argument. This avoids duplicating similar policies.




---


# Server routes
Source: https://docs.strapi.io/cms/plugins-development/server-routes

# Server API: Routes

Routes expose your plugin's HTTP endpoints and map incoming requests to controller actions. They are exported from the [server entry file](/cms/plugins-development/server-api#entry-file) as a `routes` value.

</Tabs>

### Named router format

With the named router format, use an object with named keys (`admin`, `content-api`, or any custom name) to declare separate router groups. Each group is a router object with a `type`, optional `prefix`, and a `routes` array. Use this format when your plugin exposes both admin and Content API routes.

</Tabs>

### Factory callback format

For advanced cases where you need access to the `strapi` instance at route configuration time (for example, to build dynamic paths or conditionally include routes based on configuration), you can export a factory callback.

:::note
The factory callback must be attached to a named route entry (such as `admin` or `content-api`), not exported as the root of `routes/index`.

`module.exports = ({ strapi }) => ({ ... })` at the root level is not a valid format.
:::

</Tabs>

For details on what Strapi adds automatically at registration time, see [Defaults applied by Strapi](#defaults-applied-by-strapi).

## Defaults applied by Strapi

When Strapi registers plugin routes, it applies the following defaults automatically:

| Property | Default value | Notes |
| --- | --- | --- |
| `type` | `'admin'` | Applied when using the array format, or when `type` is omitted from a router object in the named format |
| `prefix` | `'/<plugin-name>'` | Applied when using the array format, or when `prefix` is omitted from a router object |
| `config.auth.scope` | `['plugin::<plugin-name>.<handler>']` | Auto-generated for string handlers only, using `defaultsDeep` so existing values are not overwritten |

The following 2 declarations are equivalent. Strapi applies the defaults from the table above automatically:

</Tabs>

## Route configuration reference

Each route accepts an optional `config` object with the following properties:

### `policies`

**Type:** `Array<string | PolicyHandler | { name: string; options?: object }>`

Policies to run before the controller action. Each item is either a policy name string, an inline function, or an object with required `name` and optional `options`.

The `options` object is passed as-is to the policy function's second argument (`config` in policy signatures). The shape of this object depends on the policy.

Plugin policies are referenced as `plugin::my-plugin.policy-name`.

### `middlewares`

**Type:** `Array<string | MiddlewareHandler | { name: string; options?: object }>`

Middlewares to apply to this route. Each item is a middleware name string, an inline function, or an object with:

- `name`: a registered middleware name,
- `options` (optional): middleware options.

:::note Route middlewares vs. global server middlewares
At route validation time, Strapi validates middleware/policy objects against `{ name: string; options?: object }` (see `services/server/routing.ts`).

The middleware resolver (`services/server/middleware.ts`) still contains runtime support for `{ resolve, config }` objects, but this shape is rejected by route validation before resolution for standard plugin route declarations.

Use `{ name, options }` in route configs for compatibility with validation.
:::

### `auth`

**Type:** `false | { scope: string[]; strategies?: string[] }`

Set to `false` to make the route public. Pass an object to define the auth scope and, optionally, custom auth strategies.

At runtime, `scope` must be present when `auth` is an object.

:::note
For **string handlers** (for example, `handler: 'article.find'`), Strapi auto-injects a default `config.auth.scope` value, so patterns such as `auth: {}` can still work.

For **non-string handlers** (inline functions), do not assume auto-scope injection. Define `config.auth.scope` explicitly when `auth` is an object.
:::

:::caution
Setting `auth: false` on an admin route is almost never intentional: it exposes the endpoint to unauthenticated requests.
:::

:::strapi General backend customization examples
For configuration examples including policies, public routes, dynamic URL parameters, and regular expressions in paths, see [Routes](/cms/backend-customization/routes).
:::

## Best practices

- **Use the named router format when exposing both admin and Content API endpoints.** It makes the intent of each route explicit and avoids relying on the `type` default, which can be surprising.

- **Keep `handler` as a string.** String handlers get automatic auth scope generation, function handlers do not. Authentication still runs for both string and function handlers unless you set `config.auth: false`, but only string handlers get automatic `config.auth.scope`. If you use a function handler and need route-level permission scoping, define `config.auth.scope` explicitly.

- **Scope policies to their namespace.** When referencing a plugin policy in a route, use the full `plugin::my-plugin.policy-name` form. This avoids ambiguity if a policy with the same short name exists elsewhere in the application.

- **Do not disable auth on admin routes.** Admin routes default to requiring admin authentication. Disabling auth on an admin route exposes it to unauthenticated requests, which is almost never intentional.

- **Group related routes in dedicated files.** As the plugin grows, a single route index file becomes hard to navigate. Split by resource (e.g., `routes/article.js`, `routes/comment.js`) and re-export from `routes/index.js`.




---


# Documentation plugin
Source: https://docs.strapi.io/cms/plugins/documentation

# Documentation plugin

The Documentation plugin automates your API documentation creation. It basically generates a swagger file. It follows the 

</IdentityCard>

:::caution Unmaintained plugin
The Documentation plugin is not actively maintained and may not work with Strapi 5.
:::

</Tabs>

Once the plugin is installed, starting Strapi generates the API documentation.

## Configuration

Most configuration options for the Documentation plugin are handled via your Strapi project's code. A few settings are available in the admin panel.

### Admin panel settings

The Documentation plugin affects multiple parts of the admin panel. The following table lists all the additional options and settings that are added to a Strapi application once the plugin has been installed:

| Section impacted    | Options and settings         |
|------------------|-------------------------------------------------------------|
| Documentation    | <ul>Addition of a new Documentation option in the main navigation  which shows a panel with buttons to  open and  regenerate the documentation.</ul>        |
| Settings     | <ul><li>Addition of a "Documentation plugin" setting section, which controls whether the documentation endpoint is private or not (see [restricting access](#restrict-access)).<br/> 👉 Path reminder:  *Settings > Documentation plugin* </li><br/>  <li> Activation of role based access control for accessing, updating, deleting, and regenerating the documentation. Administrators can authorize different access levels to different types of users in the *Plugins* tab and the *Settings* tab (see [Users & Permissions documentation](/cms/features/users-permissions)).<br/>👉 Path reminder:  *Settings > Administration Panel > Roles* </li></ul>| 

#### Restricting access to your API documentation {#restrict-access}

By default, your API documentation will be accessible by anyone.

To restrict API documentation access, enable the **Restricted Access** option from the admin panel:

1. Navigate to  *Settings* in the main navigation of the admin panel.
2. Choose **Documentation**.
3. Toggle **Restricted Access** to `ON`.
4. Define a password in the `password` input.
5. Save the settings.

### Code-based configuration

To configure the Documentation plugin, create a `settings.json` file in the `src/extensions/documentation/config` folder. In this file, you can specify all your environment variables, licenses, external documentation links, and all the entries listed in the . 

The following is an example configuration:

```json title="src/extensions/documentation/config/settings.json"
{
  "openapi": "3.0.0",
  "info": {
    "version": "1.0.0",
    "title": "DOCUMENTATION",
    "description": "",
    "termsOfService": "YOUR_TERMS_OF_SERVICE_URL",
    "contact": {
      "name": "TEAM",
      "email": "contact-email@something.io",
      "url": "mywebsite.io"
    },
    "license": {
      "name": "Apache 2.0",
      "url": "https://www.apache.org/licenses/LICENSE-2.0.html"
    }
  },
  "x-strapi-config": {
    "plugins": ["upload", "users-permissions"],
    "path": "/documentation"
  },
  "servers": [
    {
      "url": "http://localhost:1337/api",
      "description": "Development server"
    }
  ],
  "externalDocs": {
    "description": "Find out more",
    "url": "https://docs.strapi.io/developer-docs/latest/getting-started/introduction.html"
  },
  "security": [
    {
      "bearerAuth": []
    }
  ]
}
```

:::tip
If you need to add a custom key, prefix it by `x-` (e.g., `x-strapi-something`).
:::

#### Creating a new version of the documentation {#create-a-new-version-of-the-documentation}

To create a new version, change the `info.version` key in the `settings.json` file:

```json title="src/extensions/documentation/config/settings.json"
{
  "info": {
    "version": "2.0.0"
  }
}
```

This will automatically create a new version.

#### Defining which plugins need documentation generated {#define-which-plugins}

If you want plugins to be included in documentation generation, they should be included in the `plugins` array in the `x-strapi-config` object. By default, the array is initialized with `["upload", "users-permissions"]`:

```json title="src/extensions/documentation/config/settings.json"
{
  "x-strapi-config": {
    "plugins": ["upload", "users-permissions"]
  }
}
```

To add more plugins, such as your custom plugins, add their name to the array.

If you do not want plugins to be included in documentation generation, provide an empty array (i.e., `plugins: []`).

#### Overriding the generated documentation

The Documentation plugins comes with 3 methods to override the generated documentation: [`excludeFromGeneration`](#excluding-from-generation), [`registerOverride`](#register-override), and [`mutateDocumentation`](#mutate-documentation).

##### excludeFromGeneration() {#excluding-from-generation}

To exclude certain APIs or plugins from being generated, use the `excludeFromGeneration` found on the documentation plugin’s `override` service in your application or plugin's [`register` lifecycle](/cms/plugins-development/admin-panel-api#register).

:::note
`excludeFromGeneration` gives more fine-grained control over what is generated.

For example, pluginA might create several new APIs while pluginB may only want to generate documentation for some of those APIs. In that case, pluginB could still benefit from the generated documentation it does need by excluding only what it does not need.
:::

*****

| Parameter | Type                       | Description                                              |
| --------- | -------------------------- | -------------------------------------------------------- |
| `api`       | String or Array of Strings | The name of the API/plugin, or list of names, to exclude |

```js title="Application or plugin register lifecycle"

module.exports = {
  register({ strapi }) {
    strapi
      .plugin("documentation")
      .service("override")
      .excludeFromGeneration("restaurant");
    // or several
    strapi
      .plugin("documentation")
      .service("override")
      .excludeFromGeneration(["address", "upload"]);
  }
}
```

##### registerOverride() {#register-override}

If the Documentation plugin fails to generate what you expect, it is possible to replace what has been generated.

The Documentation plugin exposes an API that allows you to replace what was generated for the following OpenAPI root level keys: `paths`, `tags`, `components` .

To provide an override, use the `registerOverride` function found on the Documentation plugin’s `override` service in your application or plugin's [`register` lifecycle](/cms/plugins-development/admin-panel-api#register).

| Parameter                     | Type                      | Description                                                                                                   |
| ----------------------------- | ------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `override`                     | Object                    | OpenAPI object including any of the following keys paths, tags, components. Accepts JavaScript, JSON, or yaml |
| `options`                      | Object                    | Accepts `pluginOrigin` and `excludeFromGeneration`                                                               |
| `options.pluginOrigin`          | String                    | The plugin that is registering the override                                                                   |
| `options.excludeFromGeneration` | String or Array of String | The name of the API/plugin, or list of names, to exclude                                                      |

:::caution
Plugin developers providing an override should always specify the `pluginOrigin` options key. Otherwise the override will run regardless of the user’s configuration.
:::

The Documentation plugin will use the registered overrides to replace the value of common keys on the generated documentation with what the override provides. If no common keys are found, the plugin will add new keys to the generated documentation.

If the override completely replaces what the documentation generates, you can specify that generation is no longer necessary by providing the names of the APIs or plugins to exclude in the options key array `excludeFromGeneration`.

If the override should only be applied to a specific version, the override must include a value for `info.version`. Otherwise, the override will run on all documentation versions.

```js title="Application or plugin register lifecycle"

module.exports = {
  register({ strapi }) {
    if (strapi.plugin('documentation')) {
      const override = {
        // Only run this override for version 1.0.0
        info: { version: '1.0.0' },
        paths: {
          '/answer-to-everything': {
            get: {
              responses: { 200: { description: "*" }}
            }
          }
        }
      }

      strapi
        .plugin('documentation')
        .service('override')
        .registerOverride(override, {
          // Specify the origin in case the user does not want this plugin documented
          pluginOrigin: 'upload',
          // The override provides everything don't generate anything
          excludeFromGeneration: ['upload'],
        });
    }
  },
}
```

The overrides system is provided to try and simplify amending the generated documentation. It is the only way a plugin can add or modify the generated documentation.

##### mutateDocumentation() {#mutate-documentation}

The Documentation plugin’s configuration also accepts a `mutateDocumentation` function on `info['x-strapi-config']`. This function receives a draft state of the generated documentation that be can be mutated. It should only be applied from an application and has the final say in the OpenAPI schema.

| Parameter                   | Type   | Description                                                            |
| --------------------------- | ------ | ---------------------------------------------------------------------- |
| `generatedDocumentationDraft` | Object | The generated documentation with applied overrides as a mutable object |

```js title="config/plugins.js"

module.exports = {
  documentation: {
    config: {
      "x-strapi-config": {
        mutateDocumentation: (generatedDocumentationDraft) => {
          generatedDocumentationDraft.paths[
            "/answer-to-everything" // must be an existing path
          ].get.responses["200"].description = "*";
        },
      },
    },
  },
};
```

## Usage

The Documentation plugin visualizes your API using . To access the UI, select  in the main navigation of the admin panel. Then click **Open documentation** to open the Swagger UI. Using the Swagger UI you can view all of the endpoints available on your API and trigger API calls.

:::tip
Once the plugin is installed, the plugin user interface can be accessed at the following URL:
`<server-url>:<server-port>/documentation/<documentation-version>`
(e.g., ).
:::

### Regenerating documentation {#regenerate-documentation}

There are 2 ways to update the documentation after making changes to your API:

- restart your application to regenerate the version of the documentation specified in the Documentation plugin's configuration,
- or go to the Documentation plugin page and click the **regenerate** button for the documentation version you want to regenerate.

### Authenticating requests

Strapi is secured by default, which means that most of your endpoints require the user to be authorized. If the CRUD action has not been set to Public in the [Users & Permissions feature](/cms/features/users-permissions#roles) then you must provide your JSON web token (JWT). To do this, while viewing the API Documentation, click the **Authorize** button and paste your JWT in the _bearerAuth_ _value_ field.




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




---


# Installing Plugins via the Marketplace
Source: https://docs.strapi.io/cms/plugins/installing-plugins-via-marketplace

# Using the Marketplace

Strapi comes with built-in plugins such as [Documentation](/cms/plugins/documentation), [GraphQL](/cms/plugins/graphql), and [Sentry](/cms/plugins/sentry). The Marketplace is where users can find additional plugins to customize Strapi applications, and additional providers to extend plugins. The Marketplace is located in the admin panel, indicated by  _Marketplace_. In the Marketplace, users can browse or search for plugins and providers, link to detailed descriptions for each, and submit new plugins and providers.

:::note strapi In-app Marketplace vs. Market website
The Marketplace in the admin panel displays all existing plugins, regardless of the version of Strapi they are for. All plugins can also be discoverable through the  website.

Keep in mind however that v4 and v5 plugins are not cross-compatible, but that providers are compatible both with v4 and v5 plugins.
:::

The Plugins and Providers tabs display each plugin/provider on individual cards containing:

- their name, sometimes followed by either of the following badges:
  - <img alt="maintained by Strapi icon" src="/img/strapi-logo.png" width="14px" style={{position: "relative", bottom:"2px", marginRight:"2px"}} /> to indicate it is made by Strapi,
  -  to indicate it was verified by Strapi.
- the number of times the plugin/provider was starred on GitHub and downloaded
- the description
- a **More**  button to be redirected to the Market website for additional information, including about the version of Strapi the plugin is for, and implementation instructions

In the top right corner of the Marketplace, the **Submit plugin** button redirects to the Strapi Market where it is possible to submit your own plugin and provider.

:::tip Tips

- The search bar displays incremental search results based on the plugin/provider name and description.
- Use the "Sort by" button or set filters to find plugins more easily.

:::

## Installing Marketplace plugins and providers

To install a new plugin or provider via the Marketplace:

1. Go to the  *Marketplace*.
2. Choose the **Plugins** tab to browse available plugins or the **Providers** tab to browse available providers.
3. Choose an available plugin/provider and click on the **More**  button.
4. Once redirected to the Strapi Market website, follow the plugin/provider-specific implementation instructions.

:::strapi Developing Strapi plugins
Can't find a plugin that suits your use case? Feel free to [create your own](/cms/plugins-development/developing-plugins)!
:::




---


# Sentry plugin
Source: https://docs.strapi.io/cms/plugins/sentry

# Sentry plugin

This plugin enables you to track errors in your Strapi application using Sentry.

</IdentityCard>

By using the Sentry plugin you can:

* Initialize a Sentry instance upon startup of a Strapi application
* Send Strapi application errors as events to Sentry
* Include additional metadata in Sentry events to assist in debugging
* Expose a global Sentry service usable by the Strapi server

## Installation

Install the Sentry plugin by adding the dependency to your Strapi application as follows:

</Tabs>

## Configuration

Create or edit your `/config/plugins` file to configure the Sentry plugin. The following properties are available:

| Property | Type | Default Value | Description |
| -------- | ---- | ------------- |------------ |
| `dsn` | string | `null` | Your Sentry 

</Tabs>

### Disabling for non-production environments

If the `dsn` property is set to a nil value (`null` or `undefined`) while `sentry.enabled` is true, the Sentry plugin will be available to use in the running Strapi instance, but the service will not actually send errors to Sentry. That allows you to write code that runs on every environment without additional checks, but only send errors to Sentry in production.

When you start Strapi with a nil `dsn` config property, the plugin will print the following warning:<br/>`info: @strapi/plugin-sentry is disabled because no Sentry DSN was provided`

You can make use of that by using the [`env` utility](/cms/configurations/guides/access-cast-environment-variables) to set the `dsn` configuration property depending on the environment.

</Tabs>

### Disabling the plugin completely

Like every other Strapi plugin, you can also disable this plugin in the plugins configuration file. This will cause `strapi.plugins('sentry')` to return `undefined`:

</Tabs>

## Usage

After installing and configuring the plugin, you can access a Sentry service in your Strapi application as follows:

```js
const sentryService = strapi.plugin('sentry').service('sentry');
```

This service exposes the following methods:

| Method | Description | Parameters |
| ------ | ----------- | ---------- |
| `sendError()` | Manually send errors to Sentry. | <ul><li><code>error</code>: The error to be sent.</li><li><code>configureScope</code>: Optional. Enables you to customize the error event.</li></ul> See the official  for more details. |
| `getInstance()` | Used for direct access to the Sentry instance. | - |

The `sendError()` method can be used as follows:

```js
try {
  // Your code here
} catch (error) {
  // Either send a simple error
  strapi
    .plugin('sentry')
    .service('sentry')
    .sendError(error);

  // Or send an error with a customized Sentry scope
  strapi
    .plugin('sentry')
    .service('sentry')
    .sendError(error, (scope, sentryInstance) => {
      // Customize the scope here
      scope.setTag('my_custom_tag', 'Tag value');
    });
  throw error;
}
```

The `getInstance()` method is accessible as follows:

```js
const sentryInstance = strapi
  .plugin('sentry')
  .service('sentry')
  .getInstance();
```


