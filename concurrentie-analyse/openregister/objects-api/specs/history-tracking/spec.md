---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# History Tracking — Objects API (Documentation View)

## Purpose
Track object changes on two axes: material (real-world) and formal (administrative) history. Based on the Dutch StUF standard. Enables time-travel queries for both perspectives.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/api/usage.html

## API Reference
| Method | Path | Description |
|--------|------|-------------|
| GET | `/objects/{uuid}/history` | All records of an object |
| GET | `/objects/{uuid}/{index}` | Specific record by index |
| GET | `/objects?date=2021-01-01` | Material history query |
| GET | `/objects?registrationDate=2021-01-01` | Formal history query |

## Material vs Formal History

### Material History (startAt / endAt)
- Represents when something happened in the real world
- `startAt`: when the object state became valid
- `endAt`: when the object state ceased to be valid
- Query: `?date=YYYY-MM-DD` returns objects where date is between startAt and endAt

### Formal History (registrationAt)
- Represents when the change was administratively recorded
- Auto-set to the date of API call
- Query: `?registrationDate=YYYY-MM-DD` returns objects registered at or before that date

### Key Difference Example
A tree planted on 2021-01-01 (startAt) but registered on 2021-03-03 (registrationAt):
- `?date=2021-02-02` returns the tree (it existed in reality)
- `?registrationDate=2021-02-02` returns empty (not yet registered)

## Record Fields
- `index`: Auto-increment identifier
- `startAt`: Legal start date (required)
- `endAt`: Legal end date (auto-managed)
- `registrationAt`: Registration date (auto-set)
- `correctionFor`: Index of record being corrected
- `correctedBy`: Index of record that corrects this one (auto-set)

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| History model | Immutable records per object | updated/created timestamps |
| Material history | startAt/endAt with date queries | No |
| Formal history | registrationAt with date queries | created timestamp only |
| Time-travel queries | Yes (date and registrationDate params) | No |
| Corrections | Linked correction records | Direct mutation |
| StUF compliance | Yes | No |
| History endpoint | `/objects/{uuid}/history` | Audit log (different concept) |

**Already in OpenRegister**: created/updated timestamps, audit logging
**Not yet in OpenRegister**: Immutable record history, material/formal history distinction, time-travel queries, StUF-compliant history, correction records
