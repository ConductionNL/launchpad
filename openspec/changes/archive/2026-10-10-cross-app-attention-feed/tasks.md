# Tasks: cross-app-attention-feed

- [x] 1. `AttentionSourceService`: read and check `appinfo/attention.json` of every app enabled for the user, translate with the app's own strings, report invalid declarations. Verify: PHPUnit with real files in a temp app folder.
- [x] 2. `GET /api/attention/sources`, signed-in users only. Verify: controller test.
- [x] 3. `src/services/attentionFeed.js`: resolve tokens, build the count request and the link from one filter, compare, rank. Verify: Vitest, including that the count's query and the link's query are the same filter.
- [x] 4. `AttentionWidget.vue` and its form, registered as `attention`, added to `lib/widget-types.json`. Verify: Vitest for the four states (items, nothing, failed, none declared).
- [x] 5. `en` and `nl` strings.
- [x] 6. "Mijn werkdag" version 2 with the widget on top. Verify: `ShippedTemplateServiceTest`.
- [ ] 7. Live check in a browser with at least one app carrying the file. Not done in this change's PR. (live pass, decision 139)
