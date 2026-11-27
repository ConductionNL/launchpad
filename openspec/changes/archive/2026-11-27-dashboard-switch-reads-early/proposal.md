# Proposal: dashboard-switch-reads-early

## Why
Switching to another dashboard in the workspace (More, the switcher, an address) is over the in-app budget (ADR-124: <= 300 ms after boot; done-launchpad measured 1.2-1.5 s per switch on the sandbox). The reads that follow the switch run one after another: the store reads the dashboard (`GET /api/dashboard/{id}`), the view renders it, and only then do the reactions bar (`GET /api/dashboards/{uuid}/reactions`) and, on a dashboard somebody else owns, the personal layer (`GET /api/dashboards/{id}/personal-layer`) ask for their data. Both are known before the dashboard read answers: the switcher's row already carries the dashboard's `uuid`, `id` and `isOwner`.

## What
When the store switches dashboards it starts those two reads beside the dashboard read. The app's HTTP client keeps each in-flight read for a short while (5 s) and answers the first matching GET of a component from it, once; it does not send a second request. A read the component never asks for expires; the next switch drops what the previous switch started. A failing early read answers its GET with the same error. Every other request goes out as before.

## Impact
- One round trip less per switch (the reactions and personal-layer reads overlap the dashboard read instead of following the render).
- No server change, no new endpoint.
