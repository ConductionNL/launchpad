---
competitor: flowable
analyzed_date: 2026-03-14
feature: DMN Decision Engine
category: core-engine
---

# DMN Decision Engine

## What It Is

Flowable's DMN (Decision Model and Notation) engine provides rule-based decision automation using the OMG DMN 1.1 standard. It can run standalone, embedded in applications, or plugged into the BPMN/CMMN engines.

## Key Capabilities

### Decision Modeling
- Decision tables with input/output columns
- Hit policies: UNIQUE, FIRST, PRIORITY, ANY, COLLECT, RULE ORDER, OUTPUT ORDER
- Input/output expressions using JUEL
- Decision requirements diagrams (DRD)
- Custom function delegates for extensibility

### Engine Configuration
- Standalone or embedded deployment
- Plugs into BPMN engine via DmnEngineConfigurator
- Spring/Spring Boot integration
- XML-based configuration (flowable.dmn.cfg.xml)
- Programmatic configuration

### Strict Mode
- Enabled by default - enforces DMN 1.1 hit policy constraints
- Can be disabled for lenient evaluation
- Violations logged in audit log as validation messages

### Database
- Tables prefixed with `ACT_DMN_`
- Key tables: DECISION_TABLE, DEPLOYMENT, DEPLOYMENT_RESOURCE
- Liquibase for schema management
- Same multi-database support as other engines

### REST API
- Decision table deployment
- Decision execution
- Decision table queries
- Deployment management

## Technical Details

```
DmnEngine dmnEngine = DmnEngines.getDefaultDmnEngine();

// Or programmatically:
DmnEngine dmnEngine = DmnEngineConfiguration
    .createStandaloneInMemDmnEngineConfiguration()
    .setDatabaseSchemaUpdate("true")
    .setJdbcUrl("jdbc:h2:mem:my-db")
    .buildDmnEngine();
```

### Configuration Classes
- `StandaloneDmnEngineConfiguration` - standalone with Flowable-managed transactions
- `StandaloneInMemDmnEngineConfiguration` - in-memory H2 for testing
- `SpringDmnEngineConfiguration` - Spring-managed transactions

### Extensibility
- Custom Flowable Function Delegates (extend `AbstractFlowableFunctionDelegate`)
- Custom deployment cache implementations
- Configurable cache limits (LRU)

## Relevance to Procest

### Applicable Patterns
- Decision tables for business rule evaluation
- Integration of decisions within case/process flow
- Hit policies for handling multiple matching rules
- Audit logging of decision outcomes

### Key Differences
- Flowable implements formal DMN 1.1; Procest could use simpler rule evaluation
- DMN requires modeling expertise; Procest targets less technical users
- Flowable's DMN is Java-native; Procest would implement in PHP

### Opportunities
- Procest can implement decision tables with a simpler, more accessible interface
- Focus on common government decision patterns (eligibility, routing, SLA)
- Provide a visual rule builder that doesn't require DMN knowledge
- Consider DMN import/export for interoperability with tools like Flowable
