---
status: competitor-analysis
source: https://github.com/maykinmedia/open-vtb
competitor: Maykin Media / Open VTB
date: 2026-03-13
---

# Gegevensuitvraagtaak (Data Request Task)

## Purpose

A task type that links to an external form/survey for data collection. Unlike formuliertaak which embeds the form, this task type points to an external URL where the citizen fills in the data. Pre-fill data can be passed to the external system, and received data is stored on completion.

## Data Model

The `details` field of an ExterneTaak with `taak_soort = "gegevensuitvraagtaak"` must conform to:

| Field | Type | Required | Description |
|---|---|---|---|
| uitvraagLink | URI | Yes | Link to external data request form |
| voorinvullenGegevens | object | No | Pre-fill data to pass to external system |
| ontvangenGegevens | object | No | Data received back from external system |

## Pipelinq Comparison

### Already in Pipelinq
- External system integration via OpenConnector

### Not yet in Pipelinq
- **External form linking** with pre-fill data pass-through
- **Received data capture** from external systems on task completion
- **Structured data exchange** pattern for citizen-facing forms
