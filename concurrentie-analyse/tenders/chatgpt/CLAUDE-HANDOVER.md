# Claude Handover — Tender Analyse Procest & Pipelinq

## Gebruik dit pakket als bron

Gebruik het definitieve pakket `final-tender-package.zip` als hoofdbron voor verdere aggregatie en feature-analyse.

### Leidende datasets

1. **Primaire brede dataset:** `tender-analyses-repair3.zip`
   - Dit is de breedste stabiele set met 245 tenders en 245 README-bestanden.
   - Gebruik deze als standaardbron voor tellingen, frequenties en algemene conclusies.

2. **Gerichte correctielaag:** `tender-analyses-repair4-targeted.zip`
   - Dit bevat gerichte verbeteringen voor 13 probleemdossiers uit de restgroep.
   - Als een tender-ID voorkomt in repair4-targeted, gebruik dan de README uit repair4-targeted in plaats van die uit repair3.

3. **Beslisdocument:** `RESULTATEN-BESLUIT.md`
   - Dit document legt vast welke set leidend is en hoe repair4 zich verhoudt tot repair3.

## Interpretatieregels

- Werk op **TenderNed ID / publicatieId** als unieke sleutel.
- Neem per tender **exact één README** als bronbestand.
- Prioriteit bij conflicten:
  1. `repair4-targeted` README
  2. `repair3` README
- Gebruik auditbestanden niet als bron voor requirements zelf, maar alleen voor kwaliteitsweging en selectie van twijfelgevallen.

## Wat er in de README-bestanden zit

Elke tender-README is bedoeld als deep-dive analyse voor requirement-extractie. De README’s bevatten onder meer:
- metadata
- geanalyseerde documenten
- context en scope
- functionele eisen
- wensen
- NvI-wijzigingen
- integratie-eisen
- architectuur en technische eisen
- beveiliging en compliance
- GIBIT / ICT-kwaliteitsnormen
- SLA en beheer
- gunningscriteria
- gunning (indien van toepassing)
- opvallende of unieke eisen

## Verwachte kwaliteitsverschillen

Niet alle README’s zijn even sterk. Houd rekening met deze kwaliteitsniveaus:

### Hoog vertrouwen
- tenders met `compleet = ja` in de audit
- tenders met duidelijke PvE/PvW-secties
- tenders met expliciete bronverwijzingen en meerdere geëxtraheerde eisen en wensen

### Middelmatig vertrouwen
- tenders met veel eisen maar weinig wensen
- tenders zonder duidelijke wensenbron
- tenders waar contract/SLA/GIBIT wel verwerkt zijn maar functionaliteit beperkt leesbaar was

### Lager vertrouwen
- tenders met placeholder-signalen
- tenders met OCR-noodzaak
- tenders met `eisen = 0` of `wensen = 0` ondanks meerdere documenten

Gebruik deze kwaliteitsniveaus om twijfelgevallen lichter te wegen bij conclusies over frequenties.

## Aanbevolen werkwijze voor Claude

### Stap 1 — Bouw een canonieke tenderlijst
Maak een tabel met:
- `publicatieId`
- tendernaam
- aanbestedende dienst
- gekozen bronbestand (`repair3` of `repair4-targeted`)
- kwaliteitsstatus uit audit

### Stap 2 — Parse eisen en wensen
Parse uit elke README:
- requirement-ID
- thematische sectie
- volledige Nederlandse tekst
- bronverwijzing
- documenttype indien afleidbaar (PvE, PvW, NvI, GIBIT, Ovk, Leidraad)

### Stap 3 — Normaliseer naar capability-clusters
Map requirements naar capability-clusters voor Procest en Pipelinq, bijvoorbeeld:
- zaakgericht werken
- document management
- formulieren
- procesautomatisering
- VTH
- klantinteractie / CRM
- notificaties
- integraties basisregistraties
- ZGW API / StUF / Common Ground
- security / privacy / compliance
- SLA / beheer / exit

### Stap 4 — Maak frequentiematrices
Maak ten minste deze matrices:
1. capability x aantal tenders
2. integratie x aantal tenders
3. security/compliance eis x aantal tenders
4. architectuurpatroon x aantal tenders
5. winnaar/vendor x aantal gegunde opdrachten

### Stap 5 — Vertaal naar product-specs
Gebruik de frequentiematrices om:
- **Procest** feature-specs te prioriteren
- **Pipelinq** feature-specs te prioriteren
- gedeelde platformbehoeften te identificeren
- compliancy-baseline vast te leggen

## Belangrijke beperkingen

- Een README kan fallbacktekst of onvolledige extractie bevatten, vooral bij scan-/OCR-zware PDF’s.
- Afwezigheid van een eis in een README betekent niet altijd dat de eis niet in de tender stond.
- Gebruik daarom liever **tenderfrequentie** dan absolute eisentellingen als harde waarheid.
- Behandel de audit als kwaliteitsindicator, niet als inhoudsbron.

## Aanbevolen extra output van Claude

Laat Claude na parsing minimaal deze eindproducten maken:

1. **Feature frequency matrix** voor Procest
2. **Feature frequency matrix** voor Pipelinq
3. **Compliance baseline** (BIO, ISO 27001, logging, audittrail, AVG, exit, SLA)
4. **Integratiebaseline** (BRP, BAG, KVK, DigiD, eHerkenning, ZGW APIs, StUF/ZDS, DSO, zaaksystemen)
5. **Gap-analyse**:
   - tender-vraag
   - concurrent-dekking
   - huidige productdekking
   - prioriteit

## Praktische beslisregel

Als Claude één einddataset moet maken:
- begin met alle 245 README’s uit repair3
- vervang daarna de 13 overlappende tenders met de versies uit repair4-targeted
- gebruik dat resultaat als canonieke dataset
