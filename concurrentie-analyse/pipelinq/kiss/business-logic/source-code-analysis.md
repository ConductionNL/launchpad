# KISS (Klantinteractie-Servicesysteem) — Complete Source Code Analysis

**Source:** `/tmp/kiss-setup`
**Stack:** C# ASP.NET Core 8 BFF (Backend-for-Frontend) + Vue 3 + Pinia + TypeScript
**Database:** PostgreSQL (via Entity Framework Core)
**Search:** Elasticsearch + Elastic Enterprise Search
**Auth:** OIDC (Keycloak) + JWT for external systems

---

## 1. Architecture Overview

KISS is a Dutch government **customer interaction system** (klantinteractie) used by call-center agents (Klantcontactmedewerkers/KCMs). The BFF acts as:

1. **YARP reverse proxy** — Forwards requests to external ZGW-compliant APIs (OpenKlant, OpenZaak, Objects API, KvK, Haal Centraal BRP)
2. **Internal REST API** — Manages its own PostgreSQL data (berichten, skills, links, kanalen, gespreksresultaten, contactmoment details, vragensets, verwerkingslogs)
3. **SPA host** — Serves the Vue 3 frontend

### Multi-Registry System
KISS supports **multiple registry backends simultaneously**, configured via `REGISTERS` env vars. Each registry system has:
- A `systemIdentifier` (typically the base URL)
- A `RegistryVersion` (OpenKlant1 or OpenKlant2)
- Optional registries: KlantinteractieRegistry, ContactmomentRegistry, KlantRegistry, InterneTaakRegistry, ZaaksysteemRegistry

The frontend sends a `systemIdentifier` header with each request to route to the correct backend.

---

## 2. Authentication & Authorization

### OIDC Setup
- Authority, ClientId, ClientSecret configured via env vars
- Cookie-based session (60 min, sliding, strict SameSite)
- Roles mapped from Keycloak: `Klantcontactmedewerker`, `Redacteur`, `Beheerder`, `Kennisbank`
- JWT Bearer scheme for external system API access (management reporting)

### Roles & Permissions

| Role | Permissions |
|------|------------|
| **Klantcontactmedewerker (KCM)** | afdelingen, groepen, skillsread, berichtenread, gespreksresultatenread, kanalenread, contactformulierenread, linksread |
| **Redacteur** | skillsread, berichtenread, berichtenbeheer, linksread, linksbeheer, vacsbeheer |
| **Beheerder** | afdelingen, groepen, skillsread, skillsbeheer, gespreksresultatenread, gespreksresultatenbeheer, kanalenread, kanalenbeheer, contactformulierenread, contactformulierenbeheer |
| **Kennisbank** | Search access only (subset of KCM view without restricted fields) |

### Authorization Policies
- **FallbackPolicy**: Requires KCM role (default for all endpoints)
- **RedactiePolicy**: Requires Redacteur role
- **KcmOrRedactiePolicy**: Either KCM or Redacteur
- **KcmOrKennisbankPolicy**: Either KCM or Kennisbank
- **KcmOrRedactieOrKennisbankPolicy**: Any of the three
- **ExternSysteemPolicy**: JWT Bearer token (for management reporting API)

---

## 3. Complete API Endpoint Inventory

### 3.1 Auth Endpoints (Anonymous)

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/me` | Returns current user info (email, roles, permissions, organisatieIds) |
| GET | `/api/challenge` | Initiates OIDC login flow |
| GET | `/api/logoff` | Signs out from cookie + OIDC |

### 3.2 Internal API (PostgreSQL-backed)

#### Contactmoment Details
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| PUT | `/api/contactmomentdetails` | KCM | Upsert contactmoment details (startdatum, einddatum, gespreksresultaat, vraag, bronnen) |
| GET | `/api/contactmomentdetails?id={id}` | KCM | Get contactmoment details by ID |
| GET | `/api/contactmomentendetails?from=&to=&page=&pageSize=` | JWT (ExternSysteem) | Paginated reporting overview with date range filter |

#### Gespreksresultaten (Conversation Results)
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/gespreksresultaten` | gespreksresultatenread | List all, ordered by definitie |
| GET | `/api/gespreksresultaten/{id}` | gespreksresultatenread | Get by ID |
| PUT | `/api/gespreksresultaten/{id}` | gespreksresultatenbeheer | Update |
| POST | `/api/gespreksresultaten` | gespreksresultatenbeheer | Create |
| DELETE | `/api/gespreksresultaten/{id}` | gespreksresultatenbeheer | Delete |

#### Kanalen (Channels)
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/KanalenBeheerOverzicht` | kanalenread | List all kanalen |
| GET | `/api/KanalenContactmomentKeuzelijst` | kanalenread | List for contactmoment dropdown |
| GET | `/api/KanaalBeheerDetails/{id}` | kanalenread | Get by ID |
| POST | `/api/KanaalToevoegen` | kanalenbeheer | Create (naam must be unique) |
| PUT | `/api/KanaalBewerken/{id}` | kanalenbeheer | Update |
| DELETE | `/api/KanaalVerwijderen/{id}` | kanalenbeheer | Delete |

#### Links
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/links` | linksread | List grouped by categorie |
| GET | `/api/links/{id}` | linksbeheer | Get by ID |
| POST | `/api/links` | linksbeheer | Create |
| PUT | `/api/links/{id}` | linksbeheer | Update |
| DELETE | `/api/links/{id}` | linksbeheer | Delete |

#### Categorien
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/categorien` | linksbeheer | List distinct link categories |

#### Berichten (News & Work Instructions)
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/berichten` | berichtenread | List all (admin overview) |
| GET | `/api/berichten/{id}` | berichtenread | Get by ID with skills |
| POST | `/api/berichten` | berichtenbeheer | Create |
| PUT | `/api/berichten/{id}` | berichtenbeheer | Update |
| DELETE | `/api/berichten/{id}` | berichtenbeheer | Delete |
| GET | `/api/berichten/published` | KcmOrRedactie | Search published berichten with filters (type, search, skillIds, pagination) |
| GET | `/api/berichten/featuredcount` | KcmOrRedactie | Count unread important berichten |
| PUT | `/api/berichten/{id}/read` | KcmOrRedactie | Mark bericht as read/unread |

#### Skills
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/skills` | skillsread | List all (non-deleted) |
| GET | `/api/skills/{id}` | skillsbeheer | Get by ID |
| POST | `/api/skills` | skillsbeheer | Create |
| PUT | `/api/skills/{id}` | skillsbeheer | Update |
| DELETE | `/api/skills/{id}` | skillsbeheer | Soft delete |

#### Contactverzoek Vragensets (Contact Request Question Sets)
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/contactverzoekvragensets?soort=` | contactformulierenread | List (filter by "afdeling" or "groep") |
| GET | `/api/contactverzoekvragensets/{id}` | contactformulierenread | Get by ID |
| POST | `/api/contactverzoekvragensets` | contactformulierenbeheer | Create |
| PUT | `/api/contactverzoekvragensets/{id}` | contactformulierenbeheer | Update |
| DELETE | `/api/contactverzoekvragensets/{id}` | contactformulierenbeheer | Delete |

#### FAQ
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/faq` | KCM | Top 10 most asked questions from last 500 contactmomenten |

#### Feedback
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/feedback` | KcmOrRedactieOrKennisbank | Send feedback email via SMTP |

#### Verwerkingslogs (Processing Logs)
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/verwerkingslogs` | Redacteur | Last 10000 API call logs |

#### Environment
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/environment/use-vacs` | vacsbeheer | Check if VACs feature is enabled |
| GET | `/api/environment/use-medewerkeremail` | KCM | Check if medewerker email feature is enabled |
| GET | `/api/environment/build-info` | KcmOrRedactieOrKennisbank | Get build version |
| GET | `/api/environment/registers` | KCM | Get registry system configs (no secrets) |
| GET | `/api/environment/use-logboek` | KCM | Check if logboek feature is enabled |

#### Connections
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/connections` | KcmOrRedactie | Get all configured connection URLs |

#### Seed
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/seed/start` | Redacteur | Seed demo data (berichten, skills, links, gespreksresultaten) |
| GET | `/api/seed/check` | Redacteur | Check if database is already populated |

#### Health
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/healthz` | Anonymous | Health check |

### 3.3 Proxy Endpoints (External Service Routing)

#### Klantinteracties (OpenKlant 2+) — Dynamic Registry
| Method | Route | Header | Proxies To |
|--------|-------|--------|------------|
| GET/POST/PUT | `/api/klantinteracties/{**path}` | systemIdentifier | KlantinteractieRegistry base URL + path |
| POST | `/api/postklantcontacten` | systemIdentifier | Creates actor + klantcontact + actorklantcontact link |
| POST | `/api/postinternetaak` | systemIdentifier | Posts interne taak to klantinteracties API |

#### Contactmomenten (OpenKlant 1) — Dynamic Registry
| Method | Route | Header | Proxies To |
|--------|-------|--------|------------|
| GET/POST | `/api/contactmomenten/{**path}` | systemIdentifier | ContactmomentRegistry + path |
| POST | `/api/postcontactmomenten` | systemIdentifier | Adds medewerkerIdentificatie server-side |

#### Klanten (OpenKlant 1) — Dynamic Registry
| Method | Route | Header | Proxies To |
|--------|-------|--------|------------|
| GET/POST | `/api/klanten/{**path}` | systemIdentifier | KlantRegistry + path |

#### Zaaksysteem — Dynamic Registry
| Method | Route | Header | Proxies To |
|--------|-------|--------|------------|
| GET | `/api/zaken/{**path}` | systemIdentifier | ZaaksysteemRegistry.ZakenBaseUrl |
| GET | `/api/catalogi/{**path}` | systemIdentifier | ZaaksysteemRegistry.CatalogiBaseUrl |
| GET | `/api/documenten/{**path}` | systemIdentifier | ZaaksysteemRegistry.DocumentenBaseUrl |

#### Interne Taak (OpenKlant 1, Objects API) — Dynamic Registry
| Method | Route | Header | Proxies To |
|--------|-------|--------|------------|
| GET | `/api/internetaak/api/{version}/objects` | systemIdentifier | InterneTaakRegistry, adds type filter |
| POST | `/api/internetaak/api/{version}/objects` | systemIdentifier | InterneTaakRegistry, sets type+typeVersion+medewerkerIdentificatie |

#### YARP Reverse Proxy Routes (Static Config)
| Route Pattern | Cluster | Proxies To | Auth |
|---------------|---------|------------|------|
| `/api/afdelingen/{*any}` | afdelingen | AFDELINGEN_BASE_URL (Objects API) | Permission: afdelingen |
| `/api/groepen/{*any}` | groepen | GROEPEN_BASE_URL (Objects API) | Permission: groepen |
| `/api/kvk/{*any}` | kvk | KVK_BASE_URL | KCM (default) |
| `/api/vacs/{*any}` | vacs | VAC_OBJECTEN_BASE_URL (Objects API) | Permission: vacsbeheer |
| `/api/logboek/{*any}` | logboek | LOGBOEK_BASE_URL (Objects API, GET only) | KCM (default) |
| `/api/enterprisesearch/api/as/v1/engines/{engine}/search_explain` | enterprisesearch | ENTERPRISE_SEARCH_BASE_URL | KcmOrKennisbank |

#### Haal Centraal BRP (Custom Controller)
| Method | Route | Auth | Proxies To |
|--------|-------|------|------------|
| POST | `/api/haalcentraal/brp/personen` | KCM | HAAL_CENTRAAL_BASE_URL/brp/personen |

#### Elasticsearch (Custom Controller)
| Method | Route | Auth | Proxies To |
|--------|-------|------|------------|
| POST | `/api/elasticsearch/{index}/_search` | KcmOrKennisbank | ELASTIC_BASE_URL (with role-based field filtering for Kennisbank users) |

#### VACs Custom POST
| Method | Route | Auth | Proxies To |
|--------|-------|------|------------|
| POST | `/api/vacs/api/{version}/objects` | vacsbeheer | VAC_OBJECTEN_BASE_URL, sets type + typeVersion |

---

## 4. Data Models

### 4.1 PostgreSQL Entities (BeheerDbContext)

#### ContactmomentDetails
```
Id: string (PK)
Startdatum: DateTimeOffset
Einddatum: DateTimeOffset
Gespreksresultaat: string?
Vraag: string?
SpecifiekeVraag: string?
EmailadresKcm: string?
VerantwoordelijkeAfdeling: string?
Bronnen: List<ContactmomentDetailsBron>
```

#### ContactmomentDetailsBron
```
Id: int (PK, auto)
ContactmomentDetailsId: string (FK)
Soort: string (e.g., "kennisartikel", "website", "vac", "nieuwsbericht", "werkinstructie")
Titel: string
Url: string
```

#### Gespreksresultaat
```
Id: Guid (PK)
DateCreated: DateTimeOffset
DateUpdated: DateTimeOffset?
Definitie: string (e.g., "Afgehandeld", "Doorverbonden", "Contactverzoek gemaakt")
```

#### Kanaal
```
Id: Guid (PK)
DateCreated: DateTimeOffset
DateUpdated: DateTimeOffset?
Naam: string (unique, e.g., "Telefoon", "E-mail", "Balie")
```

#### Link
```
Id: int (PK, auto)
DateCreated: DateTimeOffset
DateUpdated: DateTimeOffset?
Titel: string
Url: string
Categorie: string
```

#### Bericht (News/Work Instruction)
```
Id: int (PK, auto)
DateCreated: DateTimeOffset
DateUpdated: DateTimeOffset?
PublicatieDatum: DateTimeOffset
PublicatieEinddatum: DateTimeOffset?
Titel: string
Inhoud: string (HTML)
IsBelangrijk: bool
Type: string ("nieuws" | "werkinstructie")
Skills: List<Skill> (M:N)
Gelezen: List<BerichtGelezen>
```

#### Skill
```
Id: int (PK, auto)
DateCreated: DateTimeOffset
DateUpdated: DateTimeOffset
IsDeleted: bool (soft delete)
Naam: string
Berichten: List<Bericht> (M:N)
```

#### BerichtGelezen
```
BerichtId: int (FK)
UserId: string
GelezenOp: DateTimeOffset
```

#### ContactVerzoekVragenSet
```
Id: int (PK)
Titel: string
JsonVragen: string? (JSON-serialized array of question definitions)
OrganisatorischeEenheidId: string
OrganisatorischeEenheidNaam: string
OrganisatorischeEenheidSoort: string ("afdeling" | "groep")
```

#### VerwerkingsLog
```
Id: Guid (PK)
UserId: string?
InsertedAt: DateTimeOffset
ApiEndpoint: string
Method: string
```

### 4.2 TypeScript Types (Frontend)

#### Persoon (from BRP / Haal Centraal)
```typescript
{
  _typeOfKlant: "persoon"
  bsn: string
  geboortedatum?: Date
  geslacht: string
  voornaam: string
  voorvoegselAchternaam?: string
  achternaam: string
  geboorteplaats?: string
  geboorteland?: string
  adresregel1/2/3?: string
  geheimhoudingPersoonsgegevens?: boolean
}
```

#### Bedrijf (from KvK)
```typescript
{
  _typeOfKlant: "bedrijf"
  kvkNummer: string
  type: string
  vestigingsnummer?: string
  rsin?: string
  bedrijfsnaam: string
  postcode?: string
  huisnummer?: string
  straatnaam: string
  woonplaats?: string
}
```

#### Klant (OpenKlant abstraction)
```typescript
{
  _typeOfKlant: "klant"
  id?: string
  klantnummer: string
  url: string
  bsn?: string
  bedrijfsnaam?: string
  vestigingsnummer?: string
  kvkNummer?: string
  telefoonnummers: string[]
  emailadressen: string[]
}
```

#### KlantContactPostmodel (OpenKlant 2)
```typescript
{
  kanaal: string
  onderwerp: string
  inhoud: string
  indicatieContactGelukt: boolean
  taal: string ("nld")
  vertrouwelijk: boolean
  plaatsgevondenOp: string (ISO datetime)
}
```

#### InternetaakPostModel (OpenKlant 2 Contactverzoek)
```typescript
{
  nummer: string
  gevraagdeHandeling: string ("Contact opnemen met betrokkene")
  aanleidinggevendKlantcontact: { uuid: string }
  toegewezenAanActoren: { uuid: string }[]
  toelichting: string
  status: "te_verwerken" | "verwerkt"
}
```

#### ContactverzoekData (OpenKlant 1 / Objects API)
```typescript
{
  status: string ("te verwerken")
  contactmoment: string (URL)
  registratiedatum: string
  toelichting?: string
  actor: {
    naam: string
    soortActor: string ("medewerker" | "organisatorische eenheid")
    identificatie: string
    typeOrganisatorischeEenheid?: "afdeling" | "groep"
    naamOrganisatorischeEenheid?: string
    identificatieOrganisatorischeEenheid?: string
  }
  betrokkene: {
    rol: "klant"
    klant?: string (URL)
    persoonsnaam?: { voornaam, voorvoegselAchternaam, achternaam }
    organisatie?: string
    digitaleAdressen: DigitaalAdres[]
  }
  verantwoordelijkeAfdeling: string
}
```

#### ZaakDetails
```typescript
{
  url: string
  uuid: string
  zaaksysteemId: string
  identificatie: string
  startdatum?: Date
  zaaktypeOmschrijving: string
  status: string
  behandelaar: string
  aanvrager: string
  omschrijving: string
  toelichting: string
  rollen: RolType[]
}
```

#### Partij (OpenKlant 2)
```typescript
{
  nummer?: string
  uuid: string
  url: string
  partijIdentificatie: {
    contactnaam?: { voornaam, voorvoegselAchternaam, achternaam }
    naam?: string
  }
  partijIdentificatoren: { uuid: string }[]
  _expand?: { digitaleAdressen?: DigitaalAdresApiViewModel[] }
}
```

---

## 5. Pinia Stores

### contactmoment store (`src/stores/contactmoment/index.ts`)

The core store — manages **multiple concurrent contactmomenten** (KCMs can switch between parallel conversations).

**State:**
```typescript
{
  contactmomentLoopt: boolean          // Whether any contactmoment is active
  contactmomenten: ContactmomentState[]  // All active contactmomenten
  huidigContactmoment: ContactmomentState | undefined  // Currently displayed one
  vragenSets: ContactVerzoekVragenSet[]  // Cached question sets
  loading: boolean
}
```

**ContactmomentState:**
```typescript
{
  vragen: Vraag[]           // Multiple questions per contactmoment
  huidigeVraag: Vraag       // Currently active question
  session: Session          // Switchable store session
  route: string
}
```

**Vraag (per-question state):**
```typescript
{
  zaken: ContactmomentZaak[]
  notitie: string
  contactverzoek: ContactmomentContactVerzoek
  startdatum: string
  kanaal: string
  gespreksresultaat: string
  klanten: { klant: ContactmomentKlant; shouldStore: boolean }[]
  medewerkers: { medewerker: Medewerker; shouldStore: boolean }[]
  websites/kennisartikelen/nieuwsberichten/werkinstructies/vacs: { item; shouldStore }[]
  vraag: Bron | undefined    // Selected FAQ/VAC question
  specifiekevraag: string
  afdeling?: Afdeling
}
```

**Key Actions:**
- `start()` — Load vragensets, create new contactmoment, push to stack
- `stop()` — Remove current contactmoment, switch to next or end
- `switchContactmoment(cm)` — Switch between parallel conversations
- `startNieuweVraag()` — Add another question to current contactmoment
- `setKlant(klant)` — Link a person/company to the current question
- `upsertZaak(zaak)` — Link a case to the current question
- `addKennisartikel/addWebsite/addVac/toggleNieuwsbericht/toggleWerkinstructie` — Track knowledge sources used

### user store (`src/stores/user.ts`)

**State:**
```typescript
{
  promise: Promise<void>       // Resolves when user data is loaded
  preferences: Ref<{
    kanaal: string             // Preferred channel (persisted to localStorage)
    skills: number[]           // Preferred skill IDs
  }>
  user: {
    isLoggedIn: boolean
    isSessionExpired: boolean
    isRedacteur: boolean
    isKcm: boolean
    isKennisbank: boolean
    email: string
    organisatieIds: string[]
    permissions: Permission[]
  }
}
```

### toast store (`src/stores/toast.ts`)
Simple reactive message queue for toast notifications (confirm/error messages with auto-dismiss).

### switchable store (`src/stores/switchable-store/`)
Allows multiple "sessions" of reactive state — each contactmoment gets its own isolated session so switching between conversations preserves state.

---

## 6. Vue Views & Features

### 6.1 Main Views (Routes)

| Route | View | Description |
|-------|------|-------------|
| `/` | HomeView | Landing page, start new contactmoment |
| `/afhandeling` | AfhandelingView | Finish/submit contactmoment (guard: contactmoment must be active) |
| `/personen` | PersonenView | Search persons (BRP) |
| `/personen/:internalKlantId` | PersoonDetailView | Person details + contact history |
| `/bedrijven` | BedrijvenView | Search companies (KvK) |
| `/bedrijven/:internalKlantId` | BedrijfDetailView | Company details |
| `/zaken` | ZakenView | Search cases (zaaksysteem) |
| `/zaken/:zaakId` | ZaakDetailView | Case details + documents |
| `/contactverzoeken` | ContactenverzoekenView | Search contact requests |
| `/links` | LinksView | Quick links for KCMs |

### 6.2 Beheer (Admin) Views

All under `/beheer`, requires beheer tab permissions:

| Route | View | Permission |
|-------|------|------------|
| `/beheer/NieuwsEnWerkinstructies` | NieuwsEnWerkinstructiesBeheer | berichtenbeheer |
| `/beheer/NieuwsEnWerkinstructie/:id?` | NieuwsEnWerkinstructieBeheer | berichtenbeheer |
| `/beheer/Skills` | SkillsBeheer | skillsbeheer |
| `/beheer/Skill/:id?` | SkillBeheer | skillsbeheer |
| `/beheer/Links` | LinksBeheer | linksbeheer |
| `/beheer/Link/:id?` | LinkBeheer | linksbeheer |
| `/beheer/gespreksresultaten` | GespreksresultatenBeheer | gespreksresultatenbeheer |
| `/beheer/gespreksresultaat/:id?` | GespreksresultaatBeheer | gespreksresultatenbeheer |
| `/beheer/formulieren-contactverzoek-afdeling` | ContactverzoekFormulierenBeheer (soort=afdeling) | contactformulierenbeheer |
| `/beheer/formulieren-contactverzoek-groep` | ContactverzoekFormulierenBeheer (soort=groep) | contactformulierenbeheer |
| `/beheer/kanalen` | KanalenBeheer | kanalenbeheer |
| `/beheer/kanaal/:id?` | KanaalBeheer | kanalenbeheer |
| `/beheer/vacs` | VacsBeheer | vacsbeheer |
| `/beheer/vac/:uuid?` | VacBeheer | vacsbeheer |

### 6.3 Feature Modules

#### `features/contact/` — Core contactmoment workflow
- **ContactmomentStarter** — Start button, creates new contactmoment
- **ContactmomentSwitcher** — Switch between parallel contactmomenten
- **ContactmomentVraag** — Current question display/edit
- **ContactmomentVragenMenu** — Question tabs
- **ContactmomentAfhandeling** — Finish flow: select gespreksresultaat, submit
- **ContactmomentFinisher** — Final submission logic
- **ContactmomentCanceller** — Cancel/discard
- **ContactmomentenOverzicht** — List past contactmomenten for a klant
- **ContactmomentenForKlantIdentificator** — Show history by BSN/KvK
- **ContactmomentenForObjectUrl** — Show history by zaak URL

#### `features/contact/contactverzoek/` — Contact request (internal task)
- **ContactverzoekFormulier** — Form for creating contact request
- **ContactverzoekOnderwerpen** — Subject selection
- **GroepenSearch** — Search organizational groups
- **MedewerkerSearch** — Search employees
- **AfdelingenSearch** — Search departments
- **ContactverzoekenOverzicht** — List contact requests
- **ContactverzoekenZoeker** — Search contact requests
- **LogboekOverzicht** — Logbook view for contact requests

#### `features/bedrijf/` — Company features
- **BedrijfZoeker** — Search by KvK number, handelsnaam, vestigingsnummer, postcode
- **BedrijvenOverzicht** — Results table
- **HandelsregisterGegevens** — Company details display

#### `features/zaaksysteem/` — Case management
- **ZaakZoeker** — Search cases
- **ZakenOverzicht** — Results list
- **ZakenForKlant** — Cases linked to a person/company
- **ZaakAlgemeen** — General case info
- **ZaakDocumenten** — Case documents
- **ZaakDeeplink** — Deep link to external zaaksysteem

#### `features/klant/` — Customer/party management
- **KlantDetails** — Display klant details (from OpenKlant partij)

#### `features/werkbericht/` — Work messages
- **WerkBerichten** — Published berichten with skill filtering
- **WerkBericht** — Single bericht display

#### `features/links/` — Quick links
- **LinkList** — Categorized link display

#### `features/feedback/` — Content feedback
- **ContentFeedback** / **FeedbackForm** — Send feedback email

#### `features/login/` — Authentication
- **LoginOverlay** — Login prompt
- **RedirectPage** — Post-login redirect

#### `features/Kanalen/` — Channel selection
- **KanalenOverzicht** — Channel dropdown for contactmoment

---

## 7. Objects API Usage (External Object Schemas)

KISS expects several object types stored in a ZGW Objects API (or compatible). Each is configured with its own base URL, token, and objecttype URL.

### 7.1 Afdelingen (Departments)

**Base URL:** `AFDELINGEN_BASE_URL`
**Object Type:** `AFDELINGEN_OBJECT_TYPE_URL`
**Auth:** Token or ClientId/ClientSecret

Used for: Department search when creating contact requests.

**Expected schema shape:**
```json
{
  "record": {
    "data": {
      "id": "string",
      "identificatie": "string",
      "naam": "string"
    }
  }
}
```

### 7.2 Groepen (Groups)

**Base URL:** `GROEPEN_BASE_URL`
**Object Type:** `GROEPEN_OBJECT_TYPE_URL`
**Auth:** Token or ClientId/ClientSecret

Used for: Group search when creating contact requests.

**Expected schema shape:**
```json
{
  "record": {
    "data": {
      "id": "string",
      "afdelingId": "string",
      "identificatie": "string",
      "naam": "string"
    }
  }
}
```

### 7.3 VACs (Vraag-Antwoord Combinaties)

**Base URL:** `VAC_OBJECTEN_BASE_URL`
**Object Type:** `VAC_OBJECT_TYPE_URL`
**Type Version:** `VAC_OBJECT_TYPE_VERSION`
**Auth:** Token

Used for: FAQ-style question-answer pairs managed in admin, displayed to KCMs.

**Expected schema shape:**
```json
{
  "type": "<objectTypeUrl>",
  "record": {
    "typeVersion": "<version>",
    "data": {
      "vraag": "string",
      "antwoord": "string",
      "afdelingen": [
        {
          "afdelingnaam": "string"
        }
      ]
    }
  }
}
```

### 7.4 Interne Taken (Internal Tasks / Contact Requests for OpenKlant 1)

**Base URL:** `INTERNE_TAAK_BASE_URL` (per registry system)
**Object Type:** `INTERNE_TAAK_OBJECT_TYPE_URL`
**Type Version:** `INTERNE_TAAK_TYPE_VERSION`
**Auth:** Token or ClientId/ClientSecret

Used for: Storing contactverzoeken as objects when using OpenKlant 1 (no native klantinteracties API).

**Expected schema shape (ContactverzoekData):**
```json
{
  "type": "<objectTypeUrl>",
  "record": {
    "typeVersion": "<version>",
    "startAt": "2024-01-15",
    "data": {
      "status": "te verwerken",
      "contactmoment": "https://contactmomenten.api/url",
      "registratiedatum": "2024-01-15T10:30:00Z",
      "toelichting": "string",
      "actor": {
        "naam": "string",
        "soortActor": "medewerker|organisatorische eenheid",
        "identificatie": "string",
        "typeOrganisatorischeEenheid": "afdeling|groep",
        "naamOrganisatorischeEenheid": "string",
        "identificatieOrganisatorischeEenheid": "string"
      },
      "betrokkene": {
        "rol": "klant",
        "klant": "https://klanten.api/url",
        "persoonsnaam": {
          "voornaam": "string",
          "voorvoegselAchternaam": "string",
          "achternaam": "string"
        },
        "organisatie": "string",
        "digitaleAdressen": [
          {
            "adres": "string",
            "soortDigitaalAdres": "email|telefoonnummer|overig",
            "omschrijving": "string"
          }
        ]
      },
      "verantwoordelijkeAfdeling": "string",
      "medewerkerIdentificatie": {
        "achternaam": "string",
        "identificatie": "string",
        "voorletters": "string",
        "voorvoegselAchternaam": "string"
      }
    }
  }
}
```

### 7.5 Logboek (Logbook)

**Base URL:** `LOGBOEK_BASE_URL`
**Object Type:** `LOGBOEK_OBJECT_TYPE_URL`
**Type Version:** `LOGBOEK_OBJECT_TYPE_VERSION`
**Auth:** Token
**Access:** GET only (read-only from KISS perspective)

Used for: Viewing logbook entries related to contact requests.

---

## 8. OpenKlant Integration Flow

### 8.1 OpenKlant 2 Flow (Klantinteracties API)

**Person search → Contact registration → Case linking:**

1. **Search person** — POST `/api/haalcentraal/brp/personen` (BRP lookup by BSN, name+DOB, or postcode+housenumber)
2. **Find or create Partij** — GET/POST `/api/klantinteracties/api/v1/partijen` with BSN identifier
3. **Create Klantcontact** — POST `/api/postklantcontacten`:
   - BFF creates/finds Actor (medewerker) by email
   - BFF posts klantcontact with kanaal, onderwerp, inhoud
   - BFF links actor to klantcontact via actorklantcontacten
4. **Create Betrokkene** — POST `/api/klantinteracties/api/v1/betrokkenen` linking partij to klantcontact
5. **Save Digitale Adressen** — POST `/api/klantinteracties/api/v1/digitaleadressen` (email, phone)
6. **Link Zaak** — POST `/api/klantinteracties/api/v1/onderwerpobjecten` with zaak identifier
7. **Create Contactverzoek (Interne Taak)** — POST `/api/postinternetaak`:
   - Creates actor(s) for target department/group/medewerker
   - Posts internetaak with toelichting, status "te_verwerken"
8. **Save ContactmomentDetails** — PUT `/api/contactmomentdetails` (local DB: gespreksresultaat, bronnen, startdatum/einddatum)

### 8.2 OpenKlant 1 Flow (Legacy)

1. **Search person** — Same BRP lookup
2. **Find Klant** — GET `/api/klanten/{path}` (OpenKlant v1 klanten API)
3. **Create Contactmoment** — POST `/api/postcontactmomenten` (adds medewerkerIdentificatie server-side)
4. **Link Klant** — POST klantcontactmomenten
5. **Link Zaak** — POST objectcontactmomenten
6. **Create Contactverzoek** — POST `/api/internetaak/api/v2/objects` (Objects API format with ContactverzoekData)

---

## 9. Search Architecture

KISS uses **two search backends** (legacy system):

1. **Elasticsearch** — Direct search via `/api/elasticsearch/{index}/_search`
   - Role-based field filtering: Kennisbank users have fields excluded (configurable via `ELASTIC_EXCLUDED_FIELDS_KENNISBANK`)
   - Used for knowledge articles, VACs, websites

2. **Elastic Enterprise Search** — Via YARP proxy `/api/enterprisesearch/api/as/v1/engines/{engine}/search_explain`
   - Used for search_explain queries
   - Bearer token auth

---

## 10. OpenRegister Schema Requirements

To replicate KISS functionality in OpenRegister, the following schemas would be needed:

### Required Object Schemas:

1. **Afdeling** — `{ id, identificatie, naam }`
2. **Groep** — `{ id, afdelingId, identificatie, naam }`
3. **VAC** — `{ vraag, antwoord, afdelingen: [{ afdelingnaam }] }`
4. **InterneTaak** — Full ContactverzoekData structure (see section 7.4)
5. **Logboek** — Read-only log entries for contact requests

### Required Internal Schemas (currently PostgreSQL):

6. **ContactmomentDetails** — `{ id, startdatum, einddatum, gespreksresultaat, vraag, specifiekeVraag, emailadresKcm, verantwoordelijkeAfdeling, bronnen[] }`
7. **Gespreksresultaat** — `{ id, definitie }`
8. **Kanaal** — `{ id, naam }` (unique name)
9. **Link** — `{ id, titel, url, categorie }`
10. **Bericht** — `{ id, titel, inhoud, type, publicatieDatum, publicatieEinddatum, isBelangrijk, skills[] }`
11. **Skill** — `{ id, naam, isDeleted }`
12. **ContactVerzoekVragenSet** — `{ id, titel, jsonVragen, organisatorischeEenheidId, organisatorischeEenheidNaam, organisatorischeEenheidSoort }`
13. **VerwerkingsLog** — `{ id, userId, insertedAt, apiEndpoint, method }` (audit trail)

---

## 11. Environment Variables Reference

### Core
- `OIDC_AUTHORITY`, `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`, `OIDC_METADATA_URL`
- `OIDC_KLANTCONTACTMEDEWERKER_ROLE`, `OIDC_REDACTEUR_ROLE`, `OIDC_BEHEERDER_ROLE`, `OIDC_KENNISBANK_ROLE`
- `OIDC_MEDEWERKER_IDENTIFICATIE_CLAIM`, `OIDC_MEDEWERKER_IDENTIFICATIE_TRUNCATE`
- `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_HOST`, `POSTGRES_DB`, `POSTGRES_PORT`
- `ORGANISATIE_IDS`

### External Services
- `HAAL_CENTRAAL_BASE_URL`, `HAAL_CENTRAAL_API_KEY`
- `KVK_BASE_URL`, `KVK_API_KEY`
- `ZAKEN_API_KEY`, `ZAKEN_API_CLIENT_ID`
- `ENTERPRISE_SEARCH_BASE_URL`, `ENTERPRISE_SEARCH_PRIVATE_API_KEY`
- `ELASTIC_BASE_URL`, `ELASTIC_USERNAME`, `ELASTIC_PASSWORD`, `ELASTIC_EXCLUDED_FIELDS_KENNISBANK`

### Objects API
- `AFDELINGEN_BASE_URL`, `AFDELINGEN_TOKEN`, `AFDELINGEN_OBJECT_TYPE_URL`
- `GROEPEN_BASE_URL`, `GROEPEN_TOKEN`, `GROEPEN_OBJECT_TYPE_URL`
- `VAC_OBJECTEN_BASE_URL`, `VAC_OBJECTEN_TOKEN`, `VAC_OBJECT_TYPE_URL`, `VAC_OBJECT_TYPE_VERSION`
- `LOGBOEK_BASE_URL`, `LOGBOEK_TOKEN`, `LOGBOEK_OBJECT_TYPE_URL`, `LOGBOEK_OBJECT_TYPE_VERSION`

### Multi-Registry (per system, via REGISTERS array)
- `REGISTRY_VERSION` (OpenKlant1 | OpenKlant2)
- `IS_DEFAULT`
- `KLANTINTERACTIE_BASE_URL`, `KLANTINTERACTIE_TOKEN` (OK2)
- `CONTACTMOMENTEN_BASE_URL`, `CONTACTMOMENTEN_API_CLIENT_ID`, `CONTACTMOMENTEN_API_KEY` (OK1)
- `KLANTEN_BASE_URL`, `KLANTEN_CLIENT_ID`, `KLANTEN_CLIENT_SECRET` (OK1)
- `INTERNE_TAAK_BASE_URL`, `INTERNE_TAAK_OBJECT_TYPE_URL`, etc. (OK1)
- `ZAAKSYSTEEM_BASE_URL` or `ZAAKSYSTEEM_ZAKEN/CATALOGI/DOCUMENTEN_BASE_URL`
- `ZAAKSYSTEEM_API_KEY`, `ZAAKSYSTEEM_API_CLIENT_ID`
- `ZAAKSYSTEEM_DEEPLINK_URL`, `ZAAKSYSTEEM_DEEPLINK_PROPERTY`

### Features
- `USE_VACS` — Enable VAC management
- `USE_MEDEWERKEREMAIL` — Use email for medewerker identification
- `MANAGEMENTINFORMATIE_API_KEY` — JWT key for external reporting API
- `EMAIL_HOST`, `EMAIL_PORT`, `EMAIL_USERNAME`, `EMAIL_PASSWORD` — SMTP for feedback
- `FEEDBACK_EMAIL_FROM`, `FEEDBACK_EMAIL_TO`
