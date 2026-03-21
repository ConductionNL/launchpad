# Spec Proposal -- Requirements-Driven OpenSpec Suggestions

Op basis van de frequentie van eisen en thema's in 74 Nederlandse overheidsaanbestedingen,
vertaald naar concrete OpenSpec-voorstellen per Conduction-app.

## 1. Procest (Zaaksysteem / Case Management)

Gebaseerd op **69 relevante tenders** (procest + both).

### Must-have (>50% van tenders)

| # | Capability | Freq | % | Beschrijving |
|---|-----------|------|---|-------------|
| 1 | **Common Ground / architectuur** | 63/69 | 91% | Componentgebaseerd, API-first, ZGW-APIs, NLX/Haven, open standaarden |
| 2 | **Rapportage en dashboards** | 61/69 | 88% | Management dashboards, KPI-rapportages, ad-hoc queries, CSV/PDF-export |
| 3 | **Gebruikersbeheer en autorisatie** | 59/69 | 86% | RBAC, rollen/rechten per zaaktype, delegatie, audit trail op autorisaties |
| 4 | **Zaakgericht werken / case management** | 57/69 | 83% | Volledige zaakafhandeling conform RGBZ/ZGW, inclusief zaaktypen, statussen, resultaten, besluiten |
| 5 | **Document management** | 55/69 | 80% | DMS-functionaliteit: versioning, metadata, check-in/out, templates |
| 6 | **Zoeken en filteren** | 54/69 | 78% | Full-text zoeken, faceted search, geavanceerde filters op alle zaak- en documentvelden |
| 7 | **Archivering en vernietiging** | 53/69 | 77% | NEN 2082 / TMLO-conform, selectielijsten, vernietigingsworkflow, e-Depot export |
| 8 | **Integraties / koppelingen** | 46/69 | 67% | Brede integratie-laag: basisregistraties, financieel, portalen, ketenpartners |
| 9 | **Klantinteractie / CRM** | 45/69 | 65% | KCC/KCS, klantbeeld 360, contactmomenten, kanaalintegratie |
| 10 | **Formulieren / intake** | 42/69 | 61% | Webformulieren, e-formulieren met DigiD, voorinvullen uit basisregistraties |
| 11 | **Privacy en beveiliging** | 40/69 | 58% | AVG-logging, verwerkingsregister, doelbinding, dataminimalisatie |

### Should-have (25-50% van tenders)

| # | Capability | Freq | % | Beschrijving |
|---|-----------|------|---|-------------|
| 1 | **Communicatie en notificaties** | 32/69 | 46% | E-mail integratie, notificaties, berichten naar burger via MijnOverheid |
| 2 | **Performance / SLA** | 28/69 | 41% | Beschikbaarheid 99.5%+, responstijd <3s, backup/restore, monitoring |
| 3 | **Documentcreatie** | 27/69 | 39% | Sjabloongeneratie, samenvoegen, digitaal ondertekenen |
| 4 | **Workflow / procesautomatisering** | 26/69 | 38% | BPMN workflows, zero-coding procesinrichting, termijnbewaking, escalatie |
| 5 | **Geo/kaart** | 24/69 | 35% | Kaartintegratie (BAG/BGT), locatie op zaak, GIS-viewer |
| 6 | **VTH-specifiek** | 20/69 | 29% | Vergunningen/Toezicht/Handhaving: DSO-koppeling, Omgevingswet, checklists, inspecties |

### Nice-to-have (<25% van tenders)

| # | Capability | Freq | % | Beschrijving |
|---|-----------|------|---|-------------|
| 1 | **Werkvoorraad/taakbeheer** | 16/69 | 23% | Werkvoorraadlijsten, taaktoewijzing, prioritering, teamoverzichten |
| 2 | **Zaaktypeconfiguratie (ZTC)** | 16/69 | 23% | Zero-coding zaaktype-inrichting, statusdiagrammen, checklistconfiguratie |
| 3 | **Zaakregistratie** | 16/69 | 23% | Aanmaken, importeren, koppelen van zaken aan personen/objecten/locaties |
| 4 | **Objectregistratie** | 15/69 | 22% | Generieke objectregistratie, configureerbare entiteiten, relaties |
| 5 | **Persoonlijke portaal (PIP)** | 15/69 | 22% | Mijn-omgeving voor burgers, zaakvoortgang, documenten inzien |
| 6 | **Bestuurlijke besluitvorming** | 14/69 | 20% | B&W/College-stukken, besluitvorming, koppeling RIS (iBabs/Notubiz) |
| 7 | **Doorontwikkeling en exit** | 14/69 | 20% | Roadmap-inzage, exit-strategie, dataportabiliteit, escrow |
| 8 | **Gebruiksvriendelijkheid / UX** | 12/69 | 17% | Intuitive UI, minimale klikpaden, responsief design, toegankelijkheid |
| 9 | **Mobiel werken** | 11/69 | 16% | Mobiele app voor inspecteurs, offline werken, foto-upload |
| 10 | **Migratie** | 11/69 | 16% | Datamigratie van legacy systemen, zaak- en documentconversie |
| 11 | **Leges/heffingen** | 8/69 | 12% | Legesberekening, heffingen, financiele koppelingen |
| 12 | **Inrichting en beheer** | 8/69 | 12% | Functioneel beheer zonder leveranciersafhankelijkheid, zero-coding configuratie |
| 13 | **Digitaal ondertekenen** | 1/69 | 1% | Zynyo/ValidSign integratie, PKIoverheid, gekwalificeerde handtekening |

## 2. Pipelinq (CRM / Klantinteractie)

Gebaseerd op **9 primair CRM-relevante tenders** + klantinteractie-thema's uit 69 zaaksysteem-tenders.

**52 tenders** met expliciete klantinteractie-eisen:

| # | Capability | Freq | Beschrijving |
|---|-----------|------|-------------|
| 1 | **KCC/frontoffice werkplek** | 52/52 | Geintegreerde werkplek voor KCC-medewerkers met snelle toegang tot klantgegevens en zaken |
| 2 | **Rapportage contactmomenten** | 51/52 | Wachttijden, aantallen per kanaal, first-call resolution, SLA-monitoring |
| 3 | **Klantbeeld 360 graden** | 43/52 | Geconsolideerd overzicht van alle klantcontacten, zaken, documenten per persoon/bedrijf |
| 4 | **Basisregistratie-integratie** | 36/52 | Automatisch ophalen persoons-/bedrijfsgegevens uit BRP, KVK, BAG |
| 5 | **MijnOverheid / portaal-integratie** | 30/52 | Statusupdates naar MijnOverheid, Berichtenbox, zaakvoortgang tonen |
| 6 | **Omnichannel contactregistratie** | 28/52 | Registratie van contactmomenten via telefoon, e-mail, balie, chat, social media |
| 7 | **Terugbelnotities / taakbeheer** | 16/52 | Terugbelverzoeken, taaktoewijzing aan backoffice, follow-up tracking |
| 8 | **Chatbot / AI-assistentie** | 15/52 | Chatbot voor veelgestelde vragen, routing naar juiste afdeling |
| 9 | **Kennisbank / FAQ** | 1/52 | Kennisbank voor KCC-medewerkers met zoeken, categoriseren, versiebeheer |
| 10 | **Telefonie-integratie (CTI)** | 1/52 | Click-to-call, screen pop, nummerherkenning, gesprekslabels |

## 3. Docudesk (Document Management)

Gebaseerd op **14 DMS-relevante tenders**.

| # | Capability | Freq | Beschrijving |
|---|-----------|------|-------------|
| 1 | **Documentopslag en versioning** | 12/14 | Versiebeheer, check-in/out, metadata, classificatie |
| 2 | **Archivering (NEN 2082 / TMLO)** | 12/14 | Recordmanagement, selectielijsten, vernietigingsworkflow, e-Depot |
| 3 | **Zoeken in documenten** | 12/14 | Full-text search in documenten, OCR, metadata-zoeken |
| 4 | **E-depot / overbrenging** | 10/14 | Overbrenging naar regionaal/nationaal e-Depot |
| 5 | **Office 365 integratie** | 8/14 | Online bewerken, co-authoring, SharePoint-sync |
| 6 | **Digitaal ondertekenen** | 5/14 | Gekwalificeerde digitale handtekening, PKIoverheid, ValidSign/Zynyo |
| 7 | **Documentcreatie / sjablonen** | 4/14 | Sjabloongeneratie (SmartDocuments-achtig), samenvoegen, PDF-conversie |
| 8 | **CMIS-koppelvlak** | 3/14 | Standaard DMS-interface voor koppeling met zaaksystemen |
| 9 | **Scannen en OCR** | 2/14 | Documenten scannen, automatische classificatie, OCR |
| 10 | **Vertrouwelijkheidsniveaus** | 1/14 | Rubricering, toegangsbeperking per document, watermarks |

## 4. OpenRegister (Foundation -- Cross-Cutting)

Eisen die in ALLE typen aanbestedingen terugkomen (zaak, VTH, DMS, CRM).

| # | Capability | Freq | % | Beschrijving |
|---|-----------|------|---|-------------|
| 1 | **Rapportage / BI-export** | 65/73 | 89% | Dashboard data, CSV/PDF-export, BI-tool koppeling |
| 2 | **RBAC / autorisatiemodel** | 63/73 | 86% | Rollen/rechten, delegatie, zaaktype-niveau, data-niveau autorisatie |
| 3 | **API-laag (REST/ZGW)** | 62/73 | 85% | Open API standaarden, REST, ZGW-compatible endpoints |
| 4 | **Zoeken en filteren** | 56/73 | 77% | Full-text search, faceted filtering, sortering, exports |
| 5 | **Archivering / retentie** | 54/73 | 74% | Selectielijsten, automatische vernietiging, TMLO-metadata |
| 6 | **SSO / identity-integratie** | 49/73 | 67% | SAML/OIDC, Azure AD, Active Directory koppeling |
| 7 | **Audit trail / logging** | 41/73 | 56% | Niet-muteerbare audit trail op CRUD-operaties, wie/wat/wanneer |
| 8 | **Notificaties** | 37/73 | 51% | Event-driven notificaties, e-mail, in-app meldingen |
| 9 | **Objectregistratie** | 23/73 | 32% | Generieke objectregistratie, zero-coding schema-inrichting |
| 10 | **Multi-tenancy** | 21/73 | 29% | Meerdere organisaties op 1 installatie, data-isolatie |

## 5. OpenConnector (Integraties)

Meest gevraagde integraties, gerangschikt op frequentie.

| # | Integratie | Freq | % | Categorie | Prioriteit |
|---|-----------|------|---|-----------|-----------|
| 2 | **DigiD** | 59 | 81% | Authenticatie | Must-have |
| 3 | **StUF** | 58 | 79% | Standaarden | Must-have |
| 4 | **ZGW** | 48 | 66% | Standaarden | Must-have |
| 5 | **BAG** | 47 | 64% | Basisregistraties | Must-have |
| 6 | **BRP** | 44 | 60% | Basisregistraties | Must-have |
| 10 | **eHerkenning** | 38 | 52% | Authenticatie | Must-have |
| 11 | **GBA** | 37 | 51% | Basisregistraties | Must-have |
| 12 | **Active Directory** | 36 | 49% | Identity/SSO | Should-have |
| 13 | **RGBZ** | 35 | 48% | Standaarden | Should-have |
| 15 | **MijnOverheid** | 30 | 41% | Portalen | Should-have |
| 16 | **Azure AD** | 29 | 40% | Identity/SSO | Should-have |
| 17 | **KVK** | 28 | 38% | Basisregistraties | Should-have |
| 19 | **e-Depot** | 25 | 34% | Archivering | Should-have |
| 20 | **Office 365** | 24 | 33% | Microsoft | Should-have |
| 21 | **DSO** | 24 | 33% | Portalen | Should-have |
| 22 | **iBabs** | 23 | 32% | Bestuurlijk | Should-have |
| 24 | **SharePoint** | 17 | 23% | Microsoft | Nice-to-have |
| 25 | **ValidSign** | 16 | 22% | Overig | Nice-to-have |
| 27 | **OLO** | 14 | 19% | Portalen | Nice-to-have |
| 28 | **Omgevingsloket** | 14 | 19% | Portalen | Nice-to-have |
| 30 | **Microsoft 365** | 12 | 16% | Overig | Nice-to-have |
| 31 | **BRK** | 12 | 16% | Basisregistraties | Nice-to-have |
| 32 | **CMIS** | 11 | 15% | Standaarden | Nice-to-have |
| 33 | **iNavigator** | 11 | 15% | Overig | Nice-to-have |
| 35 | **BGT** | 10 | 14% | Basisregistraties | Nice-to-have |

## 6. Compliance Baseline

Security/compliance eisen die in >50% van de tenders voorkomen -- **must-have voor elke Conduction-app**.

| Eis | Freq | % | Status | Actie |
|-----|------|---|--------|-------|
| **AVG** | 71 | 97% | Deels | Verwerkingsregister + doelbinding in OpenRegister implementeren |
| **ISO 27001** | 71 | 97% | Niet | Certificeringstraject starten (organisatieniveau) |
| **BIO** | 64 | 88% | Deels | BIO-gap-analyse uitvoeren, maatregelen documenteren |
| **verwerkersovereenkomst** | 64 | 88% | Ja | IBD-model verwerkersovereenkomst beschikbaar maken |
| **DigiD assessment** | 56 | 77% | Niet | DigiD-aansluiting + jaarlijks ICT-beveiligingsonderzoek regelen |
| **penetratietest** | 51 | 70% | Niet | Jaarlijkse pentest door erkend bureau inplannen |
| **ISAE 3402** | 44 | 60% | Niet | Hosting bij ISAE 3402-gecertificeerde provider |
| **SOC 2** | 37 | 51% | Niet | Via hosting provider (SOC 2 Type II) |
| **TPM** | 36 | 49% | Niet | Third Party Memorandum DigiD jaarlijks uitvoeren |
| **WCAG** | 33 | 45% | Deels | WCAG 2.1 AA audit op alle frontend componenten |
| **GIBIT 2020** | 23 | 32% | Ja | Conform GIBIT 2020 inkoopvoorwaarden leveren |
| **GIBIT 2023** | 22 | 30% | Ja | Conform GIBIT 2023 inkoopvoorwaarden leveren |

### Prioriteiten certificeringstraject

1. **ISO 27001** -- universeel vereist, opent de deur voor alle aanbestedingen
2. **BIO-compliance** -- verplicht voor overheidsleveranciers
3. **DigiD assessment / TPM** -- noodzakelijk voor burger-facing applicaties
4. **Penetratietest** -- jaarlijks, door onafhankelijk bureau
5. **ISAE 3402 / SOC 2** -- via hosting provider regelen
6. **WCAG 2.1 AA** -- wordt wettelijk verplicht (per 2025)
7. **NEN 2082** -- specifiek voor archivering/recordmanagement (Docudesk)

## 7. Gap Analysis

Eisen die tenders vragen EN concurrenten bieden, maar wij (nog) niet ondersteunen.

| Gap | Tender % | Concurrenten | Prioriteit | Voorgestelde actie |
|-----|---------|-------------|-----------|-------------------|
| **DigiD / eHerkenning authenticatie** | 80% | Centric, Visma, xxllnc | Hoog | OpenConnector DigiD-adapter + SAML/OIDC proxy |
| **ZGW API-compliance (volledig)** | 65% | Visma Circle, xxllnc, Mintlab | Hoog | Procest ZGW API endpoints completeren (Zaken, Documenten, Catalogi, Besluiten, Autorisaties) |
| **StUF-koppelvlakken (BG/ZKN)** | 78% | Alle gevestigde leveranciers | Middel | OpenConnector StUF-BG/ZKN adapter (legacy, maar nog vereist) |
| **DSO/Omgevingsloket koppeling** | 32% | Centric (Key2), Genetics (PB) | Hoog (VTH) | OpenConnector DSO-adapter voor vergunningaanvragen |
| **e-Depot / NEN 2082** | 34% | BCT, Decos, Centric | Middel | Docudesk e-Depot export + NEN 2082 metadata-mapping |
| **Digitaal ondertekenen** | 15% | SmartDocuments, Decos | Laag | Docudesk ValidSign/Zynyo integratie |
| **BPMN workflow engine** | 33% | Visma Circle (Flowable), Camunda-partijen | Hoog | Procest zero-coding workflow designer (huidige n8n uitbreiden) |
| **Office 365 integratie** | 32% | Alle grote leveranciers | Middel | Docudesk Office 365 co-authoring + online bewerken |
| **Mobile app (inspectie)** | 10% | Genetics (PowerBrowser Mobile) | Middel (VTH) | Procest mobiele inspectie-app (PWA of native) |
| **Legesberekening** | 12% | Centric, Roxit | Laag | Procest legesmodule met configureerbare tarieven |
| **ISO 27001 certificering** | 96% | Alle gevestigde leveranciers | Kritiek | Organisatie-breed certificeringstraject |
| **Penetratietest rapport** | 69% | Alle gevestigde leveranciers | Kritiek | Jaarlijkse pentest door erkend bureau |
| **SaaS hosting NL/EU (ISAE 3402)** | 60% | Alle gevestigde leveranciers | Kritiek | Hosting bij gecertificeerde provider |
| **Migratie-tooling** | 45% | Alle gevestigde leveranciers | Hoog | Generieke import-pipeline voor zaak/document-migratie uit legacy systemen |
| **Bestuurlijke besluitvorming (RIS)** | 12% | Mintlab, BCT | Laag | Procest koppeling met iBabs/Notubiz |
| **Rapportage / BI-dashboards** | 85% | Alle leveranciers | Hoog | Geintegreerde dashboards + BI-export (CSV, OData) in alle apps |

### Top 5 urgente gaps

1. **ISO 27001 certificering** -- zonder dit worden we bij 96% van de tenders uitgesloten
2. **DigiD/eHerkenning** -- authenticatie is een knock-out eis bij 80% van de tenders
3. **ZGW API volledigheid** -- de standaard voor zaaksystemen, vereist bij 65%+ en stijgend
4. **Rapportage/dashboards** -- gevraagd bij 85%, basis management-informatie is een hygienefactor
5. **BPMN/workflow engine** -- zero-coding procesinrichting is een kernverwachting (33%+ en stijgend)

### Strategische kansen

1. **Common Ground + open source** -- 88% noemt Common Ground, 35% vraagt open source. Dit is onze USP tegenover Visma/Centric.
2. **Zero-coding configuratie** -- 36% vraagt dit expliciet. Onze OpenRegister zero-coding aanpak is een differentiator.
3. **Multi-tenant SaaS** -- 92% wil SaaS. Onze Nextcloud-basis maakt multi-tenant eenvoudiger dan bij legacy-leveranciers.
4. **Kleine gemeenten** -- Gemiddeld 2.4 inschrijvers. De markt is niet druk. Veel tenders krijgen slechts 1-2 inschrijvingen.
5. **Leveranciersonafhankelijkheid** -- Veel gemeenten vervangen hun huidige leverancier. Exit-strategie en dataportabiliteit zijn verkoopargumenten.