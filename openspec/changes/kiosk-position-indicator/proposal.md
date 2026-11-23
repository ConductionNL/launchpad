# Proposal: kiosk-position-indicator

## Why

Board LpKiosk draws the canteen screen without app chrome, and in its bottom right corner "2 van 4 · volgende over
18 s": which dashboard of the playlist is showing and when the next one comes. REQ-KIOSKUI-002 says the kiosk link
plays the playlist "with no login and no controls", and no requirement shows the position.

## What changes

- `dashboard-kiosk-mode`: a quiet position line on a playlist of more than one dashboard (REQ-KIOSKUI-003). It is text,
  not a control.

## Impact

- The kiosk render (playlist player), translations.
