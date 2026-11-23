# Proposal: embed-outage-state

## Why

Board LpManagement draws the Power BI report card in its outage state: an "Outage" tag in the card head, the line "Embedded report · app.powerbi.com · signed in as Pieter Jansen", and in the body "The report could not be loaded", what the connection answered and when ("gave an error today at 09:30: token expired"), Reload and "Open in a new tab". On development the iframe widget shows "This site does not allow itself to be embedded. Its owner blocks framing, which cannot be overridden here" for every failure (REQ-IFRAME-004), with no tag, no source line, no time and no Reload.

## What changes

- `iframe-embed-widget`: the source line and the outage state of an embedded report (REQ-IFRAME-005).

## Impact

- The iframe widget renderer, the connection state it reads (app-connections), translations.
