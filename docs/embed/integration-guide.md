# Integrating an embed

There are two ways to show an embed. Both need the token and the LaunchPad address,
for example `https://cloud.example.nl/index.php/apps/launchpad`.

## 1. Plain iframe

```html
<iframe
  src="https://cloud.example.nl/index.php/apps/launchpad/embed/widget/42?token=<jwt>"
  title="Open requests"
  style="border: 0; width: 100%; height: 400px"></iframe>
```

Give the iframe a `title` that says what it shows: screen readers announce it.

## 2. JavaScript SDK

The SDK creates the iframe, completes a handshake with it, relays its events and resizes it.

```js
import { LaunchPadEmbed } from '@launchpad/embed-sdk'

const embed = await LaunchPadEmbed.render(document.getElementById('woo'), {
  baseUrl: 'https://cloud.example.nl/index.php/apps/launchpad',
  token: '<jwt>',
  subjectType: 'dashboard',
  subjectId: '<dashboard uuid>',
  title: 'WOO requests',
})
```

See the [SDK reference](sdk-reference.md).

## Your website's own headers

The embed is shown only on the origins listed in the token. Your own
`Content-Security-Policy` must allow framing the LaunchPad address, for example
`frame-src https://cloud.example.nl`.
