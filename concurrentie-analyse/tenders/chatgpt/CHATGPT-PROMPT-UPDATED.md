# ChatGPT Prompt — Tender PDF Analysis (Updated after production run)

## Setup

1. Go to ChatGPT → **Projects** → **New Project**
2. Name: `Tender Analyse Procest & Pipelinq`
3. Paste the contents of `CHATGPT-PROJECT-SETUP-UPDATED.md` into the project instructions field
4. Upload the tender batches as local archives (`.zip` or `.tar.gz`) rather than only a JSON with live URLs
5. Start the conversation with the **Kick-off Prompt** below

---

## Kick-off Prompt

I've uploaded one or more tender archives containing Dutch government tender documents. These archives together contain the tender folders and their local source files.

**Your task:** Process all tenders continuously and produce one detailed analysis `README.md` per tender in the required format.

## Critical execution rules

1. **Work from the uploaded local archives first.** Do not prefer live TenderNed downloading when local files are available.
2. **Read all documents per tender** except publication notices and empty bid forms.
3. **Never truncate requirement text.** Quote eisen and wensen fully and verbatim.
4. **Every requirement needs a source reference** in the format `[Bron: {document name}, §{section}, p.{page}]` whenever determinable.
5. **NvI amendments must go in a separate amendments table.**
6. **Requirement IDs must be traceable** (`PvE-27`, `PvW-W4`, `NvI-Q12`, `GIBIT-3.4`, etc.).
7. **Do not stop for confirmation between tenders.** Process the full uploaded set continuously.
8. **After the first broad pass, create a completeness audit** and then run targeted repair rounds only on the weak restgroup.
9. **Final delivery must be one consolidated package** with:
   - one `README.md` per tender
   - `README-INDEX.md`
   - `COMPLETENESS-AUDIT.md`
   - `COMPLETENESS-AUDIT.csv`
   - a decision note explaining which dataset is leading if a correction layer exists

## Target output per tender

Use the deep-dive tender analysis format with these sections:
- Metadata
- Geanalyseerde documenten
- Context en scope
- Functionele eisen
- Wensen
- Nota van Inlichtingen — Wijzigingen
- Integratie-eisen
- Architectuur en technische eisen
- Beveiliging en compliance
- GIBIT / ICT-kwaliteitsnormen
- SLA en beheer
- Gunningscriteria
- Gunning
- Opvallende of unieke eisen

## Recommended operating sequence

1. unpack and inventory all uploaded tender archives
2. merge them into one canonical local source tree
3. run a broad extraction pass over all tenders
4. create a completeness audit
5. identify the restgroup with OCR/placeholder/low-extraction issues
6. run one or more targeted repair rounds only on that restgroup
7. produce one final consolidated zip for downstream use

## Important lessons from the previous full run

- Local uploaded archives are faster and more reliable than URL-only ingestion.
- The right goal is not “perfect in one pass” but “broad stable pass + audit + targeted repairs”.
- Combined PvE/PvW tables need explicit parsing for IDs like `E1`, `W1`, `W2`.
- Scan-heavy PDFs and NvI tables often require a separate repair strategy.
- A small targeted correction layer can be more effective than repeatedly reprocessing all tenders.
- Always preserve one stable broad dataset and document which targeted files override it.

## End-of-run summary required

At the end, report at least:
- total tenders analyzed
- total documents inventoried and read
- total extracted eisen and wensen
- top 20 recurring capabilities
- most common integrations
- most common architecture patterns
- vendors found in award notices
- which tenders remain low-confidence after the final repair round
