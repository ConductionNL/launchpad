# Berichten Business Logic Flow

## Message Creation and Delivery

```mermaid
sequenceDiagram
    participant Gov as Government Application
    participant API as Berichten API
    participant DB as Database
    participant MO as Mijn Overheid Berichtenbox
    participant Portal as Citizen Portal

    Gov->>API: POST /berichten/api/v1/berichten/
    Note over Gov,API: onderwerp, berichtTekst,<br/>ontvanger (BSN URN),<br/>publicatiedatum, berichtType,<br/>bijlagen[]

    API->>DB: BEGIN TRANSACTION
    API->>DB: Create Bericht
    API->>DB: Bulk create Bijlagen
    API->>DB: COMMIT
    API-->>Gov: 201 Created {uuid, urn}

    Note over API,MO: If berichtType is set:<br/>Message forwarded to Mijn Overheid<br/>(external integration, not in this codebase)

    Note over Portal: Citizen checks portal after publicatiedatum
    Portal->>API: GET /berichten/api/v1/berichten/<br/>?ontvanger=urn:nld:brp:bsn:123
    API-->>Portal: List of visible messages

    Note over Portal: Read tracking via geopend_op<br/>(must be set by portal, not API)
```

## Message Visibility Logic

```mermaid
flowchart TD
    A[Bericht Created] --> B{publicatiedatum <= now?}
    B -->|No| C[Not yet visible]
    B -->|Yes| D{berichtType set?}

    D -->|Yes| E[Visible in Portal +<br/>Forwarded to Mijn Overheid]
    D -->|No| F[Visible in Portal only]

    E --> G{Bijlagen with<br/>is_bericht_type_bijlage = true?}
    G -->|Yes| H[Skip these bijlagen<br/>for Mijn Overheid<br/>(already in template)]
    G -->|No| I[Forward all bijlagen<br/>(max 1 PDF for MO)]
```

## API Immutability

```mermaid
flowchart LR
    A[POST /berichten/] --> B[Create OK]
    C[GET /berichten/] --> D[List OK]
    E[GET /berichten/uuid/] --> F[Retrieve OK]
    G[PUT /berichten/uuid/] --> H[405 Method Not Allowed]
    I[PATCH /berichten/uuid/] --> J[405 Method Not Allowed]
    K[DELETE /berichten/uuid/] --> L[405 Method Not Allowed]
```
