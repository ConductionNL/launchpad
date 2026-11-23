# LaunchPad embed SDK

Embed a LaunchPad widget or dashboard in any web page with an embed token.

An administrator issues the token in LaunchPad (Admin settings, Embed tokens) for one widget or
dashboard and the exact origins of the pages that may show it. The token is shown once.

The SDK is served by your Nextcloud instance, with the app, so it always matches the LaunchPad
version that renders the embed. It is not published on npm.

| File | Address |
| --- | --- |
| UMD bundle (global `LaunchPadEmbed`) | `https://cloud.example.nl/index.php/apps/launchpad/embed-sdk/embed-sdk.umd.js` |
| ES module | `https://cloud.example.nl/index.php/apps/launchpad/embed-sdk/embed-sdk.esm.js` |

Both are public, may be loaded from any origin and are cached for a day. Add `?v=<app version>`
to load a new copy after an upgrade; the `X-LaunchPad-Version` header names the version served.

## Plain iframe

```html
<iframe
  src="https://cloud.example.nl/index.php/apps/launchpad/embed/widget/42?token=<jwt>"
  title="Open requests"
  style="border: 0; width: 100%; height: 400px"></iframe>
```

## Script tag (UMD)

```html
<script src="https://cloud.example.nl/index.php/apps/launchpad/embed-sdk/embed-sdk.umd.js"></script>
<script>
  LaunchPadEmbed.render(document.getElementById('woo'), {
    baseUrl: 'https://cloud.example.nl/index.php/apps/launchpad',
    token: '<jwt>',
    subjectType: 'widget',
    subjectId: '42',
  })
</script>
```

## JavaScript (ESM)

```js
import { LaunchPadEmbed } from 'https://cloud.example.nl/index.php/apps/launchpad/embed-sdk/embed-sdk.esm.js'

const embed = await LaunchPadEmbed.render(document.getElementById('woo'), {
  baseUrl: 'https://cloud.example.nl/index.php/apps/launchpad',
  token: '<jwt>',
  subjectType: 'widget',
  subjectId: '42',
  title: 'Open requests',
})

embed.on('filterApplied', (filter) => console.log(filter))
embed.on('error', ({ error, message }) => console.warn(error, message))
window.addEventListener('resize', () => embed.resize())
```

`render()` resolves once the embed has loaded and accepted the handshake; it rejects when the
embed refuses (for instance `origin_mismatch` when the page is not one of the token's origins).

## TypeScript

The types are in `src/types.ts` in the LaunchPad repository: `EmbedConfig`, `EmbedInstance`,
`EmbedEventType`. Copy them into your project, or declare the module yourself.

## Security

- Messages are only accepted from the embed's own window at the LaunchPad origin, and only
  posted to that origin, never `*`.
- The embed answers only a parent page whose origin the token lists; the server also refuses
  framing from other origins (`Content-Security-Policy: frame-ancestors`).
- A read token cannot change anything, whatever runs in the page: writes are refused by the
  server.

## Releases

The SDK is built with the app (`npm run build` runs `npm run build:embed-sdk`, which writes
`js/embed-sdk/`), so every LaunchPad release carries its own SDK.
