---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Beheer (Administration) - KISS

## Purpose
KISS provides a dedicated admin section ("Beheer") for managing configuration data that drives the KCM workspace: channels, skills, quick links, conversation results, contact request forms, VACs, and news/work instructions. Only users with the Beheerder (admin) or Redacteur (editor) role can access this section.

## Architecture Overview
- **Frontend**: `src/features/beheer/` — Vue 3 views with CRUD interfaces for each entity type
- **BFF**: ASP.NET Core controllers for skills, links, gespreksresultaten (PostgreSQL); proxied endpoints for VACs, afdelingen, groepen (Objecten API)
- **Storage**: Mixed — some entities in BFF PostgreSQL (skills, links, gespreksresultaten), others in Objecten API (VACs, afdelingen, groepen, werkberichten)
- **Auth**: Protected by permission-based access control (Beheerder/Redacteur roles)

## Data Model

### Skills
```csharp
class Skill {
    int Id;
    string Naam;          // Skill name (e.g., "Burgerzaken", "Belastingen")
}
```
Skills are tags assigned to employees and werkberichten for filtering. Managed in BFF PostgreSQL.

### Links
```csharp
class Link {
    int Id;
    string Titel;         // Display text
    string Url;           // Target URL
    string? Categorie;    // Optional category grouping
}
```
Quick links displayed in the KCM sidebar for frequently used external resources.

### Gespreksresultaten (Conversation Results)
```csharp
class Gespreksresultaat {
    int Id;
    string Resultaat;     // e.g., "Doorverbonden", "Zelfstandig afgehandeld"
}
```
Dropdown values for how a contactmoment ended.

### Kanalen (Channels)
```csharp
class Kanaal {
    int Id;
    string Naam;          // e.g., "Telefoon", "Balie", "E-mail", "Chat"
}
```
Communication channels selectable when creating a contactmoment.

### ContactVerzoekVragenSet (Contact Request Forms)
```typescript
interface ContactVerzoekVragenSet {
    id: number;
    titel: string;
    vraagAntwoord: Vraag[];   // Dynamic form fields
    organisatorischeEenheidId: string;
    organisatorischeEenheidSoort: "afdeling" | "groep";
}

interface Vraag {
    vraag: string;            // Question label
    type: "input" | "textarea" | "dropdown" | "checkbox";
    opties?: string[];        // Dropdown/checkbox options
    verplicht: boolean;       // Required?
}
```
Department-specific intake forms shown when a KCM creates a contactverzoek for that department.

### VAC (Vraag-Antwoord Combinatie)
```typescript
interface Vac {
    uuid: string;
    vraag: string;            // The question
    antwoord: string;         // The answer (HTML)
    toelichting?: string;     // Additional notes
    afdelingen?: VacAfdeling[];
    trefwoorden?: { trefwoord: string }[];
    status: string;
    doelgroep?: string;       // Target audience
}
```
Q&A pairs stored in the Objecten API, indexed in Elasticsearch.

## Business Logic

### CRUD Operations
All admin entities follow standard CRUD patterns:
1. **List**: Paginated table with search/filter
2. **Create**: Form with validation
3. **Edit**: Pre-populated form
4. **Delete**: Confirmation dialog

### Role-Based Access
- **Beheerder**: Full access to all admin entities (skills, links, channels, gespreksresultaten, VACs, contactverzoek forms, werkberichten)
- **Redacteur**: Access to content-related entities only (werkberichten, VACs, kennisartikelen)
- **Klantcontactmedewerker**: No admin access (read-only consumer of configured data)

### Data Synchronization
Changes to entities stored in the Objecten API (VACs, werkberichten) require an Elasticsearch re-sync via KISS-Elastic-Sync before they appear in search results. BFF-stored entities (skills, links, gespreksresultaten, kanalen) take effect immediately.

### Contactverzoek Form Builder
Admins can create custom intake forms per department/group. When a KCM creates a contactverzoek for a department that has a configured VragenSet, the form fields are dynamically rendered. Answers are serialized and stored as part of the contactverzoek notes.

## Requirements (as observed)
- Must provide CRUD for channels, skills, links, conversation results
- Must support dynamic contact request form configuration per department
- Must support VAC (Q&A) management with rich text answers
- Must support news and work instruction management
- Must enforce role-based access (Beheerder vs Redacteur)
- Must support category grouping for links

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Channel management | Yes (kanalen CRUD) | No channel concept |
| Skills/tags | Yes (admin-managed) | Tags via OpenRegister schema |
| Quick links | Yes (configurable sidebar) | No dedicated links feature |
| Conversation results | Yes (configurable dropdown) | Pipeline stages instead |
| Dynamic forms | Yes (VragenSets per dept) | No dynamic form builder |
| VAC management | Yes (Q&A CRUD) | No Q&A system |
| Role-based admin | Yes (3 roles) | Nextcloud group-based |
| Content management | Yes (werkberichten) | No CMS features |

**Gap for Pipelinq**: A configurable form builder for intake forms (per pipeline stage or category) would be valuable. The quick links sidebar is a simple but useful UX feature.
