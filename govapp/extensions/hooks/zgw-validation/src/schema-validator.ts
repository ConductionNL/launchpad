import type { ValidateFunction } from 'ajv';
import type { ValidationError, ValidationMode } from './types.js';
import { formatErrors } from './error-formatter.js';
import { SchemaLoader } from './schema-loader.js';

/**
 * SchemaValidator validates incoming data against pre-compiled JSON Schemas.
 *
 * It supports two modes:
 * - CREATE: Full validation including required fields
 * - UPDATE (PATCH): Validates only present fields; required constraints are not enforced
 *
 * All errors are collected in a single pass (allErrors mode).
 */
export class SchemaValidator {
	private loader: SchemaLoader;

	constructor(loader: SchemaLoader) {
		this.loader = loader;
	}

	/**
	 * Validate data against the schema for a given collection.
	 *
	 * @param data - The input data to validate
	 * @param collection - Directus collection name (e.g. "zaken")
	 * @param mode - "create" for full validation, "update" for PATCH mode
	 * @returns Array of validation errors (empty if valid)
	 */
	validate(data: Record<string, unknown>, collection: string, mode: ValidationMode): ValidationError[] {
		const validators = this.loader.getValidators(collection);
		if (!validators) {
			// Not a ZGW collection - no validation needed
			return [];
		}

		const validator: ValidateFunction = mode === 'create'
			? validators.createValidator
			: validators.patchValidator;

		const valid = validator(data);

		if (valid) {
			return [];
		}

		return formatErrors(validator.errors);
	}

	/**
	 * Check if a collection has a registered schema.
	 */
	hasSchema(collection: string): boolean {
		return this.loader.hasSchema(collection);
	}
}
