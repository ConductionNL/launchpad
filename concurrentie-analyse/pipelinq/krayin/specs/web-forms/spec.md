---
competitor: krayin
analyzed_date: 2026-03-14
feature: web-forms
priority: low
---

# Web-to-Lead Forms

## Overview

Krayin generates embeddable HTML forms that capture lead data from external websites. Forms are configured with selectable attributes and customizable styling (colors, labels, button text).

## Data Model

### WebForm
- form_id (unique identifier), title, description
- submit_button_label, submit_success_action/content
- Styling: background_color, form_background_color, form_title_color, form_submit_button_color, attribute_label_color

### WebFormAttribute
- Maps which attributes appear on each form

## Views

- Preview: standalone form page at public URL
- Embed: embeddable HTML snippet
- Controls: admin configuration

## Pipelinq Comparison Notes

- Standard CRM lead capture feature
- No CAPTCHA or spam protection
- No conditional fields or multi-step forms
- Pipelinq could use OpenRegister + web forms for similar functionality
