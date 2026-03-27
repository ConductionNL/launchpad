import Ajv from 'ajv';
import addFormats from 'ajv-formats';
import { readFileSync, existsSync, readdirSync, statSync } from 'fs';
import { resolve, dirname, join } from 'path';
import { fileURLToPath } from 'url';
import type { ValidateFunction } from 'ajv';
import type { ValidatorCache, ValidatorCacheEntry } from './types.js';
import { ZGW_COLLECTION_MAPPINGS, resolveSchemaPath, getEnvOverrideName } from './collection-map.js';

/**
 * SchemaLoader reads JSON Schema files from disk at startup, compiles them with ajv,
 * and caches compiled validator functions for both CREATE and PATCH modes.
 *
 * PATCH mode validators are cloned schemas with all `required` arrays removed,
 * allowing partial payloads without required-field errors.
 */
export class SchemaLoader {
	private cache: ValidatorCache = new Map();
	private ajv: Ajv;
	private schemasDir: string;

	constructor(schemasDir?: string) {
		// Default schemas directory is relative to this file's location
		if (schemasDir) {
			this.schemasDir = schemasDir;
		} else {
			// In a built extension, schemas/ is a sibling of dist/
			const currentDir = typeof __dirname !== 'undefined'
				? __dirname
				: dirname(fileURLToPath(import.meta.url));
			this.schemasDir = resolve(currentDir, '..', 'schemas');
		}

		this.ajv = new Ajv({
			allErrors: true,
			strict: false,
			loadSchema: undefined,
		});
		addFormats(this.ajv);
	}

	/**
	 * Load all schemas from disk, compile them, and populate the cache.
	 * Throws if any mapped schema file is missing or contains invalid JSON.
	 *
	 * @returns The number of schemas loaded
	 */
	loadAll(): number {
		const startTime = Date.now();
		let count = 0;

		for (const mapping of ZGW_COLLECTION_MAPPINGS) {
			const schemaPath = resolveSchemaPath(mapping.collection);
			if (!schemaPath) {
				throw new Error(
					`[zgw-validation] No schema path resolved for collection "${mapping.collection}"`
				);
			}

			const fullPath = resolve(this.schemasDir, schemaPath);

			if (!existsSync(fullPath)) {
				const envName = getEnvOverrideName(mapping.collection);
				const envHint = envName && process.env[envName]
					? ` (overridden by ${envName}=${process.env[envName]})`
					: '';
				throw new Error(
					`[zgw-validation] Schema file missing for collection "${mapping.collection}": ${fullPath}${envHint}`
				);
			}

			const rawSchema = this.loadSchemaFile(fullPath);
			this.rejectHttpRefs(rawSchema, fullPath);

			// Compile CREATE validator (original schema with required enforced)
			const createValidator = this.compileSchema(rawSchema, fullPath);

			// Compile PATCH validator (schema with all required arrays stripped)
			// Remove $id to avoid duplicate key conflict in ajv's internal registry
			const patchSchema = this.stripRequired(structuredClone(rawSchema));
			delete patchSchema['$id'];
			const patchValidator = this.compileSchema(patchSchema, fullPath);

			this.cache.set(mapping.collection, { createValidator, patchValidator });
			count++;
		}

		const elapsed = Date.now() - startTime;
		console.log(`[zgw-validation] Loaded and compiled ${count} schemas in ${elapsed}ms`);

		return count;
	}

	/**
	 * Get the cached validator entry for a collection.
	 *
	 * @param collection - Directus collection name
	 * @returns The cached validators, or undefined if not a ZGW collection
	 */
	getValidators(collection: string): ValidatorCacheEntry | undefined {
		return this.cache.get(collection);
	}

	/**
	 * Get the CREATE-mode validator for a collection.
	 */
	getCreateValidator(collection: string): ValidateFunction | undefined {
		return this.cache.get(collection)?.createValidator;
	}

	/**
	 * Get the PATCH/UPDATE-mode validator for a collection.
	 */
	getPatchValidator(collection: string): ValidateFunction | undefined {
		return this.cache.get(collection)?.patchValidator;
	}

	/**
	 * Check if a collection has a registered schema.
	 */
	hasSchema(collection: string): boolean {
		return this.cache.has(collection);
	}

	/**
	 * Read and parse a JSON Schema file from disk.
	 * Also resolves local $ref paths by pre-loading referenced schemas into ajv.
	 */
	private loadSchemaFile(filePath: string): Record<string, unknown> {
		const content = readFileSync(filePath, 'utf-8');
		let schema: Record<string, unknown>;

		try {
			schema = JSON.parse(content) as Record<string, unknown>;
		} catch (err) {
			throw new Error(
				`[zgw-validation] Invalid JSON in schema file: ${filePath} - ${(err as Error).message}`
			);
		}

		// Pre-load any local $ref targets so ajv can resolve them
		this.resolveLocalRefs(schema, dirname(filePath));

		return schema;
	}

	/**
	 * Recursively find all $ref values in a schema and pre-load local file references into ajv.
	 */
	private resolveLocalRefs(obj: unknown, baseDir: string, visited: Set<string> = new Set()): void {
		if (obj === null || typeof obj !== 'object') return;

		if (Array.isArray(obj)) {
			for (const item of obj) {
				this.resolveLocalRefs(item, baseDir, visited);
			}
			return;
		}

		const record = obj as Record<string, unknown>;

		if (typeof record['$ref'] === 'string') {
			const ref = record['$ref'];

			// Only process local file references (not internal #/definitions/... refs)
			if (!ref.startsWith('#') && !ref.startsWith('http://') && !ref.startsWith('https://')) {
				const refPath = resolve(baseDir, ref);

				if (!visited.has(refPath) && existsSync(refPath)) {
					visited.add(refPath);
					const refContent = readFileSync(refPath, 'utf-8');
					const refSchema = JSON.parse(refContent) as Record<string, unknown>;

					// Recursively resolve refs in the referenced schema
					this.resolveLocalRefs(refSchema, dirname(refPath), visited);

					// Add to ajv if it has an $id
					const schemaId = refSchema['$id'] as string | undefined;
					if (schemaId && !this.ajv.getSchema(schemaId)) {
						this.ajv.addSchema(refSchema);
					}
				}
			}
		}

		// Recurse into all object properties
		for (const value of Object.values(record)) {
			this.resolveLocalRefs(value, baseDir, visited);
		}
	}

	/**
	 * Reject any HTTP/HTTPS $ref URIs found in a schema.
	 * Throws at load time (not validation time) as required by the spec.
	 */
	private rejectHttpRefs(obj: unknown, filePath: string): void {
		if (obj === null || typeof obj !== 'object') return;

		if (Array.isArray(obj)) {
			for (const item of obj) {
				this.rejectHttpRefs(item, filePath);
			}
			return;
		}

		const record = obj as Record<string, unknown>;

		if (typeof record['$ref'] === 'string') {
			const ref = record['$ref'];
			if (ref.startsWith('http://') || ref.startsWith('https://')) {
				throw new Error(
					`[zgw-validation] HTTP $ref is not allowed: "${ref}" found in ${filePath}. ` +
					'Only local file references are supported.'
				);
			}
		}

		for (const value of Object.values(record)) {
			this.rejectHttpRefs(value, filePath);
		}
	}

	/**
	 * Compile a JSON Schema object into an ajv ValidateFunction.
	 */
	private compileSchema(schema: Record<string, unknown>, filePath: string): ValidateFunction {
		try {
			return this.ajv.compile(schema);
		} catch (err) {
			throw new Error(
				`[zgw-validation] Failed to compile schema ${filePath}: ${(err as Error).message}`
			);
		}
	}

	/**
	 * Recursively remove all `required` arrays from a schema object.
	 * Used to create PATCH-mode validators that don't enforce required fields.
	 *
	 * This handles nested objects (properties with sub-objects that have their own required).
	 */
	private stripRequired(schema: Record<string, unknown>): Record<string, unknown> {
		delete schema['required'];

		// Recurse into properties
		if (schema['properties'] && typeof schema['properties'] === 'object') {
			for (const prop of Object.values(schema['properties'] as Record<string, unknown>)) {
				if (prop && typeof prop === 'object') {
					this.stripRequired(prop as Record<string, unknown>);
				}
			}
		}

		// Recurse into items (array schemas)
		if (schema['items'] && typeof schema['items'] === 'object') {
			this.stripRequired(schema['items'] as Record<string, unknown>);
		}

		// Recurse into allOf, anyOf, oneOf
		for (const keyword of ['allOf', 'anyOf', 'oneOf']) {
			if (Array.isArray(schema[keyword])) {
				for (const subSchema of schema[keyword] as Record<string, unknown>[]) {
					if (subSchema && typeof subSchema === 'object') {
						this.stripRequired(subSchema);
					}
				}
			}
		}

		// Recurse into definitions
		if (schema['definitions'] && typeof schema['definitions'] === 'object') {
			for (const def of Object.values(schema['definitions'] as Record<string, unknown>)) {
				if (def && typeof def === 'object') {
					this.stripRequired(def as Record<string, unknown>);
				}
			}
		}

		return schema;
	}
}
