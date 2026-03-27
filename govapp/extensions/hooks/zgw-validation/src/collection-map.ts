import type { SchemaMapping } from './types.js';

/**
 * Complete mapping of all 22 ZGW Directus collection names to their JSON Schema file paths.
 *
 * The envOverride field allows municipalities to override the default schema for a collection
 * by setting an environment variable pointing to a custom schema path (relative to schemas/).
 *
 * Schema paths are relative to the `schemas/` directory within the extension.
 */
export const ZGW_COLLECTION_MAPPINGS: SchemaMapping[] = [
	// ZRC (Zaken API) - 7 resources
	{ collection: 'zaken', schemaPath: 'zgw/zrc/1.5.1/zaak.json', envOverride: 'ZRC_ZAAK_SCHEMA' },
	{ collection: 'statussen', schemaPath: 'zgw/zrc/1.5.1/status.json', envOverride: 'ZRC_STATUS_SCHEMA' },
	{ collection: 'resultaten', schemaPath: 'zgw/zrc/1.5.1/resultaat.json', envOverride: 'ZRC_RESULTAAT_SCHEMA' },
	{ collection: 'rollen', schemaPath: 'zgw/zrc/1.5.1/rol.json', envOverride: 'ZRC_ROL_SCHEMA' },
	{ collection: 'zaakobjecten', schemaPath: 'zgw/zrc/1.5.1/zaakobject.json', envOverride: 'ZRC_ZAAKOBJECT_SCHEMA' },
	{ collection: 'zaakinformatieobjecten', schemaPath: 'zgw/zrc/1.5.1/zaakinformatieobject.json', envOverride: 'ZRC_ZAAKINFORMATIEOBJECT_SCHEMA' },
	{ collection: 'zaakeigenschappen', schemaPath: 'zgw/zrc/1.5.1/zaakeigenschap.json', envOverride: 'ZRC_ZAAKEIGENSCHAP_SCHEMA' },

	// ZTC (Catalogi API) - 9 resources
	{ collection: 'catalogussen', schemaPath: 'zgw/ztc/1.3.1/catalogus.json', envOverride: 'ZTC_CATALOGUS_SCHEMA' },
	{ collection: 'zaaktypen', schemaPath: 'zgw/ztc/1.3.1/zaaktype.json', envOverride: 'ZTC_ZAAKTYPE_SCHEMA' },
	{ collection: 'statustypen', schemaPath: 'zgw/ztc/1.3.1/statustype.json', envOverride: 'ZTC_STATUSTYPE_SCHEMA' },
	{ collection: 'resultaattypen', schemaPath: 'zgw/ztc/1.3.1/resultaattype.json', envOverride: 'ZTC_RESULTAATTYPE_SCHEMA' },
	{ collection: 'roltypen', schemaPath: 'zgw/ztc/1.3.1/roltype.json', envOverride: 'ZTC_ROLTYPE_SCHEMA' },
	{ collection: 'eigenschappen', schemaPath: 'zgw/ztc/1.3.1/eigenschap.json', envOverride: 'ZTC_EIGENSCHAP_SCHEMA' },
	{ collection: 'informatieobjecttypen', schemaPath: 'zgw/ztc/1.3.1/informatieobjecttype.json', envOverride: 'ZTC_INFORMATIEOBJECTTYPE_SCHEMA' },
	{ collection: 'besluittypen', schemaPath: 'zgw/ztc/1.3.1/besluittype.json', envOverride: 'ZTC_BESLUITTYPE_SCHEMA' },
	{ collection: 'zaaktypeinformatieobjecttypen', schemaPath: 'zgw/ztc/1.3.1/zaaktypeinformatieobjecttype.json', envOverride: 'ZTC_ZAAKTYPEINFORMATIEOBJECTTYPE_SCHEMA' },

	// DRC (Documenten API) - 3 resources
	{ collection: 'documenten', schemaPath: 'zgw/drc/1.5.0/enkelvoudiginformatieobject.json', envOverride: 'DRC_ENKELVOUDIGINFORMATIEOBJECT_SCHEMA' },
	{ collection: 'gebruiksrechten', schemaPath: 'zgw/drc/1.5.0/gebruiksrechten.json', envOverride: 'DRC_GEBRUIKSRECHTEN_SCHEMA' },
	{ collection: 'objectinformatieobjecten', schemaPath: 'zgw/drc/1.5.0/objectinformatieobject.json', envOverride: 'DRC_OBJECTINFORMATIEOBJECT_SCHEMA' },

	// BRC (Besluiten API) - 2 resources
	{ collection: 'besluiten', schemaPath: 'zgw/brc/1.0.2/besluit.json', envOverride: 'BRC_BESLUIT_SCHEMA' },
	{ collection: 'besluitinformatieobjecten', schemaPath: 'zgw/brc/1.0.2/besluitinformatieobject.json', envOverride: 'BRC_BESLUITINFORMATIEOBJECT_SCHEMA' },

	// NRC (Notificaties API) - 1 resource
	{ collection: 'notificaties', schemaPath: 'zgw/nrc/1.0.0/notificatie.json', envOverride: 'NRC_NOTIFICATIE_SCHEMA' },
];

/**
 * All ZGW collection names that the validation hook should intercept.
 */
export const ZGW_COLLECTIONS: string[] = ZGW_COLLECTION_MAPPINGS.map((m) => m.collection);

/**
 * Resolve the effective schema path for a collection, checking for environment variable overrides.
 *
 * @param collection - Directus collection name
 * @returns The schema path (relative to schemas/), or undefined if not a ZGW collection
 */
export function resolveSchemaPath(collection: string): string | undefined {
	const mapping = ZGW_COLLECTION_MAPPINGS.find((m) => m.collection === collection);
	if (!mapping) return undefined;

	if (mapping.envOverride) {
		const envValue = process.env[mapping.envOverride];
		if (envValue) {
			// Environment variable value should not include .json extension
			return envValue.endsWith('.json') ? envValue : `${envValue}.json`;
		}
	}

	return mapping.schemaPath;
}

/**
 * Get the environment variable override name for a collection.
 */
export function getEnvOverrideName(collection: string): string | undefined {
	const mapping = ZGW_COLLECTION_MAPPINGS.find((m) => m.collection === collection);
	return mapping?.envOverride;
}
