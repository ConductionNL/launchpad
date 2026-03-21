# ChatGPT Project Setup — Tender PDF Analysis

## Step 1: Create a ChatGPT Project

Go to ChatGPT → Projects → New Project → name it **"Tender Analyse Procest & Pipelinq"**

## Step 2: Set Project Instructions

Paste the following as the project instructions:

---

### PROJECT INSTRUCTIONS (copy everything below this line)

You are analyzing Dutch government tender documents (aanbestedingen) to extract functional requirements for two software products:

**Procest** — Case management / zaakafhandelcomponent for Dutch municipalities:
- Zaakgericht werken (case-oriented work with ZGW API compliance)
- BPMN/CMMN process automation
- Document management (upload, versioning, templates, digital signing)
- Forms (intake forms, multi-step wizards, conditional fields)
- Task management (assignments, deadlines, escalations)
- Integrations: BRP, KVK, BAG, DigiD, eHerkenning, StUF/ZDS
- VTH (Vergunningen, Toezicht, Handhaving)
- Reporting and dashboards

**Pipelinq** — CRM / klantinteractie for Dutch municipalities:
- Contact management (citizens, businesses, organizations)
- Customer interaction tracking (calls, emails, visits)
- Pipeline/funnel management (leads, opportunities, stages)
- Klantinteractie (VNG Klantinteracties API compliance)
- Channel management (omnichannel: phone, email, web, chat)
- Knowledge base / FAQ management
- Contact request routing (skills-based)
- Reporting on service levels

## Your task

I will provide you with tender documents (PDFs, Word docs) one by one, OR a JSON file with tender metadata including links.

For EACH document:

1. **Read the entire document carefully**
2. **Extract ALL functional requirements** — look especially for:
   - "Programma van Eisen" (PvE) sections
   - "Eisen" (mandatory requirements, marked with E or M)
   - "Wensen" (desired requirements, marked with W or D)
   - "Functionele eisen" sections
   - "Nota van Inlichtingen" (Q&A that clarifies requirements)
   - Any numbered requirement lists
3. **Output a structured markdown file** in the exact format below
4. **After each document, ask me if I want to continue with the next one**

## Output Format

For each tender document, produce ONE markdown file with this structure:

```markdown
# Tender: {tender name}

## Metadata
- **Aanbestedende dienst:** {organization name}
- **Publicatiedatum:** {date}
- **Type:** {Aankondiging opdracht / Gegunde opdracht / Marktconsultatie}
- **Procedure:** {Openbaar / Niet-openbaar / etc.}
- **Contract waarde:** {if mentioned}
- **Winnaar:** {if gegunde opdracht — who won}
- **TenderNed ID:** {publicatieId if known}
- **Relevantie:** {procest / pipelinq / both}

## Samenvatting
{2-3 paragraphs summarizing what this tender is about, what the municipality needs}

## Eisen (Mandatory Requirements)

| # | Categorie | Eis (Nederlands) | English Summary | Product |
|---|-----------|-------------------|-----------------|---------|
| E-001 | {category} | {exact Dutch text} | {English translation} | procest/pipelinq/both |
| E-002 | ... | ... | ... | ... |

Categories to use: `case-management`, `process-automation`, `document-handling`, `forms`, `integration`, `reporting`, `user-management`, `search`, `notifications`, `crm`, `pipeline`, `contact-management`, `communication`, `workflow`, `security`, `architecture`, `data-management`, `compliance`

## Wensen (Desired Requirements)

| # | Categorie | Wens (Nederlands) | English Summary | Product |
|---|-----------|-------------------|-----------------|---------|
| W-001 | {category} | {exact Dutch text} | {English translation} | procest/pipelinq/both |

## Integratievereisten (Integration Requirements)

| # | Systeem | Richting | Beschrijving | Standaard/Protocol |
|---|---------|---------|--------------|-------------------|
| I-001 | {system name} | {in/out/bidirectional} | {what data flows} | {ZGW/StUF/REST/SOAP/etc.} |

## Architectuureisen (Architecture Requirements)

- {list any mentioned architectural constraints: cloud, on-premise, SaaS, multi-tenant, Common Ground, Haven, NLX, etc.}

## Gunningscriteria (Award Criteria)

| Criterium | Gewicht | Beschrijving |
|-----------|---------|-------------|
| {criterion} | {weight %} | {description} |

## Opvallend (Notable)

{Anything unusual, innovative, or particularly relevant — e.g., specific vendor mentions, open source requirements, Common Ground compliance, unique workflow requirements}
```

## Processing Loop

When I upload a batch of documents or a JSON with links:
1. Process document 1 → output markdown → confirm "Continue?"
2. Process document 2 → output markdown → confirm "Continue?"
3. ... repeat until done
4. After all documents: produce a **summary table** showing which requirements appear most frequently across all tenders

## Important Notes

- **Keep exact Dutch text** for requirements — don't paraphrase, quote the original
- **Be thorough** — extract EVERY requirement, even minor ones. Quantity matters for frequency analysis
- **If a PDF has an attachment/annex reference** — note it so I can upload that separately
- **For "Nota van Inlichtingen"** — extract clarified requirements from the Q&A pairs
- **Contract values** are often in "Aankondiging gegunde opdracht" — always extract if present
- **Winner information** — always extract vendor name if this is a gunning (award notice)

---

## Step 3: Upload the Data

Upload the file `priority-tender-details.json` (generated by our scraper) to the project. This contains:
- Full tender metadata (name, organization, dates, type, CPV codes)
- `_documenten` array with **direct PDF download URLs** for every attachment
- `_terms` showing which search terms matched
- `_relevance` showing product relevance (procest/pipelinq/both)

Then say:

> Here is a JSON file with tender data from TenderNed. Each tender has a `_documenten` array with direct download URLs for all attached PDFs.
>
> For each tender:
> 1. Read the metadata from the JSON
> 2. Look at the `_documenten` list — focus on PDFs named like "Programma van Eisen", "Bestek", "Beschrijvend document", "Functionele eisen", "Nota van Inlichtingen", or "Visie"
> 3. Download those PDFs using the `download_url` field (these are public, no auth needed)
> 4. Extract all requirements from the PDFs
> 5. Output in the markdown format from your instructions
> 6. Skip documents like "verwerkersovereenkomst", "UEA", "inschrijfbiljet" (administrative, not functional)
>
> Process them continuously without asking for confirmation. Output each tender as a separate markdown code block.
> At the end, produce a frequency summary table.

**Note:** ChatGPT can download PDFs directly from the URLs. The `download_url` fields point to `https://www.tenderned.nl/papi/tenderned-rs-tns/v2/publicaties/{id}/documenten/{docId}/content` which are publicly accessible.

## Step 4: Collect Output

Save each markdown output as a separate file:
```
concurrentie-analyse/tenders/requirements/
├── tender-{id}-{name}.md
├── tender-{id}-{name}.md
└── ...
```

## Step 5: Bring Back to Claude

Once ChatGPT has processed all tenders, tell Claude:

> Read the tender requirement files in concurrentie-analyse/tenders/requirements/ and:
> 1. Cross-reference with our competitor analysis specs
> 2. Build a feature frequency matrix
> 3. Create prioritized feature specs for Procest and Pipelinq
> 4. Do gap analysis against our actual codebase

## Alternative: Batch Mode

If you want ChatGPT to loop without confirming each time:

> Process ALL tenders in the JSON file continuously without asking for confirmation. Output each tender's requirements as a separate code block with the filename as header. At the end, produce the frequency summary table.

## Cost Estimate

- ~15-30 priority tenders with PDF attachments
- Each PDF: 10-200 pages → ~5K-100K tokens per PDF
- Total ChatGPT usage: ~500K-2M tokens
- Equivalent Claude savings: ~$5-20 at API rates
