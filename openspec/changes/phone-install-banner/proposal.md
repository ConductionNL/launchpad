# Proposal: phone-install-banner

## Why

Board LpMobiel draws, at the top of the workplace on a phone, a banner "Zet Launchpad op uw beginscherm. Dan opent uw
werkplek met één tik, net als een app." with "Niet nu" and "Installeren". REQ-PLB-006 lets a person install the page
through the browser's own "Add to home screen" option, but nothing on the page offers it, so few people find it.

## What changes

- `platform-baseline`: an install banner on a phone (REQ-PLB-009) that uses the browser's install prompt where the
  browser has one and explains the steps where it has not. LaunchPad still ships no manifest or service worker of its
  own (REQ-PLB-006).

## Impact

- A banner component in the workspace shell, translations. "Niet nu" is a per-device convenience kept in the browser.
