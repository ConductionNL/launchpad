# Plugin Action Flow -- Valtimo

## How Plugins Are Triggered from BPMN

```mermaid
flowchart TD
    BPMN([BPMN Process Execution]) --> ACTIVITY[Operaton reaches a BPMN activity]
    ACTIVITY --> LOOKUP{ProcessLink lookup\nfor activity ID}

    LOOKUP -->|No link found| DEFAULT[Default Operaton behavior\nJava Delegate / Expression]
    LOOKUP -->|Link found| LINK_TYPE{ProcessLink type?}

    LINK_TYPE -->|PLUGIN| PLUGIN_FLOW
    LINK_TYPE -->|FORM| FORM_FLOW
    LINK_TYPE -->|FORM_FLOW| FORMFLOW_FLOW
    LINK_TYPE -->|BUILDING_BLOCK| BLOCK_FLOW

    subgraph PLUGIN_FLOW [Plugin Action Execution]
        direction TB
        P1[Read ProcessLink action properties] --> P2[Resolve PluginConfiguration by ID]
        P2 --> P3[Decrypt encrypted properties]
        P3 --> P4[Resolve input parameters via ValueResolvers]
        P4 --> P5{Value resolution}
        P5 -->|doc:/path| READ_DOC[Read from case document JSON]
        P5 -->|pv:name| READ_PV[Read from process variable]
        P5 -->|fixed value| READ_FIXED[Use literal value]
        P5 -->|env:KEY| READ_ENV[Read environment variable]
        READ_DOC --> P6
        READ_PV --> P6
        READ_FIXED --> P6
        READ_ENV --> P6
        P6[Invoke @PluginAction method with resolved params]
        P6 --> P7{Action result}
        P7 -->|Success| P8[Store output as process variables]
        P7 -->|Failure| P9[Throw BpmnError or RuntimeException]
        P8 --> P10[Continue process flow]
        P9 --> P11{Error boundary\nevent defined?}
        P11 -->|Yes| P12[Follow error path]
        P11 -->|No| P13[Process instance fails]
    end

    FORM_FLOW[Form: Render Form.io\nto user task]
    FORMFLOW_FLOW[FormFlow: Start\nmulti-step wizard]
    BLOCK_FLOW[Building Block: Execute\nreusable call activity]

    DEFAULT --> CONTINUE([Continue Process])
    P10 --> CONTINUE
    P12 --> CONTINUE
    FORM_FLOW --> CONTINUE
    FORMFLOW_FLOW --> CONTINUE
    BLOCK_FLOW --> CONTINUE

    style BPMN fill:#e1f5fe
    style CONTINUE fill:#c8e6c9
    style P13 fill:#ffcdd2
```

## Plugin Discovery and Registration

```mermaid
flowchart LR
    subgraph STARTUP [Application Startup]
        direction TB
        S1[Spring context initializes] --> S2[Scan for @Plugin annotations]
        S2 --> S3[Register PluginDefinition in DB]
        S3 --> S4[Scan for @PluginAction annotations]
        S4 --> S5[Register PluginActionDefinitions]
        S5 --> S6[Scan for @PluginProperty annotations]
        S6 --> S7[Register property schemas]
    end

    subgraph CONFIG [Admin Configuration]
        direction TB
        C1[Admin selects plugin type] --> C2[Fill in plugin properties]
        C2 --> C3[Sensitive props encrypted]
        C3 --> C4[PluginConfiguration saved to DB]
    end

    subgraph LINK [Process Link Setup]
        direction TB
        L1[Admin opens process link UI] --> L2[Select BPMN activity]
        L2 --> L3[Choose plugin + action]
        L3 --> L4[Map input parameters\nwith value resolver expressions]
        L4 --> L5[ProcessLink saved to DB]
    end

    STARTUP --> CONFIG --> LINK

    style STARTUP fill:#e8f5e9
    style CONFIG fill:#fff3e0
    style LINK fill:#e3f2fd
```

## Plugin Lifecycle Events

```mermaid
flowchart LR
    CREATE[Config Created] -->|@PluginEvent CREATED| SETUP[Run setup logic\ne.g. register webhook]
    UPDATE[Config Updated] -->|@PluginEvent UPDATED| REFRESH[Refresh connections\ne.g. re-auth]
    DELETE[Config Deleted] -->|@PluginEvent DELETED| CLEANUP[Run teardown\ne.g. unregister webhook]
```

## Example: ZGW Zaken API Plugin Action

```
BPMN Service Task: "Create Zaak"
  |
  +-- ProcessLink: plugin action
  |     pluginConfigId: "zaken-api-config-1"
  |     actionKey: "create-zaak"
  |     inputs:
  |       zaakTypeUrl: "https://catalogi.example.nl/api/v1/zaaktypen/abc-123"
  |       startDate: "pv:requestDate"
  |       description: "doc:/request/summary"
  |
  +-- Runtime:
        1. Resolve "zaken-api-config-1" -> base URL, auth config
        2. Decrypt OpenZaak credentials
        3. Resolve "pv:requestDate" -> "2026-03-13"
        4. Resolve "doc:/request/summary" -> "Vergunning aanvraag bouwwerk"
        5. POST to Zaken API -> zaak created
        6. Store zaak URL as process variable "zaakUrl"
```
