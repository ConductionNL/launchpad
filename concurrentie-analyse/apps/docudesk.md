# Docudesk — Feature Reference

**Application**: Docudesk
**Description**: Document generation from templates and data anonymization/redaction. NOT a DMS — OpenRegister provides DMS.
**Software categories**: Document generation (`document-generation`), Data anonymization & redaction (`data-anonymization`)
**Generated**: 2026-03-21

> Two distinct capabilities: (1) Template-based document generation (G2: Document Generation — Windward, Docmosis category) and (2) Data anonymization/redaction (G2: Data De-Identification, ISO/IEC 27038 Digital Redaction, ISO/IEC 27559 Anonymization).

## Document generation — Standard Features

*6 features defined. Evidence will grow as sync sources populate this category.*

- **[core]** `data-merge` Data merge / mail merge — Populate templates with data from registers or APIs
- **[core]** `output-formats` Output formats — Generate PDF, DOCX, ODT, HTML from templates
- **[core]** `template-management` Template management — Create, version, and organize document templates
- [standard] `batch-generation` Batch generation — Generate multiple documents in bulk
- [standard] `conditional-content` Conditional content — Show/hide template sections based on data values
- [advanced] `digital-signing` Digital signing — Sign generated documents electronically

## Data anonymization & redaction — Standard Features

*6 features defined. Evidence will grow as sync sources populate this category.*

- **[core]** `pii-detection` PII detection — Automatically detect personal data (BSN, names, addresses)
- **[core]** `pseudonymization` Pseudonymization — Replace identifiers with pseudonyms (GDPR Art. 4(5))
- **[core]** `text-redaction` Text redaction — Black-out or remove sensitive text from documents (ISO 27038)
- [standard] `audit-trail` Anonymization audit trail — Log what was anonymized, when, and by whom
- [standard] `batch-anonymization` Batch anonymization — Anonymize multiple documents at once
- [standard] `woo-publication` WOO publication — Prepare documents for public disclosure under WOO

---

**Summary**: 0 TEC features, 0 evidence links, 0 additional (non-TEC) features, 12 standard features

*Generated from `concurrentie-analyse/intelligence.db` by `scripts/generate_app_features.py`*