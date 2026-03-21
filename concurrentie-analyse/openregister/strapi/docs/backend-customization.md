
# Controllers
Source: https://docs.strapi.io/cms/backend-customization/controllers

# Controllers

Controllers are JavaScript files that contain a set of methods, called actions, reached by the client according to the requested [route](/cms/backend-customization/routes). Whenever a client requests the route, the action performs the business logic code and sends back the [response](/cms/backend-customization/requests-responses). Controllers represent the C in the model-view-controller (MVC) pattern.

In most cases, the controllers will contain the bulk of a project's business logic. But as a controller's logic becomes more and more complicated, it's a good practice to use [services](/cms/backend-customization/services) to organize the code into re-usable parts.

<figure style={{width: '100%', margin: '0'}}>
  <img src="/img/assets/backend-customization/diagram-controllers-services.png" alt="Simplified Strapi backend diagram with controllers highlighted" />
  <em><figcaption style={{fontSize: '12px'}}>The diagram represents a simplified version of how a request travels through the Strapi back end, with controllers highlighted. The backend customization introduction page includes a complete, <a href="/cms/backend-customization#interactive-diagram">interactive diagram</a>.</figcaption></em>
</figure>

:::caution Sanitize inputs and outputs
When overriding core actions, always validate and sanitize queries and responses to avoid leaking private fields or bypassing access rules. Use `validateQuery` (optional), `sanitizeQuery` (recommended), and `sanitizeOutput` before returning data from custom actions. See the example below for a safe `find` override.
:::

## Implementation

Controllers can be [generated or added manually](#adding-a-new-controller). Strapi provides a `createCoreController` factory function that automatically generates core controllers and allows building custom ones or [extend or replace the generated controllers](#extending-core-controllers).

### Adding a new controller

A new controller can be implemented:

- with the [interactive CLI command `strapi generate`](/cms/cli)
- or manually by creating a JavaScript file:
  - in `./src/api/[api-name]/controllers/` for API controllers (this location matters as controllers are auto-loaded by Strapi from there)
  - or in a folder like `./src/plugins/[plugin-name]/server/controllers/` for plugin controllers, though they can be created elsewhere as long as the plugin interface is properly exported in the `strapi-server.js` file (see [Server API for Plugins documentation](/cms/plugins-development/server-api))

</Tabs>

Each controller action can be an `async` or `sync` function.
Every action receives a context object (`ctx`) as a parameter. `ctx` contains the [request context](/cms/backend-customization/requests-responses#ctxrequest) and the [response context](/cms/backend-customization/requests-responses#ctxresponse).

<details>
<summary>Example: GET /hello route calling a basic controller</summary>

A specific `GET /hello` [route](/cms/backend-customization/routes) is defined, the name of the router file (i.e. `index`) is used to call the controller handler (i.e. `index`). Every time a `GET /hello` request is sent to the server, Strapi calls the `index` action in the `hello.js` controller, which returns `Hello World!`:

</Tabs>

</details>

:::note 
When a new [content-type](/cms/backend-customization/models#content-types) is created, Strapi builds a generic controller with placeholder code, ready to be customized.
:::

:::tip 
To see a possible advanced usage for custom controllers, read the [services and controllers](/cms/backend-customization/examples/services-and-controllers) page of the backend customization examples cookbook.
:::

### Controllers & Routes: How routes reach controller actions

- Core mapping is automatic: when you generate a content-type, Strapi creates the matching controller and a router file that already targets the standard actions (`find`, `findOne`, `create`, `update`, and `delete`). Overriding any of these actions inside the generated controller does not require touching the router — the route keeps the same handler string and executes your updated logic.
- Adding a route should only be done for new actions or paths. If you introduce a brand-new method such as `exampleAction`, create or update a route entry whose `handler` points to the action so HTTP requests can reach it. Use the fully-qualified handler syntax `<scope>::<api-or-plugin-name>.<controllerName>.<actionName>` (e.g. `api::restaurant.restaurant.exampleAction` for an API controller or `plugin::menus.menu.exampleAction` for a plugin controller).
- Regarding controller and route filenames: the default controller name comes from the filename inside `./src/api/[api-name]/controllers/`. Core routers created with `createCoreRouter` adopt the same name, so the generated handler string matches automatically. Custom routers can follow any file naming scheme, as long as the `handler` string references an exported controller action.

The example below adds a new controller action and exposes it through a custom route without duplicating the existing CRUD route definitions:

```js title="./src/api/restaurant/controllers/restaurant.js"
const { createCoreController } = require('@strapi/strapi').factories;

module.exports = createCoreController('api::restaurant.restaurant', ({ strapi }) => ({
  async exampleAction(ctx) {
    const specials = await strapi.service('api::restaurant.restaurant').find({ filters: { isSpecial: true } });
    return this.transformResponse(specials.results);
  },
}));
```

```js title="./src/api/restaurant/routes/01-custom-restaurant.js"
module.exports = {
  routes: [
    {
      method: 'GET',
      path: '/restaurants/specials',
      handler: 'api::restaurant.restaurant.exampleAction',
    },
  ],
};
```

### Sanitization and Validation in controllers  {#sanitization-and-validation-in-controllers}

:::warning 
It's strongly recommended you sanitize (v4.8.0+) and/or validate (v4.13.0+) your incoming request query utilizing the new `sanitizeQuery` and `validateQuery` functions to prevent the leaking of private data.
:::

Sanitization means that the object is “cleaned” and returned.

Validation means an assertion is made that the data is already clean and throws an error if something is found that shouldn't be there.

In Strapi 5, both query parameters and input data (i.e., create and update body data) are validated. Any create and update data requests with the following invalid input will throw a `400 Bad Request` error:

- relations the user do not have permission to create
- unrecognized values that are not present on a schema
- non-writable fields and internal timestamps like `createdAt` and `createdBy` fields
- setting or updating an `id` field (except for connecting relations)

#### Sanitization when utilizing controller factories

Within the Strapi factories the following functions are exposed that can be used for sanitization and validation:

| Function Name    | Parameters                 | Description                                                                          |
|------------------|----------------------------|--------------------------------------------------------------------------------------|
| `sanitizeQuery`  | `ctx`                      | Sanitizes the request query                                                          |
| `sanitizeOutput` | `entity`/`entities`, `ctx` | Sanitizes the output data where entity/entities should be an object or array of data |
| `sanitizeInput`  | `data`, `ctx`              | Sanitizes the input data                                                             |
| `validateQuery`  | `ctx`                      | Validates the request query (throws an error on invalid params)                      |
| `validateInput`  | `data`, `ctx`              | (EXPERIMENTAL) Validates the input data (throws an error on invalid data)                           |

These functions automatically inherit the sanitization settings from the model and sanitize the data accordingly based on the content-type schema and any of the content API authentication strategies, such as the Users & Permissions plugin or API tokens.

:::warning
Because these methods use the model associated with the current controller, if you query data that is from another model (i.e., doing a find for "menus" within a "restaurant" controller method), you must instead use the `strapi.contentAPI` methods, such as `strapi.contentAPI.sanitize.query` described in [Sanitizing Custom Controllers](#sanitize-validate-custom-controllers), or else the result of your query will be sanitized against the wrong model.
:::

</Tabs>

#### Sanitization and validation when building custom controllers  {#sanitize-validate-custom-controllers}

Within custom controllers, Strapi exposes the following functions via `strapi.contentAPI` for sanitization and validation. To add custom query or body parameters to Content API routes (e.g. in `register`), see [Custom Content API parameters](/cms/backend-customization/routes#custom-content-api-parameters).

| Function Name                | Parameters         | Description                                             |
|------------------------------|--------------------|---------------------------------------------------------|
| `strapi.contentAPI.sanitize.input`  | `data`, `schema`, `auth`      | Sanitizes the request input including non-writable fields, removing restricted relations, and other nested "visitors" added by plugins |
| `strapi.contentAPI.sanitize.output` | `data`, `schema`, `auth`      | Sanitizes the response output including restricted relations, private fields, passwords, and other nested "visitors" added by plugins  |
| `strapi.contentAPI.sanitize.query`  | `ctx.query`, `schema`, `auth` | Sanitizes the request query including filters, sort, fields, and populate  |
| `strapi.contentAPI.validate.query`  | `ctx.query`, `schema`, `auth` | Validates the request query including filters, sort, fields (currently not populate) |
| `strapi.contentAPI.validate.input`  | `data`, `schema`, `auth` | (EXPERIMENTAL) Validates the request input including non-writable fields, removing restricted relations, and other nested "visitors" added by plugins |

:::note 
Depending on the complexity of your custom controllers, you may need additional sanitization that Strapi cannot currently account for, especially when combining the data from multiple sources.
:::

</Tabs>

### Extending core controllers  {#extending-core-controllers}

Default controllers and actions are created for each content-type. These default controllers are used to return responses to API requests (e.g. when `GET /api/articles/3` is accessed, the `findOne` action of the default controller for the "Article" content-type is called). Default controllers can be customized to implement your own logic. The following code examples should help you get started.

:::tip 
An action from a core controller can be replaced entirely by [creating a custom action](#adding-a-new-controller) and naming the action the same as the original action (e.g. `find`, `findOne`, `create`, `update`, or `delete`).
:::

:::tip 
When extending a core controller, you do not need to re-implement any sanitization as it will already be handled by the core controller you are extending. Where possible it's strongly recommended to extend the core controller instead of creating a custom controller.
:::

<details>
<summary>Collection type examples</summary>

:::tip
The [backend customization examples cookbook](/cms/backend-customization/examples) shows how you can overwrite a default controller action, for instance for the [`create` action](/cms/backend-customization/examples/services-and-controllers#custom-controller).
:::

</Tabs>
</details>

<details>
<summary>Single type examples</summary>

</Tabs>
</details>

## Usage 

Controllers are declared and attached to a route. Controllers are automatically called when the route is called, so controllers usually do not need to be called explicitly. However, [services](/cms/backend-customization/services) can call controllers, and in this case the following syntax should be used:

```js
// access an API controller
strapi.controller('api::api-name.controller-name');
// access a plugin controller
strapi.controller('plugin::plugin-name.controller-name');
```

:::tip  
To list all the available controllers, run `yarn strapi controllers:list`.
:::




---


# Middlewares
Source: https://docs.strapi.io/cms/backend-customization/middlewares

# Middlewares customization

</Tabs>

Globally scoped custom middlewares should be added to the [middlewares configuration file](/cms/configurations/middlewares#loading-order) or Strapi won't load them.

API level and plugin middlewares can be added into the specific router that they are relevant to like the following:

```js title="./src/api/[api-name]/routes/[collection-name].js or ./src/plugins/[plugin-name]/server/routes/index.js"
module.exports = {
  routes: [
    {
      method: "GET",
      path: "/[collection-name]",
      handler: "[controller].find",
      config: {
        middlewares: ["[middleware-name]"],
        // See the usage section below for middleware naming conventions
      },
    },
  ],
};
```

<details>
<summary>Example of a custom timer middleware</summary>

</Tabs>

</details>

The GraphQL plugin also allows [implementing custom middlewares](/cms/plugins/graphql#middlewares), with a different syntax.

:::tip Discover loaded middlewares
Run `yarn strapi middlewares:list` to list all registered middlewares and double‑check naming when wiring them in routers.
:::

## Usage

Middlewares are called different ways depending on their scope:

- use `global::middleware-name` for application-level middlewares
- use `api::api-name.middleware-name` for API-level middlewares
- use `plugin::plugin-name.middleware-name` for plugin middlewares

:::tip
To list all the registered middlewares, run `yarn strapi middlewares:list`.
:::

### Restricting content access with an "is-owner policy"

It is often required that the author of an entry is the only user allowed to edit or delete the entry. In previous versions of Strapi, this was known as an "is-owner policy". With Strapi v4, the recommended way to achieve this behavior is to use a middleware.

Proper implementation largely depends on your project's needs and custom code, but the most basic implementation could be achieved with the following procedure: 

1. From your project's folder, create a middleware with the Strapi CLI generator, by running the `yarn strapi generate` (or `npm run strapi generate`) command in the terminal.
2. Select `middleware` from the list, using keyboard arrows, and press Enter.
3. Give the middleware a name, for instance `isOwner`.
4. Choose `Add middleware to an existing API` from the list.
5. Select which API you want the middleware to apply.
6. Replace the code in the `/src/api/[your-api-name]/middlewares/isOwner.js` file with the following, replacing `api::restaurant.restaurant` in line 22 with the identifier corresponding to the API you choose at step 5 (e.g., `api::blog-post.blog-post` if your API name is `blog-post`):

  ```js showLineNumbers title="src/api/blog-post/middlewares/isOwner.js"
    "use strict";

    /**
     * `isOwner` middleware
     */

    module.exports = (config, { strapi }) => {
      // Add your own logic here.
      return async (ctx, next) => {
        const user = ctx.state.user;
        const entryId = ctx.params.id ? ctx.params.id : undefined;
        let entry = {};

        /** 
         * Gets all information about a given entry,
         * populating every relations to ensure 
         * the response includes author-related information
         */
        if (entryId) {
          entry = await strapi.documents('api::restaurant.restaurant').findOne(
            entryId,
            { populate: "*" }
          );
        }

        /**
         * Compares user id and entry author id
         * to decide whether the request can be fulfilled
         * by going forward in the Strapi backend server
         */
        if (user.id !== entry.author.id) {
          return ctx.unauthorized("This action is unauthorized.");
        } else {
          return next();
        }
      };
    };
  ```

7. Ensure the middleware is configured to apply on some routes. In the `config` object found in the `src/api/[your-api–name]/routes/[your-content-type-name].js` file, define the action keys (`find`, `findOne`, `create`, `update`, `delete`, etc.) for which you would like the middleware to apply, and declare the `isOwner` middleware for these routes.<br /><br />For instance, if you wish to allow GET requests (mapping to the `find` and `findOne` actions) and POST requests (i.e., the `create` action) to any user for the `restaurant` content-type in the `restaurant` API, but would like to restrict PUT (i.e., `update` action) and DELETE requests only to the user who created the entry, you could use the following code in the `src/api/restaurant/routes/restaurant.js` file:

  ```js title="src/api/restaurant/routes/restaurant.js"

  /**
   * restaurant router
   */
      
  const { createCoreRouter } = require("@strapi/strapi").factories;

  module.exports = createCoreRouter("api::restaurant.restaurant", {
    config: {
      update: {
        middlewares: ["api::restaurant.is-owner"],
      },
      delete: {
        middlewares: ["api::restaurant.is-owner"],
      },
    },
  });
  ```

:::info
You can find more information about route middlewares in the [routes documentation](/cms/backend-customization/routes).
:::




---


# Policies
Source: https://docs.strapi.io/cms/backend-customization/policies

# Policies

Policies are functions that execute specific logic on each request before it reaches the [controller](/cms/backend-customization/controllers). They are mostly used for securing business logic.

Each [route](/cms/backend-customization/routes) of a Strapi project can be associated to an array of policies. For example, a policy named `is-admin` could check that the request is sent by an admin user, and restrict access to critical routes.

Policies can be global or scoped. [Global policies](#global-policies) can be associated to any route in the project. Scoped policies only apply to a specific [API](#api-policies) or [plugin](#plugin-policies) and should live under the corresponding `./src/api/<api-name>/policies/` or `./src/plugins/<plugin-name>/policies/` folder.

<figure style={{width: '100%', margin: '0'}}>
  <img src="/img/assets/backend-customization/diagram-routes.png" alt="Simplified Strapi backend diagram with routes and policies highlighted" />
  <em><figcaption style={{fontSize: '12px'}}>The diagram represents a simplified version of how a request travels through the Strapi back end, with policies and routes highlighted. The backend customization introduction page includes a complete, <a href="/cms/backend-customization#interactive-diagram">interactive diagram</a>.</figcaption></em>
</figure>

## Implementation

A new policy can be implemented:

- with the [interactive CLI command `strapi generate`](/cms/cli#strapi-generate) 
- or manually by creating a JavaScript file in the appropriate folder (see [project structure](/cms/project-structure)):
  - `./src/policies/` for global policies
  - `./src/api/[api-name]/policies/` for API policies
  - `./src/plugins/[plugin-name]/policies/` for plugin policies

<br/>

Global policy implementation example:

</Tabs>

`policyContext` is a wrapper around the [controller](/cms/backend-customization/controllers) context. It adds some logic that can be useful to implement a policy for both REST and GraphQL.

<br/>

Policies can be configured using a `config` object:

</Tabs>

## Usage

To apply policies to a route, add them to its configuration object (see [routes documentation](/cms/backend-customization/routes#policies)).

Policies are called different ways depending on their scope:

- use `global::policy-name` for [global policies](#global-policies)
- use `api::api-name.policy-name` for [API policies](#api-policies)
- use `plugin::plugin-name.policy-name` for [plugin policies](#plugin-policies)

:::tip
To list all the available policies, run `yarn strapi policies:list`.
:::

### Global policies

Global policies can be associated to any route in a project.

</Tabs>

### Plugin policies

Plugins can add and expose policies to an application. For example, the [Users & Permissions feature](/cms/features/users-permissions) comes with policies to ensure that the user is authenticated or has the rights to perform an action:

</Tabs>

### API policies

API policies are associated to the routes defined in the API where they have been declared.

</Tabs>

To use a policy in another API, reference it with the following syntax: `api::[apiName].[policyName]`:

</Tabs>




---


# Routes
Source: https://docs.strapi.io/cms/backend-customization/routes

# Routes

Requests sent to Strapi on any URL are handled by routes. By default, Strapi generates routes for all the content-types (see [REST API documentation](/cms/api/rest)). Routes can be [added](#implementation) and configured:

- with [policies](#policies), which are a way to block access to a route,
- and with [middlewares](#middlewares), which are a way to control and change the request flow and the request itself.

Once a route exists, reaching it executes some code handled by a controller (see [controllers documentation](/cms/backend-customization/controllers)). To view all existing routes and their hierarchal order, you can run `yarn strapi routes:list` (see [CLI reference](/cms/cli)).

:::tip
If you only customize the default controller actions (`find`, `findOne`, `create`, `update`, or `delete`) that Strapi generates for a content-type, you can leave the router as-is. Those core routes already target the same handler names and will run your new controller logic. Add or edit a route only when you need a brand-new HTTP path/method or want to expose a custom controller action.
:::

<figure style={{width: '100%', margin: '0'}}>
  <img src="/img/assets/backend-customization/diagram-routes.png" alt="Simplified Strapi backend diagram with routes highlighted" />
  <em><figcaption style={{fontSize: '12px'}}>The diagram represents a simplified version of how a request travels through the Strapi back end, with routes highlighted. The backend customization introduction page includes a complete, <a href="/cms/backend-customization#interactive-diagram">interactive diagram</a>.</figcaption></em>
</figure>

## Implementation

Implementing a new route consists in defining it in a router file within the `./src/api/[apiName]/routes` folder (see [project structure](/cms/project-structure)).

There are 2 different router file structures, depending on the use case:

- configuring [core routers](#configuring-core-routers)
- or creating [custom routers](#creating-custom-routers).

### Configuring core routers

Core routers (i.e. `find`, `findOne`, `create`, `update`, and `delete`) correspond to [default routes](/cms/api/rest#endpoints) automatically created by Strapi when a new [content-type](/cms/backend-customization/models#model-creation) is created.

Strapi provides a `createCoreRouter` factory function that automatically generates the core routers and allows:

- passing in configuration options to each router
- and disabling some core routers to [create custom ones](#creating-custom-routers).

A core router file is a JavaScript file exporting the result of a call to `createCoreRouter` with the following parameters:

| Parameter | Description                                                                                  | Type     |
| ----------| -------------------------------------------------------------------------------------------- | -------- |
| `prefix`  | Allows passing in a custom prefix to add to all routers for this model (e.g. `/test`)        | `String` |
| `only`    | Core routes that will only be loaded<br /><br/>Anything not in this array is ignored.        | `Array` | -->
| `except`  | Core routes that should not be loaded<br/><br />This is functionally the opposite of the `only` parameter. | `Array` |
| `config`  | Configuration to handle [policies](#policies), [middlewares](#middlewares) and [public availability](#public-routes) for the route | `Object` |

<br/>

</Tabs>

<br />

Generic implementation example:

</Tabs>

This only allows a `GET` request on the `/restaurants` path from the core `find` [controller](/cms/backend-customization/controllers) without authentication. When you reference custom controller actions in custom routers, prefer the fully‑qualified `api::<api-name>.<controllerName>.<actionName>` form for clarity (e.g., `api::restaurant.restaurant.review`).

### Creating custom routers

Creating custom routers consists in creating a file that exports an array of objects, each object being a route with the following parameters:

| Parameter                  | Description                                                                      | Type     |
| -------------------------- | -------------------------------------------------------------------------------- | -------- |
| `method`                   | Method associated to the route (i.e. `GET`, `POST`, `PUT`, `DELETE` or `PATCH`)  | `String` |
| `path`                     | Path to reach, starting with a forward-leading slash (e.g. `/articles`)| `String` |
| `handler`                  | Function to execute when the route is reached.<br/>Use the fully-qualified syntax `api::api-name.controllerName.actionName` (or `plugin::plugin-name.controllerName.actionName`). The short `<controllerName>.<actionName>` form for legacy projects also works. | `String` |
| `config`<br /><br />_Optional_ | Configuration to handle [policies](#policies), [middlewares](#middlewares) and [public availability](#public-routes) for the route<br/><br/>           | `Object` |

<br/>

Dynamic routes can be created using parameters and regular expressions. These parameters will be exposed in the `ctx.params` object. For more details, please refer to the 

</Tabs>

</details>

## Configuration

Both [core routers](#configuring-core-routers) and [custom routers](#creating-custom-routers) have the same configuration options. The routes configuration is defined in a `config` object that can be used to handle [policies](#policies) and [middlewares](#middlewares) or to [make the route public](#public-routes).

### Policies

[Policies](/cms/backend-customization/policies) can be added to a route configuration:

- by pointing to a policy registered in `./src/policies`, with or without passing a custom configuration
- or by declaring the policy implementation directly, as a function that takes `policyContext` to extend 

</Tabs>

</TabItem>

</Tabs>

</TabItem>

</Tabs>

### Middlewares

[Middlewares](/cms/backend-customization/middlewares) can be added to a route configuration:

- by pointing to a middleware registered in `./src/middlewares`, with or without passing a custom configuration
- or by declaring the middleware implementation directly, as a function that takes 

</Tabs>

</TabItem>

</Tabs>

</TabItem>

</Tabs>

### Public routes

By default, routes are protected by Strapi's authentication system, which is based on [API tokens](/cms/features/api-tokens) or on the use of the [Users & Permissions plugin](/cms/features/users-permissions).

In some scenarios, it can be useful to have a route publicly available and control the access outside of the normal Strapi authentication system. This can be achieved by setting the `auth` configuration parameter of a route to `false`:

</Tabs>

</TabItem>

</Tabs>

</TabItem>

</Tabs>

## Custom Content API parameters {#custom-content-api-parameters}

You can extend the `query` and body parameters allowed on Content API routes by registering them in the [register](/cms/configurations/functions#register) lifecycle. Registered parameters are then validated and sanitized like core parameters. Clients can send extra query keys (e.g. `?search=...`) or root-level body keys (e.g. `clientMutationId`) without requiring custom routes or controllers.

| What | Where |
|------|--------|
| Enable strict parameters (reject unknown query/body keys) | [API configuration](/cms/configurations/api): set `rest.strictParams: true` in `./config/api.js` (or `./config/api.ts`). |
| Add allowed parameters (app) | Call `addQueryParams` / `addInputParams` in [register](/cms/configurations/functions#register) in `./src/index.js` or `./src/index.ts`. |
| Add allowed parameters (plugin) | Call `addQueryParams` / `addInputParams` in the plugin's [register](/cms/plugins-development/server-lifecycle#register) lifecycle. |

When `rest.strictParams` is enabled, only core parameters and parameters on each route's request schema are accepted; the parameters you register are merged into that schema. Use the `z` instance from `@strapi/utils` (or `zod/v4`) for schemas.

### `addQueryParams`

`strapi.contentAPI.addQueryParams(options)` registers extra `query` parameters. Schemas must be scalar or array-of-scalars (string, number, boolean, enum). For nested structures, use `addInputParams` instead. Each entry can have an optional `matchRoute: (route) => boolean` callback to add the parameter only to routes for which the callback returns true. You cannot register core query param names (e.g. `filters`, `sort`, `fields`) as extra params; they are reserved.

### `addInputParams`

`strapi.contentAPI.addInputParams(options)` registers extra input parameters: root-level keys in the request body (e.g. alongside `data`), with any Zod type. The optional `matchRoute` callback works the same way as for `addQueryParams`. You cannot register reserved names such as `id` or `documentId` as input params.

### `matchRoute`

The `matchRoute` callback receives a `route` object with the following properties:

- `route.method`: the HTTP method (`'GET'`, `'POST'`, etc.)
- `route.path`: the route path
- `route.handler`: the controller action string
- `route.info`: metadata about the route

For example, to target only GET routes, use `matchRoute: (route) => route.method === 'GET'`. To target only routes whose path includes `articles`, use `matchRoute: (route) => route.path.includes('articles')`.

</Tabs>




---


# Services
Source: https://docs.strapi.io/cms/backend-customization/services

# Services

Services are a set of reusable functions. They are particularly useful to respect the "don’t repeat yourself" (DRY) programming concept and to simplify [controllers](/cms/backend-customization/controllers.md) logic.

<figure style={{width: '100%', margin: '0'}}>
  <img src="/img/assets/backend-customization/diagram-controllers-services.png" alt="Simplified Strapi backend diagram with services highlighted" />
  <em><figcaption style={{fontSize: '12px'}}>The diagram represents a simplified version of how a request travels through the Strapi back end, with services highlighted. The backend customization introduction page includes a complete, <a href="/cms/backend-customization#interactive-diagram">interactive diagram</a>.</figcaption></em>
</figure>

## Implementation

Services can be [generated or added manually](#adding-a-new-service). Strapi provides a `createCoreService` factory function that automatically generates core services and allows building custom ones or [extend or replace the generated services](#extending-core-services).

### Adding a new service

A new service can be implemented:

- with the [interactive CLI command `strapi generate`](/cms/cli#strapi-generate)
- or manually by creating a JavaScript file in the appropriate folder (see [project structure](/cms/project-structure.md)):
  - `./src/api/[api-name]/services/` for API services
  - or `./src/plugins/[plugin-name]/services/` for [plugin services](/cms/plugins-development/server-controllers-services).

To manually create a service, export a factory function that returns the service implementation (i.e. an object with methods). This factory function receives the `strapi` instance:

</Tabs>

:::strapi Document Service API
To get started creating your own services, see Strapi's built-in functions in the [Document Service API](/cms/api/document-service) documentation.
:::

<details>

<summary>Example of a custom email service (using Nodemailer)</summary>

The goal of a service is to store reusable functions. A `sendNewsletter` service could be useful to send emails from different functions in our codebase that have a specific purpose:

</Tabs>

The service is now available through the `strapi.service('api::restaurant.restaurant').sendNewsletter(...args)` global variable. It can be used in another part of the codebase, like in the following controller:

</Tabs>

</details>

:::note
When a new [content-type](/cms/backend-customization/models.md#content-types) is created, Strapi builds a generic service with placeholder code, ready to be customized.
:::

### Extending core services

Core services are created for each content-type and could be used by [controllers](/cms/backend-customization/controllers.md) to execute reusable logic through a Strapi project. Core services can be customized to implement your own logic. The following code examples should help you get started.

:::tip
A core service can be replaced entirely by [creating a custom service](#adding-a-new-service) and naming it the same as the core service (e.g. `find`, `findOne`, `create`, `update`, or `delete`).
:::

<details>
<summary>Collection type examples</summary>

</Tabs>

</details>

<details>

<summary>Single type examples</summary>

</Tabs>

</details>

## Usage

Once a service is created, it's accessible from [controllers](/cms/backend-customization/controllers.md) or from other services:

```js
// access an API service
strapi.service('api::apiName.serviceName').FunctionName();
// access a plugin service
strapi.service('plugin::pluginName.serviceName').FunctionName();
```

In the syntax examples above, `serviceName` is the name of the service file for API services or the name used to export the service file to `services/index.js` for plugin services.

:::tip
To list all the available services, run `yarn strapi services:list`.
:::

### Core service methods

Services generated with `createCoreService` inherit methods that wrap the [Document Service API](/cms/api/document-service). The available methods depend on the content-type:

#### Collection types

| Method | Description |
| --- | --- |
| `find(params)` | Wrapper for [`findMany`](/cms/api/document-service#findmany); returns a paginated list of documents. |
| `findOne(documentId, params)` | Wrapper for [`findOne`](/cms/api/document-service#findone); returns a single document by its `documentId`. |
| `create(params)` | Wrapper for [`create`](/cms/api/document-service#create); creates a new document. |
| `update(documentId, params)` | Wrapper for [`update`](/cms/api/document-service#update); updates an existing document. |
| `delete(documentId, params)` | Wrapper for [`delete`](/cms/api/document-service#delete); removes a document. |
| `count(params)` | Wrapper for [`count`](/cms/api/document-service#count); returns the number of matching documents. |
| `publish(documentId, params)` | Wrapper for [`publish`](/cms/api/document-service#publish); publishes a draft document. |
| `unpublish(documentId, params)` | Wrapper for [`unpublish`](/cms/api/document-service#unpublish); unpublishes a document. |
| `discardDraft(documentId, params)` | Wrapper for [`discardDraft`](/cms/api/document-service#discarddraft); deletes the draft copy. |

#### Single types

| Method | Description |
| --- | --- |
| `find(params)` | Returns the single document (uses [`findFirst`](/cms/api/document-service#findfirst) internally). |
| `createOrUpdate({ data, ...params })` | Creates the document if it doesn't exist or updates it (uses [`update`](/cms/api/document-service#update)). |
| `delete(params)` | Deletes the document (uses [`delete`](/cms/api/document-service#delete)). |
| `count(params)` | Counts documents matching the filters (uses [`count`](/cms/api/document-service#count)). |
| `publish(params)` | Publishes a draft document (uses [`publish`](/cms/api/document-service#publish)). |
| `unpublish(params)` | Unpublishes the document (uses [`unpublish`](/cms/api/document-service#unpublish)). |
| `discardDraft(params)` | Deletes the draft copy (uses [`discardDraft`](/cms/api/document-service#discarddraft)). |

#### Parameters and default behavior

Core service methods accept the same parameters as their underlying [Document Service API](/cms/api/document-service) calls, such as `fields`, `filters`, `sort`, `pagination`, `populate`, `locale`, and `status`. When no `status` is provided, Strapi automatically sets `status: 'published'` so only published content is returned. To query draft documents, explicitly pass `status: 'draft'` or another value supported by the Document Service.

The `createCoreService` factory also exposes a `getFetchParams(params)` helper that converts a controller's query object into the parameter format expected by these methods. This helper can be reused when overriding core methods to forward sanitized parameters to `strapi.documents()`.


