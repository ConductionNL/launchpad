
# Admin panel
Source: https://docs.strapi.io/cms/features/admin-panel

# Administration panel

The admin panel is the back office of your Strapi application. From the admin panel, you will be able to manage content-types and write their actual content, but also manage users, both administrators and end users of your Strapi application.

### Modifying profile information (name, email, username)

1. Go to the *Profile* section of your profile.
2. Fill in the following options:

| Profile & Experience | Instructions                                      |
| -------------------- | ------------------------------------------------- |
| First name           | Write your first name in the textbox.             |
| Last name            | Write your last name in the textbox.              |
| Email                | Write your complete email address in the textbox. |
| Username             | (optional) Write a username in the textbox.       |

3. Click on the **Save** button.

### Changing account password

1. Go to the *Change password* section of your profile.
2. Fill in the following options:

| Password modification | Instructions                                |
| --------------------- | ------------------------------------------- |
| Current password      | Write your current password in the textbox. |
| Password              | Write the new password in the textbox.      |
| Password confirmation | Write the same new password in the textbox. |

3. Click on the **Save** button.

:::tip
You can click on the  icon for the passwords to be shown.
:::

### Choosing interface language

In the *Experience* section of your profile, select your preferred language using the *Interface language* dropdown.

:::note
Keep in mind that choosing an interface language only applies to your account on the admin panel. Other users of the same application's admin panel can use a different language.
:::

### Choosing interface mode (light, dark)

By default, the chosen interface mode is based on your browser's mode. You can however, in the *Experience* section of your profile, manually choose either the Light Mode or Dark Mode using the *Interface mode* dropdown.

:::note
Keep in mind that choosing an interface mode only applies to your account on the admin panel.
:::

### Resetting guided tour

In the *Guided tour* section of your profile, you can click the **Reset guided tour** button to reset the guided tour which is available in the homepage of the admin panel. It allows you to see again the guided tour of the admin panel if you closed it beforehand, and to follow again its various steps.

### Customizing the logo

**Path to configure the admin panel:**  *Settings > Global settings > Overview*

The default Strapi logos, displayed in the main navigation of a Strapi application and the authentication pages, can be modified.

1. Click on the upload area for *Menu logo* or *Auth logo*.
2. Upload your chosen logo, either by browsing files, drag & dropping the file in the right area, or by using a URL. The logo shouldn't be more than 750x750px. 
3. Click on the **Upload logo** button in the upload window.
4. Click on the **Save** button in the top right corner.

Once uploaded, the new logo can be replaced with another one , or reset  with the default Strapi logo or the logo set in the configuration files.

:::note
Both logos can also be customized programmatically via the Strapi application's configuration files (see [Admin panel customization](/cms/admin-panel-customization/logos)). However, the logos uploaded via the admin panel supersedes any logo set through the configuration files.
:::

## Usage

:::caution
In order to access the admin panel, your Strapi application must be launched, and you must be aware of the URL to its admin panel (e.g. `api.example.com/admin`).
:::

To access the admin panel:

1. Go to the URL of your Strapi application's admin panel.
2. Enter your credentials to log in.
3. Click on the **Login** button. You should be redirected to the homepage of the admin panel.

:::note
If you prefer or are required to log in via an SSO provider, please refer to the [Single Sign-On documentation](/cms/features/sso).
:::




---


# API Tokens
Source: https://docs.strapi.io/cms/features/api-tokens

# API Tokens

API tokens allow users to authenticate REST and GraphQL API queries (see [APIs introduction](/cms/api/content-api)).

:::caution Security
Prefer read‑only tokens for public access, scope server tokens to only what you need, rotate long‑lived tokens, and store them in a secrets manager. Never expose admin tokens in client‑side code.
:::

</IdentityCard>

</Tabs>

This key is used to encrypt and decrypt token values. Without this key, tokens remain usable, but will not be viewable after initial display. New Strapi projects will have this key automatically generated.

## Usage

Using API tokens allows executing a request on [REST API](/cms/api/rest) or [GraphQL API](/cms/api/graphql) endpoints as an authenticated user.

API tokens can be helpful to give access to people or applications without managing a user account or changing anything in the Users & Permissions plugin.

When performing a request to Strapi's REST API, the API token should be added to the request's `Authorization` header with the following syntax: `bearer your-api-token`.

:::note
Read-only API tokens can only access the `find` and `findOne` functions.
:::




---


# Audit Logs
Source: https://docs.strapi.io/cms/features/audit-logs

# Audit Logs

The Audit Logs feature provides a searchable and filterable display of all activities performed by users of the Strapi application.

</IdentityCard>

## Usage

**Path to use the feature:**  Settings > Administration Panel - Audit Logs

The Audit Logs feature logs the following events:

| Event | Actions |
| --- | --- |
| Content Type | `create`, `update`, `delete` |
| Entry (draft/publish) | `create`, `update`, `delete`, `publish`, `unpublish` |
| Media | `create`, `update`, `delete` |
| Login / Logout | `success`, `fail` |
| Role / Permission | `create`, `update`, `delete` |
| User | `create`, `update`, `delete` |

For each log item, the following information is displayed:

- Action: type of action performed by the user (e.g.`create` or `update`).
- Date: date and time of the action.
- User: user who performed the action.
- Details: displays a modal with more details about the action (e.g. the User IP address, the request body, or the response body).

### Filtering logs

By default, all logs are displayed in reverse chronological order. You can filter the logs by:

- Action: select the type of action to filter by (e.g `create` or `update`).
- User: select the user to filter by.
- Date: select a date (range) and time to filter by.

### Accessing log details {#log-details}

For any log item, click the  icon to access a modal with more details about that action. In the modal, the *Payload* section displays the details in an interactive JSON component, enabling you to expand and collapse the JSON object.




---


# Role-Based Access Control (RBAC)
Source: https://docs.strapi.io/cms/features/rbac

# Role-Based Access Control (RBAC)

The Role-Based Access Control (RBAC) feature allows the management of the administrators, who are the users of the admin panel. More specifically, RBAC manages the administrators' accounts and roles.

</IdentityCard>

</Tabs>

4. Click on the **Save** button on the top right corner.

:::tip
To create admin permissions for your custom plugin, please refer to our [dedicated guide](/cms/plugins-development/guides/admin-permissions-for-plugins).
:::

#### Setting custom conditions for permissions

For each permission of each category, a 

## Usage

**Path to use the feature:**  *Settings > Administration panel > Users*

The *Users* interface displays a table listing all the administrators of your Strapi application. More specifically, for each administrator listed in the table, their main account information are displayed, including name, email and attributed role. The status of their account is also indicated: active or inactive, depending on whether the administrator has already logged in to activate the account or not.

From this interface, it is possible to:

- make a textual search  to find specific administrators,
- set filters  to find specific administrators,
- create a new administrator account (see [Creating a new account](#creating-a-new-account)) ,
- delete an administrator account  (see [Deleting an account](#deleting-an-account)),
- or access information regarding an administrator account, and edit it  (see [Editing an account](#editing-an-account)).

:::tip
Sorting can be enabled for most fields displayed in the table. Click on a field name, in the header of the table, to sort on that field.
:::

### Creating a new account

1. Click on the  **Invite new user** button.
2. In the *Invite new user* window, fill in the Details information about the new administrator:

  | User information | Instructions                                                                 |
  | ---------------- | ---------------------------------------------------------------------------- |
  | First name       | (mandatory) Write the administrator's first name in the textbox.             |
  | Last name        | (mandatory) Write the administrator's last name in the textbox.              |
  | Email            | (mandatory) Write the administrator's complete email address in the textbox. |

3. Fill in the Login settings about the new administrator:

  | Setting          | Instructions                                                                                                    |
  | ---------------- | --------------------------------------------------------------------------------------------------------------- |
  | User's roles     | (mandatory) Choose from the drop-down list the role to attribute to the new administrator.                      |
  | Connect with SSO | (optional) Click **TRUE** or **FALSE** to connect the new administrator account with SSO.                       |

4. Click on the **Invite user** button in the bottom right corner of the *Add new user* window.
5. A URL appears at the top of the window: it is the URL to send the new administrator for them to log in for the first time to your Strapi application. Click the copy button  to copy the URL.
6. Click on the **Finish** button in the bottom right corner to finish the new administrator account creation. The new administrator should now be listed in the table.

:::note
The administrator invitation URL is accessible from the administrator's account until it has been activated.
:::

### Deleting an account

It is possible to delete one or several administrator accounts at the same time.

1. Click on the delete button  on the right side of the account's record, or select one or more accounts by ticking the boxes on the left side of the accounts' records then click on the  **Delete** button above the table.
2. In the deletion window, click on the **Confirm** button to confirm the deletion.

### Editing an account

1. Click on the name of the administrator whose account you want to edit.
2. In the *Details* area, edit your chosen account details:

| User information      | Instructions  |
| --------------------- | ----------------------- |
| First name            | Write the administrator's first name in the textbox.                                        |
| Last name             | Write the administrator's last name in the textbox.                                         |
| Email                 | Write the administrator's complete email address in the textbox.                            |
| Username              | Write the administrator's username in the textbox.                                          |
| Password              | Write the new administrator account's password in the textbox.                              |
| Confirm password      | Write the new password in the textbox for confirmation.                                     |
| Active                | Click on **TRUE** to activate the administrator's account.                                  |

3. (optional) In the *Roles* area, edit the role of the administrator:
  - Click on the drop-down list to choose a new role, and/or add it to the already attributed one.
  - Click on the delete button  to delete an already attributed role.
4. Click on the **Save** button in the top right corner.




---


# Single Sign-On (SSO)
Source: https://docs.strapi.io/cms/features/sso

# Single Sign-On (SSO)

The Single Sign-On (SSO) feature can be made available on a Strapi application to allow administrators to authenticate through an identity provider (e.g. Microsoft Azure Active Directory).

</IdentityCard>

## Usage

To access the admin panel using a specific provider instead of logging in with a regular Strapi administrator account:

1. Go to the URL of your Strapi application's admin panel.
2. Click on a chosen provider, which logo should be displayed at the bottom of the login form. If you cannot see your provider, click the  button to access the full list of all available providers.
3. You will be redirected to your provider's own login page where you will be able to authenticate.




---


# Users & Permissions
Source: https://docs.strapi.io/cms/features/users-permissions

# Users & Permissions

The Users & Permissions feature allows the management of the end-users  of a Strapi project. It provides a full authentication process based on JSON Web Tokens (JWT) to protect your API, and an access-control list (ACL) strategy that enables you to manage permissions between groups of users.

</IdentityCard>

## Admin panel configuration

The Users & Permissions feature is configured from both the admin panel settings, and via the code base.

### Roles

The Users & Permissions feature allows to create and manage roles for end users, to configure what they can have access to.

#### Creating a new role

**Path:** 

<!---
:::tip
Click the search button 

</Tabs>

### Registration configuration

If you have added any additional fields in your User **model**  that need to be accepted on registration, you need to added them to the list of allowed fields in the `config.register` object of [the `/config/plugins` file](/cms/configurations/plugins), otherwise they will not be accepted.

The following example shows how to ensure a field called "nickname" is accepted by the API on user registration:

</Tabs>

### Rate limiting configuration

Rate limiting is applied to authentication and registration endpoints to prevent abuse.  The following parameters can be configured to change its behavior. Additional configuration options are provided by the 

</Tabs>

### Templating emails

By default this plugin comes with two templates: reset password and email address confirmation. The templates use 

</Tabs>

### Security configuration

JWTs can be verified and trusted because the information is digitally signed. To sign a token a _secret_ is required. By default Strapi generates and stores it in `/extensions/users-permissions/config/jwt.js`.

This is useful during development but for security reasons it is recommended to set a custom token via an environment variable `JWT_SECRET` when deploying to production.

By default you can set a `JWT_SECRET` environment variable and it will be used as secret. If you want to use another variable you can update the configuration file.

</Tabs>

#### Creating a custom callback validator {#creating-a-custom-password-validation}

By default, Strapi SSO only redirects to the redirect URL that is exactly equal to the url in the configuration:

</ApiCall>

To log out of all sessions, send the following request:

</ApiCall>

#### Identifier

The `identifier` parameter sent with requests can be an email or username, as in the following examples:

</Tabs>

#### Token usage

The `jwt` may then be used for making permission-restricted API requests. To make an API request as a user place the JWT into an `Authorization` header of the `GET` request.

Any request without a token will assume the `public` role permissions by default. Modify the permissions of each user's role in the admin dashboard.

Authentication failures return a `401 (unauthorized)` error.

The `token` variable is the `data.jwt` received when logging in or registering.

```js

const token = 'YOUR_TOKEN_HERE';

// Request API.
axios
  .get('http://localhost:1337/posts', {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  })
  .then(response => {
    // Handle success.
    console.log('Data: ', response.data);
  })
  .catch(error => {
    // Handle error.
    console.log('An error occurred:', error.response);
  });
```

#### User registration

Creating a new user in the database with a default role as 'registered' can be done like in the following example:

```js

// Request API.
// Add your own code here to customize or restrict how the public can register new users.
axios
  .post('http://localhost:1337/api/auth/local/register', {
    username: 'Strapi user',
    email: 'user@strapi.io',
    password: 'strapiPassword',
  })
  .then(response => {
    // Handle success.
    console.log('Well done!');
    console.log('User profile', response.data.user);
    console.log('User token', response.data.jwt);
  })
  .catch(error => {
    // Handle error.
    console.log('An error occurred:', error.response);
  });
```

#### User object in Strapi context

The `user` object is available to successfully authenticated requests.

The authenticated `user` object is a property of `ctx.state`.

```js
create: async ctx => {
  const { id } = ctx.state.user;

  const depositObj = {
    ...ctx.request.body,
    depositor: id,
  };

  const data = await strapi.services.deposit.add(depositObj);

  // Send 201 `created`
  ctx.created(data);
};
```


