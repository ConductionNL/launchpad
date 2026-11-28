---
description: Start a new OpenSpec change for LaunchPad
---

Start an OpenSpec change named `$ARGUMENTS` (kebab-case).

1. Read `openspec/config.yaml` (project context and per-artifact rules) and
   look at one or two existing changes in `openspec/changes/` and
   `openspec/changes/archive/` for the house structure.
2. Check `openspec/specs/` for the capabilities the change touches.
3. Create `openspec/changes/<name>/` with:
   - `proposal.md`: Why, What Changes, Capabilities, Impact.
   - `design.md`: Context, Goals / Non-Goals, Decisions, Risks / Trade-offs.
   - `tasks.md`: checklist of implementation and test tasks.
   - `specs/<capability>/spec.md`: delta requirements with GIVEN/WHEN/THEN
     scenarios.
   Most existing changes also carry `screens.md`, listing the screens the
   change touches.
4. Accepted behaviour lives in `openspec/specs/`. When the change is done,
   its delta specs are merged there and the change folder moves to
   `openspec/changes/archive/<YYYY-MM-DD>-<name>/`.
