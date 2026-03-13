# OpenZaak Ecosystem and Community

## Governance

### Open Source Model
- **License:** EUPL-1.2
- **Foundation for Public Code** provided codebase stewardship (scaling processes and infrastructure)
- **Technical Steering Group (TSG)** decides on experimental features and API alignment
- **Community-driven** development with shared funding model

### Funding Model
No license fees. Revenue through ecosystem vendors who provide:
- Hosting and infrastructure
- Implementation and customization
- Support and training
- Development contributions

## Founding Municipalities

Open Zaak was commissioned by these municipalities under Dimpact coordination:

1. **Amsterdam** — largest city, lead funder
2. **Rotterdam** — second largest
3. **Utrecht** — key contributor, demonstrated integration with Open Forms
4. **Tilburg**
5. **Arnhem**
6. **Haarlem**
7. **'s-Hertogenbosch**
8. **Delft**
9. **SED Coalition** — Hoorn, Medemblik, Stede Broec, Drechterland, Enkhuizen

## Service Providers

| Provider | Role |
|----------|------|
| **Maykin Media** | Primary maintainer and developer |
| **Contezza** | Service provider |
| **Dimpact** | Coordination and procurement cooperative |

## Deployment Options (via opengem.nl)

### SaaS
- Full management by experts
- No license costs
- Continuous updates
- Rapid implementation (within one day)

### On-Premise
- Self-managed infrastructure
- One-time installation costs
- Optional service agreements

### Training Programs
- **Functional Management** — zaaktype configuration, access control, troubleshooting
- **Technical Management** — installation, updates, Docker, Ansible, system administration

## Integration Ecosystem

### Core Common Ground Components

| Component | Relationship to OpenZaak |
|-----------|------------------------|
| **Open Notificaties** | Required companion — handles notification routing |
| **Open Formulieren** | Form intake that creates zaken in OpenZaak |
| **Open Klant** | Customer contact management, reads zaak data |
| **Open Inwoner** | Citizen portal, reads cases and documents from OpenZaak |
| **Open Archiefbeheer** | Archiving/destruction lists based on OpenZaak data |
| **Objects API** | Generic object storage, can be linked via ZaakObjecten |
| **Open Personen** | Person data (BRP), referenced in zaak roles |

### Case-Handling Frontends (Consumers)

| Frontend | Description |
|----------|------------|
| **Dimpact ZAC** | Open-source workflow-based case handling component |
| **Valtimo (GZAC)** | Low-code process automation for case management |
| **Procest** | Native Nextcloud case management (our product) |
| **Custom applications** | Any ZGW-API compliant consumer |

### External System Integration

| System | Integration Method |
|--------|-------------------|
| **BAG/BRT** (Kadaster) | External API credentials for zaakobjecten |
| **Haal Centraal** | Person/address data for roles |
| **DigiD** | Citizen authentication for mandates |
| **eHerkenning** | Business authentication for mandates |
| **NLX** | Secure government data exchange network |

## Common Ground Alignment

Open Zaak follows Common Ground principles:
- **Data at the source** — no permanent copies of external data
- **API-first** — all functionality exposed via standard APIs
- **Haven-compatible** — optimized for the cloud-agnostic reference infrastructure
- **Component-based** — integrates with other Common Ground components

## Related Projects

| Project | URL | Relationship |
|---------|-----|-------------|
| open-zaak/open-zaak | github.com/open-zaak/open-zaak | Main repository |
| open-zaak/open-notificaties | github.com/open-zaak/open-notificaties | Required companion |
| VNG-Realisatie/gemma-zaken | github.com/VNG-Realisatie/gemma-zaken | API standards |
| Sudwest-Fryslan/OpenZaakBrug | github.com/Sudwest-Fryslan/OpenZaakBrug | ZDS-to-ZGW translation bridge |
| OneGround/ZGW-APIs | github.com/OneGround/ZGW-APIs | Alternative C# implementation |

## Community Channels

- **Slack:** Open Zaak channel on Samen Organiseren workspace
- **Common Ground:** Dedicated group on commonground.nl
- **GitHub:** Issue tracker and pull requests
- **OpenGem:** Product page at opengem.nl/producten/open-zaak/
