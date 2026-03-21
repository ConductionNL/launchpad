---
competitor: monica
analyzed_date: 2026-03-14
feature: template-module-system
category: customization
---

# Template and Module System

## Overview

Monica has a sophisticated template/module system that allows administrators to define what information appears on contact pages. Templates contain pages, pages contain module rows, and module rows contain fields. This makes the contact detail view fully customizable.

## Architecture

### Templates
- Define the overall layout structure for a contact page
- Multiple templates can exist (e.g., "Friend", "Family Member", "Colleague")
- Each contact is assigned one template
- Templates are managed at the account settings level

### Template Pages
- Sub-sections within a template
- Ordered (positionable)
- Each page contains module rows

### Modules
- Reusable UI components that can be placed on template pages
- Built-in modules correspond to contact sub-domains (notes, calls, activities, etc.)
- Modules can be enabled/disabled globally
- Module rows define where modules appear on a page
- Module row fields define specific field configurations

## Settings Configuration

Managed under Settings > Personalize:
- **Templates:** Create, edit, delete templates; manage pages and their positions
- **Template Pages:** Add pages to templates, reorder them
- **Template Page Modules:** Assign modules to pages, reorder them
- **Modules:** Global enable/disable of available modules

## Data Model

```
Template
  -> TemplatePage (ordered)
    -> ModuleRow (ordered)
      -> ModuleRowField
Module (global, reusable)
```

## Technical Implementation

- Settings: `app/Domains/Settings/ManageTemplates/` (Services, Web)
- Settings: `app/Domains/Settings/ManageModules/` (Web)
- Models: Template, TemplatePage, Module, ModuleRow, ModuleRowField
- Vue pages: Settings/Personalize/Templates/, Settings/Personalize/Modules/

## Relevance to Pipelinq

This is one of Monica's most architecturally interesting features for Pipelinq:
1. **Configurable entity views** — The same concept could let users customize what fields/modules appear on pipeline stage cards
2. **Multiple templates per entity type** — Different pipeline types could have different stage card layouts
3. **Module system** — Reusable, positionable UI components map directly to pipeline card widgets
4. **Per-entity template assignment** — Each pipeline item could display differently based on its type
5. **Admin-controlled customization** — Keeps the UI consistent while allowing flexibility
