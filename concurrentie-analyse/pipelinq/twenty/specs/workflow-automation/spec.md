---
competitor: twenty
analyzed_date: 2026-03-14
feature: Workflow Automation
category: automation
maturity: stable
---

# Workflow Automation

## Summary

Twenty has a built-in visual workflow engine with 7 trigger types, 12 action types, branching, iteration, and code execution. Workflows are a core differentiator from simpler CRMs.

## Trigger Types (7)

| Trigger | Notes |
|---------|-------|
| Record Created | Not ideal for manual creation (auto-save fires early) |
| Record Updated | Optional field-level monitoring |
| Record Created or Updated | Combined trigger |
| Record Deleted | Object-type scoped |
| Manual | Global, Single-record, or Bulk modes |
| Scheduled | Interval or cron; UTC timezone |
| Webhook | GET or POST with defined body |

## Action Types (12)

**Record:** Create, Update, Delete, Search (max 200), Upsert (by email/domain/ID/unique field)
**Flow:** Iterator (loop arrays), Filter (conditional gate), Delay (duration or date; 1 credit)
**Communication:** Send Email (single recipient, no HTML signatures), Form (manual triggers only)
**Integration:** Code (custom JavaScript), HTTP Request (full REST methods)
**AI:** AI Agent (coming soon -- prompt-based analysis, classification, generation)

## Advanced Features

- **Step chaining:** Reference fields from any previous step's output
- **Versioning:** Draft and published versions
- **Run monitoring:** Execution tracking and debugging
- **Credits system:** Actions consume workflow credits
- **Branching:** Parallel paths and conditional logic
- **Iterator:** Loop through arrays with multiple nested actions

## Common Patterns

- Post-close automations (closed-won triggers)
- Stale opportunity detection
- Formula fields via workflow
- External data sync (Typeform, warehouses)
- PDF/quote/invoice generation
- Email count tracking
- Task due date alerts
- Lead categorization

## Relevance to Pipelinq

Twenty's workflow engine is tightly integrated with the CRM, which is both a strength and a limitation:

**Strengths vs Pipelinq:**
- Built-in, no external dependency (unlike n8n integration)
- Visual workflow builder in the same UI
- Direct access to CRM data in every step
- Webhook triggers for external integration

**Pipelinq differentiators:**
- n8n provides 400+ pre-built node types vs Twenty's 12 action types
- n8n has a much larger integration ecosystem
- Pipelinq pipelines are purpose-built for stage-based workflows, not generic automation
- OpenRegister's MCP protocol enables AI-native workflow interaction
- Nextcloud ecosystem provides document management, collaboration, and more built-in
