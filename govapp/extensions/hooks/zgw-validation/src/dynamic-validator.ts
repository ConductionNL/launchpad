import type { EigenschapSpecificatie, ValidationError } from './types.js';

/**
 * DynamicValidator handles validation for ZaakEigenschap.waarde based on
 * the linked Eigenschap's specificatie.
 *
 * This is the only field in ZGW where validation rules depend on a related record,
 * so it's handled as a targeted special case rather than a generic system.
 */
export class DynamicValidator {
	/**
	 * Validate a ZaakEigenschap's waarde field against the linked Eigenschap's specificatie.
	 *
	 * @param waarde - The value to validate
	 * @param specificatie - The Eigenschap specificatie that defines the constraints
	 * @returns Array of validation errors (empty if valid)
	 */
	validateWaarde(waarde: unknown, specificatie: EigenschapSpecificatie): ValidationError[] {
		const errors: ValidationError[] = [];

		if (waarde === null || waarde === undefined) {
			// waarde is required - but that's handled by the static schema validation
			return errors;
		}

		const stringValue = String(waarde);

		switch (specificatie.formaat) {
			case 'tekst':
				errors.push(...this.validateTekst(stringValue, specificatie));
				break;

			case 'getal':
				errors.push(...this.validateGetal(stringValue, specificatie));
				break;

			case 'datum':
				errors.push(...this.validateDatum(stringValue));
				break;

			case 'datum_tijd':
				errors.push(...this.validateDatumTijd(stringValue));
				break;

			default:
				// Unknown format - no additional dynamic validation
				break;
		}

		return errors;
	}

	/**
	 * Validate a text value against length constraints.
	 */
	private validateTekst(value: string, specificatie: EigenschapSpecificatie): ValidationError[] {
		const errors: ValidationError[] = [];

		if (specificatie.lengte) {
			const maxLength = parseInt(specificatie.lengte, 10);
			if (!isNaN(maxLength) && value.length > maxLength) {
				errors.push({
					field: 'waarde',
					rule: 'maxLength',
					message: `Waarde mag maximaal ${maxLength} tekens bevatten.`,
				});
			}
		}

		return errors;
	}

	/**
	 * Validate a numeric value, optionally against min/max constraints.
	 */
	private validateGetal(value: string, specificatie: EigenschapSpecificatie): ValidationError[] {
		const errors: ValidationError[] = [];
		const numValue = Number(value);

		if (isNaN(numValue)) {
			errors.push({
				field: 'waarde',
				rule: 'type',
				message: 'Waarde moet een geldig getal zijn.',
			});
			return errors;
		}

		if (specificatie.minWaarde) {
			const min = Number(specificatie.minWaarde);
			if (!isNaN(min) && numValue < min) {
				errors.push({
					field: 'waarde',
					rule: 'minimum',
					message: `Waarde moet minimaal ${min} zijn.`,
				});
			}
		}

		if (specificatie.maxWaarde) {
			const max = Number(specificatie.maxWaarde);
			if (!isNaN(max) && numValue > max) {
				errors.push({
					field: 'waarde',
					rule: 'maximum',
					message: `Waarde mag maximaal ${max} zijn.`,
				});
			}
		}

		return errors;
	}

	/**
	 * Validate a date string in YYYY-MM-DD format.
	 */
	private validateDatum(value: string): ValidationError[] {
		const errors: ValidationError[] = [];
		const dateRegex = /^\d{4}-\d{2}-\d{2}$/;

		if (!dateRegex.test(value)) {
			errors.push({
				field: 'waarde',
				rule: 'format',
				message: 'Waarde is geen geldige datum (JJJJ-MM-DD).',
			});
			return errors;
		}

		// Also check the date is actually valid (e.g., not 2024-02-30)
		const date = new Date(value);
		if (isNaN(date.getTime())) {
			errors.push({
				field: 'waarde',
				rule: 'format',
				message: 'Waarde is geen geldige datum (JJJJ-MM-DD).',
			});
		}

		return errors;
	}

	/**
	 * Validate a datetime string in ISO 8601 format.
	 */
	private validateDatumTijd(value: string): ValidationError[] {
		const errors: ValidationError[] = [];
		const date = new Date(value);

		if (isNaN(date.getTime()) || !value.includes('T')) {
			errors.push({
				field: 'waarde',
				rule: 'format',
				message: 'Waarde is geen geldig datum/tijd formaat (ISO 8601).',
			});
		}

		return errors;
	}

	/**
	 * Fetch the Eigenschap specificatie from Directus via ItemsService.
	 *
	 * @param eigenschapId - The ID of the linked Eigenschap
	 * @param services - Directus services context (from hook context)
	 * @returns The specificatie object, or null if not found
	 */
	async fetchSpecificatie(
		eigenschapId: string | number,
		services: {
			ItemsService: new (collection: string, options: Record<string, unknown>) => {
				readOne: (id: string | number) => Promise<Record<string, unknown>>;
			};
		},
		schema: Record<string, unknown>,
		accountability: Record<string, unknown> | null
	): Promise<EigenschapSpecificatie | null> {
		try {
			const eigenschapService = new services.ItemsService('eigenschappen', {
				schema,
				accountability: accountability ?? { admin: true },
			});

			const eigenschap = await eigenschapService.readOne(eigenschapId);

			if (!eigenschap || !eigenschap['specificatie']) {
				return null;
			}

			const spec = eigenschap['specificatie'] as Record<string, unknown>;
			return {
				formaat: (spec['formaat'] as string) ?? 'tekst',
				lengte: spec['lengte'] as string | undefined,
				minWaarde: spec['min_waarde'] as string | undefined,
				maxWaarde: spec['max_waarde'] as string | undefined,
			};
		} catch {
			// If we can't fetch the Eigenschap, skip dynamic validation
			console.warn(
				`[zgw-validation] Could not fetch Eigenschap ${eigenschapId} for dynamic validation`
			);
			return null;
		}
	}
}
