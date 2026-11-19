# REST API

LaunchPad has a documented REST API for dashboards, widgets, tiles, sharing,
public links, and export and import. Scripts and other applications can manage
dashboards with it instead of clicking through the interface.

## The reference

The full reference is the OpenAPI 3.1 document in the repository root,
[`openapi.json`](https://github.com/ConductionNL/launchpad/blob/development/openapi.json).
A running instance also serves it:

```
GET https://<your-nextcloud>/index.php/apps/launchpad/api/v1/openapi.json
```

Open it in any OpenAPI viewer (Swagger UI, Redoc, Postman, Insomnia) to see
every operation with its parameters, request bodies and responses.

All paths in the reference are relative to the base URL:

```
https://<your-nextcloud>/index.php/apps/launchpad/api/v1
```

## Authentication

The API uses Nextcloud's own authentication. There is no separate LaunchPad
token.

1. In Nextcloud, open **Personal settings → Security** and create an **app
   password** under "Devices & sessions". Use it instead of your own password,
   so you can revoke it on its own.
2. Send it with HTTP Basic authentication, with your Nextcloud user name.
3. Add the header `OCS-APIRequest: true`, so Nextcloud treats the call as an
   API request and does not ask for a browser login.

```bash
curl -u alice:app-password \
     -H 'OCS-APIRequest: true' \
     -H 'Accept: application/json' \
     https://cloud.example.com/index.php/apps/launchpad/api/v1/dashboards
```

The API sees exactly what the user sees in the interface: the same dashboards,
shares and admin rights. An administrator-only operation answers `403` for
anyone else.

Errors are JSON with a `message` explaining what went wrong.

## Stable and internal operations

Every operation in the reference carries `x-launchpad-stability`:

| Value | Meaning |
| --- | --- |
| `public` | Stable. Changes only with a deprecation notice in the changelog. |
| `internal` | Used by LaunchPad's own interface. It may change in any release; do not build on it. |

The public operations cover:

- **Dashboards**: list, read, create, update and delete (`/dashboard`, `/dashboards`, `/dashboards/visible`).
- **Widgets on a dashboard**: add, move or resize, and remove (`/dashboard/{dashboardId}/widgets`, `/widgets/{placementId}`).
- **Tiles**: list, create, update and delete (`/tiles`, `/dashboard/{dashboardId}/tile`).
- **Sharing**: who a dashboard is shared with, and changing that (`/dashboard/{id}/shares`, `/dashboard/share/{shareId}`).
- **Public links**: create, list and revoke (`/dashboards/{uuid}/public-share`, `/dashboards/{uuid}/public-shares`).
- **Export and import** (administrators): `/admin/export`, `/admin/import`.

## Keeping the reference complete

`composer lint:openapi` (part of `composer check:strict`) fails when an API
route has no operation in `openapi.json`, when an operation has no
`x-launchpad-stability`, or when an operation is documented for a route that
no longer exists. A new route therefore cannot be merged undocumented.
