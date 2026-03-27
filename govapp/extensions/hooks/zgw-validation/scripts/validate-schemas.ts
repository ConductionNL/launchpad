/**
 * CI script: meta-validate all JSON Schema files in schemas/.
 *
 * Checks:
 * 1. Every .json file in schemas/zgw/ and schemas/common/ is valid JSON Schema Draft 7
 * 2. All $ref pointers resolve to existing local files (no broken refs)
 * 3. Every collection in the mapping has a corresponding schema file on disk
 * 4. No HTTP $ref URIs exist in any schema
 *
 * Exit code 0 = all checks pass, non-zero = failure.
 */

import Ajv from 'ajv';
import addFormats from 'ajv-formats';
import { readFileSync, existsSync, readdirSync, statSync } from 'fs';
import { resolve, dirname, join, relative } from 'path';
import { ZGW_COLLECTION_MAPPINGS, resolveSchemaPath } from '../src/collection-map.js';

const schemasDir = resolve(import.meta.dirname ?? __dirname, '..', 'schemas');
let errors: string[] = [];
let schemaCount = 0;

/**
 * Recursively find all .json files in a directory.
 */
function findJsonFiles(dir: string): string[] {
	const files: string[] = [];
	if (!existsSync(dir)) return files;

	for (const entry of readdirSync(dir, { withFileTypes: true })) {
		const fullPath = join(dir, entry.name);
		if (entry.isDirectory()) {
			files.push(...findJsonFiles(fullPath));
		} else if (entry.name.endsWith('.json')) {
			files.push(fullPath);
		}
	}
	return files;
}

/**
 * Check that a schema object has no HTTP $ref URIs, recursively.
 */
function checkNoHttpRefs(obj: unknown, filePath: string): void {
	if (obj === null || typeof obj !== 'object') return;
	if (Array.isArray(obj)) {
		for (const item of obj) checkNoHttpRefs(item, filePath);
		return;
	}

	const record = obj as Record<string, unknown>;
	if (typeof record['$ref'] === 'string') {
		const ref = record['$ref'];
		if (ref.startsWith('http://') || ref.startsWith('https://')) {
			errors.push(`HTTP $ref found: "${ref}" in ${relative(schemasDir, filePath)}`);
		}
	}

	for (const value of Object.values(record)) {
		checkNoHttpRefs(value, filePath);
	}
}

/**
 * Check that all local $ref paths resolve to existing files.
 */
function checkLocalRefs(obj: unknown, filePath: string): void {
	if (obj === null || typeof obj !== 'object') return;
	if (Array.isArray(obj)) {
		for (const item of obj) checkLocalRefs(item, filePath);
		return;
	}

	const record = obj as Record<string, unknown>;
	if (typeof record['$ref'] === 'string') {
		const ref = record['$ref'];
		if (!ref.startsWith('#') && !ref.startsWith('http://') && !ref.startsWith('https://')) {
			const refPath = resolve(dirname(filePath), ref);
			if (!existsSync(refPath)) {
				errors.push(
					`Broken $ref: "${ref}" in ${relative(schemasDir, filePath)} -> ${refPath} does not exist`
				);
			}
		}
	}

	for (const value of Object.values(record)) {
		checkLocalRefs(value, filePath);
	}
}

console.log('Validating JSON Schema files...\n');

// 1. Validate all .json files are valid JSON Schema Draft 7
const zgwFiles = findJsonFiles(join(schemasDir, 'zgw'));
const commonFiles = findJsonFiles(join(schemasDir, 'common'));
const customFiles = findJsonFiles(join(schemasDir, 'custom'));
const allFiles = [...zgwFiles, ...commonFiles, ...customFiles];

const ajv = new Ajv({ strict: false, allErrors: true });
addFormats(ajv);

for (const filePath of allFiles) {
	const relPath = relative(schemasDir, filePath);
	try {
		const content = readFileSync(filePath, 'utf-8');
		const schema = JSON.parse(content) as Record<string, unknown>;

		// Check $schema property
		if (schema['$schema'] !== 'http://json-schema.org/draft-07/schema#') {
			errors.push(`Missing or wrong $schema in ${relPath} (expected Draft 7)`);
		}

		// Check type property
		if (schema['type'] !== 'object') {
			errors.push(`Missing or wrong top-level type in ${relPath} (expected "object")`);
		}

		// Try to compile
		try {
			ajv.compile(schema);
		} catch (compileErr) {
			errors.push(`Schema compilation failed for ${relPath}: ${(compileErr as Error).message}`);
		}

		// Check for HTTP refs
		checkNoHttpRefs(schema, filePath);

		// Check local ref resolution
		checkLocalRefs(schema, filePath);

		schemaCount++;
	} catch (parseErr) {
		errors.push(`Invalid JSON in ${relPath}: ${(parseErr as Error).message}`);
	}
}

// 2. Check collection mapping completeness
console.log('Checking collection mapping completeness...\n');
for (const mapping of ZGW_COLLECTION_MAPPINGS) {
	const schemaPath = resolve(schemasDir, mapping.schemaPath);
	if (!existsSync(schemaPath)) {
		errors.push(
			`Collection "${mapping.collection}" mapped to ${mapping.schemaPath} but file does not exist`
		);
	}
}

// Report
console.log(`Validated ${schemaCount} schema files.`);
console.log(`Checked ${ZGW_COLLECTION_MAPPINGS.length} collection mappings.\n`);

if (errors.length > 0) {
	console.error(`FAILED: ${errors.length} error(s) found:\n`);
	for (const err of errors) {
		console.error(`  - ${err}`);
	}
	process.exit(1);
} else {
	console.log('All checks passed.');
	process.exit(0);
}
