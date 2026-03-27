import { SchemaLoader } from './schema-loader.js';
import { SchemaValidator } from './schema-validator.js';
import { DynamicValidator } from './dynamic-validator.js';
import { ZGW_COLLECTIONS } from './collection-map.js';
import type { ValidationError, ValidationMode } from './types.js';

/**
 * Directus Hook extension for JSON Schema validation of ZGW resources.
 *
 * Registers filter hooks on items.create and items.update for all 22 ZGW collections.
 * Validation runs BEFORE the database write within the same transaction.
 * Invalid data triggers a ForbiddenException that rolls back the transaction.
 */
export default ({ filter }: { filter: (event: string, handler: (...args: unknown[]) => unknown) => void }) => {
	const loader = new SchemaLoader();
	const validator = new SchemaValidator(loader);
	const dynamicValidator = new DynamicValidator();

	// Load and compile all schemas at startup
	try {
		const count = loader.loadAll();
		console.log(`[zgw-validation] Hook initialised: ${count} collections with schema validation`);
	} catch (err) {
		console.error('[zgw-validation] Failed to initialise:', (err as Error).message);
		throw err;
	}

	// Register filter hooks for all ZGW collections
	for (const collection of ZGW_COLLECTIONS) {
		// CREATE validation: full schema including required fields
		filter(`items.create.${collection}`, async (
			input: unknown,
			meta: unknown,
			context: unknown
		) => {
			const data = input as Record<string, unknown>;
			const errors = validator.validate(data, collection, 'create');

			// Dynamic validation for zaakeigenschappen
			if (collection === 'zaakeigenschappen' && data['eigenschap']) {
				const dynamicErrors = await runDynamicValidation(
					data,
					dynamicValidator,
					context as Record<string, unknown>
				);
				errors.push(...dynamicErrors);
			}

			if (errors.length > 0) {
				throwValidationError(errors);
			}

			return input;
		});

		// UPDATE validation: PATCH mode (required fields not enforced)
		filter(`items.update.${collection}`, async (
			input: unknown,
			meta: unknown,
			context: unknown
		) => {
			const data = input as Record<string, unknown>;
			const errors = validator.validate(data, collection, 'update');

			// Dynamic validation for zaakeigenschappen waarde updates
			if (collection === 'zaakeigenschappen' && data['waarde'] !== undefined && data['eigenschap']) {
				const dynamicErrors = await runDynamicValidation(
					data,
					dynamicValidator,
					context as Record<string, unknown>
				);
				errors.push(...dynamicErrors);
			}

			if (errors.length > 0) {
				throwValidationError(errors);
			}

			return input;
		});
	}
};

/**
 * Run dynamic validation for ZaakEigenschap.waarde against the linked Eigenschap's specificatie.
 */
async function runDynamicValidation(
	data: Record<string, unknown>,
	dynamicValidator: DynamicValidator,
	context: Record<string, unknown>
): Promise<ValidationError[]> {
	try {
		const services = context['services'] as Record<string, unknown> | undefined;
		const schema = context['schema'] as Record<string, unknown> | undefined;
		const accountability = context['accountability'] as Record<string, unknown> | null | undefined;

		if (!services || !schema) return [];

		const specificatie = await dynamicValidator.fetchSpecificatie(
			data['eigenschap'] as string | number,
			services as Parameters<DynamicValidator['fetchSpecificatie']>[1],
			schema,
			accountability ?? null
		);

		if (specificatie && data['waarde'] !== undefined) {
			return dynamicValidator.validateWaarde(data['waarde'], specificatie);
		}
	} catch (err) {
		console.warn('[zgw-validation] Dynamic validation failed:', (err as Error).message);
	}

	return [];
}

/**
 * Throw a Directus ForbiddenException with structured validation errors.
 *
 * The error payload is attached via the `extensions` field, which Directus passes through
 * to the error response body. Django then translates this into RFC 9457 format.
 */
function throwValidationError(errors: ValidationError[]): never {
	// Directus expects errors to be thrown as objects with specific shape.
	// The ForbiddenException class is not directly importable in hooks,
	// so we throw a structured error that Directus will treat as a 403.
	const error = new Error('Validation failed') as Error & {
		status: number;
		extensions: { errors: ValidationError[] };
	};
	error.status = 403;
	error.extensions = { errors };
	throw error;
}
