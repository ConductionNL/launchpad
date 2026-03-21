
# API calls configuration
Source: https://docs.strapi.io/cms/configurations/api

# API configuration

General settings for API calls can be set in the `./config/api.js` (or `./config/api.ts`) file. Both `rest` and `documents` options live in this single config file.

| Property                      | Description                                                                                                                                                                                                                                          | Type         | Default |
| ----------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------ | ------- |
| `responses`                   | Global API response configuration                                                                                                                                                                                                                    | Object       | -       |
| `responses.privateAttributes` | Set of globally defined attributes to be treated as private.                                                                                                                                                                                         | String array | `[]`    |
| `rest`                        | REST API configuration                                                                                                                                                                                                                               | Object       | -       |
| `rest.prefix`                 | The API prefix                       | String      | `/api`   |
| `rest.defaultLimit`           | Default `limit` parameter used in API calls (see [REST API documentation](/cms/api/rest/sort-pagination#pagination-by-offset))                                                                      | Integer      | `25`    |
| `rest.maxLimit`               | Maximum allowed number that can be requested as `limit` (see [REST API documentation](/cms/api/rest/sort-pagination#pagination-by-offset)). | Integer      | `100`   |
| `rest.strictParams`           | When `true`, only allowed query and body parameters are accepted on Content API routes; unknown top-level keys are rejected. Add allowed parameters via [Custom Content API parameters](/cms/backend-customization/routes#custom-content-api-parameters) in `register`. | Boolean      | -       |
| `documents`                   | Document Service configuration                                                                                                                                                                                                                        | Object       | -       |
| `documents.strictParams`      | When `true`, Document Service methods reject parameters with unrecognized root-level keys (e.g., invalid `status`, `locale`). When `false` or unset, unknown parameters are ignored. See [Document Service API](/cms/api/document-service#configuration). | Boolean      | -       |

:::note 
If the `rest.maxLimit` value is less than the `rest.defaultLimit` value, `maxLimit` will be the limit used.
:::

:::tip
`rest.strictParams` applies to incoming REST Content API requests (query and body). `documents.strictParams` applies to parameters passed to `strapi.documents()` in server-side code. You can enable one or both in the same config file.
:::

**Example:**

</Tabs>




---


# CRON jobs
Source: https://docs.strapi.io/cms/configurations/cron

# Cron jobs

:::prerequisites
The `cron.enabled` configuration option should be set to `true` in the `./config/server.js` (or `./config/server.ts` for TypeScript projects) [file](/cms/configurations/server).
:::

`cron` allows scheduling arbitrary functions for execution at specific dates, with optional recurrence rules. These functions are named cron jobs. `cron` only uses a single timer at any given time, rather than reevaluating upcoming jobs every second/minute.

This feature is powered by the 

</Tabs>

<details>
<summary>Advanced example #1: Timezones</summary>

The following cron job runs on a specific timezone:

</Tabs>

</details>

<details>
<summary>Advanced example #2: One-off cron jobs</summary>
The following cron job is run only once at a given time:

</Tabs>

</details>

<details>
<summary>Advanced example #3: Start and end times</summary>

The following cron job uses start and end times:

</Tabs>

</details>

### Using the key format

:::warning
Using the key format creates an anonymous cron job which may cause issues when trying to disable the cron job or with some plugins. It is recommended to use the object format.
:::

To define a cron job with the key format, create a file with the following structure:

</Tabs>

## Enabling cron jobs

To enable cron jobs, set `cron.enabled` to `true` in the [server configuration file](/cms/configurations/server) and declare the jobs:

</Tabs>

## Adding or removing cron jobs

Use `strapi.cron.add` anywhere in your custom code add CRON jobs to the Strapi instance:

```js title="./src/plugins/my-plugin/strapi-server.js"
module.exports = () => ({
  bootstrap({ strapi }) {
    strapi.cron.add({
      // runs every second
      myJob: {
        task: ({ strapi }) => {
          console.log("hello from plugin");
        },
        options: {
          rule: "* * * * * *",
        },
      },
    });
  },
});
```

Use `strapi.cron.remove` anywhere in your custom code to remove CRON jobs from the Strapi instance, passing in the key corresponding to the CRON job you want to remove:

```js
strapi.cron.remove("myJob");
```

:::note
Cron jobs that are using the [key as the rule](/cms/configurations/cron#using-the-key-format) can not be removed.
:::

## Listing cron jobs

Use `strapi.cron.jobs` anywhere in your custom code to list all the cron jobs that are currently running:

```js
strapi.cron.jobs
```




---


# Features configuration
Source: https://docs.strapi.io/cms/configurations/features

# Features configuration

The `config/features.js|ts` file is used to enable feature flags. Currently this file only includes a `future` object used to enable experimental features through **future flags**.

Some incoming Strapi features are not yet ready to be shipped to all users, but Strapi still offers community users the opportunity to provide early feedback on these new features or changes. With these experimental features, developers have the flexibility to choose and integrate new features and changes into their Strapi applications as they become available in the current major version as well as assist us in shaping these new features.

Such experimental features are indicated by a 

  </Tabs>

4. Rebuild the admin panel and restart the server:

  </Tabs>

## Future flags API

Developers can use the following APIs to interact with future flags:

- Features configuration is part of the `config` object and can be read with `strapi.config.get('features')` or with `strapi.features.config`.

- `strapi.features.future` returns the `isEnabled()` that can be used to determine if a future flag is enabled, using the following method: `strapi.features.future.isEnabled('featureName')`.

## Available future flags

| Property name | Related feature | Suggested environment variable name |
| ------------- | --------------- | ---------------------------------- |
| `experimental_firstPublishedAt` | [Draft & Publish](/cms/features/draft-and-publish#recording-the-first-publication-date) | `STRAPI_FUTURE_EXPERIMENTAL_FIRST_PUBLISHED_AT` |




---


# Lifecycle functions
Source: https://docs.strapi.io/cms/configurations/functions

# Functions

<div className="dont_hide_secondary_bar">

The `./src/index.js` file (or `./src/index.ts` file in a [TypeScript-based](/cms/typescript) project) includes global [register](#register), [bootstrap](#bootstrap) and [destroy](#destroy) functions that can be used to add dynamic and logic-based configurations.

The functions can be synchronous, asynchronous, or return a promise.

</Tabs>

### Asynchronous

Asynchronous functions use the `async` keyword to `await` tasks such as API calls or database queries before Strapi continues.

</Tabs>

### Returning a promise

Promise-returning functions hand back a promise so Strapi can wait for its resolution before continuing.

</Tabs>

## Lifecycle functions

Lifecycle functions let you place code at specific phases of Strapi's startup and shutdown.

- The `register()` function is for configuration-time setup before services start.
- The `bootstrap()` function is for initialization that needs Strapi's APIs.
- The `destroy()` function is for teardown when the application stops.

### Register

The `register` lifecycle function, found in `./src/index.js` (or in `./src/index.ts`), is an asynchronous function that runs before the application is initialized.

`register()` is the very first thing that happens when a Strapi application is starting. This happens _before_ any setup process and you don't have any access to database, routes, policies, or any other backend server elements within the `register()` function.

The `register()` function can be used to:

- [extend plugins](/cms/plugins-development/plugins-extension#extending-a-plugins-interface)
- extend [content-types](/cms/backend-customization/models) programmatically
- load some [environment variables](/cms/configurations/environment)
- register a [custom field](/cms/features/custom-fields) that would be used only by the current Strapi application,
- register a [custom provider for the Users & Permissions plugin](/cms/configurations/users-and-permissions-providers/new-provider-guide).

More specifically, typical use-cases for `register()` include front-load security tasks such as loading secrets, rotating API keys, or registering authentication providers before the app finishes initializing.

</Tabs>

### Bootstrap

The `bootstrap` lifecycle function, found in `./src/index.js` (or in `./src/index.ts`), is called at every server start.

`bootstrap()` is run _before_ the back-end server starts but _after_ the Strapi application has setup, so you have access to anything from the `strapi` object.

The `bootstrap` function can be used to:

- create an admin user if there isn't one
- fill the database with some necessary data
- declare custom conditions for the [Role-Based Access Control (RBAC)](/cms/configurations/guides/rbac) feature

More specifically, a typical use-case for `bootstrap()` is supporting editorial workflows. For example by seeding starter content, attaching webhooks, or scheduling cron jobs at startup.

:::tip
You can run `yarn strapi console` (or `npm run strapi console`) in the terminal and interact with the `strapi` object.
:::

</Tabs>

### Destroy

The `destroy` function, found in `./src/index.js` (or in `./src/index.ts`), is an asynchronous function that runs before the application gets shut down.

The `destroy` function can be used to gracefully:

- stop [services](/cms/backend-customization/services) that are running
- [clean up plugin actions](/cms/plugins-development/server-lifecycle#destroy) (e.g. close connections, remove listeners, etc.)

More specifically, a typical use-case for `destroy()` is to handle operational clean-up, such as closing database or queue connections and removing listeners so the application can shut down cleanly.

</Tabs>

## Usage

<br/>

### Combined usage

All 3 lifecycle functions can be put together to configure custom behavior during application startup and shutdown.

1. Decide when your logic should run.
   - Add initialization-only tasks (e.g. registering a custom field or provider) in `register()`.
   - Add startup tasks that need full Strapi access (e.g. seeding or attaching webhooks) in `bootstrap()`.
   - Add cleanup logic (e.g. closing external connections) in `destroy()`.
2. Place the code in `src/index.js|ts`. Keep `register()` lean because it runs before Strapi is fully set up.
3. Restart Strapi to confirm each lifecycle executes in sequence.

```ts title="src/index.ts"
let cronJobKey: string | undefined;

  register({ strapi }) {
    strapi.customFields.register({
      name: 'color',
      type: 'string',
      plugin: 'color-picker',
    });
  },

  async bootstrap({ strapi }) {
    cronJobKey = 'log-reminders';

    strapi.cron.add({
      [cronJobKey]: {
        rule: '0 */6 * * *', // every 6 hours
        job: async () => {
          strapi.log.info('Remember to review new content in the admin panel.');
        },
      },
    });
  },

  async destroy({ strapi }) {
    if (cronJobKey) {
      strapi.cron.remove(cronJobKey);
    }
  },
};
```

:::strapi Additional information
You might find additional information in  about registering lifecycle functions.
:::




---


# Middlewares configuration
Source: https://docs.strapi.io/cms/configurations/middlewares

# Middlewares configuration

</Tabs>

:::tip
If you aren't sure where to place a middleware in the stack, add it to the end of the list.
:::

## Naming conventions

Global middlewares can be classified into different types depending on their origin, which defines the following naming conventions:

| Middleware type   | Origin                                                                                                                                                                                                                                  | Naming convention                                                                                                    |
|-------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------|
| Internal          | Built-in middlewares (i.e. included with Strapi), automatically loaded                                                                                                                                                                  | `strapi::middleware-name`                                                                                            |
| Application-level | Loaded from the `./src/middlewares` folder                                                                                                                                                                                              | `global::middleware-name`                                                                                            |
| API-level         | Loaded from the `./src/api/[api-name]/middlewares` folder                                                                                                                                                                               | `api::api-name.middleware-name`                                                                                      |
| Plugin            | Exported from `strapi-server.js` in the [`middlewares` property of the plugin interface](/cms/plugins-development/server-policies-middlewares)                                                                                               | `plugin::plugin-name.middleware-name`                                                                                |
| External          | Can be:<ul><li>either node modules installed with 

</Tabs>

</details>

### `compression`

The `compression` middleware is based on 

</Tabs>

</details>

### `cors`

This security middleware is about cross-origin resource sharing (CORS) and is based on 

</Tabs>

</details>

<details>
<summary> Example: Custom configuration for the cors middleware within a function as parameter</summary>

`origin` can take a Function as parameter following this signature 

```ts title="./config/middlewares.ts"

  // ...
  {
    name: 'strapi::cors',
    config: {
      origin: (ctx): string | string[] => {
        const origin = ctx.request.header.origin;
        if (origin === 'http://localhost:3000') {
          return origin; // The returns will be part of the Access-Control-Allow-Origin header
        }
        
        return ''; // Fail cors check
      }
    },
  },
  // ...
]
```

</details>

### `errors`

The errors middleware handles [errors](/cms/error-handling.md) thrown by the code. Based on the type of error it sets the appropriate HTTP status to the response. By default, any error not supposed to be exposed to the end user will result in a 500 HTTP response.

The middleware doesn't have any configuration options.

### `favicon`

The `favicon` middleware serves the favicon and is based on 

</Tabs>

</details>

### `ip`

The `ip` middleware is an IP filter middleware based on 

</Tabs>

</details>

### `logger`

The `logger` middleware is used to log requests.

To define a custom configuration for the `logger` middleware, create a dedicated configuration file (`./config/logger.js`). It should export an object that must be a complete or partial 

</Tabs>

</details>

### `poweredBy`

The `poweredBy` middleware adds a `X-Powered-By` parameter to the response header. It accepts the following options:

| Option      | Description                        | Type     | Default value          |
|-------------|------------------------------------|----------|------------------------|
| `poweredBy` | Value of the `X-Powered-By` header | `String` | `'Strapi <strapi.io>'` |

<details>
<summary> details Example: Custom configuration for the poweredBy middleware</summary>

</Tabs>

</details>

### `query`

The `query` middleware is a query parser based on 

</Tabs>

</details>

### `response-time`

The `response-time` middleware enables the `X-Response-Time` (in milliseconds) for the response header.

The middleware doesn't have any configuration options.

### `public`

The `public` middleware is a static file serving middleware, based on 

</Tabs>

</details>

### `security`

The security middleware is based on 

</Tabs>

</details>

### `session`

The `session` middleware allows the use of cookie-based sessions, based on 

</Tabs>

</details>




---


# Plugins configuration
Source: https://docs.strapi.io/cms/configurations/plugins

# Plugins configuration

Plugin configurations are stored in `/config/plugins.js|ts` (see [project structure](/cms/project-structure)). Each plugin can be configured with the following available parameters:

| Parameter                  | Description                                                                                                                                                            | Type    |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- |
| `enabled`                  | Enable (`true`) or disable (`false`) an installed plugin                                                                                                               | Boolean |
| `config`<br/><br/>_Optional_ | Used to override default plugin configuration ([defined in strapi-server.js](/cms/plugins-development/server-configuration)) | Object  |
| `resolve`<br/> _Optional, only required for local plugins_             | Path to the plugin's folder                                                                                                                                            | String  |

:::note Configurations for core features and providers
* Some core features of Strapi have historically been implemented as core plugins. This explains that their configuration is still defined in the `/config/plugins` file despite not technically being plugins in Strapi 5 anymore. This includes:
  - the [Upload configuration](/cms/features/media-library#available-options) for the package which powers the Media Library, 
  - and the [Users & Permissions configuration](/cms/features/users-permissions#code-based-configuration).

  The detailed [GraphQL plugin configuration](/cms/plugins/graphql#code-based-configuration) is also documented in its dedicated plugin page.

* Additionally, providers configuration for the Media Library and the Email features are also defined in `/config/plugins`. Their configurations are detailed in the [Upload providers configuration](/cms/features/media-library#code-based-configuration) and the [Email providers configuration](/cms/features/email#providers).
:::

**Basic example custom configuration for plugins:**

</Tabs>

:::tip
If no specific configuration is required, a plugin can also be declared with the shorthand syntax `'plugin-name': true`.
:::




---


# TypeScript configuration
Source: https://docs.strapi.io/cms/configurations/typescript

# TypeScript configuration

[TypeScript](/cms/typescript)-enabled Strapi projects have a specific project structure and handle TypeScript project configuration through [`tsconfig.json` files](#project-structure-and-typescript-specific-configuration-files).

Strapi also has dedicated TypeScript features that are configured [in the `config/typescript.js|ts` file](#strapi-specific-configuration-for-typescript).

## Project structure and TypeScript-specific configuration files

TypeScript-enabled Strapi applications have a specific [project structure](/cms/project-structure) with the following dedicated folders and configuration files:

| TypeScript-specific directories and files | Location         | Purpose                                                                                                                                          |
| ----------------------------------------- | ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `./dist` directory                        | application root | Adds the location for compiling the project JavaScript source code.                                                                              |
| `build` directory                         | `./dist`         | Contains the compiled administration panel JavaScript source code. The directory is created on the first `yarn build` or `npm run build` command |
| `tsconfig.json` file                      | application root | Manages TypeScript compilation for the server.                                                                                                   |
| `tsconfig.json` file                      | `./src/admin/`   | Manages TypeScript compilation for the admin panel.                                                                                              |

## Strapi-specific configuration for TypeScript

:::caution 🚧 This feature is considered experimental.
These settings are considered experimental and might have issues or break some features.
:::

Types generated by Strapi are based on the user project structure. Once the type definitions are emitted into their dedicated files, Strapi reads the type definitions to adapt the autocompletion results accordingly.

To avoid having to [manually generate types](/cms/typescript/development#generate-typings-for-content-types-schemas) every time the server restarts, an optional `config/typescript.js|ts` configuration file can be added, which currently accepts only one parameter:

| Parameter      | Description                                                    | Type      | Default |
| -------------- | -------------------------------------------------------------- | --------- | ------- |
| `autogenerate` | Enable or disable automatic types generation on server restart | `Boolean` | `false` |

**Example:**

</Tabs>


