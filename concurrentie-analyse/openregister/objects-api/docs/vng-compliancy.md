# Objects API — VNG Compliancy and Standards

## VNG Standardization Status

The Objects and Objecttypes API specifications are proposed by the Municipality of Utrecht and submitted to the VNG (Vereniging van Nederlandse Gemeenten) to become a Dutch national standard.

## API Strategy Compliancy

Designed to adhere to API principles from the Nederlandse API Strategie:

| Principle | Objects API | Objecttypes API |
|-----------|-----------|-----------------|
| API-01: Safe/Idempotent ops | Yes (except PUT) | Yes |
| API-02: No server state | Yes | Yes |
| API-03: Default HTTP ops | Yes | Yes |
| API-04: Dutch interface | No (English) | No (English) |
| API-05: Plural nouns | Yes | Yes |
| API-09: Custom representation | Yes | No |
| API-16: OAS 3.0 docs | Yes | Yes |
| API-18: Deprecation schedule | Yes | Yes |
| API-19: Max 1yr transition | Yes (6 months) | Yes (6 months) |
| API-20: Major version in URI | Yes | Yes |
| API-48: No trailing slashes | Yes | Yes |
| API-51: OAS at base-URI | Yes | Yes |

## VNG Checklist Status

### Stakeholder Documentation: Mostly compliant
- Developer getting-started guide: Yes
- Installation instructions: Yes
- Contributing guide: Yes
- Postman scripts: Yes

### Information Model: Mostly compliant
- Semantic information model (MIM): Yes
- Enterprise Architect models: Yes

### Architecture: Partially compliant
- Stakeholders described: Yes
- Interaction patterns modeled: Yes
- Reference components described: Yes
- Archimate models: Not done

### API Specifications: Mostly compliant
- OpenAPI 3.x: Yes
- Nederlandse API Strategie core rules: Yes
- SDK generation tested: Yes

### Compliancy & Testing: Partially compliant
- Reference implementation: Yes
- Test cases described: Yes
- Automated compliancy tests: Not yet
- Postman tests on api-test.nl: Not yet

### Reference Implementation: Compliant
- Both consumer and provider: Yes
- Implements OAS spec: Yes
- Demonstrates API standard: Yes

## Related Dutch Standards

- MIM (Metamodel Informatiemodellering) — information model standard
- StUF — formal/material history standard
- IMBOR — public space management info model
- BGT — large-scale topography base registration
- IMGeo — geometry information model
- Gemeentelijk Gegevensmodel (GGM) — municipal data model
