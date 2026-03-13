# Contactmoment Flow - KISS

Start interaction -> search -> link -> close

```mermaid
flowchart TD
    A[KCM receives call/walk-in/email] --> B{Start new contactmoment?}
    B -->|Yes| C[Create new session with unique ID]
    B -->|No| Z[Handle outside KISS]

    C --> D[Select channel: telefoon/balie/email/chat]
    D --> E[Search for citizen or business]

    E --> F{Search type?}
    F -->|Person| G[Search BRP: BSN / name+DOB / postcode]
    F -->|Business| H[Search KvK: name / KvK-nr / vestigingsnr]
    F -->|Skip| I[Continue without identification]

    G --> J{Person found?}
    H --> J
    J -->|Yes| K[Link klant to contactmoment]
    J -->|No| L[Register new klant in OpenKlant]
    L --> K
    I --> M

    K --> M[Handle question - vraag]

    M --> N{KCM needs info?}
    N -->|Yes| O[Search knowledge base]
    O --> P{Source type?}
    P -->|Kennisartikel| Q[View SDG product page]
    P -->|VAC| R[View Q&A pair]
    P -->|Werkinstructie| S[View work instruction]
    P -->|Smoelenboek| T[View employee details]
    Q --> U[Track source as bron]
    R --> U
    S --> U
    T --> U
    U --> N

    N -->|No| V{Need to link case?}
    V -->|Yes| W[Search zaaksysteem]
    W --> X[Link zaak to vraag]
    X --> V
    V -->|No| Y{Another question?}

    Y -->|Yes| AA[Add new vraag to contactmoment]
    AA --> M

    Y -->|No| AB{Need follow-up?}
    AB -->|Yes| AC[Create contactverzoek]
    AC --> AD[Assign to dept/group/employee]
    AD --> AE[Fill intake form if configured]
    AE --> AF[Save contactverzoek]

    AB -->|No| AG[Navigate to finalization screen]
    AF --> AG

    AG --> AH[Review: channel, questions, notes, result]
    AH --> AI[Select gespreksresultaat]
    AI --> AJ{Validate required fields}
    AJ -->|Invalid| AH
    AJ -->|Valid| AK[Save to OpenKlant API]
    AK --> AL[Save extended details to BFF PostgreSQL]
    AL --> AM[Log verwerking entries]
    AM --> AN[Close session / remove tab]
    AN --> AO[Ready for next interaction]

    style C fill:#e1f5fe
    style K fill:#c8e6c9
    style U fill:#fff3e0
    style AF fill:#f3e5f5
    style AK fill:#c8e6c9
    style AN fill:#ffcdd2
```

## Key Decision Points

1. **Identification**: KCM can proceed without identifying the citizen, but linking enables contact history view
2. **Source tracking**: Every knowledge article, VAC, or employee consulted is tracked with `shouldStore` flag
3. **Multi-question**: A single call can generate multiple vragen, each with their own linked cases and sources
4. **Contactverzoek**: Created only when the KCM cannot resolve the question directly
5. **Finalization**: All vragen within the contactmoment are saved as a batch
