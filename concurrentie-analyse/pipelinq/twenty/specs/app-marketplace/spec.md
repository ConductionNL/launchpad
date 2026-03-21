---
competitor: twenty
analyzed_date: 2026-03-14
feature: App Marketplace & SDK
category: extensibility
maturity: alpha
---

# App Marketplace & SDK

## Summary

Twenty has an alpha-stage app platform with a TypeScript SDK, scaffold tool, and npm-based marketplace. Apps can define custom objects, fields, logic functions, UI components, views, and AI skills.

## SDK Architecture

### App Structure
```
src/
  application-config.ts   -- Required main config
  roles/                  -- Permission roles
  objects/                -- Custom object definitions
  fields/                 -- Field definitions
  logic-functions/        -- Business logic (triggers, pre/post-install)
  front-components/       -- React UI components
  views/                  -- Saved view configs
  navigation-menu-items/  -- Sidebar links
  skills/                 -- AI agent skills
```

### Entity Definitions
Uses `define<Entity>` helpers: `defineObject`, `defineField`, `defineLogicFunction`, `defineFrontComponent`, `defineRole`, `defineView`, `defineNavigationMenuItem`, `defineSkill`

Detection via AST parsing of export patterns, not file location.

### Development Workflow
```bash
npx create-twenty-app@latest my-app
yarn twenty app:dev       # Live sync to workspace
yarn twenty entity:add    # Add entities interactively
yarn twenty function:logs # Watch logs
```

**Prerequisites:** Node.js 24+, Yarn 4

## Distribution

| Channel | Scope | Review |
|---------|-------|--------|
| npm (public) | Global marketplace | None (auto-discovered with `twenty-app-` prefix) |
| Tarball (internal) | Single server | None |
| Dev mode | Local | N/A |

No monetization system. No review/approval process. CI/CD via included GitHub Actions.

## Relevance to Pipelinq

**Twenty's app platform strengths:**
- Full SDK with scaffold tool
- React component extensibility
- AI skill definitions
- npm-based distribution (frictionless)
- Live development sync

**Pipelinq differentiators:**
- Nextcloud app ecosystem is mature and battle-tested
- Nextcloud App Store has review process and quality controls
- PHP-based apps integrate deeply with Nextcloud server
- ExApp (External App) framework supports Python, Docker-based apps
- Broader platform: files, chat, calendar, mail, office suite built-in
