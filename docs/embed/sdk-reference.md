# @launchpad/embed-sdk reference

## `LaunchPadEmbed.render(container, config)`

Creates the embed's iframe in `container` and resolves with an embed instance once the embed
has loaded and answered the handshake. Rejects when the config is incomplete, when the embed
refuses (the error has a `code`, e.g. `origin_mismatch`, `token_revoked`) or when it does not
answer within `timeout` milliseconds (default 10000).

| Config | |
| --- | --- |
| `baseUrl` | LaunchPad's address, e.g. `https://cloud.example.nl/index.php/apps/launchpad`. |
| `token` | The embed token. |
| `subjectType` | `widget` or `dashboard`. |
| `subjectId` | The widget id or dashboard uuid the token names. |
| `title` | The iframe's accessible title (default `LaunchPad`). |
| `viewport` | `small`, `medium`, `large` or a width, for the usage figures. |
| `timeout` | Milliseconds to wait for an answer. |

## Embed instance

| Method | |
| --- | --- |
| `on(eventType, handler)` | Listen for an event; any number of listeners. Returns the instance. |
| `off(eventType, handler?)` | Stop one listener, or all of an event. Returns the instance. |
| `resize()` | Sends the container's size; resolves with `{width, height}` the embed took. |
| `destroy()` | Removes the iframe and every listener; pending calls reject. |

## Events

| Event | Payload |
| --- | --- |
| `filterApplied` | The filter the visitor set. |
| `drillDown` | What the visitor opened. |
| `export` | The export started. |
| `error` | `{error, message}`, e.g. when the token is revoked while the page is open. |

## Messages

Host and embed talk by `postMessage`, with types starting with `launchpad-embed:`
(`init`, `ready`, `error`, `event`, `resize`, `resized`). The SDK accepts messages only from the
embed's own window at the LaunchPad origin; the embed answers only a parent whose origin the
token lists. Neither side ever posts to `*`.
