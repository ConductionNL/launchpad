# Application Roadmap

Tracking potential new Nextcloud apps — features, competitors, market size, and profit potential.

## App Lifecycle

Apps progress through these stages, supported by Claude Code skills:

```
Idea --> Exploring --> In Development --> Released --> Parked
 |         |              |
 |    /app-create     /opsx:ff
 |    /app-explore    /opsx:apply
 |         |              |
 |    appspec/        openspec/
 |    features/       changes/
 |                        |
 +-- concurrentie-analyse/ (competitive intelligence feeds all stages)
```

| Status | Meaning | Skills |
|--------|---------|--------|
| **Idea** | Concept noted, not yet explored | Add entry here |
| **Exploring** | Active research: competitors, features, market | `/app-create`, `/app-explore` |
| **In Development** | Code exists, features being built | `/opsx:ff`, `/opsx:apply`, `/app-apply` |
| **Released** | Published to Nextcloud App Store | `/opsx:verify` |
| **Parked** | Paused — revisit later | Update status here |

### How competitive intelligence flows into development

1. **Competitor analyses** live in `concurrentie-analyse/{app-name}/` — deep-dives on each competitor
2. **Tender analyses** live in `concurrentie-analyse/tenders/` — real procurement requirements
3. `/app-explore` **auto-loads** competitive intelligence during feature exploration
4. `/app-create` **checks** for existing research before asking questions
5. Features identified from competitor gaps become `appspec/features/` entries
6. Those features flow into OpenSpec changes via `/opsx:ff`

---

## Template

```
### App Name
- **Status:** Idea / Exploring / In Development / Released / Parked
- **Priority:** High / Medium / Low
- **Folder:** `concurrentie-analyse/{folder}/`
- **Description:** One-line summary
- **Problem:** What problem does this solve?
- **Target Market:** Who would use this?
- **Estimated Market Size:** Number of potential customers / organizations
- **Revenue Model:** SaaS / Support / Hosting / License
- **Estimated Monthly Revenue Potential:** €X per customer × Y customers

#### Core Features
- Feature 1
- Feature 2

#### Competitors
| Competitor | Type | Pricing | Strengths | Weaknesses |
|------------|------|---------|-----------|------------|
| Name | SaaS/OSS | €X/mo | ... | ... |

#### Notes
- Additional context, links, observations
```

---

## Apps

### Open Register
- **Status:** In Development
- **Priority:** High
- **Folder:** `concurrentie-analyse/openregister/`
- **Description:** Flexible data register management within Nextcloud
- **Problem:** Organizations need structured data storage without building custom databases
- **Target Market:** Dutch municipalities, government organizations, SMEs
- **Estimated Market Size:** 350+ Dutch municipalities, 1000+ EU government bodies
- **Revenue Model:** Support / Hosting / SLA contracts
- **Estimated Monthly Revenue Potential:** TBD

#### Core Features
- Dynamic schema-based registers
- REST API with MCP support
- Multi-tenancy and access control
- Import/export and data validation

#### Competitors
| Competitor | Type | Pricing | Strengths | Weaknesses |
|------------|------|---------|-----------|------------|
| Baserow | OSS/SaaS | €5-20/user/mo | Modern UI, API-first | No Nextcloud integration |
| NocoDB | OSS | Free / Enterprise | Simple, spreadsheet-like | Limited business logic |
| Directus | OSS/SaaS | Free / €99+/mo | Flexible, headless CMS | Complex setup |
| Strapi | OSS/SaaS | Free / €29+/mo | Developer-friendly | Content-focused, not registers |
| NocoBase | OSS | Free / Enterprise | Plugin architecture | Early stage, small community |
| PocketBase | OSS | Free | Lightweight, single binary | Limited enterprise features |
| CKAN | OSS | Free | Government standard for open data | Heavy, complex deployment |

#### Notes
- Competitor analyses available in `concurrentie-analyse/openregister/`

---

### Pipelinq
- **Status:** In Development
- **Priority:** High
- **Folder:** `concurrentie-analyse/pipelinq/`
- **Description:** CRM and pipeline management within Nextcloud
- **Problem:** Organizations want CRM without external SaaS dependencies
- **Target Market:** SMEs, freelancers, municipalities needing relationship management
- **Estimated Market Size:** TBD
- **Revenue Model:** Support / Hosting
- **Estimated Monthly Revenue Potential:** TBD

#### Core Features
- Contact and deal management
- Pipeline views (Kanban, list, calendar)
- Activity tracking
- Integration with Nextcloud contacts/calendar

#### Competitors
| Competitor | Type | Pricing | Strengths | Weaknesses |
|------------|------|---------|-----------|------------|
| Twenty | OSS | Free / Enterprise | Modern UI, GraphQL | Early stage |
| EspoCRM | OSS | Free / €15+/user/mo | Feature-rich | Dated UI |
| Krayin | OSS | Free | Laravel-based | Small community |
| Monica | OSS | Free / €9/mo | Personal CRM focus | Not business-grade |
| BottleCRM | OSS | Free | Simple | Limited features |
| Erxes | OSS | Free / Enterprise | All-in-one | Complex, heavy |

#### Notes
- Competitor analyses available in `concurrentie-analyse/pipelinq/`

---

### Procest
- **Status:** In Development
- **Priority:** High
- **Folder:** `concurrentie-analyse/procest/`
- **Description:** Case and process management within Nextcloud
- **Problem:** Government organizations need ZGW-compatible case management
- **Target Market:** Dutch municipalities, government organizations
- **Estimated Market Size:** 350+ Dutch municipalities
- **Revenue Model:** Support / Hosting / SLA contracts
- **Estimated Monthly Revenue Potential:** TBD

#### Core Features
- ZGW API compatibility
- Case lifecycle management
- Process workflows
- Document linking

#### Competitors
| Competitor | Type | Pricing | Strengths | Weaknesses |
|------------|------|---------|-----------|------------|
| Dimpact ZAC | OSS | Free | ZGW-native, municipality-focused | Dimpact-ecosystem only |
| XXLlnc Zaken | Commercial | Custom | Full ZGW suite | Vendor lock-in |
| Flowable | OSS/Commercial | Free / Enterprise | Powerful BPM engine | Complex, Java-based |
| ArkCase | OSS | Free / Enterprise | Case management focus | US-centric |
| CaseFabric | Commercial | Custom | Dutch government focus | Closed source |

#### Notes
- Competitor analyses available in `concurrentie-analyse/procest/`

---

### (Add new app ideas below)

<!--
Copy the template above and fill in the details for each new app idea.
Move apps between statuses as they progress.
-->
