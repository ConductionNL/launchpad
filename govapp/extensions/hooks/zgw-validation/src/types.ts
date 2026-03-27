import type { ValidateFunction } from 'ajv';

/**
 * A single validation error with field path, rule name, and Dutch message.
 */
export interface ValidationError {
	/** Dot-notation path to the invalid field (e.g. "verlenging.reden") */
	field: string;
	/** ajv keyword name in camelCase (e.g. "required", "minLength", "format") */
	rule: string;
	/** Human-readable Dutch error message */
	message: string;
}

/**
 * Maps a Directus collection name to its schema file path (relative to schemas/).
 */
export interface SchemaMapping {
	/** Directus collection name (e.g. "zaken") */
	collection: string;
	/** Schema file path relative to schemas/ directory (e.g. "zgw/zrc/1.5.1/zaak.json") */
	schemaPath: string;
	/** Optional environment variable name that overrides the default schema path */
	envOverride?: string;
}

/**
 * Cached compiled validators for a single collection.
 */
export interface ValidatorCacheEntry {
	/** Compiled ajv validator for CREATE mode (enforces required) */
	createValidator: ValidateFunction;
	/** Compiled ajv validator for PATCH/UPDATE mode (required arrays removed) */
	patchValidator: ValidateFunction;
}

/**
 * The full validator cache: collection name -> compiled validators.
 */
export type ValidatorCache = Map<string, ValidatorCacheEntry>;

/**
 * Validation mode determines which compiled validator to use.
 */
export type ValidationMode = 'create' | 'update';

/**
 * Specificatie from an Eigenschap record, used for dynamic ZaakEigenschap validation.
 */
export interface EigenschapSpecificatie {
	/** Format type: "tekst", "getal", "datum", "datum_tijd" */
	formaat: string;
	/** Maximum length for tekst format */
	lengte?: string;
	/** Minimum value for getal format */
	minWaarde?: string;
	/** Maximum value for getal format */
	maxWaarde?: string;
}
