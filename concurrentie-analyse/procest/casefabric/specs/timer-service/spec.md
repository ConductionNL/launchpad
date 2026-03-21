---
competitor: casefabric
analyzed_date: 2026-03-14
feature: Timer Service
category: core
---

# Timer Service

## Overview

CaseFabric implements CMMN TimerEvents through a dedicated timer service that supports scheduled, duration-based, and cron-based timer expressions. Timers are persisted to survive engine restarts.

## Implementation Details

### Timer Types

CMMN `TimerEventDefinition` supports:
- **Date/Time** -- fire at a specific instant
- **Duration** -- fire after a time period (ISO 8601 duration)
- **Cron** -- recurring schedule (via iCal4j library)

### Architecture

- `TimerEventSink` -- receives timer registrations from case actors
- `Timer` / `Scheduled` -- timer scheduling abstractions
- `TimerStoreProvider` -- pluggable persistence backend

### Persistence Backends

| Backend | Class | Use Case |
|---------|-------|----------|
| In-Memory | `InMemoryStore` | Testing |
| JDBC | `JDBCTimerStore` | Production (PostgreSQL, etc.) |
| Cassandra | `CassandraTimerStore` | Distributed production |

JDBC storage uses `TimerServiceTables` (Slick) with `TimerServiceRecord`.

### Configuration

```hocon
cafienne.engine.timer-service {
  # Timer service configuration
}
```

### Integration with Case Lifecycle

1. Case definition contains `TimerEventDefinition` with expression
2. When timer plan item becomes Available, expression is evaluated
3. Timer registered with `TimerEventSink`
4. On timer fire, `Occur` transition triggers on the TimerEvent plan item
5. Connected sentries evaluate (e.g., activate a task after deadline)

## Relevance for Procest

1. **Deadline management** -- essential for government SLA compliance (e.g., Wmo, Wob deadlines)
2. **Duration timers** -- "activate reminder after 2 weeks"
3. **Cron scheduling** -- recurring checks or escalations
4. **Persistent timers** -- survive server restarts
