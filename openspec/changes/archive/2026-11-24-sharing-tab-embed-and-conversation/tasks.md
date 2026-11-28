# Tasks: sharing-tab-embed-and-conversation

- [x] Sharing tab: Embed on your website with the token shown once, the token table, the code for the website and New embed token (`SharingEmbedTokens.vue`; administrators only while the embed token API is admin-only)
- [x] Revoke from the table, effective within 60 seconds (REQ-EMB-009) (Withdraw uses the existing token revocation)
- [x] Sharing tab: Conversation beside this dashboard switch, only for a dashboard that is shared (`SharingConversation.vue`, `conversation_enabled` column, `DashboardDiscussion` honours it)
- [x] Translations in every shipped locale
- [x] Unit tests: token shown once, revoke, switch hidden on an unshared dashboard
- [x] Playwright: create an embed token for one widget and copy the code (written in `tests/e2e/sharing-tab-embed-and-conversation.spec.ts`, runs in nightly; not verified on a live instance)
