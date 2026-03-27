import { describe, it, expect, beforeAll } from 'vitest';
import { resolve } from 'path';
import { SchemaLoader } from '../src/schema-loader.js';
import { SchemaValidator } from '../src/schema-validator.js';

const schemasDir = resolve(__dirname, '..', 'schemas');

describe('SchemaValidator', () => {
	let loader: SchemaLoader;
	let validator: SchemaValidator;

	beforeAll(() => {
		loader = new SchemaLoader(schemasDir);
		loader.loadAll();
		validator = new SchemaValidator(loader);
	});

	describe('CREATE mode', () => {
		it('validates a valid zaak with no errors', () => {
			const validZaak = {
				bronorganisatie: '123456789',
				zaaktype: 'https://example.com/api/v1/zaaktypen/abc',
				verantwoordelijkeOrganisatie: '987654321',
				startdatum: '2024-01-15',
			};

			const errors = validator.validate(validZaak, 'zaken', 'create');
			expect(errors).toEqual([]);
		});

		it('returns errors for missing required fields', () => {
			const invalidZaak = {
				omschrijving: 'Test zaak',
			};

			const errors = validator.validate(invalidZaak, 'zaken', 'create');
			expect(errors.length).toBeGreaterThanOrEqual(4);

			const requiredErrors = errors.filter((e) => e.rule === 'required');
			const requiredFields = requiredErrors.map((e) => e.field);
			expect(requiredFields).toContain('bronorganisatie');
			expect(requiredFields).toContain('zaaktype');
			expect(requiredFields).toContain('verantwoordelijkeOrganisatie');
			expect(requiredFields).toContain('startdatum');
		});

		it('returns multiple errors in a single validation pass', () => {
			const invalidZaak = {
				bronorganisatie: 'not-a-rsin', // pattern error
				zaaktype: 'not-a-url', // format error
				verantwoordelijkeOrganisatie: '123456789',
				startdatum: 'not-a-date', // format error
			};

			const errors = validator.validate(invalidZaak, 'zaken', 'create');
			expect(errors.length).toBeGreaterThanOrEqual(3);

			const fieldNames = errors.map((e) => e.field);
			expect(fieldNames).toContain('bronorganisatie');
			expect(fieldNames).toContain('zaaktype');
			expect(fieldNames).toContain('startdatum');
		});

		it('validates a valid status with no errors', () => {
			const validStatus = {
				zaak: 'https://example.com/api/v1/zaken/123',
				statustype: 'https://example.com/api/v1/statustypen/456',
				datumStatusGezet: '2024-01-15T10:30:00Z',
			};

			const errors = validator.validate(validStatus, 'statussen', 'create');
			expect(errors).toEqual([]);
		});

		it('validates enum values', () => {
			const zaakWithBadEnum = {
				bronorganisatie: '123456789',
				zaaktype: 'https://example.com/api/v1/zaaktypen/abc',
				verantwoordelijkeOrganisatie: '987654321',
				startdatum: '2024-01-15',
				vertrouwelijkheidaanduiding: 'onbekend', // not in enum
			};

			const errors = validator.validate(zaakWithBadEnum, 'zaken', 'create');
			expect(errors.length).toBeGreaterThanOrEqual(1);
			const enumError = errors.find((e) => e.field === 'vertrouwelijkheidaanduiding');
			expect(enumError).toBeDefined();
			expect(enumError!.rule).toBe('enum');
		});

		it('catches invalid date format', () => {
			const zaakWithBadDate = {
				bronorganisatie: '123456789',
				zaaktype: 'https://example.com/api/v1/zaaktypen/abc',
				verantwoordelijkeOrganisatie: '987654321',
				startdatum: '2024-13-45', // invalid date
			};

			const errors = validator.validate(zaakWithBadDate, 'zaken', 'create');
			expect(errors.length).toBeGreaterThanOrEqual(1);
			const dateError = errors.find((e) => e.field === 'startdatum');
			expect(dateError).toBeDefined();
			expect(dateError!.rule).toBe('format');
		});

		it('catches invalid URI format', () => {
			const zaakWithBadUri = {
				bronorganisatie: '123456789',
				zaaktype: 'not-a-url',
				verantwoordelijkeOrganisatie: '987654321',
				startdatum: '2024-01-15',
			};

			const errors = validator.validate(zaakWithBadUri, 'zaken', 'create');
			const uriError = errors.find((e) => e.field === 'zaaktype');
			expect(uriError).toBeDefined();
			expect(uriError!.rule).toBe('format');
		});

		it('validates nested object errors (verlenging)', () => {
			const zaakWithBadVerlenging = {
				bronorganisatie: '123456789',
				zaaktype: 'https://example.com/api/v1/zaaktypen/abc',
				verantwoordelijkeOrganisatie: '987654321',
				startdatum: '2024-01-15',
				verlenging: {
					// missing required: reden, duur
				},
			};

			const errors = validator.validate(zaakWithBadVerlenging, 'zaken', 'create');
			const verlengingErrors = errors.filter((e) => e.field.startsWith('verlenging'));
			expect(verlengingErrors.length).toBeGreaterThanOrEqual(2);

			const fields = verlengingErrors.map((e) => e.field);
			expect(fields).toContain('verlenging.reden');
			expect(fields).toContain('verlenging.duur');
		});

		it('validates a valid besluit with no errors', () => {
			const validBesluit = {
				verantwoordelijkeOrganisatie: '123456789',
				besluittype: 'https://example.com/api/v1/besluittypen/abc',
				datum: '2024-06-01',
				ingangsdatum: '2024-06-15',
			};

			const errors = validator.validate(validBesluit, 'besluiten', 'create');
			expect(errors).toEqual([]);
		});
	});

	describe('UPDATE (PATCH) mode', () => {
		it('allows partial payload without required field errors', () => {
			const partialUpdate = {
				omschrijving: 'Nieuwe omschrijving',
			};

			const errors = validator.validate(partialUpdate, 'zaken', 'update');
			expect(errors).toEqual([]);
		});

		it('still enforces type constraints in PATCH mode', () => {
			const badTypeUpdate = {
				startdatum: 12345, // should be string
			};

			const errors = validator.validate(badTypeUpdate, 'zaken', 'update');
			expect(errors.length).toBeGreaterThanOrEqual(1);
			const typeError = errors.find((e) => e.field === 'startdatum');
			expect(typeError).toBeDefined();
			expect(typeError!.rule).toBe('type');
		});

		it('still enforces format constraints in PATCH mode', () => {
			const badFormatUpdate = {
				startdatum: 'not-a-date',
			};

			const errors = validator.validate(badFormatUpdate, 'zaken', 'update');
			expect(errors.length).toBeGreaterThanOrEqual(1);
			const formatError = errors.find((e) => e.field === 'startdatum');
			expect(formatError).toBeDefined();
			expect(formatError!.rule).toBe('format');
		});

		it('still enforces enum constraints in PATCH mode', () => {
			const badEnumUpdate = {
				vertrouwelijkheidaanduiding: 'onbekend',
			};

			const errors = validator.validate(badEnumUpdate, 'zaken', 'update');
			expect(errors.length).toBeGreaterThanOrEqual(1);
		});

		it('validates a valid partial update with no errors', () => {
			const validUpdate = {
				omschrijving: 'Updated',
				toelichting: 'Some explanation',
			};

			const errors = validator.validate(validUpdate, 'zaken', 'update');
			expect(errors).toEqual([]);
		});
	});

	describe('non-ZGW collections', () => {
		it('returns empty errors for unknown collections', () => {
			const data = { anything: 'goes' };
			const errors = validator.validate(data, 'directus_users', 'create');
			expect(errors).toEqual([]);
		});

		it('reports hasSchema correctly', () => {
			expect(validator.hasSchema('zaken')).toBe(true);
			expect(validator.hasSchema('statussen')).toBe(true);
			expect(validator.hasSchema('directus_users')).toBe(false);
		});
	});
});
