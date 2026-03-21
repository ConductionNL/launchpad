---
competitor: espocrm
analyzed_date: 2026-03-14
feature: sales-pipeline
---

# Sales Pipeline

## Overview

EspoCRM's sales pipeline is built around the **Opportunity** entity with a configurable `stage` enum field. Each stage maps to a `probability` percentage, and the system provides built-in pipeline reports and a Kanban view.

## Opportunity Entity

### Core Fields
- **name** (varchar, required) - Deal name
- **amount** (currency, required) - Deal value with multi-currency support
- **amountConverted** (currencyConverted, read-only) - Auto-converted to base currency
- **amountWeightedConverted** (float, computed) - `amount * probability * exchangeRate / 100`
- **stage** (enum, audited) - Pipeline stage
- **lastStage** (enum) - Previous stage (for won/lost tracking)
- **probability** (int, 0-100) - Win probability percentage
- **closeDate** (date, required, audited) - Expected close date
- **leadSource** (enum) - Origin of the opportunity
- **account** (link) - Associated company
- **contacts** (linkMultiple, M:N with role) - Associated people
- **contact** (link) - Primary contact
- **campaign** (link) - Source campaign
- **originalLead** (linkOne) - Lead it was converted from

### Default Stages with Probability Map
```json
{
    "Prospecting": 10,
    "Qualification": 20,
    "Proposal": 50,
    "Negotiation": 80,
    "Closed Won": 100,
    "Closed Lost": 0
}
```

Stages are fully customizable via the admin Field Manager. The probability map is stored as metadata on the stage field definition.

### Stage Styling
- Proposal: primary (blue)
- Negotiation: warning (orange)
- Closed Won: success (green)
- Closed Lost: info (grey)

## Probability Auto-Assignment

The `Probability` hook (`Hooks/Opportunity/Probability.php`) automatically sets the probability when creating a new opportunity based on the stage's probability map. This only fires on creation and only when probability is not explicitly set.

## Contact Roles on Opportunities

Contacts linked to opportunities have a `role` column on the M:N relationship:
- Decision Maker
- Evaluator
- Influencer
- (empty/custom)

This enables tracking multiple stakeholders per deal.

## Pipeline Reports

### Built-in Report Classes (in `Tools/Opportunity/Report/`)

1. **SalesPipeline** - Bar chart of deal values grouped by stage (excludes lost stages), filterable by date range and team. Supports fiscal year shifts.

2. **SalesByMonth** - Monthly revenue trend line, showing won deals over time.

3. **ByStage** - Distribution of opportunities across stages.

4. **ByLeadSource** - Attribution analysis showing which lead sources generate the most revenue.

All reports use the `SelectBuilderFactory` with strict access control, ensuring users only see data they have permission to view.

### Weighted Pipeline
The `amountWeightedConverted` is a computed virtual field that calculates:
```
deal_amount * probability_percentage * exchange_rate / 100
```
This enables weighted pipeline totals and forecasting without stored data.

## Kanban View for Pipeline

Opportunities can be viewed as a Kanban board where columns represent stages. See the [Kanban Board spec](../kanban-board/spec.md) for details.

## Auditing

The `stage`, `amount`, `closeDate`, `leadSource`, `account`, and `campaign` fields are all `audited: true`, creating a full change history in the activity stream.

## Relevance to Pipelinq

### What EspoCRM does well
- Simple, proven pipeline model with probability mapping
- Multi-currency with automatic conversion
- Weighted pipeline calculations
- Contact role tracking per deal
- Built-in pipeline visualization reports

### Opportunities for Pipelinq
- **Multiple pipelines**: EspoCRM has one global pipeline (one stage list). Pipelinq could support multiple pipeline definitions per team/use case
- **Custom pipeline stages per schema**: In OpenRegister, each schema can define its own stage progression
- **Pipeline analytics**: EspoCRM reports are static; Pipelinq could provide real-time analytics via n8n workflows
- **Automation**: No automated stage transitions in EspoCRM open-source; Pipelinq + n8n can trigger workflows on stage changes
- **Collaborative deals**: EspoCRM lacks real-time collaboration; Pipelinq inherits Nextcloud's collaboration features
