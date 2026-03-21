# Tender Analyse: {aanbestedingNaam}

## Metadata

| Veld | Waarde |
|------|--------|
| **TenderNed ID** | {publicatieId} |
| **Aanbestedende dienst** | {opdrachtgeverNaam} |
| **Publicatiedatum** | {publicatieDatum} |
| **Sluitingsdatum** | {sluitingsDatum} |
| **Type publicatie** | {Aankondiging opdracht / Gegunde opdracht / Marktconsultatie} |
| **Procedure** | {Openbaar / Niet-openbaar / etc.} |
| **Product relevantie** | {procest / pipelinq / both} |
| **TenderNed URL** | https://www.tenderned.nl/aankondigingen/overzicht/{publicatieId} |

## Geanalyseerde documenten

| # | Document | Type | Pagina's | Gelezen | Samenvatting |
|---|----------|------|----------|---------|-------------|
| 1 | {bestandsnaam} | PDF | {~N} | {ja/nee} | {wat bevat dit document} |

**Documenten beschikbaar: {N} / Documenten gelezen: {M}**

## Context en scope

{Beschrijf in 2-4 alinea's:}
- Wat zoekt deze organisatie? Wat is hun huidige situatie?
- Welke processen/afdelingen worden bediend?
- Hoeveel gebruikers? Welke schaal?
- Waarom een nieuw systeem? (vervanging, uitbreiding, Common Ground migratie?)
- Eventuele bijzonderheden

## Functionele eisen

{Per eis: volledige Nederlandse tekst, GEEN afkorting/samenvatting. Groepeer per thema.}

### Zaakgericht werken / Case management

- **{ID}**: > "{volledige Nederlandse tekst}" [Bron: {document}, p.{pagina}]

### Document management

- **{ID}**: > "{volledige Nederlandse tekst}" [Bron: {document}, p.{pagina}]

### Formulieren / Intake

### Workflow / Procesautomatisering

### Zoeken en filteren

### Rapportage en dashboards

### VTH (Vergunningen, Toezicht, Handhaving)

### Klantinteractie / CRM

### Communicatie en notificaties

### Gebruikersbeheer en autorisatie

### Archivering en vernietiging

### Overige functionele eisen

**Totaal eisen: {N}**

## Wensen

{Zelfde format als eisen. Inclusief puntentelling/weging indien vermeld.}

### {thema}

- **{ID}** ({weging/punten}): > "{volledige Nederlandse tekst}" [Bron: {document}, p.{pagina}]

**Totaal wensen: {N}**

## Nota van Inlichtingen — Wijzigingen

| NvI ref | Betreft eis | Type wijziging | Oorspronkelijke tekst | Gewijzigde/verduidelijkte tekst |
|---------|-------------|---------------|----------------------|-------------------------------|
| NvI-{nr} | {eis-ID} | Verduidelijking / Wijziging / Nieuw / Vervallen | "{kort}" | "{volledig}" |

## Integratie-eisen

| # | Systeem | Richting | Standaard/Protocol | Details | Bron |
|---|---------|---------|-------------------|---------|------|
| I-001 | {bijv. BRP/GBA} | {in/out/bi} | {StUF-BG 3.10 / Haal Centraal} | {welke data, welke operaties} | {eis-ID}, p.{pagina} |

## Architectuur en technische eisen

- **Hosting model**: {SaaS / on-premise / hybrid, datacenter NL/EU, multi-tenant}
- **Common Ground**: {welke laag, welke componenten, API-first}
- **Standaarden**: {ZGW APIs, StUF, CMIS, SAML, OIDC, SCIM, etc.}
- **Performance**: {concurrent users, response times, throughput}
- **Beschikbaarheid**: {uptime SLA (99.x%), onderhoudsvensters}
- **Schaalbaarheid**: {groei-verwachtingen, piekbelasting}

{Per punt de volledige eistekst citeren met bronverwijzing}

## Beveiliging en compliance

- **BIO**: {welk niveau} [Bron: {document}, p.{pagina}]
- **ISO 27001**: {vereist? gecertificeerd?}
- **DigiD assessment**: {vereist?}
- **Penetratietest**: {frequentie, scope}
- **SOC 2 / ISAE 3402**: {vereist?}
- **AVG / GDPR**: {DPIA, verwerkersovereenkomst, datalocatie, bewaartermijn, recht op vergetelheid}
- **Logging en audit trail**: {wat moet gelogd, bewaartermijn}
- **Encryptie**: {at rest, in transit, key management}

## GIBIT / ICT-kwaliteitsnormen

- **GIBIT versie**: {2020 / 2023}
- **Afwijkingen**: {organisatie-specifieke toevoegingen of afwijkingen}

{Relevante artikelen met volledige tekst en bronverwijzing}

## SLA en beheer

- **Uptime**: {target, bijv. 99.5%} [Bron: {document}, {sectie}]
- **RPO/RTO**: {recovery point/time objectives}
- **Responstijden support**: {P1/P2/P3/P4 targets}
- **Backup**: {frequentie, retentie}
- **Updates/patches**: {frequentie, onderhoudsvensters}
- **Exit-clausule**: {dataportabiliteit, transitieperiode, format}

## Gunningscriteria

| Criterium | Gewicht | Beschrijving |
|-----------|---------|-------------|
| {bijv. Kwaliteit} | {bijv. 70%} | {welke aspecten worden beoordeeld} |
| {bijv. Prijs} | {bijv. 30%} | {prijsmodel details} |

## Gunning (alleen bij gegunde opdracht)

- **Winnaar**: {leverancier}
- **Contract waarde**: {bedrag}
- **Looptijd**: {duur + verlengopties}
- **Aantal inschrijvingen**: {indien vermeld}

## Opvallende of unieke eisen

- {Eisen die opvallen: ongebruikelijk, innovatief, of bijzonder relevant voor onze producten}
