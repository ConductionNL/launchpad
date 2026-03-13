# Product Lifecycle State Machine

## State Diagram

```
                          Manual transition (if allowed by producttype.toegestane_statussen)
                    +-------------------------------------------------------------------+
                    |                                                                   |
                    v                                                                   |
              +-----------+                                                             |
  create ---->| INITIEEL  |---- manual ----> IN_AANVRAAG ---- manual ----> GEREED       |
              +-----------+         |              |               |          |          |
                    |               |              |               |          |          |
                    |               v              v               v          v          |
                    |          INGETROKKEN    INGETROKKEN     INGETROKKEN  INGETROKKEN   |
                    |          GEWEIGERD      GEWEIGERD       GEWEIGERD   GEWEIGERD     |
                    |                                                                   |
   start_datum      |                                                                   |
   reached          |                                                                   |
   (auto)           v                                                                   |
              +-----------+                                                             |
              |  ACTIEF   |<--- auto (start_datum) from INITIEEL/IN_AANVRAAG/GEREED     |
              +-----------+                                                             |
                    |                                                                   |
   eind_datum       |                                                                   |
   reached          v                                                                   |
   (auto)     +-----------+                                                             |
              | VERLOPEN  |<--- auto (eind_datum) from INITIEEL/IN_AANVRAAG/GEREED/ACTIEF
              +-----------+
```

## State Definitions

| State         | Description                              | Auto-set by  |
|---------------|------------------------------------------|--------------|
| INITIEEL      | Initial state on creation                | Default      |
| IN_AANVRAAG   | Application in progress                  | Manual       |
| GEREED        | Ready / prepared                         | Manual       |
| ACTIEF        | Active / in use                          | start_datum  |
| INGETROKKEN   | Withdrawn                                | Manual       |
| GEWEIGERD     | Rejected                                 | Manual       |
| VERLOPEN      | Expired                                  | eind_datum   |

## Transition Rules

### Manual Transitions
- Any state -> any other state, IF the target state is in `producttype.toegestane_statussen`
- INITIEEL is always available (not restricted by toegestane_statussen)
- No path restrictions (e.g., can go from INITIEEL directly to VERLOPEN if allowed)

### Automatic Transitions (on save + daily Celery task)
1. **INITIEEL/IN_AANVRAAG/GEREED -> ACTIEF**: When `start_datum <= today`
2. **INITIEEL/IN_AANVRAAG/GEREED/ACTIEF -> VERLOPEN**: When `eind_datum <= today`
3. Priority: start_datum checked first, then eind_datum (in `Product.save()`)

### Date Constraints
- Setting `start_datum` requires ACTIEF in `toegestane_statussen`
- Setting `eind_datum` requires VERLOPEN in `toegestane_statussen`
- `eind_datum` must be after `start_datum`

## Celery Daily Task
```
Every day at 00:00:
  for each product:
    if check_start_datum() or check_eind_datum():
      product.save()  # triggers automatic transition + audit log
```
