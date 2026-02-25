# Projectvoorstel: OWC-Architectuurstack binnen Common Ground

> **Gevraagd budget:** € 300.000
> **Looptijd:** Q1 2025 – Q1 2026
> **Indienende partij:** Open Webconcept (OWC) gemeenten

---

## Managementsamenvatting

De Open Webconcept (OWC) gemeenten vragen een bijdrage van € 300.000 om drie bestaande producten – **Open WOO**, **Vrij BRP** en het **WordPress CMS** – te laten landen binnen het programma Common Ground en op te nemen in Haven.

**Kernboodschap:** Wij ontwikkelen niet, wij hergebruiken. De OWC-stack is gebouwd op bewezen Eurostack-componenten en sluit direct aan bij de Nationale Digitale Strategie (NDS) en het programma Mijn Bureau.

**Wat vragen we concreet?**
1. **Onboarding Open WOO** in Haven (€ 50.000) – geen ontwikkeling, alleen certificering en documentatie
2. **Pilot Vrij BRP** bij gemeente Utrecht (€ 120.000) – overzetting naar Eurostack-architectuur
3. **Landing WordPress CMS** in Common Ground (€ 40.000) – inclusief vergelijkingsonderzoek formulieroplossingen
4. **Onderzoek** naar generieke registeroplossing (€ 30.000)
5. **Coördinatie en onvoorzien** (€ 60.000)

**Waarom nu?**
- De Wet open overheid (Woo) wordt in 2025 volledig verplicht
- Het VNG Najaarscongres (november 2025) is het ideale moment voor een demonstratie
- De OWC-architectuur sluit aan bij Mijn Bureau, dat landelijk wordt uitgerold

---

## 1. Aanleiding en probleemstelling

### 1.1 Fragmentatie binnen Common Ground

Het programma Common Ground kent momenteel meerdere interpretaties en implementaties. Deze fragmentatie leidt tot:

- Onduidelijkheid over de te volgen architectuurkeuzes
- Duplicatie van ontwikkelinspanningen
- Moeilijkheden bij samenwerking en kennisdeling tussen gemeenten
- Complexiteit bij leveranciersselectie en contractvorming

De OWC-gemeenten zien de noodzaak om deze interpretaties te consolideren en te komen tot een meer eenduidige richting.

### 1.2 Capaciteitsvraagstuk

Een fundamentele vraag: hebben 342 Nederlandse gemeenten gezamenlijk de capaciteit om alle benodigde software zelf te ontwikkelen én te onderhouden?

De OWC-gemeenten menen van niet. Daarom pleiten wij voor een radicale focus op hergebruik van bestaande, bewezen componenten.

**Voorbeeld:** In plaats van een eigen documentopslagsysteem te bouwen, gebruiken wij Nextcloud – een platform met miljoenen gebruikers wereldwijd, continue security-updates en een actieve ontwikkelcommunity.

---

## 2. De OWC-visie: minimale code, maximaal hergebruik

### 2.1 Kernprincipe: het ideale aantal regels code is nul

Elke regel code die wij zelf schrijven brengt met zich mee:
What would be the 
| Aspect | Risico |
|--------|--------|
| Onderhoudslast | Code moet worden bijgehouden, getest en gedocumenteerd |
| Beveiligingsrisico's | Elke regel is een potentiële kwetsbaarheid |
| Technische schuld | Code veroudert en moet worden gemoderniseerd |
| Innovatieremming | Bestaande code maakt verandering moeilijker |

### 2.2 Hergebruik boven eigen ontwikkeling

De OWC-gemeenten hanteren een duidelijke voorkeursvolgorde:

```
1. Neem over wat er al is
2. Pas aan waar nodig
3. Ontwikkel alleen zelf wanneer geen alternatief bestaat
```

In de praktijk betekent dit:

- **Libraries** boven eigen implementaties
- **Add-ons** op bestaande frameworks boven nieuwe frameworks
- **Configuratie** boven maatwerk

### 2.3 Selectiecriterium: Eurostack-catalogus

Bij de selectie van componenten hanteren we een harde voorwaarde: de oplossing moet zijn opgenomen in de [Eurostack-catalogus](https://euro-stack.com).

Dit borgt dat:
- We alleen componenten kiezen met Europese verankering en ondersteuning
- De oplossing past binnen het bredere Eurostack-ecosysteem
- Er geen afhankelijkheid ontstaat van niet-Europese cloudproviders
- De component voldoet aan Europese standaarden voor privacy en soevereiniteit

**Voorbeeld:** Voor workflow automation kozen we uit de categorie [Workflow Automation](https://euro-stack.com/categories/workflow-automation) en selecteerden n8n.

### 2.4 Aansluiting bij NDS en Mijn Bureau

De Nederlandse (rijks)overheid werkt via het NDS-programma aan een gemeenschappelijk ICT-landschap. Bij thema's als AI en de digitale werkplek (Mijn Bureau) wordt zwaar geleund op Europese componenten.

De OWC-architectuur is hierop afgestemd. Door nu in te zetten op Nextcloud positioneren gemeenten zich voor naadloze integratie met [Mijn Bureau](https://github.com/MinBZK/mijn-bureau) zodra dit landelijk wordt uitgerold.

---

## 3. Architectuur: twee platformen, één fundament

De OWC-architectuur leunt op twee centrale platformen die onderliggende componenten delen:

```
┌─────────────────────────────────────────────────────────────────┐
│                        LAAG 5: PRESENTATIE                       │
│  ┌─────────────────────┐              ┌─────────────────────┐   │
│  │   WordPress (CMS)   │              │  Statische frontends │   │
│  │   - Website         │              │  - WOO zoekinterface │   │
│  │   - Formulieren     │              │  - BRP balie         │   │
│  │   - Mijn Omgeving   │              │                      │   │
│  └─────────────────────┘              └─────────────────────┘   │
│            NL Design System                NL Design System      │
├─────────────────────────────────────────────────────────────────┤
│                     LAAG 4: BUSINESS LOGICA                      │
│                           n8n (flows)                            │
│                     Camunda (procesorchestratie)                 │
├─────────────────────────────────────────────────────────────────┤
│                       LAAG 3: DATA-OPSLAG                        │
│  ┌─────────────────────┐    ┌─────────────────────────────────┐ │
│  │    Open Register    │    │          Nextcloud              │ │
│  │  (data-orchestratie)│    │    (documenten & werkplek)      │ │
│  └─────────────────────┘    └─────────────────────────────────┘ │
│                          PostgreSQL                              │
├─────────────────────────────────────────────────────────────────┤
│                    LAAG 2: INFRASTRUCTUUR                        │
│   Keycloak │ Prometheus │ Grafana │ OpenTelemetry │ Hugging Face │
└─────────────────────────────────────────────────────────────────┘
```

### 3.1 Platform voor inwoners: WordPress

Voor de interactie met inwoners en bedrijven zetten we in op [WordPress](https://wordpress.org) – wereldwijd het meest gebruikte CMS met 43% marktaandeel.

**Configuratie binnen OWC:**

| Component | Invulling |
|-----------|-----------|
| Basis-styling | [NL Design System](https://nldesignsystem.nl) |
| Gemeente-styling | Huisstijl per gemeente bovenop NL Design |
| Formulieren | WordPress-plugin (geïntegreerd) |
| Mijn Omgeving | WordPress-plugin (geïntegreerd) |

**Voorbeeld:** De gemeente Den Haag draait op het OWC WordPress-platform. Eén installatie verzorgt website, formulieren én Mijn Omgeving – in plaats van drie losse systemen.

**Huidige adoptie:** Circa 40 gemeenten draaien op dit gezamenlijke CMS.

### 3.2 Platform voor medewerkers: Nextcloud

Voor de interne werkomgeving zetten we in op [Nextcloud](https://euro-stack.com/solutions/nextcloud-hub) – een complete digitale werkplek met bestandsopslag, agenda, e-mail, chat, videobellen en kantoorapplicaties.

**Voordelen:**

| Aspect | Toelichting |
|--------|-------------|
| Open source | Volledige controle, geen vendor lock-in |
| Privacy by design | Data blijft binnen eigen infrastructuur (AVG, BIO) |
| Uitbreidbaar | Gemeentelijke apps als Nextcloud-apps |
| Bewezen schaal | Miljoenen gebruikers wereldwijd |
| Interoperabel | CalDAV, CardDAV, WebDAV standaarden |

**Aansluiting Mijn Bureau:** Nextcloud is de basis van [Mijn Bureau](https://github.com/MinBZK/mijn-bureau), het NDS-programma voor de digitale werkplek van de overheid.

### 3.3 Puntoplossingen: statische frontends (laag 5)

Naast de twee platformen zijn er situaties waarin losse puntoplossingen wenselijk zijn. Hiervoor gelden strikte kaders:

- **Uitsluitend statische frontend componenten** – geen eigen backend of database
- **Gebaseerd op NL Design System** – consistente gebruikerservaring
- **Data via platform-API's** – geen eigen persistentielaag
- **Business logica in platformen** – de frontend is slechts weergave

**Voorbeelden:**
- **WOO zoekinterface** – publieke zoekpagina, data uit Nextcloud
- **BRP balie-componenten** – schermen voor burgerzaken, data uit Open Register

---

## 4. Technische stack: 100% Eurostack

### 4.1 Laag 4: Business logica met n8n

Voor business logica gebruiken we [n8n](https://euro-stack.com/solutions/n8n) – een open source workflow automation platform.

**Kernprincipe:** Business logica moet je niet programmeren, maar beschrijven.

**n8n versus Camunda:**

| Aspect | n8n | Camunda |
|--------|-----|---------|
| Toepassing | Business logica in flows | Procesorchestratie (DMN) |
| Voorbeeld | API-aanroepen, validaties | Zaakafhandeling, beslisbomen |
| Aanpak | Visuele flow-editor | BPMN-modellering |

Beide tools hebben hun plek binnen de OWC-architectuur. n8n voor concrete logica, Camunda voor procesmodellering.

**Kenmerken n8n:**
- 170.000+ GitHub-sterren
- 400+ kant-en-klare integraties: [n8n Integrations Store](https://n8n.io/integrations/)
- Zero-code aanpak waar mogelijk

### 4.2 Laag 3: Data-opslag met Open Register

**Open Register** is een Nextcloud-app voor data-orchestratie:

```
Request → Valideren → Opslaan → Response
            ↓
    ┌───────────────────┐
    │ VNG-standaarden   │
    │ GGM-standaarden   │
    │ RAAKT-standaarden │
    │ TOOI-standaarden  │
    │ Domeinspecifiek   │
    └───────────────────┘
```

**Opslag:**
- Gestructureerde data → [PostgreSQL](https://euro-stack.com/solutions/postgresql)
- Documenten → [Nextcloud](https://euro-stack.com/solutions/nextcloud-hub)

**Aanvullende functionaliteit:**
- **Federatief zoeken** via NLX/FSC – data over organisatiegrenzen
- **Vectorisatie** – data leesbaar maken voor LLM's

### 4.3 Laag 2: Infrastructuur

| Functie | Component | Bron |
|---------|-----------|------|
| Authenticatie | Keycloak | [euro-stack.com/solutions/keycloak](https://euro-stack.com/solutions/keycloak) |
| Monitoring | Prometheus | [euro-stack.com/solutions/prometheus](https://euro-stack.com/solutions/prometheus) |
| Dashboards | Grafana | [euro-stack.com/solutions/grafana](https://euro-stack.com/solutions/grafana) |
| Logging | OpenTelemetry | Eurostack-compatible |
| AI/ML | Hugging Face | [huggingface.co](https://huggingface.co) |

### 4.4 Minimale technische schuld

De OWC-architectuur resulteert in een **100% Eurostack backend**.

**Wat onderhouden wij zelf?**

| Component | Type | Omvang |
|-----------|------|--------|
| Open Register configuratie | API-definities, validatieregels | Configuratie |
| n8n flows | Domeinspecifieke business logica | Low-code |
| Frontends laag 5 | NL Design componenten | Minimal code |

**Wat onderhouden wij niet?**

Database, authenticatie, monitoring, logging, AI-infrastructuur – dat is allemaal standaard Eurostack die we *gebruiken*, niet *onderhouden*.

---

## 5. Producten

### 5.1 Gemeentelijk CMS (WordPress)

**Strategische waarde:** De gemeentelijke website is het primaire digitale contactpunt met inwoners. Door deze laag binnen Common Ground te brengen, wordt het programma zichtbaar waar het ertoe doet.

**Huidige status:** Operationeel bij 40+ gemeenten.

**Wat vragen we?** Geen ontwikkeling – alleen landing in het programma en een vergelijkingsonderzoek.

**Formulieren en Mijn Omgeving – bewust bescheiden gepositioneerd**

Het CMS bevat geïntegreerde formulieren- en Mijn Omgeving-plugins. Wij profileren deze bewust minder om een componentenoorlog met Open Formulieren en Open Inwoner te voorkomen.

Onze aanpak:
- Functionaliteit beschikbaar voor gemeenten die de volledige OWC-stack kiezen
- Gemeenten kunnen ook Open Formulieren/Open Inwoner gebruiken
- Wij zoeken actief samenwerking om te convergeren

### 5.2 Open WOO

**Strategische waarde:** De Wet open overheid wordt in 2025 volledig verplicht. Gemeenten hebben nú een oplossing nodig.

**Huidige status:** Operationeel, klaar voor onboarding.

**Wat vragen we?** Geen ontwikkeling – alleen onboarding in Haven en documentatie.

**Componenten:**
- Nextcloud-app voor medewerkers (publiceren, categoriseren)
- Publieke zoekinterface (React/NL Design)
- Koppeling met PLOOI (landelijk platform)
- Automatische anonimisering

### 5.3 Vrij BRP

**Strategische waarde:** De BRP is de kernregistratie van Nederland. Vrijwel elk overheidsproces leunt op BRP-gegevens.

**Huidige status:** Werkend, maar nog niet volledig op Eurostack-architectuur.

**Wat vragen we?** Ontwikkeling van een Proof of Concept bij gemeente Utrecht om de migratie naar Eurostack te beproeven.

**Componenten:**
- Nextcloud-app voor medewerkers
- Balie-interface (NL Design)
- Koppeling met landelijke BRP-voorzieningen
- Audittrail conform AVG

---

## 6. Wat vragen we van Common Ground?

### 6.1 Huidige situatie

De hierboven beschreven architectuur is een visie. De producten zijn werkend, maar nog niet volledig ingericht volgens Eurostack-principes en nog niet opgenomen in Haven.

### 6.2 Drie concrete projectonderdelen

#### 1. Open WOO: onboarding (geen ontwikkeling)

- Doorlopen Common Ground/Haven opnameproces
- Certificering onderliggende componenten (React, Nextcloud, n8n, PostgreSQL)
- Technische en functionele documentatie

**Resultaat:** Open WOO beschikbaar in Haven vóór Woo-verplichting.

#### 2. Vrij BRP: pilot gemeente Utrecht (ontwikkeling)

- Analyse huidige implementatie
- Ontwerp migratie naar Nextcloud + n8n + PostgreSQL
- Ontwikkeling Proof of Concept
- Validatie tegen BRP-eisen

**Resultaat:** Werkende POC op VNG Najaarscongres.

**Let op:** Gemeente Utrecht financiert aanvullend.

#### 3. WordPress CMS: landing en vergelijking (geen ontwikkeling)

- Opname in Common Ground-programma
- Vergelijkingsonderzoek: hergebruik (OWC) vs. zelf ontwikkelen (Open Formulieren/Open Inwoner)

**Resultaat:** Inzichten over voor- en nadelen beide aanpakken.

---

## 7. Onderzoek: één registeroplossing?

Naast productoplevering onderzoeken we een fundamentele vraag:

**Kan Open Register fungeren als één generieke oplossing voor alle registers?**

Huidige situatie: elke registratie heeft een eigen applicatie (BRP-systeem, WOO-systeem, BAG-viewer, BRK-applicatie). Elk met eigen interfaces, koppelingen en onderhoudslasten.

Open Register is in essentie register-agnostisch – het valideert en slaat op, ongeacht of het BRP, WOO of BAG betreft.

**Onderzoeksvragen:**
- Kunnen meerdere registers op één Open Register-instantie?
- Wat zijn de grenzen?
- Hoe verhoudt dit zich tot landelijke stelselafspraken?

**Potentiële impact:** Fundamentele vereenvoudiging van het gemeentelijke applicatielandschap.

---

## 8. Betrokken partijen

### Marktpartijen

| Partij | Rol | Focus |
|--------|-----|-------|
| [Conduction](https://conduction.nl) | Technisch lead | Open Register, Nextcloud-apps, architectuur |
| [Shift2](https://shift2.nl) | WordPress lead | CMS, NL Design integratie |
| [Acato](https://acato.nl) | Frontend & UX | Website, Mijn Omgeving |
| [Yard](https://yard.nl) | Implementatie | Partner voor 40+ gemeenten |

### Gemeenten

| Gemeente | Rol |
|----------|-----|
| Utrecht | Pilotgemeente Vrij BRP, aanvullende financiering |
| OWC-gemeenten | Testomgevingen, expertise, stuurgroep |

---

## 9. Budget en uren

**Totaal gevraagd:** € 300.000 (2.400 uur à € 125)

### Belangrijk: geen ontwikkeling voor Open WOO en WordPress

De producten zijn reeds ontwikkeld. Het budget is bedoeld voor:
- Onboarding en certificering
- Documentatie
- Architectuurbeschrijvingen
- Onderzoek

Alleen voor **Vrij BRP** is ontwikkeling voorzien (POC Utrecht).

### Urenverdeling per activiteit

| Activiteit | Uren | Budget | Partij |
|------------|------|--------|--------|
| Open WOO: onboarding Haven | 200 | € 25.000 | Conduction |
| Open WOO: documentatie | 200 | € 25.000 | Conduction |
| WordPress: landing programma | 160 | € 20.000 | Shift2 |
| WordPress: vergelijkingsonderzoek | 160 | € 20.000 | Shift2 + Acato |
| Vrij BRP: architectuurontwerp | 240 | € 30.000 | Conduction |
| Vrij BRP: ontwikkeling POC | 560 | € 70.000 | Conduction |
| Vrij BRP: validatie | 160 | € 20.000 | Conduction + Utrecht |
| Onderzoek: registeroplossing | 240 | € 30.000 | Conduction |
| Projectcoördinatie | 160 | € 20.000 | OWC |
| Onvoorzien en review | 320 | € 40.000 | Diversen |
| **Totaal** | **2.400** | **€ 300.000** | |

### Samenvatting per product

| Product | Type | Uren | Budget |
|---------|------|------|--------|
| Open WOO | Onboarding (geen ontwikkeling) | 400 | € 50.000 |
| WordPress CMS | Landing (geen ontwikkeling) | 320 | € 40.000 |
| Vrij BRP | Ontwikkeling POC | 960 | € 120.000 |
| Onderzoek | Verkenning | 240 | € 30.000 |
| Overig | Coördinatie + buffer | 480 | € 60.000 |

### Aanvullende bijdragen (buiten dit budget)

**Gemeente Utrecht:** Financiert gemeentespecifieke implementatiekosten Vrij BRP.

**OWC-gemeenten (in-kind):**
- Testomgevingen en geanonimiseerde productiedata
- Functionele expertise
- Stuurgroepdeelname
- Review en acceptatie

---

## 10. Planning en deadlines

### Externe drivers

| Driver | Deadline | Impact |
|--------|----------|--------|
| Wet open overheid verplicht | 2025 | Open WOO moet beschikbaar zijn |
| VNG Najaarscongres | November 2025 | Demonstratie OWC-stack |

### Planning per product

**Open WOO**
| Mijlpaal | Periode |
|----------|---------|
| Start onboarding | Q1 2025 |
| Onboarding afgerond | Q2 2025 |
| Beschikbaar in Haven | Q2/Q3 2025 |
| **Vóór Woo-verplichting** | **2025** |

**Vrij BRP**
| Mijlpaal | Periode |
|----------|---------|
| Start project Utrecht | Q1 2025 |
| Ontwerp migratie | Q1/Q2 2025 |
| POC operationeel | **Zomer 2025** |
| Validatie | Q3 2025 |
| **VNG Najaarscongres** | **November 2025** |

**WordPress CMS**
| Mijlpaal | Periode |
|----------|---------|
| Landing in programma | Q2 2025 |
| Start vergelijkingsonderzoek | Q3 2025 |
| Eerste bevindingen | Q4 2025 |
| Eindrapportage | Q1 2026 |

### Kritieke deadlines

- **Zomer 2025:** POC Vrij BRP live bij gemeente Utrecht
- **Najaar 2025:** Open WOO beschikbaar vóór Woo-verplichting
- **November 2025:** Demonstratie OWC-stack op VNG Najaarscongres

---

## 11. Gewenste resultaten

1. **Open WOO** opgenomen in Haven als Common Ground-oplossing voor de Woo
2. **Vrij BRP** als werkende POC op Eurostack-architectuur
3. **WordPress CMS** geland in Common Ground met vergelijkingsrapportage
4. **Onderzoeksrapport** over generieke registeroplossing
5. **Documentatie** van de OWC-architectuurstack en de relatie tot de standaard Common Ground-architectuur
6. **Advies** voor consolidatie richting één hoofdarchitectuur

---

## 12. Governance

*[Toe te voegen: governance-structuur, stuurgroep, besluitvorming]*

---

## 13. Slot: een commitment aan Common Ground

Dit projectvoorstel vertegenwoordigt een significante commitment van de Open Webconcept gemeenten aan het programma Common Ground.

**Wat brengen wij in?**

De OWC-gemeenten investeren al jaren in de ontwikkeling van open source oplossingen voor gemeentelijke dienstverlening. Met dit voorstel bieden wij aan om deze investeringen – het WordPress CMS, Open WOO en Vrij BRP – onder te brengen binnen Common Ground. Dit betekent:

- **Overdracht van intellectueel eigendom** – De ontwikkelde oplossingen worden beschikbaar voor het hele Common Ground-ecosysteem
- **Kennisdeling** – De architectuurprincipes en technische keuzes worden gedocumenteerd en gedeeld
- **Actieve participatie** – De OWC-gemeenten committeren zich aan langdurige deelname in stuurgroepen en werkgroepen
- **Financiële bijdrage** – Naast de gevraagde € 300.000 dragen gemeente Utrecht en de OWC-gemeenten substantieel bij (in cash en in-kind)

**Wat vragen wij terug?**

Wij vragen het programma Common Ground om ruimte te maken voor een tweede architectuurvariant. Niet om te concurreren, maar om te leren. Door twee benaderingen naast elkaar te beproeven – de bestaande Common Ground-stack en de OWC-stack – ontstaat inzicht in wat werkt en wat niet.

Het einddoel is niet twee stacks, maar één: de beste elementen van beide benaderingen gecombineerd in een toekomstbestendige architectuur voor alle Nederlandse gemeenten.

**Onze belofte**

De OWC-gemeenten beloven:
- Transparantie over onze resultaten, inclusief wat niet werkt
- Samenwerking met bestaande Common Ground-teams
- Bereidheid om onze keuzes aan te passen op basis van bevindingen
- Focus op het gezamenlijke doel: betere digitale dienstverlening voor inwoners

Dit is geen vrijblijvend experiment. Dit is een serieuze investering in de toekomst van de gemeentelijke informatievoorziening, gedragen door gemeenten die bereid zijn voorop te lopen.

---

## Bronnen en verwijzingen

### Eurostack-componenten
| Component | URL |
|-----------|-----|
| Eurostack catalogus | [euro-stack.com](https://euro-stack.com) |
| Workflow Automation | [euro-stack.com/categories/workflow-automation](https://euro-stack.com/categories/workflow-automation) |
| n8n | [euro-stack.com/solutions/n8n](https://euro-stack.com/solutions/n8n) |
| PostgreSQL | [euro-stack.com/solutions/postgresql](https://euro-stack.com/solutions/postgresql) |
| Nextcloud | [euro-stack.com/solutions/nextcloud-hub](https://euro-stack.com/solutions/nextcloud-hub) |
| Keycloak | [euro-stack.com/solutions/keycloak](https://euro-stack.com/solutions/keycloak) |
| Prometheus | [euro-stack.com/solutions/prometheus](https://euro-stack.com/solutions/prometheus) |
| Grafana | [euro-stack.com/solutions/grafana](https://euro-stack.com/solutions/grafana) |

### Overige bronnen
| Onderwerp | URL |
|-----------|-----|
| n8n Integrations | [n8n.io/integrations](https://n8n.io/integrations/) |
| Mijn Bureau (NDS) | [github.com/MinBZK/mijn-bureau](https://github.com/MinBZK/mijn-bureau) |
| NL Design System | [nldesignsystem.nl](https://nldesignsystem.nl) |
| WordPress | [wordpress.org](https://wordpress.org) |
| Hugging Face | [huggingface.co](https://huggingface.co) |

### Common Ground
| Onderwerp | URL |
|-----------|-----|
| Common Ground | [commonground.nl](https://commonground.nl) |
| Haven | [haven.commonground.nl](https://haven.commonground.nl) |
| Open Formulieren | [open-formulieren.nl](https://open-formulieren.nl) |
| Open Inwoner | [opengem.nl/producten/open-inwoner](https://opengem.nl/producten/open-inwoner) |

### Standaarden
| Standaard | Toelichting |
|-----------|-------------|
| VNG ZGW | Zaakgericht werken standaarden |
| GGM | Gemeentelijk Gegevensmodel |
| RAAKT | Referentiearchitectuur |
| TOOI | Thesaurus en Ontologie Overheidsinformatie |
| NLX/FSC | Federatieve servicekoppeling |

---

*Versie 1.0 – Januari 2025*
*Open Webconcept gemeenten*
