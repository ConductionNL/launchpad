# Open Formulieren — Ecosystem & Municipalities

## Origin & Governance

- **Initiated:** ~2020 by Maykin Media
- **Commissioned by:** Dimpact (cooperative of Dutch municipalities)
- **Developed by:** Maykin Media (Amsterdam-based Python/Django development firm)
- **License:** EUPL-1.2 (European Union Public Licence)
- **Governance:** Dimpact members + contributing municipalities

## Development Consortium

Original development consortium:
- **Dimpact** — 10+ member municipalities
- **Gemeente Den Haag** — Co-funder and early adopter
- **Gemeente Utrecht** — Co-funder and early adopter
- **SED organisatie** — Joint administrative organization
- **Maykin Media** — Technical development partner

## Municipal Adoption

### Scale
- **150+ municipalities** via Dimpact membership
- **30+ municipalities** with components in production
- Growing adoption through OpenGem initiative

### Known Municipalities Using Open Formulieren
- Gemeente Den Haag
- Gemeente Utrecht
- Gemeente Rotterdam (doorontwikkeling/further development via Raakvlak Advies)
- Gemeente Horst aan de Maas
- Gemeente Venlo
- Gemeente Roermond
- Gemeente Nijmegen (contributor to v3.2)
- Provincie Zeeland
- Multiple SED municipalities
- Many Dimpact member municipalities

## Common Ground Ecosystem

Open Formulieren is part of the broader Common Ground ecosystem of Dutch government open-source components:

### Direct Integrations

| Component | Relationship | Description |
|-----------|-------------|-------------|
| **OpenZaak** | Registration target | Creates Zaken from form submissions via ZGW APIs |
| **Objects API** | Registration target | Stores submission data as Objects |
| **Objecttypes API** | Configuration | Defines schemas for Objects API registration |
| **Open Notificaties** | Event consumer | Can listen for notifications about case status changes |
| **Open Klant** | Contact registry | Can use contact/customer information |

### Related Products (OpenGem / Maykin)

| Product | Description |
|---------|-------------|
| **Open Inwoner** | Citizen portal showing cases, messages, and form links |
| **Open Klant** | Contact and customer management |
| **Open Zaak** | ZGW API implementation (case storage) |
| **Open Notificaties** | Event notification hub |
| **Open Archiefbeheer** | Archive management |

### Broader Common Ground

- **Haal Centraal APIs** — National registry access (BRP, KvK)
- **NL Design System** — Government design system for consistent styling
- **Haven** — Kubernetes hosting platform for Common Ground components
- **Dimpact** — Cooperative facilitating joint procurement

## VNG Standards Compliance

Open Formulieren aligns with VNG (Vereniging van Nederlandse Gemeenten) standards:

- **ZGW APIs** — Zaakgericht Werken API standards for case management
- **WMEBV** — Wet Modernisering Elektronisch Bestuurlijk Verkeer (e-forms accessibility law)
- **SDG** — Single Digital Gateway (EU regulation) — generic SDG Annex II form designs developed on behalf of VNG
- **WCAG AA** — Web Content Accessibility Guidelines
- **NL Design System** — Government design system tokens and patterns

## Distribution Channels

### OpenGem (opengem.nl)
- **SaaS offering** by Maykin Media
- Managed hosting, updates, and support
- Monthly cancellable subscriptions
- Implementation and configuration services
- "Samen Delen" (sharing together) — community form library

### Dimpact
- Joint procurement vehicle for member municipalities
- Coordinated development roadmap
- Shared development costs across members

### Self-Hosted
- Docker images on Docker Hub (`openformulieren/open-forms`)
- GitHub repository with Docker Compose
- Community support via GitHub issues

## Comparison with Procest Ecosystem

| Aspect | Open Formulieren | Procest |
|--------|-----------------|---------|
| Distribution | OpenGem SaaS, Dimpact, self-hosted | Nextcloud App Store |
| Municipal adoption | 150+ municipalities | Early stage |
| Ecosystem | Common Ground (standalone apps) | Nextcloud ecosystem (integrated apps) |
| Hosting model | Standalone Docker | Nextcloud app (single platform) |
| Community | Active GitHub, Dimpact working groups | Conduction community |
| Funding model | Dimpact + municipality co-funding | Conduction development |
| Standards body | VNG / Common Ground | VNG / Common Ground |

### Strategic Analysis

Open Formulieren has a massive adoption advantage through the Dimpact cooperative, which gives it access to 150+ municipalities. Procest would need to build its adoption through the Nextcloud App Store and direct municipality engagement. However, Procest's Nextcloud-native approach offers a differentiated value proposition: instead of managing multiple standalone Common Ground components, municipalities get case management, document handling, collaboration, and workflow in a single platform.
