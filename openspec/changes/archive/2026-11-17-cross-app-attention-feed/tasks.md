# Tasks: cross-app-attention-feed

- [x] 1. `AttentionSourceService`: read and check `appinfo/attention.json` of every app enabled for the user, translate with the app's own strings, report invalid declarations. Verify: PHPUnit with real files in a temp app folder.
- [x] 2. `GET /api/attention/sources`, signed-in users only. Verify: controller test.
- [x] 3. `src/services/attentionFeed.js`: resolve tokens, build the count request and the link from one filter, compare, rank. Verify: Vitest, including that the count's query and the link's query are the same filter.
- [x] 4. `AttentionWidget.vue` and its form, registered as `attention`, added to `lib/widget-types.json`. Verify: Vitest for the four states (items, nothing, failed, none declared).
- [x] 5. `en` and `nl` strings.
- [x] 6. "Mijn werkdag" version 2 with the widget on top. Verify: `ShippedTemplateServiceTest`.
- [x] 7. Live check in a browser with at least one app carrying the file. Done 17 Nov 2026 on a live NC 34 rig with OpenRegister 2.1.47 and pipelinq 0.5.21 (which ships `appinfo/attention.json`): for a member of `sales` with one ticket in progress past its deadline, "Vandaag eerst" showed "1 thing from all your apps that needs attention today", "Tickets past their deadline" with the reason and "Open these tickets". The count asked `assignee=werker&status=in_progress&slaDeadline[lt]=2026-11-18&_limit=1`, the link was `/apps/pipelinq/tickets?assignee=@me&status=in_progress&slaDeadline[lt]=@today%2B1d` and opens. Without pipelinq it read "None of your apps reports attention items yet.", and with the ticket out of the member's reach "Nothing needs your attention right now."
