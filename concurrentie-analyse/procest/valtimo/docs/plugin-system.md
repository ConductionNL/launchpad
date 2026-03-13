# Valtimo Plugin System

Sources:
- https://docs.valtimo.nl/fundamentals/architectural-overview/modules
- Search results for plugin architecture

## Overview

Plugins are extensions on the Valtimo platform that connect to external or internal services. Some are provided by the platform; developers can create custom ones.

## Plugin Architecture

### Plugin Definition
A plugin definition specifies:
- Configurable properties
- Available actions
- Event handlers

### Plugin Configuration
- A single plugin can be configured multiple times
- Example: A Twitter plugin with separate configs for different users
- Each config stores its own property values

### Plugin Properties
- Configurable per-instance
- Example: username, password, API keys, URLs

### Plugin Actions
- Executable operations per plugin
- Attached to BPMN activities via Process Links
- Example: post a tweet, upload a document, send an email

### Plugin Events
- Lifecycle hooks: created, updated, deleted
- Used for setup/teardown (e.g., external service approval)

## Extensibility

- Custom plugins via Java, Kotlin, or Angular
- Integrated as dependencies
- Interact with processes through Process Links
- Actions attached to BPMN activities

## Available Built-in Plugins

### Core Plugins
- Flowmailer (email)
- SmartDocuments (document generation)

### ZGW Plugins
- Zaken API
- Documenten API
- Besluiten API
- Catalogi API
- Objecten API
- Objecttypen API
- Klanten API
- Contactmomenten API
- Notificaties API
- OpenZaak (auth)
- Portaaltaak
- Verzoek

### Other
- Haalcentraal BRP (civilian data)
- Wordpress Mail

## Process Exchange / Building Blocks

Reusable plugin+process combinations shared via the community:
- Repository: https://github.com/generiekzaakafhandelcomponent/Bouwblokken
- Contains BPMN definitions, forms, form flows, plugins, frontend/backend code
- Community-contributed with pull request workflow
