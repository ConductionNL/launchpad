---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Process Task Implementations
category: integration
---

# Process Tasks

## Overview

CMMN ProcessTasks are automated tasks that execute without human intervention. CaseFabric provides several built-in process task implementations for common integration patterns, each running as a separate Akka actor or inline within the case actor.

## Implementation Details

### Architecture

Process tasks can run in two modes:
- **Actor-based** (`ProcessTaskActor`) -- separate Akka actor with its own lifecycle
- **Inline** (`InlineSubProcess`) -- executes within the case actor's thread

Base classes:
- `ProcessDefinition` / `SubProcessDefinition` -- definition parsing
- `SubProcess` -- runtime execution base
- `ProcessTaskActorInformer` / `ProcessTaskInlineInformer` -- callback to case actor

### HTTP Call (`HTTPCall`)

Executes HTTP requests to external services:

- `HTTPCallDefinition` -- parsed from XML, specifies URL, method, headers, body
- `HTTPCall` extends `SubProcess` -- runtime execution
- Input parameters mapped to request (URL, headers, body)
- Response mapped to output parameters (status, headers, body)
- `Result` class wraps the HTTP response

### Mail / SMTP (`Mail`)

Sends emails via configured SMTP server:

- `MailDefinition` -- template for email construction
- `AddressTemplate` -- from/to/cc/bcc address expressions
- `BodyTemplate` -- email body with template substitution
- `AttachmentTemplate` / `Attachment` -- file attachments
- `CalendarInvite` -- iCal calendar invitation support
- `InvalidMailAddressException` / `InvalidMailException` -- validation

Configuration in `cafienne.engine.mail-service`:
- SMTP host, port, authentication
- javax.mail properties passthrough

### PDF Report (`PDFReport`)

Generates PDF documents using JasperReports:

- `PDFReportDefinition` -- references JasperReports template
- `JasperDefinition` / `JasperSubReportDefinition` -- report structure
- `ReportDataDefinition` -- data source configuration
- `PDFReport` extends `SubProcess` -- runtime generation
- Output: binary PDF content

### Calculation (`Calculation`)

Data transformation without external calls:

- `CalculationDefinition` -- defines transformation steps
- `StepDefinition` -- base for calculation steps
  - `MapStepDefinition` -- transform/map data
  - `FilterStepDefinition` -- filter data
- Expression definitions for conditions and transformations
- `InputSource` / `Source` -- data source abstraction
- `Result` -- transformation output

### SMTP Call (`SMTPCall`)

Lower-level SMTP integration:

- `SMTPCallDefinition` -- raw SMTP parameters
- Used internally by Mail implementation

### Parameter Mapping

All process tasks use CMMN parameter mapping:
- `ParameterMappingDefinition` -- maps case file items to task inputs and outputs
- `InputParameterDefinition` / `OutputParameterDefinition` -- parameter contracts
- `TaskInputParameterDefinition` / `TaskOutputParameterDefinition` -- task-specific
- `BindingOperation` -- controls how values are bound (merge, replace, etc.)

### Process Lifecycle Events

| Event | Description |
|-------|-------------|
| `ProcessStarted` | Process execution begins |
| `ProcessCompleted` | Process finished successfully |
| `ProcessFailed` | Process execution failed |
| `ProcessSuspended` | Process paused |
| `ProcessResumed` | Process resumed |
| `ProcessReactivated` | Process restarted after failure |
| `ProcessTerminated` | Process killed |

## Relevance for Procest

1. **HTTP integration** -- essential for connecting to external services (ZGW APIs, etc.)
2. **Email/SMTP** -- case notifications, though Procest may prefer n8n for this
3. **PDF generation** -- document creation from case data (permits, decisions)
4. **Calculation tasks** -- data transformation without external dependencies
5. **Parameter mapping** -- declarative data flow between case and integration
