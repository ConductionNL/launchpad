# Valtimo ZGW Integration

Sources:
- https://docs.valtimo.nl/fundamentals/getting-started/modules/zgw
- Various ZGW feature pages

## Overview

GZAC (Generiek Zaakafhandelcomponent) is Valtimo's edition tailored for the Dutch Common Ground Zaakgericht Werken (ZGW) API landscape. It adds ZGW-specific modules and plugins to the core platform.

## Architecture

GZAC operates as a process-driven component in the Common Ground architecture:
- **Directs processes** while delegating other tasks to other components
- Archives case information necessary for customer requests
- All tasks and actions processed/managed by associated BPMN processes

## Supported ZGW APIs

### Zaken API
- Case lifecycle management
- Create, update, close cases
- Link documents, decisions, and contacts to cases

### Documenten API
- Document storage and retrieval per ZGW standard
- Upload with metadata support
- Document versioning

### Catalogi API
- Case type definitions (zaaktypen)
- Document type definitions (informatieobjecttypen)
- Status type definitions
- Result type definitions

### Besluiten API
- Decision record management
- Link decisions to cases

### Notificaties API
- Publish-subscribe messaging
- Event-driven inter-system communication

### Objecten API + Objecttypen API
- Generic object storage
- Flexible object type definitions
- Used for custom data beyond standard ZGW types

### Klanten API
- Customer data management
- Citizen and organization records

### Contactmomenten API
- Contact registration
- Email and communication logging

### Haalcentraal BRP
- National civilian registry lookup
- Personal data retrieval

## OpenZaak Integration

- Authentication connector for OpenZaak installations
- OpenZaak provides the ZGW API backend
- Valtimo GZAC + OpenZaak = complete ZGW case management stack

## NL Portal Integration

- Citizen-facing portal for case status and self-service
- Separate component that integrates with Valtimo
- Portaaltaak module bridges tasks between Valtimo and NL Portal
