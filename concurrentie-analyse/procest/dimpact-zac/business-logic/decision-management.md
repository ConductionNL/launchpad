# Decision (Besluit) Management Flow

## Create Decision

```mermaid
flowchart TD
    A[Create decision request] --> B[Check policy: zaak.vastleggen_besluit]
    B --> C{Zaak open + not intake + has besluittype?}
    C -->|No| D[Policy denied]
    C -->|Yes| E[Validate publication dates]

    E --> F{publicatieIndicatie on besluittype?}
    F -->|No + dates provided| G[Error: dates not allowed]
    F -->|Yes + no pub date but response date| H[Error: pub date required]
    F -->|Yes + pub date but no response date| I[Error: response date required]
    F -->|Yes + response date before calculated| J[Error: response date too early]
    F -->|Valid| K[Convert to Besluit via BRC]

    K --> L[Create Besluit in BRC]
    L --> M[Link informatieobjecten to besluit]
    M --> N[Return created besluit]
```

## Update Decision

```mermaid
flowchart TD
    A[Update decision] --> B[Re-validate publication dates]
    B --> C[Update besluit in BRC]
    C --> D[Sync informatieobjecten]
    D --> E[Remove unlinked docs]
    E --> F[Add newly linked docs]
```

## Withdraw Decision

```mermaid
flowchart TD
    A[Withdraw decision] --> B[Set vervaldatum + vervalreden]
    B --> C{Withdrawal reason?}
    C -->|INGETROKKEN_OVERHEID| D[Format: "Overheid: reason"]
    C -->|INGETROKKEN_BELANGHEBBENDE| E[Format: "Belanghebbende: reason"]
    C -->|Other| F[No explanation]
    D --> G[Update besluit in BRC]
    E --> G
    F --> G
```
