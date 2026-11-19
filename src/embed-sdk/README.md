# @launchpad/embed-sdk

Embed a LaunchPad widget or dashboard in any web page with an embed token.

An administrator issues the token in LaunchPad (Admin settings, Embed tokens) for one widget or
dashboard and the exact origins of the pages that may show it. The token is shown once.

## Plain iframe

```html
<iframe
  src="https://cloud.example.nl/index.php/apps/launchpad/embed/widget/42?token=<jwt>"
  title="Open requests"
  style="border: 0; width: 100%; height: 400px"></iframe>
```

## JavaScript (ESM)

```js
import { LaunchPadEmbed } from '@launchpad/embed-sdk'

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

The package ships its types (`src/types.ts`): `EmbedConfig`, `EmbedInstance`, `EmbedEventType`.

## Security

- Messages are only accepted from the embed's own window at the LaunchPad origin, and only
  posted to that origin, never `*`.
- The embed answers only a parent page whose origin the token lists; the server also refuses
  framing from other origins (`Content-Security-Policy: frame-ancestors`).
- A read token cannot change anything, whatever runs in the page: writes are refused by the
  server.
