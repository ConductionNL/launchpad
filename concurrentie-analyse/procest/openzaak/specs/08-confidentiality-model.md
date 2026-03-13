# Spec: Confidentiality Level Model

## Feature: Full VNG Vertrouwelijkheidaanduiding Implementation

The confidentiality model controls visibility of cases and documents based on security classification, enforced at the API level.

### Already in Procest

- Vertrouwelijkheidaanduiding attribute on cases
- Vertrouwelijkheidaanduiding attribute on documents
- Basic confidentiality awareness in ZGW mapping

### Not Yet in Procest

- **8-level confidentiality scale enforcement** (openbaar through zeer_geheim)
- **Per-application maxVertrouwelijkheidaanduiding** per zaaktype and informatieobjecttype
- **Visibility filtering** — more-confidential resources must be completely invisible (not just forbidden)
- **Default inheritance** — deriving confidentiality from zaaktype/informatieobjecttype when not explicitly set
- **Authorization + confidentiality interaction** — combined check on every API request
- **Confidentiality in search results** — filtering out confidential records from listings
- **Confidentiality audit** — tracking who accessed what at what confidentiality level
- **UI enforcement** — hiding confidential information in the Procest interface based on user permissions
- **Nextcloud RBAC mapping** — translating ZGW confidentiality to Nextcloud group permissions
