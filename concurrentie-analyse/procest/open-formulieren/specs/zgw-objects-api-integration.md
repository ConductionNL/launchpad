# Spec: ZGW & Objects API Integration — Open Formulieren vs Procest

## Feature: Integration with ZGW APIs and Objects API

### Open Formulieren's ZGW Integration

**Direction:** Outbound only (creates data in external ZGW systems)

**ZGW registration flow:**
1. Form submission triggers async Celery task
2. Plugin creates Zaak via POST to Zaken API (ZRC)
3. Uploads documents via POST to Documenten API (DRC)
4. Links documents via POST ZaakInformatieobjecten
5. Sets Zaakeigenschappen from form variable mappings
6. Creates Rol (initiator) for the submitter

**Configuration per form:**
- Select default Zaaktype (URL from ZTC)
- Select Informatieobjecttype for documents
- Map form variables → Zaakeigenschappen
- Per-upload-component document type override
- Vertrouwelijkheidaanduiding (confidentiality level)

**Required services (configured in admin):**
- Zaken API service (ZRC endpoint + auth)
- Documenten API service (DRC endpoint + auth)
- Catalogi API service (ZTC endpoint + auth)

### Procest's ZGW Integration

**Direction:** Bidirectional (reads AND writes ZGW data)

**Architecture:**
- ZGW proxy layer: Controllers for each ZGW component (ZrcController, DrcController, ZtcController, BrcController, NrcController, AcController)
- ZGW auth middleware: JWT token handling for ZGW API authentication
- ZGW mapping service: Maps between ZGW data structures and Procest internal models
- Business rules: ZgwZrcRulesService, ZgwDrcRulesService, ZgwBrcRulesService, ZgwZtcRulesService
- Pagination helper for ZGW API list endpoints

**Capabilities:**
| Operation | Open Formulieren | Procest |
|-----------|-----------------|---------|
| Create Zaak | Yes | Yes |
| Read Zaak | No | Yes |
| Update Zaak status | No | Yes |
| List Zaken | No | Yes |
| Create Informatieobject | Yes | Yes |
| Read/List Informatieobjecten | No | Yes |
| Manage ZaakInformatieobjecten | Yes (link only) | Yes (full CRUD) |
| Create/Manage Rollen | Yes (initiator only) | Yes (all roles) |
| Manage Besluiten | No | Yes |
| Read Zaaktypen/Statustypen | No | Yes |
| Subscribe to notifications | No | Yes (NRC) |
| ZGW mapping management | No | Yes |

### Open Formulieren's Objects API Integration

**Registration (outbound):**
- Creates or updates Objects in the Objects API
- JSON template with variable-to-property mappings
- Objecttype and version pinning
- "Contract" between form and processing application
- Update existing objects (since v3.0) for renewals

**Prefill (inbound, since v3.0):**
- Can read existing Objects to prefill form fields
- Object reference passed via URL parameters
- Enables renewal/update workflows

### Procest's Objects API Integration

Procest does not directly integrate with the Objects API. Instead:
- OpenRegister provides the object/data storage layer
- OpenRegister schemas define data structures (similar to Objecttypes)
- Objects are stored in OpenRegister, not in a separate Objects API instance
- ZGW Zaakeigenschappen are used for case-specific data

### Comparison

| Aspect | Open Formulieren | Procest |
|--------|-----------------|---------|
| ZGW API coverage | ZRC (create), DRC (create), ZTC (read) | ZRC, DRC, ZTC, BRC, NRC, AC (full CRUD) |
| ZGW direction | Outbound only | Bidirectional |
| Objects API | Yes (registration + prefill) | Via OpenRegister |
| ZGW business rules | None | Yes (ZgwBusinessRulesService) |
| ZGW JWT auth | No (service account only) | Yes (ZgwAuthMiddleware) |
| ZGW mapping config | Per-form in admin | Centralized mapping service |
| ZGW notifications | No | Yes (NRC subscription) |

### Strategic Implications

1. **Procest has deeper ZGW integration** — it manages the full case lifecycle, not just creation
2. **Open Formulieren is a ZGW data producer** — it only pushes data outbound
3. **The integration point is clear:** Open Formulieren → OpenZaak → Procest reads and manages
4. **No overlap:** Open Formulieren never reads/manages cases; Procest never creates citizen-facing forms
5. **Objects API divergence:** Open Formulieren uses the standard Objects API; Procest uses OpenRegister. This is a different architectural choice but serves similar purposes.
