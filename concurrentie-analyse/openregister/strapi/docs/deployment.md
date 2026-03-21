
# Database configuration
Source: https://docs.strapi.io/cms/configurations/database

# Database configuration

The `/config/database.js|ts` file is used to define database connections that will be used to store the application content.

:::strapi Supported databases
The following databases are supported by Strapi:

</Tabs>

</TabItem>
</Tabs>

## Configuration in database

Configuration files are not multi-server friendly. To update configurations in production you can use a data store to get and set settings.

### Get settings

- `environment` (string): Sets the environment you want to store the data in. By default it's current environment (can be an empty string if your configuration is environment agnostic).
- `type` (string): Sets if your configuration is for an `api`, `plugin` or `core`. By default it's `core`.
- `name` (string): You have to set the plugin or api name if `type` is `api` or `plugin`.
- `key` (string, required): The name of the key you want to store.

```js
// strapi.store(object).get(object);
// create reusable plugin store variable
const pluginStore = strapi.store({
  environment: strapi.config.environment,
  type: 'plugin',
  name: 'users-permissions',
});
await pluginStore.get({ key: 'grant' });
```

### Set settings

- `value` (any, required): The value you want to store.

```js
// strapi.store(object).set(object);
// create reusable plugin store variable
const pluginStore = strapi.store({
  environment: strapi.config.environment,
  type: 'plugin',
  name: 'users-permissions'
});
await pluginStore.set({
  key: 'grant',
  value: {
    ...
  }
});
```

## Environment variables in database configurations

Strapi version `v4.6.2` and higher includes the database configuration options in the `./config/database.js` or `./config/database.ts` file. When a new project is created the environment variable `DATABASE_CLIENT` with the value `mysql`, `postgres`, or `sqlite` is automatically added to the `.env` file depending on which database you choose during project creation. Additionally, all of the environment variables necessary to connect to your local development database are also added to the `.env` file.  The following is an example of the generated configuration file:

</Tabs>

The following are examples of the corresponding `.env` file database-related keys for each of the possible databases:

</Tabs>

### Environment variables for Strapi applications before `v4.6.2`

If you started your project with a version prior to `v4.6.2` you can convert your `database.js|database.ts` configuration file following this procedure:

1. Update your application to `v4.6.2` or a later version. See the [upgrades](/cms/upgrades) documentation.
2. Replace the contents of your `./config/database.js` or `./config/database.ts` file with the preceding JavaScript or TypeScript code.
3. Add the environment variables from the preceding code example to your `.env` file.
4. (_optional_) Add additional environment variables such as `DATABASE_URL` and the properties of the `ssl` object.
5. Save the changes and restart your application.
:::caution
Do not overwrite the environment variables: `HOST`, `PORT`, `APP_KEYS`, `API_TOKEN_SALT`, and `ADMIN_JWT_SECRET`.
:::

### Database connections using `connectionString`

Many managed database solutions use the property `connectionString` to connect a database to an application. Strapi `v4.6.2` and later versions include the `connectionString` property. The `connectionString` is a concatenation of all the database properties in the `connection.connection` object. The `connectionString`:

- overrides the other `connection.connection` properties such as `host` and `port`,
- can be disabled by setting the property to an empty string: `''`.

### Database management by environment

Development of a Strapi application commonly includes customization in the local development environment with a local development database, such as `SQLite`. When the application is ready for another environment such as production or staging the application is deployed with a different database instance, usually `MySQL`, `MariaDB`, or `PostgreSQL`. Database environment variables allow you to switch the attached database. To switch the database connection:

* set a minimum of the `DATABASE_CLIENT` and `DATABASE_URL` for `MySQL`, `MariaDB`, and `PostgreSQL`,
* or set a minimum of `DATABASE_CLIENT` and `DATABASE_FILENAME` for `SQLite`.

For deployed versions of your application the database environment variables should be stored wherever your other secrets are stored. The following table gives examples of where the database environment variables should be stored:

| Hosting option                                        | environment variable storage    |
|-------------------------------------------------------|---------------------------------|
| Virtual private server/virtual machine (e.g. AWS EC2) | `ecosystem.config.js` or `.env` |
| DigitalOcean App Platform                             | `Environment Variables` table   |
| Heroku                                                | `Config vars` table                   |

## Databases installation

Strapi gives you the option to choose the most appropriate database for your project. Strapi supports PostgreSQL, SQLite, or MySQL.

### SQLite

SQLite is the default (see [Quick Start Guide](/cms/quick-start)) and recommended database to quickly create an application locally.

#### Install SQLite during application creation

Use one of the following commands:

</Tabs>

This will create a new project and launch it in the browser.

#### Install SQLite manually

In a terminal, run the following command:

</Tabs>

Add the following code to your `/config/database.ts|js` file:

</Tabs>

### PostgreSQL

When connecting Strapi to a PostgreSQL database, the database user requires SCHEMA permissions. While the database admin has this permission by default, a new database user explicitly created for the Strapi application will not. This would result in a 500 error when trying to load the admin console.

To create a new PostgreSQL user with the SCHEMA permission, use the following steps:

```shell
# Create a new database user with a secure password
$ CREATE USER my_strapi_db_user WITH PASSWORD 'password';
# Connect to the database as the PostgreSQL admin
$ \c my_strapi_db_name admin_user
# Grant schema privileges to the user
$ GRANT ALL ON SCHEMA public TO my_strapi_db_user;
```




---


# Environment variables configuration
Source: https://docs.strapi.io/cms/configurations/environment

# Environment configuration and variables

Strapi provides specific environment variable names. Defining them in an environment file (e.g., `.env`) will make these variables and their values available in your code.

:::tip
An `env()` utility can be used to [retrieve the value of environment variables](/cms/configurations/guides/access-cast-environment-variables#accessing-environment-variables) and [cast variables to different types](/cms/configurations/guides/access-cast-environment-variables).
:::

Additionally, specific [configurations for different environments](#environment-configurations) can be created.

## Strapi's environment variables {#strapi}

Strapi provides the following environment variables:

 Setting                                                    | Description                                                                                                                                                                                                                                                                   | Type      | Default value   |
|------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------|-----------------|
| `STRAPI_TELEMETRY_DISABLED`                                | Don't send telemetry usage data to Strapi                                                                                                                                                                                                                                     | `Boolean` | `false`         |
| `STRAPI_LICENSE`                                           | The license key to activate the Enterprise Edition                                                                                                                                                                                                                            | `String`  | `undefined`     |
| `NODE_ENV` | Type of environment where the application is running.<br/><br/>`production` enables specific behaviors (see 

</Tabs>

With these configuration files the server will start on various ports depending on the environment variables passed:

```bash
yarn start                                   # uses host 127.0.0.1
NODE_ENV=production yarn start               # uses host defined in .env. If not defined, uses 0.0.0.0
HOST=10.0.0.1 NODE_ENV=production yarn start # uses host 10.0.0.1
```

<br/>

To learn deeper about how to use environment variables in your code, please refer to the following guide:




---


# Server configuration
Source: https://docs.strapi.io/cms/configurations/server

# Server configuration

The `/config/server.js` file is used to define the server configuration for a Strapi application.

:::caution
Changes to the `server.js` file require rebuilding the admin panel. After saving the modified file run either `yarn build` or `npm run build` in the terminal to implement the changes.
:::

## Available options

The `./config/server.js` file can include the following parameters:

<!-- TODO: add admin jwt config option -->
<!-- TODO: sort options alphabetically in the table below  -->

| Parameter                           | Description                                                                                                                                                                                                                                                                                                                                                                 | Type                                                                                              | Default             |
| ----------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- | ------------------- |
| `host`<br/><br/>❗️ _Mandatory_     | Host name                                                                                                                                                                                                                                                                                                                                                                   | string                                                                                            | `localhost`         |
| `port`<br/><br/>❗️ _Mandatory_     | Port on which the server should be running.                                                                                                                                                                                                                                                                                                                                 | integer                                                                                           | `1337`              |
| `app.keys`<br/><br/>❗️ _Mandatory_ | Declare session keys (based on 

</Tabs>

</TabItem>

</Tabs>

</TabItem>
</Tabs>




---


# Deployment
Source: https://docs.strapi.io/cms/deployment

# Deployment

Strapi provides many deployment options for your project or application. Your Strapi applications can be deployed on traditional hosting servers or your preferred hosting provider.

The following documentation covers the basics of how to prepare Strapi for deployment on with several common hosting options.

:::strapi Strapi Cloud
You can use [Strapi Cloud](/cloud/intro) to quickly deploy and host your project.
:::

:::tip
If you already created a content structure with the Content-Type Builder and added some data through the Content Manager to your local (development) Strapi instance, you can leverage the [data management system](/cms/features/data-management) to transfer data from a Strapi instance to another one.

Another possible workflow is to first create the content structure locally, push your project to a git-based repository, deploy the changes to production, and only then add content to the production instance.
:::

:::caution
For self-hosted Kubernetes deployments, we recommend using **npm** rather than **pnpm**. `pnpm` aggressive hoisting of dependencies can break native modules, such as `mysql2`— that your application may rely on. `npm` flatter, more predictable `node_modules` layout helps ensure native packages load correctly.
:::

## General guidelines

### Hardware and software requirements

To provide the best possible environment for Strapi the following requirements apply to development (local) and staging and production workflows.

</Tabs>

Run the server with the `production` settings:

</Tabs>

:::caution
We highly recommend using  to manage your process.
:::

If you need a server.js file to be able to run `node server.js` instead of `npm run start` then create a `./server.js` file as follows:

```js title="path: ./server.js"

const strapi = require('@strapi/strapi');
strapi.createStrapi(/* {...} */).start();
```

:::caution

If you are developing a `TypeScript`-based project you must provide the `distDir` option to start the server.
For more information, consult the [TypeScript documentation](/cms/typescript/development#use-the-createstrapi-factory).
:::

:::tip Health check endpoint
Strapi exposes a lightweight health check route at `/_health` for uptime monitors and load balancers. When the server is ready, it responds with an HTTP `204 No Content` status and a `strapi: You are so French!` header value, which you can use to confirm the application is reachable.
:::

### Advanced configurations

If you want to host the administration on another server than the API, [please take a look at this dedicated section](/cms/configurations/admin-panel#deploy-on-different-servers).

## Additional resources

:::prerequisites
* Your Strapi project is [created](/cms/installation) and its code is hosted on GitHub.
* You have read the [general deployment guidelines](/cms/deployment#general-guidelines).
:::

The  of the Strapi website include information on how to integrate Strapi with many resources, including how to deploy Strapi on the following 3rd-party platforms:

<br/>

In addition, community-maintained guides for additional providers are available in the . This includes the following guides:

<br/>

The following external guide(s), not officially maintained by Strapi, might also help deploy Strapi on various environments:

:::strapi Multi-tenancy
If you're looking for multi-tenancy options, the Strapi Blog has a .
:::


