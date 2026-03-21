
# Upload files
Source: https://docs.strapi.io/cms/api/rest/upload

# REST API: Upload files

The [Media Library feature](/cms/features/media-library) is powered in the back-end server of Strapi by the `upload` package. To upload files to Strapi, you can either use the Media Library directly from the admin panel, or use the [REST API](/cms/api/rest), with the following available endpoints :

| Method | Path                    | Description         |
| :----- | :---------------------- | :------------------ |
| GET    | `/api/upload/files`     | Get a list of files |
| GET    | `/api/upload/files/:id` | Get a specific file |
| POST   | `/api/upload`           | Upload files        |
| POST   | `/api/upload?id=x`      | Update fileInfo     |
| DELETE | `/api/upload/files/:id` | Delete a file       |

:::note Notes
- [Folders](/cms/features/media-library#organizing-assets-with-folders) are an admin panel-only feature and are not part of the Content API (REST or GraphQL). Files uploaded through REST are located in the automatically created "API Uploads" folder.
- The GraphQL API does not support uploading media files. To upload files, use the REST API or directly add files from the [Media Library](/cms/features/media-library) in the admin panel. Some GraphQL mutations to update or delete uploaded media files are still possible (see [GraphQL API documentation](/cms/api/graphql#mutations-on-media-files) for details).
:::

## Upload files

Upload one or more files to your application.

`files` is the only accepted parameter, and describes the file(s) to upload. The value(s) can be a Buffer or Stream.

:::tip
When uploading an image, include a `fileInfo` object to set the file name, alt text, and caption.
:::

</Tabs>

:::caution
You have to send FormData in your request body.
:::

## Upload entry files

Upload one or more files that will be linked to a specific entry.

The following parameters are accepted:

| Parameter | Description |
| --------- | ----------- |
|`files`    | The file(s) to upload. The value(s) can be a Buffer or Stream. |
|`path` (optional) | The folder where the file(s) will be uploaded to (only supported on strapi-provider-upload-aws-s3). |
| `refId` | The ID of the entry which the file(s) will be linked to. |
| `ref` | The unique ID (uid) of the model which the file(s) will be linked to (see more below). |
| `source` (optional) | The name of the plugin where the model is located. |
| `field` | The field of the entry which the file(s) will be precisely linked to. |

For example, given the `Restaurant` model attributes:

```json title="/src/api/restaurant/content-types/restaurant/schema.json"
{
  // ...
  "attributes": {
    "name": {
      "type": "string"
    },
    "cover": {
      "type": "media",
      "multiple": false,
    }
  }
// ...
}
```

The following is an example of a corresponding front-end code:

```html
<form>
  <!-- Can be multiple files if you setup "collection" instead of "model" -->
  <input type="file" name="files" />
  <input type="text" name="ref" value="api::restaurant.restaurant" />
  <input type="text" name="refId" value="5c126648c7415f0c0ef1bccd" />
  <input type="text" name="field" value="cover" />
  <input type="submit" value="Submit" />
</form>

<script type="text/javascript">
  const form = document.querySelector('form');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    await fetch('/api/upload', {
      method: 'post',
      body: new FormData(e.target)
    });
  });
</script>
```

:::caution
You have to send FormData in your request body.
:::

## Update fileInfo

Update a file in your application.

`fileInfo` is the only accepted parameter, and describes the fileInfo to update:

```js

const fileId = 50;
const newFileData = {
  alternativeText: 'My new alternative text for this image!',
};

const form = new FormData();

form.append('fileInfo', JSON.stringify(newFileData));

const response = await fetch(`http://localhost:1337/api/upload?id=${fileId}`, {
  method: 'post',
  body: form,
});

```

## Models definition

Adding a file attribute to a [model](/cms/backend-customization/models) (or the model of another plugin) is like adding a new association.

The following example lets you upload and attach one file to the `avatar` attribute:

```json title="/src/api/restaurant/content-types/restaurant/schema.json"

{
  // ...
  {
    "attributes": {
      "pseudo": {
        "type": "string",
        "required": true
      },
      "email": {
        "type": "email",
        "required": true,
        "unique": true
      },
      "avatar": {
        "type": "media",
        "multiple": false,
      }
    }
  }
  // ...
}

```

The following example lets you upload and attach multiple pictures to the `restaurant` content-type:

```json title="/src/api/restaurant/content-types/restaurant/schema.json"
{
  // ...
  {
    "attributes": {
      "name": {
        "type": "string",
        "required": true
      },
      "covers": {
        "type": "media",
        "multiple": true,
      }
    }
  }
  // ...
}
```




---


# Media Library
Source: https://docs.strapi.io/cms/features/media-library

# Media Library

The 

</IdentityCard>

:::info
Code-based configuration instructions on the present page detail options for the default upload provider. If using another provider, please refer to the available configuration parameters in that provider's documentation.
:::

#### Available options

When using the default upload provider, the following specific configuration options can be declared in an `upload.config` object within [the `config/plugins` file](/cms/configurations/plugins). All parameters are optional:

| Parameter                                   | Description                                                                                                         | Type    | Default |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- | ------- | ------- |
| `providerOptions.localServer`        | Options that will be passed to 

</Tabs>

#### Local server

By default Strapi accepts `localServer` configurations for locally uploaded files. These will be passed as the options for 

</Tabs>

#### Max file size

The Strapi middleware in charge of parsing requests needs to be configured to support file sizes larger than the default of 200MB. This must be done in addition to provider options passed to the Upload package for `sizeLimit`.

:::caution
You may also need to adjust any upstream proxies, load balancers, or firewalls to allow for larger file sizes. For instance, 

</Tabs>

In addition to the middleware configuration, you can pass the `sizeLimit`, which is an integer in bytes, in the [/config/plugins file](/cms/configurations/plugins):

</Tabs>

#### Security 

</Tabs>

#### Upload request timeout

By default, the value of `strapi.server.httpServer.requestTimeout` is set to 330 seconds. This includes uploads.

To make it possible for users with slow internet connection to upload large files, it might be required to increase this timeout limit. The recommended way to do it is by setting the `http.serverOptions.requestTimeout` parameter in [the `config/servers` file](/cms/configurations/server).

An alternate method is to set the `requestTimeout` value in [the `bootstrap` function](/cms/configurations/functions#bootstrap) that runs before Strapi gets started. This is useful in cases where it needs to change programmatically—for example, to temporarily disable and re-enable it:

</Tabs>

#### Responsive Images

When the [`Responsive friendly upload` admin panel setting](#admin-panel-configuration) is enabled, the plugin will generate the following responsive image sizes:

| Name    | Largest dimension |
| :------ | :--------- |
| large   | 1000px     |
| medium  | 750px      |
| small   | 500px      |

These sizes can be overridden in `/config/plugins`:

</Tabs>

:::caution
Breakpoint changes will only apply to new images, existing images will not be resized or have new sizes generated.
:::

## Usage

**Path to use the feature:** 

### Use public assets in your code {#public-assets}

Public assets are static files (e.g., images, video, CSS files, etc.) that you want to make accessible to the outside world.

Because an API may need to serve static assets, every new Strapi project includes by default a folder named `/public`. Any file located in this directory is accessible if the request's path doesn't match any other defined route and if it matches a public file name (e.g. an image named `company-logo.png` in `./public/` is accessible through `/company-logo.png` URL).

:::tip
`index.html` files are served if the request corresponds to a folder name (`/pictures` url will try to serve `public/pictures/index.html` file).
:::

:::caution
The dotfiles are not exposed. It means that every file name that starts with `.`, such as `.htaccess` or `.gitignore`, are not served.
:::


