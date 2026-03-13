---
status: draft
source: competitive-analysis
competitor: kiss
analyzed_date: 2026-03-13
---
# Werkberichten & News (Internal Communications) - KISS

## Purpose
KISS provides an internal news and work instructions system for KCMs (klantcontactmedewerkers). Administrators and editors publish news articles and work instructions that are indexed in Elasticsearch and surfaced during search and on the homepage. This ensures KCMs always have access to the latest procedures, policy changes, and operational instructions while handling citizen interactions.

## Architecture Overview
- **Frontend**: `src/features/werkbericht/` — WerkberichtDetail.vue, WerkberichtOverview.vue, homepage carousel
- **BFF**: No dedicated BFF controller; werkberichten are stored as objects in the Objecten API and indexed via KISS-Elastic-Sync
- **Search**: Indexed in Elasticsearch as `nieuwsbericht` and `werkinstructie` source types
- **Standard**: Based on the OpenPub publication standard for Dutch government
- **Admin**: Beheer (admin) section provides CRUD for news/work instructions

## Data Model

### Werkbericht (OpenPub Object)
```typescript
interface Werkbericht {
  id: string;
  title: string;                    // Article title
  content: string;                  // Rich HTML content
  publicatieDatum: string;          // Publication date
  wijzigingsDatum?: string;         // Last modification date
  vervalDatum?: string;             // Expiration date
  featured: boolean;                // Show on homepage carousel
  type: "nieuwsbericht" | "werkinstructie"; // News vs work instruction
  skills: string[];                 // Skill-based filtering tags
  organisatieEenheid?: string;      // Target department/group
}
```

### Elasticsearch Index Entry
```json
{
  "title": "Nieuwe procedure afval ophalen",
  "object_bron": "werkinstructie",
  "object_meta": "skill:afval,skill:openbare ruimte",
  "url": "/werkberichten/123",
  "werkinstructie": {
    "content": "<p>Vanaf 1 maart geldt...</p>",
    "publicatieDatum": "2026-03-01",
    "featured": true
  }
}
```

## Business Logic

### Publication Flow
1. Redacteur (editor) or Beheerder (admin) creates a werkbericht in the admin section
2. The werkbericht is stored as an object in the Objecten API using the OpenPub object type
3. KISS-Elastic-Sync cronjob picks up the object and indexes it in Elasticsearch
4. The werkbericht becomes searchable and (if featured) appears on the KCM homepage

### Skill-Based Filtering
Werkberichten can be tagged with skills (e.g., "afval", "burgerzaken", "belastingen"). KCMs have skills assigned to their profile. The homepage carousel and search results can be filtered to show only werkberichten relevant to the KCM's skill set. This prevents information overload — a KCM handling tax questions does not see waste collection instructions.

### Featured Articles
Featured werkberichten appear in a carousel on the KISS homepage. This provides immediate visibility for urgent news or important procedure changes without requiring the KCM to search.

### Source Tracking in Contactmomenten
When a KCM consults a werkbericht during a contactmoment, it is tracked as a "bron" (source) with `shouldStore=true`. This creates an audit trail showing which work instructions influenced the KCM's response to the citizen.

### Expiration
Werkberichten support an optional `vervalDatum` (expiration date). Expired articles are no longer shown in search results or on the homepage, but remain in the Objecten API for historical reference.

## Requirements (as observed)
- Must support both news articles (nieuwsberichten) and work instructions (werkinstructies) as distinct types
- Must support rich HTML content (sanitized)
- Must be indexed in Elasticsearch for unified search
- Must support skill-based filtering for targeted distribution
- Must support featured articles on homepage carousel
- Must support expiration dates
- Must track consultation during contactmomenten (source tracking)
- Must follow OpenPub standard for Dutch government content publishing

## Comparison Notes - KISS vs Pipelinq
| Aspect | KISS | Pipelinq |
|--------|------|----------|
| Internal news system | Yes (werkberichten) | No internal news |
| Work instructions | Yes (werkinstructies) | No work instructions |
| Content standard | OpenPub (Dutch gov) | N/A |
| Skill-based filtering | Yes | No skill concept |
| Featured/carousel | Yes (homepage) | No homepage feature |
| Searchable | Yes (Elasticsearch) | N/A |
| Source tracking | Yes (bron in contactmoment) | N/A |
| Rich content | Yes (sanitized HTML) | N/A |
| Expiration | Yes (vervalDatum) | N/A |

**Gap for Pipelinq**: An internal communications/knowledge system could help teams share procedures and updates. Could be implemented as a dedicated "News" schema in OpenRegister with Elasticsearch indexing.
