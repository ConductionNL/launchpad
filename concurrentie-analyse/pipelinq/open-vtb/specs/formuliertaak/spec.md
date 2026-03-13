---
status: competitor-analysis
source: https://github.com/maykinmedia/open-vtb
competitor: Maykin Media / Open VTB
date: 2026-03-13
---

# Formuliertaak (Form Task)

## Purpose

A task type that embeds a FormIO-compatible form definition within the task itself. The citizen fills in the form in a portal, and the received data is stored back on the task. This enables structured data collection without requiring an external form system.

## Data Model

The `details` field of an ExterneTaak with `taak_soort = "formuliertaak"` must conform to:

| Field | Type | Required | Description |
|---|---|---|---|
| formulierDefinitie | object | Yes | FormIO-compatible form definition |
| formulierDefinitie.components | array | Yes | Form components |
| formulierDefinitie.components[].label | string | Yes | Display label |
| formulierDefinitie.components[].key | string | Yes | Unique field identifier |
| formulierDefinitie.components[].type | string | Yes | Field type (text, number, date, etc.) |
| formulierDefinitie.components[].values | array | No | Option values [{label, value}] |
| formulierDefinitie.components[].fileTypes | array | No | Allowed file types [{label, value}] |
| formulierDefinitie.components[].format | string | No | Display format |
| formulierDefinitie.components[].enableTime | boolean | No | Enable time input |
| formulierDefinitie.components[].decimalLimit | number | No | Decimal precision |
| formulierDefinitie.components[].data | object | No | Data with values array |
| voorinvullenGegevens | object | No | Pre-fill data (key-value) |
| ontvangenGegevens | object | No | Received form data (key-value) |

### Validation
- `formulierDefinitie` is validated against both the FORMULIER_SCHEMA and FORMULIER_DEFINITIE_SCHEMA
- Each component must have label, key, and type

## Pipelinq Comparison

### Already in Pipelinq
- Schema-based data collection via OpenRegister

### Not yet in Pipelinq
- **FormIO-compatible form definitions** embedded in tasks
- **Pre-fill data** (voorinvullenGegevens) for forms
- **Received data capture** (ontvangenGegevens) on task completion
- **Component-level form specification** (label, key, type, values, fileTypes)
