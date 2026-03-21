---
title: "KISS (Klantinteractie-Servicesysteem) - Browser Walkthrough Spec"
status: documented
date: 2026-03-14
version: "0.0.0 (dev)"
source: "http://localhost:9030"
auth: "Keycloak OIDC (http://localhost:8081/realms/commonground)"
stack: ".NET 8 BFF + Vue 3 SPA + PostgreSQL + Elasticsearch"
---

# KISS - Klantinteractie-Servicesysteem

## 1. System Overview

KISS is a Dutch government **Customer Interaction Service System** designed for KCC
(Klant Contact Centrum) employees. It provides a unified interface for:

- Looking up citizens (BRP) and companies (KVK)
- Managing contact moments (contactmomenten)
- Searching and linking cases (zaken)
- Managing contact requests (contactverzoeken)
- Publishing news and work instructions
- Administering skills, conversation results, channels, and contact forms

### Architecture

| Component | Technology | Port |
|---|---|---|
| BFF (Backend for Frontend) | ASP.NET Core 8 | 9030 (host) -> 8080 (container) |
| Frontend | Vue 3 + Pinia + Vue Router | Served by BFF |
| Database | PostgreSQL 16 | kiss-postgres (internal) |
| Search | Elasticsearch 8.12 | 9230 (host) -> 9200 (container) |
| Authentication | Keycloak OIDC | 8081 (host) |
| BRP API | Haal Centraal BRP mock | brp-personen-mock:8080 (internal) |
| KVK API | KVK Test API | api.kvk.nl/test/api (external) |
| ZGW APIs | OpenZaak | openzaak:8000 (internal) |
| Klantinteracties | OpenKlant 2 | openklant:8000 (internal) |

### Authentication & Authorization

- OIDC via Keycloak realm `commonground`, client `kiss`
- Roles: `user` (klantcontactmedewerker), `admin` (redacteur + beheerder + kennisbank)
- User state (Pinia store `user`):
  - `isLoggedIn`, `email`, `isRedacteur`, `isKcm`, `isKennisbank`
  - `organisatieIds`: array of organisation identifiers
  - `permissions`: granular array including:
    - `afdelingen`, `groepen`
    - `skillsread`, `skillsbeheer`
    - `gespreksresultatenread`, `gespreksresultatenbeheer`
    - `kanalenread`, `kanalenbeheer`
    - `contactformulierenread`, `contactformulierenbeheer`
    - `berichtenread`, `berichtenbeheer`
    - `linksread`, `linksbeheer`

### Pinia Stores

| Store | Key State |
|---|---|
| `contactmoment` | `contactmomentLoopt` (bool), `contactmomenten` (array), `vragenSets` (array), `loading` (bool) |
| `user` | `user` (auth info), `preferences` (kanaal, skills) |

---

## 2. Global Layout

### Header Bar (banner)

Dark blue-gray header bar containing:

1. **Organisation logo** (group element, left side)
2. **Global search**: combobox labeled "Zoekterm" + "Zoeken" button
   - Enterprise Search / Elasticsearch-powered
   - Searches across news, work instructions, and knowledge base
3. **Primary navigation** (horizontal link list):
   - When contactmoment is NOT active: `Nieuws en werkinstructies`, `Links`, `Beheer`, `Uitloggen`
   - When contactmoment IS active: `Contactverzoeken`, `Personen`, `Bedrijven`, `Zaken`, `Nieuws en werkinstructies` (with unread badge), `Links`, `Uitloggen`

### Left Sidebar (complementary)

Light blue-gray sidebar on the left side:

- **"Nieuw contactmoment"** button (when no contactmoment active) or **"Nieuw"** button (when active)
- Below: list of active contactmomenten (tabs for parallel handling)

### Footer

- Version info: `Versie 0.0.0 | Commit dev.dev`

---

## 3. Pages - Public / KCM View

### 3.1 Home Page - Nieuws en werkinstructies

**Route:** `/`
**Title:** "Nieuws en werkinstructies"
**Nav badge:** Shows count of unread/new items (e.g., "2")

**Content:**
- When no sample data: Shows prompt "Wilt u KISS vullen met voorbeelddata voor Gespreksresultaten, Skills, Nieuws en Werkinstructies en Links?" with "Voorbeelddata aanmaken" button
- When data exists: Displays list of berichten (news and work instructions)

**API:** `GET /api/berichten` returns array of:
```json
{
  "id": 1,
  "titel": "KennisbronnenKaart bijgewerkt",
  "type": "Nieuws",              // or "Werkinstructie"
  "publicatiedatum": "2026-03-14T18:00:03.833261+00:00",
  "dateCreated": "2026-03-14T18:00:03.833125+00:00",
  "dateUpdated": null
}
```

**Seed data (6 items):**
| Titel | Type |
|---|---|
| KISS op de testomgeving | Nieuws |
| Wijzig je wachtwoord | Werkinstructie |
| Nieuws en werkinstructies toevoegen | Werkinstructie |
| Mantelzorgcadeau volgende maand van start | Werkinstructie |
| Aankomende staking van de vuilnisophaaldienst | Nieuws |
| KennisbronnenKaart bijgewerkt | Nieuws |

---

### 3.2 Personen (Person Search)

**Route:** `/personen`
**Requires:** Active contactmoment (`contactmomentLoopt = true`)
**Heading:** "Personen"
**Instruction text:** "Zoek op een van de onderstaande combinaties."

**Three search forms:**

#### Form 1: Search by Name + Date of Birth
| Field | Type | Label | Required |
|---|---|---|---|
| Achternaam | textbox | "Achternaam" | Yes |
| Geboortedatum | textbox | "Geboortedatum" | Yes |
| | button | "Zoeken" | |

**API:** `POST /api/haalcentraal/brp/personen` with:
```json
{
  "type": "ZoekMetGeslachtsnaamEnGeboortedatum",
  "geslachtsnaam": "...",
  "geboortedatum": "YYYY-MM-DD",
  "fields": ["burgerservicenummer", "naam", "geboorte", "verblijfplaats", "geslacht"]
}
```

#### Form 2: Search by Address
| Field | Type | Label | Required |
|---|---|---|---|
| Postcode | textbox | "Postcode" | Yes (red border) |
| Huisnummer | textbox | "Huisnummer" | Yes (red border) |
| Huisletter | textbox | "Huisletter" | No |
| Toevoeging | textbox | "Toevoeging" | No |
| Achternaam | textbox | "Achternaam" | No |
| | button | "Zoeken" | |

**API:** `POST /api/haalcentraal/brp/personen` with:
```json
{
  "type": "ZoekMetPostcodeEnHuisnummer",
  "postcode": "...",
  "huisnummer": 0,
  "fields": [...]
}
```

#### Form 3: Search by BSN
| Field | Type | Label | Required |
|---|---|---|---|
| Bsn | textbox | "Bsn" | Yes (red border) |
| | button | "Zoeken" | |

**API:** `POST /api/haalcentraal/brp/personen` with:
```json
{
  "type": "RaadpleegMetBurgerservicenummer",
  "burgerservicenummer": ["999990627"],
  "fields": ["burgerservicenummer", "naam", "geboorte", "verblijfplaats", "geslacht"]
}
```

**BRP Response structure (Haal Centraal):**
```json
{
  "type": "RaadpleegMetBurgerservicenummer",
  "personen": [{
    "burgerservicenummer": "999990627",
    "geslacht": { "code": "M", "omschrijving": "man" },
    "naam": {
      "voornamen": "Stephan",
      "geslachtsnaam": "Janssen",
      "voorletters": "S.",
      "volledigeNaam": "Stephan Janssen",
      "aanduidingNaamgebruik": { "code": "E", "omschrijving": "eigen geslachtsnaam" }
    },
    "geboorte": {
      "land": { "code": "6030", "omschrijving": "Nederland" },
      "plaats": { "code": "0584", "omschrijving": "Oud-Beijerland" },
      "datum": { "type": "Datum", "datum": "1975-04-06", "langFormaat": "6 april 1975" }
    },
    "verblijfplaats": {
      "type": "Adres",
      "verblijfadres": {
        "officieleStraatnaam": "Mandelaplein",
        "huisnummer": 2,
        "postcode": "2572HT",
        "woonplaats": "'s-Gravenhage"
      }
    }
  }]
}
```

**Person detail route:** `/personen/:internalKlantId`

---

### 3.3 Bedrijven (Company Search)

**Route:** `/bedrijven`
**Requires:** Active contactmoment
**Heading:** "Bedrijven"
**Instruction text:** "Zoek op een van de onderstaande combinaties."

**Three search forms:**

#### Form 1: Search by Company Name
| Field | Type | Label | Required |
|---|---|---|---|
| Bedrijfsnaam | textbox | "Bedrijfsnaam" | Yes (red border) |
| | button | "Zoeken" | |

#### Form 2: Search by KVK/Vestigingsnummer
| Field | Type | Label | Required |
|---|---|---|---|
| KVK-nummer of vestigingsnummer | textbox | "KVK-nummer of vestigingsnummer" | Yes (red border) |
| | button | "Zoeken" | |

#### Form 3: Search by Address
| Field | Type | Label | Required |
|---|---|---|---|
| Postcode | textbox | "Postcode" | Yes |
| Huisnummer | textbox | "Huisnummer" | Yes (red border) |
| | button | "Zoeken" | |

**API:** `GET /api/kvk/...` (proxied to KVK Test API at api.kvk.nl/test/api)

**Company detail route:** `/bedrijven/:internalKlantId`

---

### 3.4 Contactverzoeken (Contact Requests)

**Route:** `/contactverzoeken`
**Requires:** Active contactmoment
**Heading:** "Contactverzoeken"

**Search form:**
| Field | Type | Label | Required |
|---|---|---|---|
| Telefoonnummer of e-mailadres | textbox | "Telefoonnummer of e-mailadres" | Yes |
| | button | "Zoeken" | |

**API:** Searches via OpenKlant 2 klantinteracties API

---

### 3.5 Zaken (Cases)

**Route:** `/zaken`
**Requires:** Active contactmoment
**Heading:** "Zaken"

**Search form:**
| Field | Type | Placeholder |
|---|---|---|
| (unnamed) | searchbox | "Zoek op zaaknummer" |
| | button | "Zoeken" (icon) |

**API:** `GET /api/zaken/...` (proxied to OpenZaak zaken API)

**Case detail route:** `/zaken/:zaakId`

---

### 3.6 Links (Quick Links)

**Route:** `/links`
**Heading:** "Links"

**Content:** Categorized list of external links displayed as definition list (dl/dt/dd).

**Structure:**
- Category heading (dt): e.g., "Common Ground", "KISS"
- Links (dd): clickable external links

**Seed data (5 links in 2 categories):**

| Category | Title | URL |
|---|---|---|
| Common Ground | Common Ground op Pleio | https://commonground.nl/ |
| Common Ground | Programma Common Ground | https://vng.nl/projecten/programma-common-ground |
| KISS | Beheerhandleiding KISS | https://kiss-klantinteractie-servicesysteem.readthedocs.io/en/stable/manual/manual.html |
| KISS | De repository van KISS, op Github | https://github.com/Klantinteractie-Servicesysteem/ |
| KISS | Documentatie KISS | https://kiss-klantinteractie-servicesysteem.readthedocs.io/ |

**API:** `GET /api/links` returns:
```json
[
  {
    "categorie": "Common Ground",
    "items": [
      { "id": 4, "titel": "Common Ground op Pleio", "categorie": "Common Ground", "url": "https://commonground.nl/" }
    ]
  }
]
```

---

### 3.7 Afhandeling (Contact Moment Finalization)

**Route:** `/afhandeling`
**Heading:** "Afhandeling"
**Back link:** "Terug" (navigates to previous page)

**Purpose:** Final step in contact moment workflow. Allows the KCM employee to:
- Summarize the contact moment
- Select conversation result (gespreksresultaat)
- Add notes
- Link to persons, companies, cases
- Submit the contact moment to OpenKlant 2

**Note:** Form content is dynamically loaded based on active contactmoment data from the backend.

---

## 4. Pages - Beheer (Administration)

### 4.0 Beheer Landing Page

**Route:** `/beheer`
**Layout:** Simplified header (only "Uitloggen"), no sidebar, no global search

**Navigation (within beheer):**
- When `berichtenbeheer` + `linksbeheer` permissions are present:
  - Home, Nieuws en werkinstructies, Skills, Links, Gespreksresultaten, Kanalen, Contactverzoekformulieren afdelingen, Contactverzoekformulieren groepen
- Without those permissions:
  - Home, Skills, Gespreksresultaten, Kanalen, Contactverzoekformulieren afdelingen, Contactverzoekformulieren groepen

---

### 4.1 Beheer - Skills

**Route (list):** `/beheer/Skills`
**Route (detail):** `/beheer/Skill/:id?`
**Heading:** "Skills"

**List view:** Vertical list of skill names, each with:
- Link to detail page
- "Verwijderen" (delete) button (trash icon)
- "toevoegen" (add) link at bottom (+ icon)

**Detail/Edit form:**
| Field | Type | Label |
|---|---|---|
| Naam | textbox | "Naam" |
| | button | "Annuleren" (link to list) |
| | button | "Opslaan" |

**API:** `GET /api/skills` returns:
```json
[
  { "id": 5, "naam": "KCC" },
  { "id": 1, "naam": "afval" }
]
```

**Seed data (8 skills):**
KCC, afval, algemeen, belastingen, burgerzaken, subsidies, vergunningen, werk en inkomen

---

### 4.2 Beheer - Gespreksresultaten (Conversation Results)

**Route (list):** `/beheer/gespreksresultaten`
**Route (detail):** `/beheer/gespreksresultaat/:id?`
**Heading:** "Gespreksresultaten"

**List view:** Same pattern as Skills - list with edit links + delete buttons + add button.

**Detail/Edit form:**
| Field | Type | Label |
|---|---|---|
| Titel | textbox | "Titel" |
| | button | "Annuleren" (link to list) |
| | button | "Opslaan" |

**API:** `GET /api/gespreksresultaten` returns:
```json
[
  { "id": "a6d61ad0-...", "definitie": "Afgehandeld na ruggespraak" }
]
```

**Seed data (8 results):**
| Definitie |
|---|
| Afgehandeld na ruggespraak |
| Afgehandeld, klant belt terug |
| Doorverbonden naar afdeling |
| Doorverbonden naar collega |
| Klant neemt opnieuw contact op |
| Verbinding verbroken |
| Verwezen naar ander instantie |
| Zelfstandig afgehandeld |

---

### 4.3 Beheer - Kanalen (Channels)

**Route (list):** `/beheer/kanalen`
**Route (detail):** `/beheer/kanaal/:id?`
**Heading:** "Kanalen"

**List view:** Same pattern. Shows "Geen kanalen gevonden." when empty.

**Detail/Edit form:**
| Field | Type | Label |
|---|---|---|
| Naam | textbox | "Naam" |
| | button | "Annuleren" (link to list) |
| | button | "Opslaan" |

**API:** `GET /api/kanalen`

**Note:** No seed data - channels must be created manually.

---

### 4.4 Beheer - Nieuws en Werkinstructies (News & Work Instructions)

**Route (list):** `/beheer/NieuwsEnWerkinstructies`
**Route (detail):** `/beheer/NieuwsEnWerkinstructie/:id?`
**Requires permission:** `berichtenbeheer`
**Heading:** "Berichten"

**List view:** Table with columns:
| Column | Description |
|---|---|
| Titel | Article title |
| Type | "Nieuws" or "Werkinstructie" |
| Publicatiedatum | Publication date (dd-MM-yyyy, HH:mm) |
| Aangemaakt op | Creation date |
| Gewijzigd op | Modification date (or "-") |
| Acties | Delete button + Detail link (chevron) |

Plus "Toevoegen" link at top right.

**Detail/Edit form:**
| Field | Type | Label | Notes |
|---|---|---|---|
| (info) | text | "Aangemaakt op [datetime]" | Read-only creation date |
| Type | radio group | "Type" | Options: "Werkinstructie", "Nieuws" |
| Titel | textbox | "Titel" | |
| Inhoud | Rich Text Editor (CKEditor 5) | "Inhoud" | Toolbar: Paragraph/Heading dropdown, Bold, Italic, Link, Bulleted List, Numbered List, Block quote, Undo, Redo, Insert table |
| Belangrijk | checkbox | "Belangrijk" | Flags item as important |
| Publicatiedatum | datetime-local | "Publicatiedatum" | |
| Publicatie-einddatum | datetime-local | "Publicatie-einddatum" | |
| Skills | checkbox group | "Skills" | All available skills as checkboxes |
| | button | "Annuleren" | |
| | button | "Opslaan" | |

**Full API entity:**
```json
{
  "id": 1,
  "publicatieDatum": "2026-03-14T18:00:03.833261+00:00",
  "publicatieEinddatum": "2027-03-14T18:00:03.833311+00:00",
  "dateCreated": "2026-03-14T18:00:03.833125+00:00",
  "dateUpdated": null,
  "titel": "KennisbronnenKaart bijgewerkt",
  "inhoud": "<p>HTML content...</p>",
  "isBelangrijk": false,
  "skills": [],
  "type": "Nieuws"
}
```

---

### 4.5 Beheer - Links

**Route (list):** `/beheer/Links`
**Route (detail):** `/beheer/Link/:id?`
**Requires permission:** `linksbeheer`
**Heading:** "Links"

**List view:** Grouped by category (h2 headings), each link with:
- Link to detail page
- "Verwijderen" (delete) button
- "toevoegen" (add) button at bottom

**Detail/Edit form fields (from API entity):**
| Field | Type | Label |
|---|---|---|
| Titel | textbox | "Titel" |
| URL | textbox/url | "URL" |
| Categorie | textbox | "Categorie" |
| | button | "Annuleren" |
| | button | "Opslaan" |

**Note:** Detail form returned 403 Forbidden in this test environment. Fields inferred from API entity structure:
```json
{
  "id": 1,
  "titel": "De repository van KISS, op Github",
  "categorie": "KISS",
  "url": "https://github.com/Klantinteractie-Servicesysteem/"
}
```

---

### 4.6 Beheer - Contactverzoekformulieren Afdelingen (Contact Request Forms - Departments)

**Route (list):** `/beheer/formulieren-contactverzoek-afdeling`
**Route (detail):** `/beheer/formulier-contactverzoek-afdeling/:id?`
**Heading:** "Formulieren contactverzoek afdelingen"

**List view:** Table with columns:
| Column | Description |
|---|---|
| Titel | Form template title |
| afdeling | Associated department |
| (actions) | Edit/delete |

Plus "Toevoegen" link.

**Detail/New form (Form Builder):**

**Info text:** "Hier kan je een template maken voor een contactverzoek. Houd er rekening mee dat dit template een aanvulling is op de standaard vragen. Deze hoef je hier dus niet toe te voegen. De standaardvragen zijn:
- Klantnaam
- Telefoonnummer 1
- Telefoonnummer 2
- Omschrijving telefoonnummer 2
- E-mailadres
- Interne toelichting voor medewerker"

| Field | Type | Label | Notes |
|---|---|---|---|
| Titel | textbox | "Titel *" | Required |
| Afdeling | combobox | "Afdeling*" | Required, loaded from Objects API (afdelingen endpoint) |
| Vraag toevoegen | select/combobox | "Vraag toevoegen" | Dynamic question builder |
| | button | "Annuleren" | |
| | button | "Opslaan" | |

**"Vraag toevoegen" options:**
| Option | Description |
|---|---|
| Kies een vraag | Default placeholder (disabled) |
| Open vraag kort | Short text input |
| Open vraag lang | Long text / textarea |
| Dropdown | Select dropdown with custom options |
| Checkbox | Checkbox field |

**Note:** The Afdeling combobox attempts to load from Objects API (`/afdelingen/api/v2/objects`) which is not configured, causing a 404 error.

---

### 4.7 Beheer - Contactverzoekformulieren Groepen (Contact Request Forms - Groups)

**Route (list):** `/beheer/formulieren-contactverzoek-groep`
**Route (detail):** `/beheer/formulier-contactverzoek-groep/:id?`
**Heading:** "Formulieren contactverzoek groepen"

**Identical to Afdelingen form builder** but with "groep" (group) instead of "afdeling" (department):

**List view table columns:** Titel, groep, (actions)

**Detail form:** Same structure as afdelingen form, with "Groep" dropdown instead of "Afdeling".

---

### 4.8 Beheer - VACs (Vraag-Antwoord Combinaties)

**Route (list):** `/beheer/vacs`
**Route (detail):** `/beheer/vac/:uuid?`
**Status:** DISABLED - requires `use-vacs` environment flag which returns 403.

VACs (Question-Answer Combinations) are FAQ-like entries. This feature requires the Objects API to be configured with VAC object types.

---

## 5. Vue Router - Complete Route Map

| Route | Page | Auth Required | Contactmoment Required |
|---|---|---|---|
| `/` | Nieuws en werkinstructies (Home) | Yes | No |
| `/personen` | Person search | Yes | Yes |
| `/personen/:internalKlantId` | Person detail | Yes | Yes |
| `/bedrijven` | Company search | Yes | Yes |
| `/bedrijven/:internalKlantId` | Company detail | Yes | Yes |
| `/contactverzoeken` | Contact request search | Yes | Yes |
| `/zaken` | Case search | Yes | Yes |
| `/zaken/:zaakId` | Case detail | Yes | Yes |
| `/afhandeling` | Contact moment finalization | Yes | Yes |
| `/links` | Quick links | Yes | No |
| `/beheer` | Admin landing | Yes (admin) | No |
| `/beheer/NieuwsEnWerkinstructies` | News management | Yes (berichtenbeheer) | No |
| `/beheer/NieuwsEnWerkinstructie/:id?` | News edit/create | Yes (berichtenbeheer) | No |
| `/beheer/Skills` | Skills list | Yes (skillsbeheer) | No |
| `/beheer/Skill/:id?` | Skill edit/create | Yes (skillsbeheer) | No |
| `/beheer/Links` | Links management | Yes (linksbeheer) | No |
| `/beheer/Link/:id?` | Link edit/create | Yes (linksbeheer) | No |
| `/beheer/gespreksresultaten` | Conversation results list | Yes (gespreksresultatenbeheer) | No |
| `/beheer/gespreksresultaat/:id?` | Conversation result edit | Yes (gespreksresultatenbeheer) | No |
| `/beheer/kanalen` | Channels list | Yes (kanalenbeheer) | No |
| `/beheer/kanaal/:id?` | Channel edit/create | Yes (kanalenbeheer) | No |
| `/beheer/formulieren-contactverzoek-afdeling` | Dept contact forms list | Yes (contactformulierenbeheer) | No |
| `/beheer/formulier-contactverzoek-afdeling/:id?` | Dept contact form edit | Yes (contactformulierenbeheer) | No |
| `/beheer/formulieren-contactverzoek-groep` | Group contact forms list | Yes (contactformulierenbeheer) | No |
| `/beheer/formulier-contactverzoek-groep/:id?` | Group contact form edit | Yes (contactformulierenbeheer) | No |
| `/beheer/vacs` | VACs list (disabled) | Yes | No |
| `/beheer/vac/:uuid?` | VAC edit (disabled) | Yes | No |
| `/redirect-to-login` | OIDC login redirect | No | No |

---

## 6. BFF API Endpoints

| Endpoint | Method | Description | Status |
|---|---|---|---|
| `/api/berichten` | GET | List news/work instructions | 200 |
| `/api/berichten/:id` | GET | Get single bericht | 200 |
| `/api/links` | GET | List links (grouped by category) | 200 |
| `/api/links/:id` | GET | Get single link | 403* |
| `/api/skills` | GET | List skills | 200 |
| `/api/gespreksresultaten` | GET | List conversation results | 200 |
| `/api/kanalen` | GET | List channels | 200 |
| `/api/contactformulieren` | GET | List contact form templates | 200 |
| `/api/environment/use-vacs` | GET | Check VAC feature flag | 403 |
| `/api/seed/check` | GET | Check if seed data exists | 409 (exists) |
| `/api/haalcentraal/brp/personen` | POST | BRP person search (proxy) | 200 |
| `/api/kvk/...` | GET | KVK company search (proxy) | - |
| `/api/logoff` | GET | OIDC logout | - |
| `/healthz` | GET | Health check | - |

*Link detail 403 appears to be a permission issue in this test environment.

---

## 7. External Service Integration

### BRP (Basisregistratie Personen) - Haal Centraal

- **Mock:** `brp-personen-mock:8080/haalcentraal/api`
- **Proxy:** KISS BFF at `/api/haalcentraal/brp/personen`
- **Search types:**
  - `RaadpleegMetBurgerservicenummer` - by BSN
  - `ZoekMetGeslachtsnaamEnGeboortedatum` - by name + DOB
  - `ZoekMetPostcodeEnHuisnummer` - by address

### KVK (Kamer van Koophandel)

- **API:** `https://api.kvk.nl/test/api`
- **Auth:** API key `l7xx1f2691f2520d487b902f4e0b57a0b197`

### OpenKlant 2

- **Base:** `openklant:8000/klantinteracties/api/v1`
- **Used for:** Contact moments, contact requests, klant (customer) records

### OpenZaak

- **Base:** `openzaak:8000/zaken/api/v1`
- **Used for:** Case search, case details, case linking

### Objects API (Optional)

- **Afdelingen** (departments): separate Objects API instance
- **Groepen** (groups): separate Objects API instance
- **VACs** (FAQ entries): separate Objects API instance
- **Interne taken** (internal tasks): separate Objects API instance
- **Status:** Not configured in this environment (404 errors)

---

## 8. Contact Moment Workflow

The core workflow of KISS:

1. **Start** - KCM employee clicks "Nieuw contactmoment" button
   - Sets `contactmomentLoopt = true` in Pinia store
   - Creates new contactmoment entry
   - Navigation switches to contactmoment tabs

2. **Search & Identify** - Employee searches for the caller:
   - `/personen` - Search citizen by BSN, name+DOB, or address
   - `/bedrijven` - Search company by KVK, name, or address
   - `/contactverzoeken` - Search existing contact requests

3. **Link Cases** - View and link relevant cases:
   - `/zaken` - Search by zaaknummer

4. **Finalize** - Complete the contact moment:
   - `/afhandeling` - Summarize, select result, submit

5. **New** - Start another contact moment or finish shift

**Parallel handling:** Multiple contactmomenten can be open simultaneously (shown as tabs in left sidebar).

---

## 9. UI Patterns & Design System

### Color scheme
- Header: Dark blue-gray (#364B5F approximate)
- Sidebar: Light blue-gray
- Buttons (primary): Dark navy (#3C4F63 approximate)
- Buttons (secondary): White with border
- Required field indicator: Red border on input
- Badge: Pink/coral circle with white text
- Links: Standard blue

### Form patterns
- All admin forms follow consistent pattern: Heading > Fields > [Annuleren | Opslaan]
- List pages: items with [edit link | delete button] + add button at bottom
- Search forms: field groups in gray boxes with "Zoeken" button
- Rich text: CKEditor 5 with standard toolbar

### Responsive
- Two-column layout: sidebar (left) + main content (right)
- Admin pages: full width, no sidebar

---

## 10. Screenshots Index

| File | Page |
|---|---|
| `/tmp/kiss-screenshots/01-home.png` | Home page (initial, no contactmoment) |
| `/tmp/kiss-screenshots/02c-personen-wide.png` | Personen search (1440px) |
| `/tmp/kiss-screenshots/03-bedrijven.png` | Bedrijven search |
| `/tmp/kiss-screenshots/04-contactverzoeken.png` | Contactverzoeken search |
| `/tmp/kiss-screenshots/05-zaken.png` | Zaken search |
| `/tmp/kiss-screenshots/06-home-with-contactmoment.png` | Home with contactmoment active |
| `/tmp/kiss-screenshots/07-links.png` | Links page |
| `/tmp/kiss-screenshots/08-beheer.png` | Beheer landing |
| `/tmp/kiss-screenshots/09-beheer-skills.png` | Beheer - Skills list |
| `/tmp/kiss-screenshots/10-beheer-skill-detail.png` | Beheer - Skill edit form |
| `/tmp/kiss-screenshots/11-beheer-gespreksresultaten.png` | Beheer - Gespreksresultaten list |
| `/tmp/kiss-screenshots/12-beheer-gespreksresultaat-detail.png` | Beheer - Gespreksresultaat edit |
| `/tmp/kiss-screenshots/13-beheer-kanalen.png` | Beheer - Kanalen (empty) |
| `/tmp/kiss-screenshots/14-beheer-kanaal-new.png` | Beheer - Kanaal new form |
| `/tmp/kiss-screenshots/15-beheer-formulieren-afdeling.png` | Beheer - Contact forms afdelingen |
| `/tmp/kiss-screenshots/16-beheer-formulier-afdeling-new.png` | Beheer - Contact form builder |
| `/tmp/kiss-screenshots/17-beheer-formulieren-groep.png` | Beheer - Contact forms groepen |
| `/tmp/kiss-screenshots/18-beheer-nieuws.png` | Beheer - Berichten table |
| `/tmp/kiss-screenshots/19-beheer-bericht-detail.png` | Beheer - Bericht edit (rich form) |
| `/tmp/kiss-screenshots/20-beheer-links.png` | Beheer - Links management |
| `/tmp/kiss-screenshots/21-beheer-link-new.png` | Beheer - Link new form |
| `/tmp/kiss-screenshots/22-afhandeling.png` | Afhandeling page |
