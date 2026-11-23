# Proposal: sharing-tab-embed-and-conversation

## Why

Board LpDelen draws the dashboard settings on the tab Delen with two parts development does not show there. "Insluiten op uw website": a token that is shown once ("Token Website Zuiddrecht is aangemaakt. Kopieer het nu"), a table of tokens with what they show, the allowed website, the state and Intrekken, the code for the website and "Nieuw insluittoken"; revoking takes effect within 60 seconds. And "Gesprek bij dit dashboard": a switch that shows a Talk conversation beside a shared dashboard, which a personal dashboard without shares does not have.

Embed tokens are specced in `embedded-analytics` (REQ-EMB-001, REQ-EMB-009) and served by `EmbedTokenApiController`, but only from the administration's Embeds tab. The Talk conversation is `dashboard-talk-leaf` (REQ-LEAF-002), which renders on shared dashboards but has no switch in the sharing tab.

## What changes

- `embedded-analytics`: embed tokens of a dashboard on its Sharing tab (REQ-EMB-011).
- `dashboard-talk-leaf`: the switch for the conversation on the Sharing tab (REQ-LEAF-006).

## Impact

- `src/modals/DashboardConfigModal.vue` (Sharing panel), the embed token API, translations.
