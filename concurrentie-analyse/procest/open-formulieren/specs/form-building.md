# Spec: Form Building — Open Formulieren vs Procest

## Feature: No-Code Form Designer

### Open Formulieren

Open Formulieren provides a full-featured drag-and-drop form builder based on a customized form.io engine:

**Component library:**
- 15+ field types: text, textarea, number, email, phone, date, time, checkbox, select, radio, file upload, BSN, IBAN, postcode, signature
- Layout components: fieldset, columns, content blocks
- Advanced: map (geo with point/line/polygon since v3.1), repeating groups, cosign
- Each component has extensive configuration: label, description, tooltip, placeholder, default value, validation rules, prefill source, conditional visibility

**Logic engine:**
- No-code conditional rules: show/hide, enable/disable, require/unrequire, set value
- JSON Logic expressions for advanced conditions
- Server-side logic evaluation for complex rules
- Step-level conditions: skip entire steps based on logic

**Multi-step forms:**
- Divide forms into sequential steps with progress indicator
- Save and continue later (server-side persistence)
- Summary/review step before submission
- Step-level validation

**Form management:**
- Form versioning and publishing workflow
- Form import/export (JSON format)
- Form categories for organization
- Community sharing via "Samen Delen"

### Procest

Procest does **not** have a form builder. Case intake happens through:

1. **Manual case creation** — Case workers create cases via the CaseCreateDialog Vue component
2. **ZGW API intake** — External systems (like Open Formulieren) push Zaken via ZGW APIs
3. **OpenRegister schemas** — Data structure defined by register schemas, not visual form definitions

**Case creation fields are hardcoded** in Vue components (CaseCreateDialog.vue, CaseDetail.vue) and mapped to ZGW Zaaktype properties. There is no user-configurable form builder.

### Gap Analysis

| Capability | Open Formulieren | Procest | Gap |
|-----------|-----------------|---------|-----|
| Visual form designer | Full drag-and-drop | None | Critical |
| Citizen-facing forms | Yes (SDK) | No | By design |
| Conditional logic | JSON Logic engine | None | Large |
| Multi-step forms | Yes | No | Large |
| Save & resume | Yes | No | Medium |
| Form versioning | Yes | No | Medium |
| Field validation | Extensive per-type | Basic ZGW validation | Medium |
| File upload in forms | Yes | Yes (DRC) | None |
| Map/geo input | Yes | No | Medium |

### Procest Strategy

**Recommended approach: Complementary integration rather than competition.**

1. **Short-term:** Position Open Formulieren as the intake layer that feeds into Procest via ZGW APIs. Document the integration path.
2. **Medium-term:** Add simple intake forms to Procest using OpenRegister schemas rendered as forms within Nextcloud — for internal use cases that don't need citizen-facing DigiD/prefill.
3. **Long-term:** Consider embedding a form.io-based builder in Nextcloud for case workers to define intake forms, but this is a major undertaking.

The form builder is NOT Procest's competitive territory. Procest wins on case lifecycle management, not intake.
