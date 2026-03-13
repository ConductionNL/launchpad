# Contactverzoek Flow - KISS

Create request -> assign -> follow-up

```mermaid
flowchart TD
    A[KCM handling contactmoment] --> B{Can resolve question directly?}
    B -->|Yes| Z[Continue with contactmoment]
    B -->|No| C[Click 'Contactverzoek aanmaken']

    C --> D[Select assignment target]
    D --> E{Assignment type?}

    E -->|Department| F[Select afdeling from list]
    E -->|Group| G[Select groep from list]
    E -->|Employee| H[Search smoelenboek by name]

    F --> I{Has VragenSet?}
    G --> I
    H --> J[Auto-resolve organizational unit]
    J --> I

    I -->|Yes| K[Load dynamic intake form]
    K --> L[KCM fills in form fields]
    L --> M[Answers serialized to toelichting]
    I -->|No| M[KCM writes free-text toelichting]

    M --> N[Enter client contact details]
    N --> O{Client identified?}
    O -->|Yes| P[Pre-fill from OpenKlant partij]
    O -->|No| Q[Manual entry: name, email, phone]
    P --> R[Validate digital addresses]
    Q --> R

    R --> S{Which API backend?}
    S -->|OpenKlant 2| T[Create Klantcontact]
    T --> U[Create Betrokkene with digitale adressen]
    U --> V[Find or create Actor for assignee]
    V --> W[Create InterneTaak linking all]

    S -->|OpenKlant 1 / e-Suite| X[Create extended contactmoment POST]
    X --> Y[Include interneTaak data in payload]

    W --> AA[Contactverzoek saved]
    Y --> AA

    AA --> AB[Contactverzoek appears in overview list]
    AB --> AC{Assigned employee/dept views list}

    AC --> AD[Open contactverzoek details]
    AD --> AE[View: client info, toelichting, form answers]
    AE --> AF[Contact the citizen]
    AF --> AG{Resolved?}
    AG -->|Yes| AH[Mark as 'verwerkt']
    AG -->|No| AI[Add notes / reassign]
    AI --> AF

    AH --> AJ[Status updated in OpenKlant]
    AJ --> AK[Contactverzoek closed]

    style C fill:#f3e5f5
    style K fill:#fff3e0
    style W fill:#c8e6c9
    style AA fill:#c8e6c9
    style AH fill:#e8f5e9
```

## Key Design Decisions

1. **VragenSets**: Dynamic forms per department ensure the right information is captured upfront, reducing back-and-forth
2. **Actor resolution**: `ensureActoren()` finds or creates Actor records in OpenKlant 2, handling the many-to-many relationship between tasks and actors
3. **Dual API support**: The contactverzoek creation differs significantly between OpenKlant 2 (separate InterneTaak) and e-Suite (embedded in contactmoment POST)
4. **Status model**: Binary only (te_verwerken / verwerkt) — no intermediate states, unlike Pipelinq's multi-stage pipeline
5. **No reassignment tracking**: If a contactverzoek is reassigned, the original assignment is overwritten, not tracked as a history
