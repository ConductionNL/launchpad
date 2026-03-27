import { describe, it, expect, beforeAll } from 'vitest';
import { resolve } from 'path';
import { SchemaLoader } from '../src/schema-loader.js';

const schemasDir = resolve(__dirname, '..', 'schemas');

describe('SchemaLoader', () => {
	describe('loadAll', () => {
		it('loads all 22 ZGW schemas successfully', () => {
			const loader = new SchemaLoader(schemasDir);
			const count = loader.loadAll();
			expect(count).toBe(22);
		});

		it('populates cache for all ZGW collections', () => {
			const loader = new SchemaLoader(schemasDir);
			loader.loadAll();

			const collections = [
				'zaken', 'statussen', 'resultaten', 'rollen',
				'zaakobjecten', 'zaakinformatieobjecten', 'zaakeigenschappen',
				'catalogussen', 'zaaktypen', 'statustypen', 'resultaattypen',
				'roltypen', 'eigenschappen', 'informatieobjecttypen', 'besluittypen',
				'zaaktypeinformatieobjecttypen', 'documenten', 'gebruiksrechten',
				'objectinformatieobjecten', 'besluiten', 'besluitinformatieobjecten',
				'notificaties',
			];

			for (const collection of collections) {
				expect(loader.hasSchema(collection)).toBe(true);
			}
		});

		it('provides both create and patch validators for each collection', () => {
			const loader = new SchemaLoader(schemasDir);
			loader.loadAll();

			const validators = loader.getValidators('zaken');
			expect(validators).toBeDefined();
			expect(validators!.createValidator).toBeDefined();
			expect(validators!.patchValidator).toBeDefined();
			expect(typeof validators!.createValidator).toBe('function');
			expect(typeof validators!.patchValidator).toBe('function');
		});

		it('returns undefined for non-ZGW collection', () => {
			const loader = new SchemaLoader(schemasDir);
			loader.loadAll();

			expect(loader.getValidators('directus_users')).toBeUndefined();
			expect(loader.getCreateValidator('directus_users')).toBeUndefined();
			expect(loader.getPatchValidator('directus_users')).toBeUndefined();
			expect(loader.hasSchema('directus_users')).toBe(false);
		});
	});

	describe('missing schema detection', () => {
		it('throws when schemas directory does not exist', () => {
			const loader = new SchemaLoader('/nonexistent/path');
			expect(() => loader.loadAll()).toThrow('[zgw-validation]');
		});
	});

	describe('PATCH mode validators strip required', () => {
		it('create validator rejects zaak without required fields', () => {
			const loader = new SchemaLoader(schemasDir);
			loader.loadAll();

			const createValidator = loader.getCreateValidator('zaken')!;
			const valid = createValidator({ omschrijving: 'Test' });
			expect(valid).toBe(false);
			expect(createValidator.errors).toBeDefined();
			expect(createValidator.errors!.length).toBeGreaterThan(0);
		});

		it('patch validator accepts zaak without required fields', () => {
			const loader = new SchemaLoader(schemasDir);
			loader.loadAll();

			const patchValidator = loader.getPatchValidator('zaken')!;
			const valid = patchValidator({ omschrijving: 'Test' });
			expect(valid).toBe(true);
		});

		it('patch validator still enforces type constraints', () => {
			const loader = new SchemaLoader(schemasDir);
			loader.loadAll();

			const patchValidator = loader.getPatchValidator('zaken')!;
			const valid = patchValidator({ startdatum: 12345 });
			expect(valid).toBe(false);
		});
	});
});
