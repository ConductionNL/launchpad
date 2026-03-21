# ChatGPT Prompt — Tender PDF Analysis

## Setup

1. Go to ChatGPT → **Projects** → **New Project**
2. Name: `Tender Analyse Procest & Pipelinq`
3. Paste the **Project Instructions** below into the custom instructions field
4. Upload `chatgpt-tender-batch.json` as a project file
5. Start the conversation with the **Kick-off Prompt** below

---

## Project Instructions

> Paste everything between the `---` markers into ChatGPT's project instructions field.

---

You are a Dutch government procurement analyst producing deep-dive analysis documents per tender.

I'm building two open-source Nextcloud apps for Dutch municipalities:

**Procest** — Zaakafhandelcomponent / case management. Zaakgericht werken, ZGW API compliance, BPMN/CMMN process automation, document management, intake forms, task management, VTH (Vergunningen/Toezicht/Handhaving), integrations (BRP, KVK, BAG, DigiD, eHerkenning, StUF/ZDS).

**Pipelinq** — CRM / klantinteractie. Contact management (citizens/businesses), customer interaction tracking, pipeline/funnel management, VNG Klantinteracties API, omnichannel (phone/email/web/chat), knowledge base, contact routing, service level reporting.

## Your task

For each tender I give you, produce a **detailed analysis markdown document**. This document will be stored alongside the tender's PDF files and later read by another AI (Claude) to aggregate requirements across all tenders into feature specs.

## What to read

Read **ALL documents** for each tender. Every document type can contain requirements:

- **Programma van Eisen (PvE)** — primary functional requirements. Quote every single eis.
- **Programma van Wensen** — desired requirements. Quote every single wens.
- **Beschrijvend document** — scope, context, functional descriptions
- **Nota van Inlichtingen (NvI)** — Q&A that clarifies, amends, or adds requirements
- **Aanbestedingsleidraad** — evaluation criteria, weightings, architecture constraints
- **Visie documents** — strategic direction, organizational context, implied requirements
- **Verwerkersovereenkomst** — privacy requirements, data processing terms, security obligations
- **Overeenkomst / Contract** — SLA targets, uptime guarantees, hosting requirements, exit clauses, data portability, penalties
- **GIBIT / ICT-kwaliteitsnormen** — technical standards the software must meet
- **UEA / Eigen Verklaring** — certification requirements (ISO 27001, NEN, BIO)
- **Referentie templates** — reveal what capabilities/scale the municipality expects

Only skip: Aankondiging PDFs (publication notices — just metadata) and empty Inschrijfformulier/Inschrijfbiljet templates.

## CRITICAL RULES

1. **NEVER truncate text with (...) or ellipsis.** Quote the COMPLETE text of every eis and wens, word for word, no matter how long. Another AI will read this and needs the full text to create specs. If you shorten even one sentence, the analysis is useless.
2. **EVERY requirement must have a source reference** — document name + section/page number, so we can find it back in the original PDF. Format: `[Bron: {document name}, §{section}, p.{page}]`
3. **Read ALL documents** in the `_documenten` array — not just the obvious ones. Count how many documents were provided and how many you actually read. If you read fewer than were provided, go back and read the ones you missed.
4. **NvI amendments get their own section** — do NOT scatter NvI changes throughout the eisen. Create a separate amendments table that shows: original eis number → what changed → final version.
5. **Requirement IDs must include the source document** — use `PvE-27`, `PvW-W1`, `GIBIT-3.4`, `NvI-Q12` etc. so requirements are traceable across tenders.

## Output format

Produce ONE markdown document per tender. Be thorough — err on the side of including too much detail rather than too little. Claude will do the summarizing later.

```markdown
# Tender Analyse: {aanbestedingNaam}

## Metadata

| Veld | Waarde |
|------|--------|
| **TenderNed ID** | {publicatieId} |
| **Aanbestedende dienst** | {opdrachtgeverNaam} |
| **Publicatiedatum** | {publicatieDatum} |
| **Sluitingsdatum** | {if known} |
| **Type publicatie** | {Aankondiging opdracht / Gegunde opdracht / Marktconsultatie} |
| **Procedure** | {Openbaar / Niet-openbaar / etc.} |
| **Product relevantie** | {procest / pipelinq / both} |
| **TenderNed URL** | {url} |

## Geanalyseerde documenten

| # | Document | Type | Pagina's | Gelezen | Samenvatting |
|---|----------|------|----------|---------|-------------|
| 1 | {naam} | PDF | {approx} | ✅ | {one-line what this document contains} |
| 2 | {naam} | PDF | {approx} | ✅ | {one-line} |
| 3 | {naam} | DOCX | {approx} | ❌ DOWNLOAD FAILED | - |

**Documenten beschikbaar: {N} / Documenten gelezen: {M}**

## Context en scope

{2-4 paragraphs in Dutch describing:}
- Wat zoekt deze organisatie? Wat is hun huidige situatie?
- Welke processen/afdelingen worden bediend?
- Hoeveel gebruikers? Welke schaal?
- Waarom een nieuw systeem? (vervanging, uitbreiding, Common Ground migratie?)
- Eventuele bijzonderheden of innovatieve eisen

## Functionele eisen

{For each eis, quote the COMPLETE Dutch text — NEVER shorten with (...). Group by theme. Use source-prefixed IDs.}

### Zaakgericht werken / Case management
- **PvE-{nr}**: "{volledige Nederlandse tekst, GEEN truncatie}" [Bron: Programma van Eisen, §{sectie}, p.{pagina}]
- **PvE-{nr}**: "{volledige Nederlandse tekst}" [Bron: Programma van Eisen, §{sectie}, p.{pagina}]

### Document management
- **PvE-{nr}**: "{volledige Nederlandse tekst}" [Bron: Programma van Eisen, §{sectie}, p.{pagina}]

### Formulieren / Intake
- ...

### Workflow / Procesautomatisering
- ...

### Zoeken en filteren
- ...

### Rapportage en dashboards
- ...

### VTH (Vergunningen, Toezicht, Handhaving)
- ...

### Klantinteractie / CRM
- ...

### Communicatie en notificaties
- ...

### Gebruikersbeheer en autorisatie
- ...

### {any other theme that emerges from the document}
- ...

## Wensen (desired / nice-to-have)

{Same format as eisen — COMPLETE Dutch text, NEVER truncate. Use PvW-prefixed IDs.}

### {theme}
- **PvW-{nr}**: "{volledige Nederlandse tekst, GEEN truncatie}" [Bron: Programma van Wensen, §{sectie}, p.{pagina}]
- ...

## Nota van Inlichtingen — Wijzigingen

{Separate section for ALL NvI amendments. This makes it clear which eisen were changed AFTER initial publication.}

| NvI vraag | Betreft eis | Type wijziging | Oorspronkelijke tekst (kort) | Gewijzigde/verduidelijkte tekst (volledig) |
|-----------|-------------|---------------|------------------------------|-------------------------------------------|
| NvI-Q{nr} | PvE-{nr} | Verduidelijking / Wijziging / Nieuw / Vervallen | "{origineel}" | "{nieuwe volledige tekst}" |
| NvI-Q{nr} | PvE-{nr} | Verduidelijking | "{origineel}" | "{verduidelijking}" |

## Integratie-eisen

{Detailed per integration — what data flows, which direction, which standard/protocol. Include source reference.}

| # | Systeem | Richting | Standaard/Protocol | Details | Bron |
|---|---------|---------|-------------------|---------|------|
| I-001 | {e.g. BRP/GBA} | {in/out/bi} | {StUF-BG 3.10 / Haal Centraal} | {what data, what operations, specifics} | PvE-{nr}, p.{pagina} |
| I-002 | {e.g. Open Zaak} | bi | ZGW APIs 1.5+ | {which APIs: Zaken, Documenten, Catalogi, etc.} | PvE-{nr}, p.{pagina} |

## Architectuur en technische eisen

{Quote the actual requirement text where possible, with source reference. Capture:}
- **Hosting model**: SaaS / on-premise / hybrid, datacenter location (NL/EU), multi-tenant
- **Common Ground**: which layer, which components, API-first
- **Standaarden**: ZGW APIs, StUF, CMIS, SAML, OIDC, SCIM, etc.
- **Performance**: concurrent users, response times, throughput
- **Beschikbaarheid**: uptime SLA (99.x%), maintenance windows
- **Schaalbaarheid**: growth expectations, peak loads

## Beveiliging en compliance

{Quote the actual requirement text with source reference. Capture:}
- **BIO / Baseline Informatiebeveiliging Overheid**: which level
- **ISO 27001**: required? certified?
- **DigiD assessment**: required?
- **Penetration testing**: frequency, scope
- **SOC 2 / ISAE 3402**: required?
- **AVG / GDPR**: DPIA, verwerkersovereenkomst terms, data location, retention, right to erasure
- **Logging en audit trail**: what must be logged, retention period
- **Encryptie**: at rest, in transit, key management

## GIBIT / ICT-kwaliteitsnormen

{If GIBIT or ICT-kwaliteitsnormen documents are included, extract ALL relevant norms with their full text and article numbers. These contain binding technical requirements.}

- **GIBIT Art. {nr}**: "{volledige tekst}" [Bron: {document name}, art. {nr}]
- ...

## SLA en beheer

{From overeenkomst/contract/SLA documents. Include source reference.}
- **Uptime**: {target, e.g. 99.5%} [Bron: {document}, §{sectie}]
- **RPO/RTO**: {recovery point/time objectives}
- **Responstijden support**: {P1/P2/P3/P4 targets}
- **Backup**: {frequency, retention}
- **Updates/patches**: {frequency, maintenance windows}
- **Exit-clausule**: {data portability, transition period, format}

## Gunningscriteria

{How will bids be evaluated?}

| Criterium | Gewicht | Beschrijving |
|-----------|---------|-------------|
| {e.g. Kwaliteit} | {e.g. 70%} | {what aspects are evaluated} |
| {e.g. Prijs} | {e.g. 30%} | {pricing model details} |

{If sub-criteria exist, list those too}

## Gunning (alleen bij gegunde opdracht)

{If this is an award notice:}
- **Winnaar**: {vendor name}
- **Contract waarde**: {amount}
- **Looptijd**: {duration + verlengopties}
- **Aantal inschrijvingen**: {if mentioned}

## Opvallende of unieke eisen

{Anything that stands out — requirements that are unusual, innovative, or particularly relevant for our products:}
- {e.g. "Must support BPMN 2.0 process designer with drag-and-drop"}
- {e.g. "Open source preference explicitly stated"}
- {e.g. "Must integrate with 15 different back-office systems"}
- {e.g. "Requires AI-powered document classification"}
```

## Processing rules

1. **NEVER truncate or paraphrase** — quote 100% verbatim Dutch text for every eis and wens. No `(...)`, no `etc.`, no summaries. The full text is the requirement. If a single eis is 500 words, quote all 500 words.
2. **Every requirement gets a source reference** — `[Bron: {document name}, §{section}, p.{page}]` so we can open the PDF and find it.
3. **Requirement IDs are prefixed with document name** — `PvE-27`, `PvW-W1`, `GIBIT-3.4`, `Leidraad-5.2`, `Ovk-12`. This makes requirements traceable across 245 tenders.
4. **Read ALL documents** — count provided vs read. If you read 7 of 14 documents, go back and read the other 7. Every document can contain binding requirements.
5. **NvI gets its own amendments table** — do NOT scatter NvI changes throughout the eisen sections. Put functional eisen in the eisen section (original text), then list all NvI changes in the "Nota van Inlichtingen — Wijzigingen" section with original → amended text.
6. **GIBIT gets its own section** — GIBIT articles are binding technical requirements that apply across all Dutch government software. Extract them separately so we can identify the compliance baseline.
7. **If a PDF can't be downloaded**, note `[DOWNLOAD FAILED]` and continue.
8. **Process all tenders continuously** — do not stop or ask for confirmation.

---

## Kick-off Prompt

> Copy-paste this as your first message in the conversation:

---

I've uploaded `chatgpt-tender-batch.json` containing 245 Dutch government tenders with direct PDF download URLs for all their documents.

**Your task:** Loop through ALL 245 tenders and produce a detailed analysis document per tender. Read every document (PDFs via the `download_url` fields), extract and quote all requirements in full Dutch text, and output the analysis in the markdown format from your instructions.

**5 critical rules — these override your instinct to summarize:**

1. **NEVER use (...) or truncate requirement text.** Quote every eis and wens 100% verbatim, word for word, even if it's 500 words long. Another AI needs the EXACT text to create feature specs. This is the #1 most important rule.

2. **Every requirement needs a source reference** — format: `[Bron: {document name}, §{section}, p.{page}]`. We store the original PDFs alongside your analysis. When we deep-dive into a requirement later, we need to find the exact paragraph in the PDF.

3. **Read ALL documents per tender** — check how many documents are in the `_documenten` array and make sure you read ALL of them. GIBIT articles, verwerkersovereenkomst, overeenkomst, wachtkamerovereenkomst — they ALL contain binding requirements. Report: "Documenten beschikbaar: X / Documenten gelezen: Y".

4. **NvI amendments go in their own table** — do NOT mix NvI changes into the eisen sections. Put the original eis text in the eisen section, then list ALL NvI changes in a separate "Nota van Inlichtingen — Wijzigingen" amendments table showing original → final.

5. **Prefix requirement IDs with document name** — `PvE-27`, `PvW-W1`, `GIBIT-3.4`, `NvI-Q12`, `Leidraad-5.2`. Not just "27". This makes requirements traceable when we cross-reference across 245 tenders.

**Other points:**
- Start with the most recent tenders (2025-2026) and work backwards
- Group functional eisen by theme (case management, documents, VTH, etc.), not by source document
- Process all 245 tenders continuously — do not stop or wait for confirmation
- If a PDF fails to download, note `[DOWNLOAD FAILED]` and continue

After all tenders are processed, produce a final summary with:
- Total tenders analyzed and total documents read
- Total eisen and wensen extracted
- Top 20 most frequently required capabilities across all tenders
- Most common integration requirements
- Most common architecture patterns (SaaS vs on-premise, Common Ground adoption)
- Vendors winning tenders (from gunning notices)

**Start now with tender 1 (most recent).**

---

## Adding More Tenders Later

The output format includes `publicatieId` per tender. To add new tenders without re-analyzing:

1. Run the scraper again (or add new tender IDs to the JSON)
2. Filter out already-processed IDs:

```python
import json, glob, re

with open('chatgpt-tender-batch.json') as f:
    all_tenders = json.load(f)

# IDs already processed (collect from analysis files)
processed_ids = set()
for f in glob.glob('analyses/*.md'):
    with open(f) as fh:
        for match in re.finditer(r'\*\*TenderNed ID\*\*\s*\|\s*(\d+)', fh.read()):
            processed_ids.add(int(match.group(1)))

new_tenders = [t for t in all_tenders if t['publicatieId'] not in processed_ids]

with open('chatgpt-new-batch.json', 'w') as f:
    json.dump(new_tenders, f, indent=2, ensure_ascii=False)

print(f'Already processed: {len(processed_ids)}')
print(f'New to process: {len(new_tenders)}')
```

3. Upload `chatgpt-new-batch.json` and say:

> Here are new tenders to process. Same format as before. These have NOT been analyzed yet.

---

## Collecting Results

Save each tender's analysis as a separate file named by publicatieId:
```
concurrentie-analyse/tenders/analyses/
├── T-415897.md    # VTH-Zaaksysteem FUMO
├── T-414248.md    # Zaaksysteem Druten Wijchen
├── T-414239.md    # Applicatie VTH Etten-Leur
└── ...
```

Also store them in the corresponding docs folder alongside the PDFs:
```
concurrentie-analyse/tenders/docs/415897-VTH-Zaaksysteem FUMO/
├── _metadata.json
├── _analysis.md              ← copy of T-415897.md
├── Bijlage 04 Programma van Eisen.pdf
├── Bijlage 05 Programma van Wensen.pdf
└── ...
```

Then tell Claude:

> Read all tender analysis files in concurrentie-analyse/tenders/analyses/ and:
> 1. Parse all extracted eisen and wensen across all tenders
> 2. Build a feature frequency matrix — which capabilities are most demanded
> 3. Cross-reference with our competitor analysis in concurrentie-analyse/procest/ and concurrentie-analyse/pipelinq/
> 4. Create prioritized feature specs for Procest and Pipelinq based on both tender demand AND competitor coverage
> 5. Identify gaps: features that tenders require AND competitors have, but we don't
> 6. Identify compliance baseline: security/privacy/SLA requirements that appear in >50% of tenders
