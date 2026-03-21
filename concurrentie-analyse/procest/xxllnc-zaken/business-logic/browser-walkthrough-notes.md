# xxllnc Zaken - Browser Walkthrough Notes

**Date:** 2026-03-14
**Product:** xxllnc Zaken (voorheen Zaaksysteem.nl)
**Vendor:** xxllnc (https://xxllnc.nl)
**Type:** SaaS zaaksysteem for Dutch (semi-)government
**GitLab:** https://gitlab.com/xxllnc/zaakgericht/zaken/start

---

## 1. Product Overview

xxllnc Zaken is a **case management system** (zaaksysteem) aimed at Dutch municipalities and government organizations. The tagline is: *"Zelfstandig bedrijfsprocessen zaakgericht digitaliseren"* (Independently digitize business processes in a case-oriented way).

Key claim: **"Het open source zaaksysteem van Nederland"** (The open source case management system of the Netherlands).

### Key Statistics (from team page)
- **100+** organizations using the platform
- **8.23M** zaken (cases) processed
- **500+** zaaktypen (case types) configured

---

## 2. Core Features (from product page)

### 2.1 Slimme formulieren (Smart Forms)
- Intelligent forms including identification and authentication
- Citizens and organizations can digitally submit requests
- Data directly stored in the case record

### 2.2 Krachtige zaakbehandeling (Powerful Case Handling)
- Self-designed process flows for case handling
- Stores information, decision trees (vraagbomen), stakeholders, documents, and archive properties

### 2.3 Uitgebreid zoeken (Advanced Search)
- Search and create overviews using filters for case information
- List view or map-layer-based views
- Saved, shared, and dashboard-embedded overviews

### 2.4 Veilig archiveren (Secure Archiving)
- Full-fledged Records Management Application (RMA)
- Successfully tested against **NEN-ISO 16175-1:2020** (formerly NEN-2082) norms
- Archive and records management compliance

### 2.5 Zelf beheren (Self-Management / Zero-Coding)
- Administrators design forms, configure processes, and use templates
- Zero-coding approach: no technical knowledge required
- End-to-end process support configuration

### 2.6 Omgeving voor inwoners (Citizen Portal / PIP)
- Personal Internet Page (PIP) for citizens and organizations
- Digital interactions with the organization
- Document publication and message exchange

---

## 3. Modules

### 3.1 Documentwatcher
- Synchronize documents from local workstation with case dossier documents
- Supports local office documents and other file formats

### 3.2 Anonimiseren (Anonymize)
- Anonymize documents directly from the case system
- Based on integration with Datamask

### 3.3 Ondertekenen (Digital Signing)
- Sign documents directly and legally from the case handling screen
- Based on integration with Zynyo or ValidSign

---

## 4. Full Feature List (from product page)

- Intuitive interface
- User-friendly dashboard with self-configurable widgets
- Responsive design (computer, tablet, phone)
- Multiple search functionalities for case information
- Extensive communication module for incoming and outgoing messages
- Document creation based on templates
- Manage and edit documents from within the case
- Create, save, and share reports with colleagues
- Flexible and smart web forms
- Personal Internet Page (PIP) with custom branding
- Large number of integrations: basisregistraties, MijnOverheid, Xential, Office365, DSO, and more

---

## 5. Technical Architecture (from GitLab)

### 5.1 Repository Structure
- **GitLab Group:** `xxllnc/zaakgericht/zaken`
- **Main repo:** `start` - mono-repo development environment containing all zaaksysteem components
- **Libraries subgroup:** 17 projects for shared libraries
- **Additional repos:** `wopi-proxy` (Office integration), `zaaksysteem-upload-service` (Windows file sync), `zsnl-smtp-incoming` (email intake)

### 5.2 Technology Stack
From GitLab language breakdown:
- **TypeScript** 27.4% (frontend)
- **Perl** 25.5% (legacy backend components)
- **Python** 19.8% (domain/backend, Python 3.13)
- **JavaScript** 13.0% (frontend)
- **PLpgSQL** 5.2% (database)

### 5.3 Backend Architecture
- **Domains/business logic** in Python: `backend/domains`
- **HTTP daemon** using Pyramid web framework: `backend/http-cm` (case management)
- **Framework:** Custom `minty` framework with:
  - `minty-pyramid` - Pyramid web framework integration
  - `minty-amqp` - AMQP consumers (RabbitMQ)
  - `minty-infra-*` - Infrastructure wrappers (S3, SQLAlchemy, etc.)
- **Message queue:** RabbitMQ
- **Object storage:** MinIO (S3-compatible)
- **Database:** PostgreSQL (based on PLpgSQL usage)
- **Frontend:** TypeScript/JavaScript mono-repo with Webpack (`frontend-mono`, Lerna)

### 5.4 Docker Development Environment
Docker-compose based local development with:
- Main app accessible at `dev.zaaksysteem.nl` (requires hosts file entry)
- MailHog for email testing
- RabbitMQ for message queuing
- MinIO for file storage
- Redis for caching
- Self-signed CA certificate for HTTPS
- Default credentials: `admin/admin`, `beheerder/beheerder`, `gebruiker/gebruiker`
- Three frontend modes: `image` (pre-built), `container` (dev in container), `local` (local dev server)
- Two backend modes: `image` (pre-built), `container` (dev in container)
- Requires minimum 10GB Docker memory

### 5.5 Project Maturity
- Created: January 22, 2020
- 54,422 commits
- 345 branches
- 2,550 tags (extensive release history)
- 5 environments
- 4 stars

---

## 6. Common Ground & ZGW Compliance

From the Zaakgericht team page and product materials:
- Supports **ZGW-API** for exchanging case data
- Can function as an **Archive component** within a Common Ground architecture
- New applications built on Common Ground principles (modular, component-based)
- Partnership with **Maykin** for **Open Formulieren** (announced Feb 2026)
  - Open source forms solution
  - Integrates with DigiD, eHerkenning, eIDAS
  - Developed with gemeenten Utrecht, Den Haag, SED-organisatie, and Dimpact
  - Will integrate into xxllnc Cloud platform

---

## 7. Market Position

### Business Model
- **SaaS-first** delivery model via "xxllnc Cloud"
- All applications available from the cloud
- Part of broader xxllnc product suite spanning:
  - **Belastingen** (taxes): Bezwaren, HaalCentraal, Heffen, Innen, Objecten, Samenwerken, Waarderen
  - **Omgeving** (environment): Omgevingsdocumenten, Toepasbare Regels, VTH
  - **Productiviteit**: Anonimiseren, Expertsystemen, Koppelen, Persoonsgegevens, Publiceren
  - **Sociaal**: Leerlingbegeleiding, Leerlingenvervoer, Onderwijsloket, Vroegsignalering, Regiesysteem, Sociale PDC, Huisbezoeken, Prognose
  - **Zaakgericht**: Formulieren, Zaken

### Target Market
- Dutch municipalities (100+ organizations)
- Semi-government organizations
- European expansion ambitions

### Support Model
- xxllnc Academy (https://academy.xxllnc.nl)
- Support portal (https://support.xxllnc.nl)
- Succesmanagers for implementation planning
- Training offerings for municipal workers
- Consulting for zaaktype building

---

## 8. Competitive Strengths vs Procest

### Strengths
1. **Massive scale** - 8.23M cases, 100+ organizations, 15+ years experience
2. **Full product suite** - Not just zaakgericht, but belastingen, sociaal, omgeving
3. **Open source** (claimed) with public GitLab repos
4. **Common Ground / ZGW-API compliance** - Industry standard compliance
5. **NEN-ISO 16175-1:2020** certified archiving
6. **Zero-coding** process configuration for administrators
7. **Citizen portal (PIP)** with custom branding
8. **Strategic partnerships** (Maykin/Open Formulieren)
9. **Extensive integration ecosystem** (MijnOverheid, DigiD, eHerkenning, Xential, Office365, DSO)
10. **Active development** - 54K+ commits, frequent releases

### Weaknesses / Opportunities for Procest
1. **Legacy tech stack** - Perl (25.5%) is a significant portion, suggesting legacy code
2. **SaaS-only** - No easy self-hosting for organizations wanting on-premise
3. **Complex setup** - Docker dev environment requires 10GB+ memory, custom CA, hosts file changes
4. **Mono-repo complexity** - Large, coupled codebase may be slower to evolve
5. **Proprietary core** - Despite "open source" claims, the mono-repo structure and SaaS model suggest limited community contributions
6. **No Nextcloud integration** - Relies on its own cloud platform
7. **Dutch market focus** - Internationalization may be limited
8. **Vendor lock-in** - Despite Common Ground principles, the integrated platform approach creates dependency
9. **Formulieren migration** - Replacing their own forms with Maykin's Open Formulieren (Feb 2026) suggests their own solution was inadequate

---

## 9. Docker Self-Hosting Assessment

**Result: Docker development environment exists but is NOT suitable for production self-hosting.**

The GitLab `start` repo provides a developer setup with docker-compose, but:
- It's explicitly a "development environment" for internal developers
- Requires GitLab access for container images
- Needs custom DNS (dev.zaaksysteem.nl)
- No production deployment documentation
- Product is delivered as SaaS via xxllnc Cloud

**We did NOT attempt to spin up the Docker environment** as it requires:
1. Full GitLab clone with submodules
2. `yq` v4.x tool
3. Custom `bin/zs` tooling
4. 10GB+ Docker memory
5. Host file modifications

This is clearly an internal developer tool, not a deployable product.

---

## 10. Screenshots Index

| # | File | Description |
|---|------|-------------|
| 01 | 01-xxllnc-homepage.png | xxllnc homepage - "Slimme applicaties voor de overheid" |
| 02 | 02-applicaties-menu.png | Full application menu showing all product categories |
| 03 | 03-zaken-product-page.png | Zaken product page with features and modules |
| 04 | 04-gitlab-repo.png | GitLab start repo overview |
| 05 | 05-gitlab-readme-top.png | README.md with Docker setup instructions |
| 06 | 06-formulieren-page.png | Formulieren product page (Common Ground forms) |
| 07 | 07-youtube-search-results.png | YouTube search (no direct xxllnc demos found) |
| 08 | 08-zaakgericht-team-page.png | Zaakgericht team page with stats (100+ orgs, 8.23M zaken) |
| 09 | 09-gitlab-zaken-group.png | GitLab Zaken group showing subprojects |
| 10 | 10-maykin-samenwerking.png | News: xxllnc + Maykin Open Formulieren partnership |

---

## 11. No Online Demo Available

- xxllnc does not offer a public demo instance
- Demo must be requested via form at `/demo-aanvragen`
- No YouTube demo videos found specific to xxllnc Zaken
- Historical video "Zaaksysteem.nl - Een introductie" exists (14 years old, not current)
