# Tasks: search-ai-dashboard-assistant

- [ ] 1. Registry entry `ai-assistant`, form sub-component and manifest entry with `requires.apps: ['hermiq']`. Verify: the registry completeness test and `npm run check:manifest`, red first.
- [ ] 2. `AiAssistantWidget.vue`: health probe, disabled state naming hermiq, chat mode through `useAiChatStream` with the dashboard context snapshot. Verify: Vitest with the real hermiq response shape.
- [ ] 3. Summary mode for placements under 6 cells. Verify: Vitest.
- [ ] 4. Scan test: no LLM SDK import and no direct model URL in `src/`. Verify: Vitest.
- [ ] 5. Strings in every shipped locale through the writing skill.
- [ ] 6. On archive fold the delta into `openspec/specs/launchpad-ai-dashboard-assistant/spec.md` and set `a-assistant` to `built`.
