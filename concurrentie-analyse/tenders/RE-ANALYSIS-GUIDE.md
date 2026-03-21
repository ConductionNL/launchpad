# Tender Re-Analysis Guide

## Goal

Extract every functional eis (requirement) and wens (wish) from PvE/PvW documents across 245 Dutch government tenders (84 unique procurements), plus integration, architecture, security, and SLA requirements. The output feeds into product specs for:

- **Procest** -- zaaksysteem / case management / VTH / document management
- **Pipelinq** -- CRM / klantinteractie / klantcontactsysteem

The first-pass analysis by ChatGPT achieved only ~28% completeness. This re-analysis reads the actual PDF/DOCX documents and produces a structured `ANALYSE.md` per tender using the standardized template.

---

## Document Priority Order

Process documents within each tender folder in this order. Each type serves a different purpose.

### 1. Programma van Eisen (PvE)

**Priority: CRITICAL**

The core deliverable. Extract every numbered eis exactly as written in Dutch.

- Quote the complete eis text verbatim (do not summarize or paraphrase)
- Preserve the original numbering scheme (e.g., E-001, PvE-3.1.4, FE-12)
- Note the page number and document filename as source reference
- Classify each eis by theme (see template for theme list)
- Flag eisen marked as "knock-out" / "uitsluitend" / "minimumeis"

### 2. Programma van Wensen (PvW)

**Priority: CRITICAL**

Same treatment as PvE but for scored wishes.

- Quote complete wens text verbatim in Dutch
- Preserve original numbering
- Include the scoring weight/points if specified
- Note evaluation method (e.g., "rapportcijfer 1-10", "voldoet/voldoet niet")

### 3. Beschrijvend Document

**Priority: HIGH**

Provides scope, context, and functional descriptions that frame the eisen.

- Extract the project scope and organizational context
- Note the current IT landscape and systems being replaced
- Identify implicit requirements described in prose but not numbered in PvE
- Extract user counts, transaction volumes, department scope
- Note any architectural diagrams or integration overviews

### 4. Nota van Inlichtingen (NvI)

**Priority: HIGH**

Contains amendments, clarifications, and corrections to eisen.

- For each NvI entry that modifies an eis: record the original eis number, the question, and the amended text
- Flag withdrawn or relaxed eisen
- Flag new eisen added via NvI
- If multiple NvI rounds exist, process them chronologically
- Note the NvI date for each modification

### 5. GIBIT / ICT-kwaliteitsnormen

**Priority: MEDIUM**

Technical standards that most Dutch government tenders reference.

- Note the GIBIT version referenced
- Extract any organization-specific additions or deviations from standard GIBIT
- Record specific ICT-kwaliteitsnormen articles that are made mandatory
- Note data portability, exit, and escrow requirements

### 6. Overeenkomst / Contract / Raamovereenkomst

**Priority: MEDIUM**

SLA definitions, exit clauses, hosting requirements.

- Extract SLA targets (uptime, response times, fix times per priority)
- Note hosting requirements (NL, EU, on-premise, SaaS, hybrid)
- Record contract duration and extension options
- Extract exit/transition obligations
- Note penalty clauses (boeteclausules)

### 7. Aanbestedingsleidraad

**Priority: LOW (for functional analysis)**

Evaluation criteria and process rules.

- Extract gunningscriteria weights (prijs vs. kwaliteit)
- Note any proof-of-concept or demo requirements
- Record the evaluation methodology
- Note the timeline and key dates

---

## Output Format

Each analyzed tender produces a file at:

```
docs/{publicatieId}-{name}/ANALYSE.md
```

Use the exact template from `ANALYSE-TEMPLATE.md`. The standardized sections enable programmatic parsing across all 84 analyses.

### Key formatting rules

1. **Eisen must be quoted verbatim** in Dutch, wrapped in blockquotes (`>`), with source reference
2. **Every eis gets a source reference** in the format `[Bron: {filename}, p.{page}]`
3. **Tables use pipe syntax** with header row and separator
4. **Theme grouping** follows the predefined categories in the template -- add custom themes only if needed
5. **No truncation** -- if a PvE has 200 eisen, all 200 must appear in the analysis
6. **NvI amendments** are recorded both in the dedicated NvI section AND as inline notes on the affected eis

---

## Quality Checklist

An analysis is **high quality** when:

- [ ] All documents in the tender folder have been read and inventoried in "Geanalyseerde documenten"
- [ ] Eis count in analysis matches the total in the PvE document(s)
- [ ] Every eis is quoted verbatim in Dutch with source reference
- [ ] Wens count matches the PvW document(s)
- [ ] NvI modifications are cross-referenced with affected eisen
- [ ] Integration requirements have system name, direction, protocol, and source
- [ ] SLA targets are specific (numbers, not "hoog" or "laag")
- [ ] Gunningscriteria weights sum to 100% (or are noted as incomplete)
- [ ] No placeholder text remains from the template
- [ ] Context section explains what the organization does and why they need this system
- [ ] Architecture section identifies hosting model, deployment requirements, and standards compliance

An analysis is **medium quality** when:

- Most eisen are captured but some may be summarized rather than quoted
- Source references are present but may lack page numbers
- NvI cross-referencing is partial

An analysis is **low quality** when:

- Eisen are summarized or paraphrased
- Significant portions of documents were not read
- Source references are missing
- Eis count does not match the PvE

---

## Deduplication

Many tenders have multiple publications on TenderNed (rectificaties, heraanbestedingen, updates). The deduplication analysis identified **84 unique procurements** from 245 publications.

The unique ranking with all metadata is at: `/tmp/tender-unique-ranking.json`

Each entry in the ranking has:
- `tid` -- the TenderNed ID of the latest/richest publication (use this one)
- `_all_tids` -- all TenderNed IDs for this procurement
- `_duplicates` -- number of duplicate publications
- `folder` -- the folder name in `docs/`

**Rule: always process the folder listed in the ranking's `folder` field.** This is the latest publication with the most complete document set. If key documents are missing from the primary folder, check duplicate folders (`_all_tids`) for earlier versions.

---

## Processing Order

### Phase 1: Top 20 richest tenders (spec_kb > 600)

These have the most specification content and yield the richest functional requirements. Process first.

| # | TenderNed ID | Spec KB | Org |
|---|-------------|---------|-----|
| 1 | 308208 | 4,847 | Omgevingsdienst Noordzeekanaalgebied |
| 2 | 384261 | 2,354 | ICC Regio Schiphol Bevlogen |
| 3 | 216588 | 1,779 | Gemeente Zaanstad |
| 4 | 21556 | 1,744 | Gemeente Deventer |
| 5 | 402863 | 1,642 | Rijkswaterstaat |
| 6 | 296963 | 1,598 | Waterschap Noorderzijlvest |
| 7 | 223537 | 1,512 | Gemeente Noordenveld |
| 8 | 236933 | 1,477 | Gemeente Middelburg |
| 9 | 255697 | 1,471 | Veiligheidsregio Twente |
| 10 | 257916 | 1,281 | gemeente Nissewaard |
| 11 | 206120 | 1,124 | Gemeente Baarn |
| 12 | 210307 | 1,067 | Gemeenschappelijk Belastingkantoor Lococensus-Tricijn |
| 13 | 414248 | 1,066 | Werkorganisatie Druten Wijchen |
| 14 | 402469 | 1,018 | Tilburg University |
| 15 | 348539 | 954 | Veiligheidsregio Noord- en Oost-Gelderland |
| 16 | 162869 | 885 | Gemeente Berkelland |
| 17 | 415897 | 832 | FUMO |
| 18 | 387927 | 783 | Gemeente De Bilt |
| 19 | 404174 | 782 | RUD Utrecht |
| 20 | 362829 | 756 | Gemeente Geertruidenberg |

### Phase 2: Remaining tenders with spec documents (spec_kb 1-755)

54 tenders with at least some PvE/PvW/Beschrijvend documents. Process by descending spec_kb.

### Phase 3: Tenders without spec documents (spec_kb = 0)

10 tenders where no PvE, PvW, or Beschrijvend Document was found. These may still have useful requirements in other documents (Aanbestedingsleidraad, Overeenkomst). Process last; mark as `skip` if no extractable requirements exist.

---

## Tips for Effective Analysis

### Reading PDFs
- PDF files can be read directly using the Read tool
- For large PDFs (>10 pages), use the `pages` parameter to read in chunks
- Start with the table of contents (usually pages 1-5) to understand structure
- Then read the eisen/wensen sections in detail

### Handling large PvE documents
- Some PvEs have 200+ eisen across 100+ pages
- Read systematically section by section
- Do not skip sections -- every eis must be captured
- Use the theme classification to organize as you go

### NvI cross-referencing
- NvI documents are often formatted as Q&A tables
- Match question references (e.g., "Vraag over eis E-042") back to the original eis
- Update the eis entry with an inline note: `**[NvI wijziging]**: {amended text}`

### Common Dutch tender terminology
- **Eis** = mandatory requirement (must comply)
- **Wens** = wish/desire (scored, not mandatory)
- **Knock-out eis** = mandatory, non-compliance = exclusion
- **Gunningscriterium** = award criterion
- **EMVI** = Economisch Meest Voordelige Inschrijving (best value)
- **PvE** = Programma van Eisen
- **PvW** = Programma van Wensen
- **NvI** = Nota van Inlichtingen
- **BvP** = Best Value Procurement
- **GIBIT** = Gemeentelijke Inkoopvoorwaarden bij IT
- **ARBIT** = Algemene Rijksvoorwaarden bij IT
- **VTH** = Vergunningen, Toezicht en Handhaving
- **KCS** = Klant Contact Systeem
- **ZGW** = Zaakgericht Werken
- **DMS** = Document Management Systeem
- **RMA** = Records Management Applicatie
