# ADR-006: OpenRegister Schema Standards

**Status:** accepted
**Scope:** company-wide
**Applies to:** specs, design
**Last updated:** 2026-03-19

## Context

OpenRegister schemas define the data models for all domain objects across Conduction apps. Without consistent schema conventions, apps create incompatible data structures that cannot interoperate. International standards (schema.org, vCard) provide well-documented, widely-understood vocabularies that reduce ambiguity and enable external integration.

## Decision

### Standard Vocabularies
- Schemas MUST use schema.org types and properties as the primary vocabulary where applicable (e.g., `schema:Person`, `schema:Organization`, `schema:Event`).
- Contact-related schemas MUST align with vCard properties (e.g., `fn`, `email`, `tel`, `adr`).
- Dutch government-specific fields SHOULD use a mapping layer that translates between international standards and Dutch API specifications (VNG Klantinteracties, ZGW, etc.).
- Apps MUST NOT invent custom property names when a schema.org equivalent exists.

### Schema Definition
- Each schema MUST have a unique, descriptive name in PascalCase (e.g., `Contact`, `PipelineStage`, `Publication`).
- Properties MUST have explicit types (`string`, `integer`, `boolean`, `datetime`, `array`, `object`, `file`).
- Required properties MUST be marked as such in the schema definition.
- Schemas MUST include a `description` field explaining the entity's purpose.

### Relations
- Cross-entity references MUST use OpenRegister's relation mechanism (register + schema + object ID).
- Apps MUST NOT store foreign keys or embed full objects — use relations instead.
- Bidirectional relations SHOULD be declared on both schemas.

### Versioning
- Schema changes that remove or rename properties are BREAKING and MUST be handled via migration in repair steps.
- Adding optional properties is non-breaking and does not require migration.
- Schema version SHOULD be tracked in the schema's metadata.

## Consequences

- Spec authors MUST define data models using schema.org vocabulary where applicable.
- Design documents MUST include schema definitions with property types, required flags, and relations.
- The "international first, Dutch mapping layer" principle means specs describe data in international terms with explicit notes on Dutch API mapping.

## Exceptions

- App-specific workflow states (e.g., pipeline stages, process statuses) have no schema.org equivalent and MAY use custom vocabularies.
- File/document metadata MAY extend beyond schema.org's `DigitalDocument` when government-specific metadata is required.
