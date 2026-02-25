# Spec-Driven Development Documentation

Documentation for the Conduction workspace's spec-driven development workflow, combining OpenSpec, GitHub Issues, and Ralph Wiggum loops.

## Guides

### [Getting Started](./getting-started.md)
Step-by-step guide from installation to your first completed change. Start here if you're new to the workflow.

### [Workflow Overview](./workflow.md)
Architecture overview of the full system: how specs, GitHub Issues, plan.json, and Ralph Wiggum loops fit together. Includes the plan.json format and flow diagrams.

### [Command Reference](./commands.md)
Detailed reference for every command — both OpenSpec built-in (`/opsx:new`, `/opsx:ff`, etc.) and custom Conduction commands (`/opsx:plan-to-issues`, `/opsx:ralph-start`, `/opsx:ralph-review`). Includes expected output and usage tips.

### [Writing Specs](./writing-specs.md)
In-depth guide on writing effective specifications: RFC 2119 keywords, Gherkin scenarios, delta specs, shared spec references, task breakdown, and common mistakes to avoid.

### [Adding a Project](./adding-a-project.md)
How to onboard a new project into the workflow: `openspec init`, configuration, project.md, and linking to shared specs.

### [End-to-End Walkthrough](./walkthrough.md)
A complete worked example showing every phase of the flow on a realistic feature (adding a search endpoint to OpenCatalogi). Shows exactly what you type and what happens.

## Quick Reference

### The Flow
```
/opsx:new → /opsx:ff → /opsx:plan-to-issues → /opsx:ralph-start → /opsx:ralph-review → /opsx:archive
  define      spec        GitHub Issues            implement           verify              complete
```

### Shared Specs
| Spec | Covers |
|------|--------|
| `specs/nextcloud-app/spec.md` | App structure, DI, route ordering, config |
| `specs/api-patterns/spec.md` | URLs, CORS, auth, pagination, errors |
| `specs/nl-design/spec.md` | Design tokens, themes, accessibility |
| `specs/docker/spec.md` | Development environment conventions |

### Key Files
| File | Purpose |
|------|---------|
| `apps-extra/project.md` | Generic coding standards (all projects) |
| `apps-extra/CLAUDE.md` | Claude Code workflow instructions |
| `apps-extra/openspec/config.yaml` | Shared schema config |
| `{project}/project.md` | Project-specific context |
| `{project}/openspec/config.yaml` | Project-specific OpenSpec config |
| `{project}/openspec/changes/*/plan.json` | Task JSON for Ralph Wiggum |
