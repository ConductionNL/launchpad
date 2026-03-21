---
competitor: flowable
analyzed_date: 2026-03-14
feature: event-registry
module_path: modules/flowable-event-registry, modules/flowable-event-registry-api, modules/flowable-event-registry-model
---

# Event Registry

## Overview

Flowable's Event Registry is a cross-cutting module that provides a standardized way to receive and send events from/to external systems. It acts as an event broker abstraction layer that can trigger BPMN processes, CMMN cases, or be used within running instances.

## Core Concepts

### EventDefinition
Defines the structure of an event:
- `key` -- unique event identifier
- `name` -- display name
- `correlationParameters` -- fields used for event routing/matching
- `payload` -- event data fields
- Deployed as versioned artifacts

### ChannelDefinition
Defines how events are received or sent:
- **Inbound channels** -- receive events (JMS, Kafka, RabbitMQ, HTTP, etc.)
- **Outbound channels** -- send events to external systems
- `key`, `name`, `category`, `deploymentId`
- `ChannelModelProcessor` transforms raw messages to event instances

### Event Correlation
Events are matched to process/case instances using correlation parameters:
- `CorrelationKeyGenerator` produces correlation keys from event data
- `CorrelationValueTransformer` converts raw values to typed parameters
- Enables routing events to the correct running instance

### Event Consumer
`EventRegistryEventConsumer` processes incoming events:
- Routes to BPMN signal/message catch events
- Routes to CMMN event listeners
- Handles non-matching events via `EventRegistryNonMatchingEventConsumer`
- Returns `EventRegistryProcessingInfo` with consumer results

## Integration Points

### CMMN Integration
- `EventRegistryEventListenerActivityBehaviour` -- CMMN event listener that waits for events
- `SendEventActivityBehavior` -- sends events from within a case
- Case start via event subscription (`CaseInstanceStartEventSubscriptionBuilder`)
- Dynamic event subscriptions (subscribe/modify/delete at runtime)

### BPMN Integration
- Event-based start events (start process on event)
- Intermediate catch events (wait for event in running process)
- Boundary events (react to events during task execution)
- Event subprocesses

## Event Services

### EventRepositoryService
- Deploy event and channel definitions
- Query event/channel definitions
- Manage deployments
- Set/get channel model processing configuration

### EventRegistry
- `eventReceived(channelModel, event)` -- process incoming event
- Registration of inbound/outbound channel adapters
- Dynamic channel model processing

### EventManagementService
- Change tenant ID operations
- Administrative operations

## Procest Comparison

| Feature | Flowable Event Registry | Procest |
|---------|------------------------|---------|
| Event model | Typed event definitions with correlation | n8n triggers/webhooks |
| Channels | JMS, Kafka, RabbitMQ, HTTP | n8n trigger nodes |
| Correlation | Automatic routing to correct instance | Manual via n8n logic |
| Event-driven start | Native event-based case start | Webhook trigger |
| Event subscriptions | Dynamic subscribe/modify/delete | Static n8n configuration |
| Outbound events | Typed event sending | n8n HTTP/webhook nodes |
| Versioning | Deployed with definitions | n8n workflow versions |
