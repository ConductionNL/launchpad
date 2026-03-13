# Objects API — Vision and Architecture

## Vision

It is difficult to know and define each object in advance and create an appropriate API for it. This would cause significant slowdown of the implementation of the Common Ground principles and the IT-landscape would see a huge amount of APIs for each object type.

Two complementing APIs are introduced:

### Objecttypes API (National Level)
- Registration for all kinds of object types
- Hold definitions of objects, obtainable via an API
- Definitions proposed by domain experts, approved by VNG for national standard
- Organizations can also run their own local Objecttypes API

### Objects API (Organization Level)
- One or more per organization
- Each object adheres to a definition in the Objecttypes API
- Can store all objects in one instance or separate by domain
- Can expose objects as Open Data

## Information Model

Uses the Dutch standard Metamodel Informatiemodellering (MIM).

Key entities:
- **Objecttype**: Contains metadata + one or more versions
- **ObjecttypeVersion**: Contains version number + JSON schema defining object attributes
- **Object**: Defined by an Objecttype, changes over time via Records
- **Record**: State of an Object at a certain time, referencing a specific ObjecttypeVersion

History follows the Dutch StUF standard for formal and material history.

## Definitions

| Term | Description |
|------|-------------|
| **Objecttype** | A definition (JSON-schema) + metadata. Represents a collection of similar objects. |
| **Object** | A self-contained data entity with own identity, structured per objecttype's JSON-schema. |
| **Objecttypes API** | API to retrieve objecttypes. Standardize types nationally or locally. |
| **Objects API** | API to CRUD objects. Store and expose objects per their objecttype. |

## Team / Stakeholders

Initiated by six organizations:
- Amsterdam
- Delft
- GBI (Gemeentelijke Basisprocessen Inkomen)
- Haarlem
- Rotterdam
- **Utrecht** (project lead)
