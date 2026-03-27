import type { ErrorObject } from 'ajv';
import type { ValidationError } from './types.js';

/**
 * Dutch error message templates keyed by ajv keyword.
 * These match VNG ZGW standard error conventions.
 */
const DUTCH_MESSAGES: Record<string, (error: ErrorObject) => string> = {
	required: (error) => {
		return 'Dit veld is verplicht.';
	},

	type: (error) => {
		const expected = error.params?.type as string | undefined;
		if (expected) {
			return `Waarde moet van type ${expected} zijn.`;
		}
		return 'Waarde heeft een ongeldig type.';
	},

	format: (error) => {
		const format = error.params?.format as string | undefined;
		switch (format) {
			case 'date':
				return 'Waarde is geen geldige datum (JJJJ-MM-DD).';
			case 'date-time':
				return 'Waarde is geen geldig datum/tijd formaat (ISO 8601).';
			case 'uri':
			case 'uri-reference':
				return 'Waarde is geen geldige URL.';
			case 'email':
				return 'Waarde is geen geldig e-mailadres.';
			case 'uuid':
				return 'Waarde is geen geldige UUID.';
			default:
				return `Waarde voldoet niet aan het verwachte formaat (${format}).`;
		}
	},

	enum: (error) => {
		const allowed = error.params?.allowedValues as unknown[] | undefined;
		if (allowed && allowed.length > 0) {
			return `Ongeldige keuze. Geldige waarden zijn: ${allowed.join(', ')}.`;
		}
		return 'Ongeldige keuze.';
	},

	minLength: (error) => {
		const limit = error.params?.limit as number | undefined;
		if (limit !== undefined) {
			return `Waarde moet minimaal ${limit} teken${limit === 1 ? '' : 's'} bevatten.`;
		}
		return 'Waarde is te kort.';
	},

	maxLength: (error) => {
		const limit = error.params?.limit as number | undefined;
		if (limit !== undefined) {
			return `Waarde mag maximaal ${limit} tekens bevatten.`;
		}
		return 'Waarde is te lang.';
	},

	minimum: (error) => {
		const limit = error.params?.limit as number | undefined;
		if (limit !== undefined) {
			return `Waarde moet minimaal ${limit} zijn.`;
		}
		return 'Waarde is te klein.';
	},

	maximum: (error) => {
		const limit = error.params?.limit as number | undefined;
		if (limit !== undefined) {
			return `Waarde mag maximaal ${limit} zijn.`;
		}
		return 'Waarde is te groot.';
	},

	pattern: (error) => {
		const pattern = error.params?.pattern as string | undefined;
		if (pattern) {
			return `Waarde voldoet niet aan het patroon: ${pattern}.`;
		}
		return 'Waarde voldoet niet aan het verwachte patroon.';
	},

	minItems: (error) => {
		const limit = error.params?.limit as number | undefined;
		if (limit !== undefined) {
			return `Lijst moet minimaal ${limit} item${limit === 1 ? '' : 's'} bevatten.`;
		}
		return 'Lijst bevat te weinig items.';
	},

	maxItems: (error) => {
		const limit = error.params?.limit as number | undefined;
		if (limit !== undefined) {
			return `Lijst mag maximaal ${limit} items bevatten.`;
		}
		return 'Lijst bevat te veel items.';
	},

	additionalProperties: (error) => {
		const additional = error.params?.additionalProperty as string | undefined;
		if (additional) {
			return `Onbekend veld: ${additional}.`;
		}
		return 'Onbekend veld.';
	},

	const: () => 'Waarde komt niet overeen met de verwachte constante.',

	oneOf: () => 'Waarde moet aan precies een van de schema-opties voldoen.',

	anyOf: () => 'Waarde moet aan ten minste een van de schema-opties voldoen.',
};

/**
 * Convert an ajv instancePath (JSON Pointer format) to dot-notation.
 *
 * Examples:
 *   "/verlenging/reden" -> "verlenging.reden"
 *   "/eigenschappen/2/waarde" -> "eigenschappen.2.waarde"
 *   "" -> "" (root level)
 */
function instancePathToDotNotation(instancePath: string): string {
	if (!instancePath) return '';
	// Remove leading "/" and replace remaining "/" with "."
	return instancePath.slice(1).replace(/\//g, '.');
}

/**
 * Resolve the field name for a "required" error.
 *
 * For "required" errors, ajv puts the instancePath on the parent object
 * and the missing property name in params.missingProperty.
 */
function resolveFieldPath(error: ErrorObject): string {
	const basePath = instancePathToDotNotation(error.instancePath);

	if (error.keyword === 'required') {
		const missingProp = error.params?.missingProperty as string | undefined;
		if (missingProp) {
			return basePath ? `${basePath}.${missingProp}` : missingProp;
		}
	}

	return basePath;
}

/**
 * Get a Dutch error message for an ajv error object.
 */
function getDutchMessage(error: ErrorObject): string {
	const messageFactory = DUTCH_MESSAGES[error.keyword];
	if (messageFactory) {
		return messageFactory(error);
	}

	// Fallback: use ajv's default English message if no Dutch translation is available
	return error.message ?? `Validatiefout: ${error.keyword}.`;
}

/**
 * Format an array of ajv error objects into structured ValidationError objects.
 *
 * - Field paths use dot notation (not JSON Pointer)
 * - Rule names are ajv keyword names (camelCase)
 * - Messages are in Dutch
 *
 * @param ajvErrors - Array of ajv error objects (from validator.errors)
 * @returns Formatted validation errors
 */
export function formatErrors(ajvErrors: ErrorObject[] | null | undefined): ValidationError[] {
	if (!ajvErrors || ajvErrors.length === 0) return [];

	return ajvErrors.map((error) => ({
		field: resolveFieldPath(error),
		rule: error.keyword,
		message: getDutchMessage(error),
	}));
}
