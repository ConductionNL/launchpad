# Spec: Submission Flow & Registration Backends — Open Formulieren vs Procest

## Feature: Form Submission and Case Registration

### Open Formulieren Submission Flow

**Citizen-facing flow:**
1. SDK renders form in citizen's browser (embedded in CMS)
2. Citizen authenticates (DigiD/eHerkenning) → BSN/KvK obtained
3. Prefill plugins auto-populate fields from BRP/KvK
4. Citizen fills form step by step with real-time validation
5. Payment (if required) via Ogone/Worldline
6. Submission → Celery async queue processes registration
7. Confirmation page + email with reference number

**Registration backends (plugin system):**

| Backend | Protocol | Creates | Status |
|---------|----------|---------|--------|
| ZGW APIs | REST | Zaak + Informatieobjecten | Primary |
| Objects API | REST | Object in Objecten API | Growing |
| StUF-ZDS | SOAP/XML | Zaak in legacy system | Legacy |
| Email | SMTP | Email notification | Simple |
| MS Graph | REST | SharePoint/OneDrive doc | Niche |

**ZGW registration details:**
- Creates Zaak with configured Zaaktype
- Uploads documents as Informatieobjecten (per-component Informatieobjecttype since v3.x)
- Links documents to Zaak via ZaakInformatieobjecten
- Sets Zaakeigenschappen from mapped form variables
- Creates Rollen (initiator role for submitter)
- Generates PDF summary as Informatieobject

**Objects API registration details:**
- Variable-to-property mappings as "contract"
- JSON template for body construction
- Can update existing objects (v3.0+)
- Objecttype version pinning
- More flexible than ZGW for custom data structures

**Error handling:**
- Plugins raise `RegistrationFailed` on failure
- Celery automatic retry with backoff
- Failed submissions flagged in admin
- Digest email notifications for administrators

### Procest Case Management

**Case intake methods:**
1. **Manual creation** — Case worker opens CaseCreateDialog, selects Zaaktype, fills properties
2. **ZGW API** — External system (Open Formulieren, etc.) creates Zaak via ZRC → Procest reads it
3. **No citizen-facing channel** — Procest is internal-only

**Case lifecycle (post-intake):**
- Status management with configurable status workflows
- Task creation and assignment to case workers
- Deadline tracking with overdue alerts
- Document management via Nextcloud Files + DRC sync
- Participant management (betrokkenen/roles)
- Activity timeline and audit trail
- Decisions (besluiten) via BRC
- Sub-case support
- KPI dashboards

**ZGW integration (bidirectional):**

| ZGW API | Direction | Procest Usage |
|---------|-----------|---------------|
| ZRC (Zaken) | Read + Write | Full case CRUD, status updates |
| DRC (Documenten) | Read + Write | Document management + Nextcloud sync |
| ZTC (Catalogi) | Read | Zaaktype definitions, statustype, etc. |
| BRC (Besluiten) | Read + Write | Decision management |
| NRC (Notificaties) | Subscribe | Real-time notifications |

### Gap Analysis

| Capability | Open Formulieren | Procest | Winner |
|-----------|-----------------|---------|--------|
| Citizen-facing intake | Yes | No | OF |
| Authentication (DigiD/eH) | Yes | No | OF |
| Prefill (BRP/KvK) | Yes | No | OF |
| Payment collection | Yes | No | OF |
| ZGW case creation | Yes (outbound) | Yes (full CRUD) | Procest |
| Case lifecycle mgmt | No | Yes | Procest |
| Task management | No | Yes | Procest |
| Document handling | Upload only | Full lifecycle | Procest |
| Status workflow | No | Yes | Procest |
| Deadline tracking | No | Yes | Procest |
| Decision management | No | Yes | Procest |
| Audit trail | Submission log | Full case trail | Procest |
| Async processing | Yes (Celery) | Yes (n8n) | Tie |

### Integration Architecture

```
[Citizen] → [Open Formulieren] → [ZGW APIs / OpenZaak] → [Procest]
                                                              ↓
                                                     Case lifecycle:
                                                     Tasks, Deadlines,
                                                     Documents, Decisions
```

### Conclusion

Open Formulieren and Procest are **complementary, not competitive** in the submission/registration space. Open Formulieren handles the intake from citizens; Procest handles everything after. The integration point is the ZGW APIs (specifically OpenZaak as the shared data layer).

**Procest should NOT build its own citizen-facing submission flow.** Instead, it should:
1. Ensure seamless ZGW intake from Open Formulieren submissions
2. Auto-detect new Zaken created by Open Formulieren and surface them in the case list
3. Map Open Formulieren's Zaakeigenschappen to Procest's case detail views
4. Import attached documents from DRC into Nextcloud Files
