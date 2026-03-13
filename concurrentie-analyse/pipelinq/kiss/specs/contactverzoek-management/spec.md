---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Contactverzoek (Contact Request) Management - KISS

## Purpose
When a KCM cannot resolve a customer query directly, they create a "contactverzoek" (contact request / internal task) to route it to the appropriate department, group, or individual employee for follow-up. This is KISS's internal task management system.

## Architecture Overview
- **Frontend**: Part of the contactmoment workflow (ContactverzoekFormulier.vue)
- **BFF**: Custom proxy controllers for OpenKlant 2 internetaak API, plus legacy Objecten API support
- **External**: OpenKlant 2 `internetaak` (internal task) API, or Objecten API for legacy support

## Data Model

### ContactverzoekData (Frontend)
```typescript
type ContactverzoekData = {
  status: string;                    // "te verwerken" / "verwerkt"
  contactmoment: string;             // URL of parent contactmoment
  registratiedatum: string;
  datumVerwerkt?: string;
  toelichting?: string;              // Notes for colleague
  actor: {
    naam: string;
    soortActor: string;              // "medewerker" / "organisatorische eenheid"
    identificatie: string;
    typeOrganisatorischeEenheid?: "afdeling" | "groep";
    naamOrganisatorischeEenheid?: string;
    identificatieOrganisatorischeEenheid?: string;
  };
  betrokkene: {
    rol: "klant";
    klant?: string;                  // URL
    persoonsnaam?: { voornaam, achternaam, voorvoegsel };
    organisatie?: string;
    digitaleAdressen: DigitaalAdres[];
  };
  verantwoordelijkeAfdeling: string;
};
```

### InternetaakPostModel (OpenKlant 2)
```typescript
interface InternetaakPostModel {
  nummer: string;
  gevraagdeHandeling: string;        // "Contact opnemen met betrokkene"
  aanleidinggevendKlantcontact: { uuid: string };
  toegewezenAanActoren: { uuid: string }[];
  toelichting: string;
  status: "te_verwerken" | "verwerkt";
  afgehandeldOp?: string;
}
```

### ContactVerzoekVragenSet (Configurable Forms)
```typescript
interface ContactVerzoekVragenSet {
  id: number;
  titel: string;
  vraagAntwoord: Vraag[];            // Dynamic form fields
  organisatorischeEenheidId: string;
  organisatorischeEenheidSoort: "afdeling" | "groep";
}
```

## Business Logic

### Actor Resolution
A contactverzoek can be assigned to:
1. **Afdeling (department)** only
2. **Groep (group)** only
3. **Afdeling + medewerker**
4. **Groep + medewerker**
5. **Medewerker** (with auto-resolved org unit)

The system uses `ensureActoren()` to find or create Actor records in OpenKlant 2.

### VragenSets (Question Sets)
Departments can configure custom intake forms (question sets) with different field types: input, textarea, dropdown, checkbox. When a KCM selects a department, the matching question set is loaded.

### Dual API Support
- **OpenKlant 2**: Uses `internetaak` API with `actoren`, `klantcontacten`, `betrokkenen`
- **Legacy (Objecten API)**: Stores contactverzoeken as objects in an Objecten register

## Requirements (as observed)
- Must support assigning to departments, groups, or individuals
- Must support configurable intake forms per department
- Must capture client contact details (phone, email)
- Must link to parent contactmoment
- Must support dual API backends (OpenKlant 2 + legacy Objecten)
- Must provide overview/search of all contactverzoeken

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Task routing | Yes (dept/group/employee) | Yes (pipeline stages + assignment) |
| Configurable forms | Yes (VragenSets) | No dynamic forms |
| Status tracking | Binary (te verwerken/verwerkt) | Multi-stage pipeline |
| Kanban view | No | Yes |
| Task overview | List-based | List + kanban |
| Client linking | Yes (betrokkene) | Yes (contact linking) |
| Department assignment | Yes (afdelingen/groepen) | No department concept |
| Follow-up tracking | Basic (status only) | Pipeline stage + My Work |
