# Kennisbank Specification (Cross-App)

## Purpose

The kennisbank (knowledge base) provides users across all Conduction apps with a searchable repository of articles, FAQs, and procedures. Articles are categorized, versioned, and linked to entity types (zaaktypen, product categories, service types) so users can find the right information for each inquiry. This is a key enabler for first-call resolution in KCC operations and for consistent case handling in case management.

While primarily consumed by KCC agents in Pipelinq for answering citizen questions, the knowledge base is cross-app: Procest case handlers use it for procedure guidance, public-facing portals can surface public articles, and admin users manage content centrally.

**Consuming apps**: Pipelinq (KCC agent search), Procest (case handler guidance), OpenRegister (storage), public portals (citizen-facing articles)
**Tender frequency**: Implicitly required by 51/52 tenders demanding FCR rates (74%+); 1/52 explicitly requires kennisbank
**Standards**: Schema.org (Article, FAQPage, HowTo), KCS methodology, WCAG AA

---

## Requirements

### Requirement 1: Article management with rich text and versioning

The system MUST support creating, editing, publishing, and archiving articles with rich text content and full version history.

#### Scenario 1.1: Create a new article
- GIVEN a kennisbank editor with permissions
- WHEN they create an article with title, category, body content, and visibility
- THEN an OpenRegister object MUST be created with the `kennisartikel` schema
- AND the article MUST have status "Concept" initially with author and timestamp

#### Scenario 1.2: Publish an article
- GIVEN a draft article
- WHEN an editor changes status to "Gepubliceerd"
- THEN the article MUST be visible to all authorized users in search
- AND publication date MUST be recorded

#### Scenario 1.3: Edit published article (versioning)
- GIVEN a published article at version 1
- WHEN an editor modifies and saves
- THEN version 2 MUST be created with version 1 retained in history
- AND "Laatst bijgewerkt" MUST update

#### Scenario 1.4: Archive obsolete article
- GIVEN a published article no longer relevant
- WHEN editor sets status to "Gearchiveerd"
- THEN it MUST NOT appear in default search
- AND MUST be accessible via "Toon gearchiveerd" filter
- AND links from other articles MUST show "Gearchiveerd" badge

#### Scenario 1.5: Rich text editing
- GIVEN the article editor
- THEN rich text MUST support: headings, bold/italic, lists, links, images, code blocks, and tables
- AND the editor MUST be compatible with Nextcloud Text (ProseMirror/Markdown)

---

### Requirement 2: Fast full-text search

The system MUST provide fast, full-text search across all published articles.

#### Scenario 2.1: Full-text search
- GIVEN 200+ published articles
- WHEN an agent searches for "paspoort verlengen"
- THEN relevant articles MUST be returned ranked by relevance
- AND search MUST cover title, body text, and tags
- AND results MUST show: title, category, snippet with highlighted matches

#### Scenario 2.2: Zero results handling
- GIVEN no articles match the query
- THEN "Geen resultaten gevonden" MUST be displayed with suggestions

#### Scenario 2.3: Search performance during active contact
- GIVEN an agent is on a phone call
- WHEN they search the kennisbank
- THEN results MUST appear within 500ms

#### Scenario 2.4: Search across apps
- GIVEN Pipelinq and Procest both consume the kennisbank
- THEN search results MUST be available from both apps' UIs
- AND the search API MUST be a shared endpoint

---

### Requirement 3: Hierarchical categorization

The system MUST support hierarchical categories for organizing articles.

#### Scenario 3.1: Browse by category
- GIVEN categories: "Burgerzaken" > "Paspoort", "Rijbewijs", "Uittreksel"
- WHEN browsing "Burgerzaken > Paspoort"
- THEN all articles in "Paspoort" MUST display with breadcrumb navigation

#### Scenario 3.2: Article in multiple categories
- GIVEN article "Verhuizing doorgeven" relevant to "Burgerzaken" and "Belastingen"
- THEN the article MUST appear in both category views

#### Scenario 3.3: Category management
- GIVEN an admin
- THEN categories MUST be creatable, editable, reorderable, and deletable
- AND deleting a category MUST NOT delete its articles (they become uncategorized)

---

### Requirement 4: Entity type linking

Articles MUST be linkable to specific entity types (zaaktypen, product types) for context-aware suggestions.

#### Scenario 4.1: Link article to zaaktype
- GIVEN article "Procedure bouwvergunning" and zaaktype "Omgevingsvergunning bouwen" in Procest
- WHEN an editor links the article to the zaaktype
- THEN the article MUST appear when a case handler views a case of that type and clicks "Kennisbank"

#### Scenario 4.2: View related articles from case
- GIVEN a case of type "Omgevingsvergunning bouwen" with 3 linked articles
- WHEN the handler clicks "Kennisbank"
- THEN the 3 related articles MUST display ordered by relevance/rating

#### Scenario 4.3: Link article to product category (Pipelinq)
- GIVEN article "Tarieven consultatiediensten" and product category "Consulting"
- WHEN linked
- THEN the article MUST appear when viewing leads/quotes with "Consulting" products

---

### Requirement 5: Agent feedback and KCS methodology

The system MUST allow users to rate articles and suggest improvements for continuous knowledge improvement.

#### Scenario 5.1: Rate article usefulness
- GIVEN an agent reads an article
- WHEN they click thumbs up/down
- THEN the rating MUST be recorded with identity and timestamp
- AND aggregate score MUST influence search ranking

#### Scenario 5.2: Suggest improvement
- GIVEN an agent finds outdated information
- WHEN they submit a suggestion
- THEN a feedback item MUST be created linked to the article
- AND editors MUST be notified
- AND the feedback MUST track status: nieuw, in behandeling, verwerkt

#### Scenario 5.3: Most-used articles report
- GIVEN article usage tracking
- THEN a report MUST show: most viewed articles, highest rated, most linked to contactmomenten
- AND this data MUST be available for optimizing the knowledge base

---

### Requirement 6: Public vs internal articles

The system MUST distinguish between agent-only (internal) and citizen-facing (public) articles.

#### Scenario 6.1: Internal-only article
- GIVEN article "Escalatieprotocol agressieve burgers" with visibility "Intern"
- THEN it MUST NOT be visible on any public-facing channel
- AND MUST only be visible to authenticated agents

#### Scenario 6.2: Public article
- GIVEN article "Hoe vraag ik een paspoort aan?" with visibility "Openbaar"
- THEN it MUST be available on public-facing channels (if configured)
- AND internal notes/annotations MUST NOT be shown to citizens

#### Scenario 6.3: Public API for citizen portals
- GIVEN public articles exist
- THEN a public API endpoint MUST serve published public articles without authentication
- AND the API MUST support search, category browsing, and article retrieval

---

### Requirement 7: Knowledge base integration in workspaces

The kennisbank MUST be integrated into the primary workspaces of consuming apps.

#### Scenario 7.1: KCC werkplek integration (Pipelinq)
- GIVEN an agent handling a contact in the KCC werkplek
- THEN a kennisbank search panel MUST be accessible via icon or keyboard shortcut (Ctrl+K)
- AND search MUST be pre-populated with current contact subject

#### Scenario 7.2: Case handling integration (Procest)
- GIVEN a case handler viewing a case
- THEN a "Kennisbank" button MUST show articles linked to the case's zaaktype
- AND the handler MUST be able to search for additional articles

#### Scenario 7.3: Insert article content into entity notes
- GIVEN an agent finds a relevant article
- WHEN they click "Gebruik antwoord"
- THEN the article summary MUST be inserted into the active entity's notes
- AND a reference to the article MUST be stored for usage tracking

---

### Requirement 8: Article templates and structured content

The system SHOULD support article templates for consistent content creation.

#### Scenario 8.1: FAQ template
- GIVEN a template "FAQ" with sections: Vraag, Antwoord, Meer informatie, Gerelateerde artikelen
- WHEN an editor creates a new FAQ article
- THEN the template sections MUST be pre-populated as structure

#### Scenario 8.2: Procedure template
- GIVEN a template "Procedure" with sections: Doel, Vereisten, Stappen, Bijzonderheden, Contactinformatie
- THEN procedure articles MUST follow this consistent structure

#### Scenario 8.3: Template management
- GIVEN an admin
- THEN templates MUST be creatable, editable, and deletable
- AND each template MUST define required and optional sections

---

### Requirement 9: Article review workflow

Published articles MUST support a review cycle for keeping content current.

#### Scenario 9.1: Review reminder
- GIVEN an article last reviewed 6 months ago and review interval configured to 6 months
- THEN a notification MUST be sent to the article author/editor: "Artikel '[title]' moet worden herzien"

#### Scenario 9.2: Review approval
- GIVEN an article under review
- WHEN the editor confirms the content is still accurate
- THEN the review date MUST be updated and the next review scheduled

#### Scenario 9.3: Bulk review management
- GIVEN 50 articles due for review
- THEN an admin dashboard MUST show: articles overdue for review, grouped by category, assignable to editors

---

### Requirement 10: Multi-language article support

Articles MUST support multiple language versions for serving diverse populations.

#### Scenario 10.1: Article with Dutch and English versions
- GIVEN article "Hoe vraag ik een paspoort aan?" in Dutch
- WHEN an editor adds an English version "How do I apply for a passport?"
- THEN both versions MUST be linked
- AND the system MUST serve the version matching the user's language preference

#### Scenario 10.2: Language gap reporting
- GIVEN 200 articles in Dutch and 50 in English
- THEN a report MUST show which articles lack translations
- AND the report MUST prioritize by article usage (most-used untranslated articles first)

#### Scenario 10.3: Language-aware search
- GIVEN a search in the KCC werkplek
- THEN articles in the agent's language MUST be ranked higher
- AND articles in other languages MUST still appear with a language indicator

---

## Data Model

### Kennisartikel Schema (OpenRegister)

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `title` | string | YES | Article title |
| `body` | string (rich text) | YES | Article content |
| `status` | string | YES | concept, gepubliceerd, gearchiveerd |
| `visibility` | string | YES | intern, openbaar |
| `categories` | array (string) | no | Category references |
| `tags` | array (string) | no | Searchable tags |
| `linkedEntityTypes` | array (string) | no | Zaaktype/product type references |
| `author` | string | YES | Nextcloud user UID |
| `version` | integer | YES | Version number |
| `language` | string | YES | ISO 639-1 language code (default: nl) |
| `linkedVersions` | object | no | Map of language code to article UUID |
| `reviewDate` | date | no | Next scheduled review date |
| `usefulnessScore` | number | no | Aggregate rating score |

---

## Dependencies

- OpenRegister (article storage, search, versioning)
- Pipelinq (KCC werkplek integration)
- Procest (case handler integration)
- Nextcloud Text (rich text editor)
- Nextcloud Full Text Search (optional: advanced search indexing)

## Standards & References

- Schema.org `Article`, `FAQPage`, `HowTo` -- content modeling
- KCS (Knowledge-Centered Service) -- knowledge management methodology
- Nextcloud Text app -- rich text editing
- WCAG AA -- accessibility for knowledge base content
- NORA -- Dutch government knowledge management principles
