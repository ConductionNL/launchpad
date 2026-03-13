---
status: draft
source: competitive-analysis-docs
competitor: objects-api
analyzed_date: 2026-03-12
---

# API Standards Compliancy — Objects API (Documentation View)

## Purpose
Compliance with Dutch national API standards (Nederlandse API Strategie) and VNG requirements for municipal software standardization.

## Official Documentation
- https://objects-and-objecttypes-api.readthedocs.io/en/latest/api/compliancy/

## Nederlandse API Strategie

Compliant with core design rules:
- Safe/Idempotent operations (except PUT)
- Stateless
- Standard HTTP methods
- Plural noun resources
- OAS 3.0 documentation
- Deprecation schedules
- Major version in URI
- No trailing slashes
- OAS at base-URI in JSON

Non-compliant:
- API-04: Interface in English (not Dutch)
- API-17: Documentation in English

## VNG Standardization

### Status
- Proposed by Municipality of Utrecht
- Submitted to VNG for national standard
- Reference implementation available
- Information model (MIM compliant)
- Postman scripts available
- SDK generation tested

### Gaps
- Not yet published on VNG Realisatie GitHub
- No automated compliancy tests on api-test.nl
- No formal VNG-R best practices documented
- Architecture models not in Archi (Archimate)

## Competitive Significance

The VNG standardization is the PRIMARY competitive threat to OpenRegister because:

1. **Municipal adoption** — VNG standards become de facto requirements for Dutch municipalities
2. **Procurement** — Municipal procurement often requires VNG-compliant components
3. **Ecosystem** — Integration with other VNG-standard APIs (OpenZaak, Open Notificaties, etc.)
4. **Compliancy testing** — Once api-test.nl tests exist, alternatives must pass them too
5. **National objecttype registry** — Shared national definitions reduce implementation effort

## OpenRegister Comparison
| Aspect | Objects API | OpenRegister |
|--------|-----------|--------------|
| API Strategy compliance | Formal compliance table | Not formally documented |
| VNG standard submission | Yes (in progress) | No |
| OAS 3.0 spec | Published, lint-checked | Not yet published |
| SDK generation | Automated CI | Not available |
| Postman collections | Published | Not available |
| api-test.nl | Not yet | No |
| MIM information model | Yes | No |

**Already in OpenRegister**: REST API, OpenAPI-compatible
**Not yet in OpenRegister**: Formal API Strategy compliance documentation, VNG standard submission, published OAS spec, SDK generation, Postman collections, MIM information model
