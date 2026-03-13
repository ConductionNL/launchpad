# OpenZaak Documentation and Specification Links

## Official Documentation

| Resource | URL |
|----------|-----|
| Open Zaak ReadTheDocs | https://open-zaak.readthedocs.io/en/latest/ |
| Open Zaak GitHub | https://github.com/open-zaak/open-zaak |
| Open Zaak Docker Hub | https://hub.docker.com/r/openzaak/open-zaak |
| OpenZaak.org (product site) | https://openzaak.org/ |
| OpenGem product page | https://opengem.nl/producten/open-zaak/ |

## VNG ZGW API Standards

| Standard | Documentation | OpenAPI YAML |
|----------|--------------|-------------|
| Zaken API 1.5.1 | https://vng-realisatie.github.io/gemma-zaken/standaard/zaken/ | https://github.com/VNG-Realisatie/gemma-zaken/tree/master/api-specificatie/zrc |
| Documenten API 1.5.0 | https://vng-realisatie.github.io/gemma-zaken/standaard/documenten/ | https://github.com/VNG-Realisatie/gemma-zaken/tree/master/api-specificatie/drc |
| Catalogi API 1.3.1 | https://vng-realisatie.github.io/gemma-zaken/standaard/catalogi/ | https://github.com/VNG-Realisatie/gemma-zaken/tree/master/api-specificatie/ztc |
| Besluiten API 1.0.2 | https://vng-realisatie.github.io/gemma-zaken/standaard/besluiten/ | https://github.com/VNG-Realisatie/gemma-zaken/tree/master/api-specificatie/brc |
| Autorisaties API 1.0.0 | https://vng-realisatie.github.io/gemma-zaken/standaard/autorisaties/ | https://github.com/VNG-Realisatie/gemma-zaken/tree/master/api-specificatie/ac |
| Notificaties API 1.0.0 | https://vng-realisatie.github.io/gemma-zaken/standaard/notificaties/ | https://github.com/VNG-Realisatie/gemma-zaken/tree/master/api-specificatie/nrc |

## VNG Reference Implementations (Live)

| API | Reference URL |
|-----|--------------|
| Catalogi API | https://catalogi-api.vng.cloud |
| Autorisaties API | https://autorisaties-api.vng.cloud |
| Notificaties API | https://notificaties-api.vng.cloud |

## Open Zaak ReDoc (per deployment)

Available at each Open Zaak instance:
- Zaken API: `https://<domain>/zaken/api/v1/schema/`
- Documenten API: `https://<domain>/documenten/api/v1/schema/`
- Catalogi API: `https://<domain>/catalogi/api/v1/schema/`
- Besluiten API: `https://<domain>/besluiten/api/v1/schema/`
- Autorisaties API: `https://<domain>/autorisaties/api/v1/schema/`

## OpenAPI Specification JSON Endpoints

Per the VNG standard, each API exposes its OpenAPI spec at:
- `{API root URL}/openapi.json`

Example: `https://openzaak.example.com/zaken/api/v1/openapi.json`

## GitHub Repositories

| Repository | URL | Description |
|-----------|-----|-------------|
| open-zaak/open-zaak | https://github.com/open-zaak/open-zaak | Main codebase |
| open-zaak/open-notificaties | https://github.com/open-zaak/open-notificaties | Notifications component |
| open-zaak/open-zaak-market-consultation | https://github.com/open-zaak/open-zaak-market-consultation | Market consultation docs |
| VNG-Realisatie/gemma-zaken | https://github.com/VNG-Realisatie/gemma-zaken | VNG API standards |
| Sudwest-Fryslan/OpenZaakBrug | https://github.com/Sudwest-Fryslan/OpenZaakBrug | ZDS-to-ZGW bridge |
| OneGround/ZGW-APIs | https://github.com/OneGround/ZGW-APIs | Alternative C# implementation |

## PDF Documents

| Document | URL |
|----------|-----|
| Zaakgericht werken in het Gemeentelijk Gegevenslandschap v1.01 | https://www.gemmaonline.nl/images/gemmaonline/f/f6/20190620_-_Zaakgericht_werken_in_het_Gemeentelijk_Gegevenslandschap_v101.pdf |

## Community and Support

| Resource | URL |
|----------|-----|
| Slack (Samen Organiseren) | https://samenorganiseren.slack.com (channel: open-zaak) |
| Common Ground group | https://commonground.nl |
| Maykin Media (maintainer) | https://www.maykinmedia.nl |
| Dimpact (coordinator) | https://www.dimpact.nl |
| Foundation for Public Code | https://publiccode.net |

## Test/Demo Instances

| Instance | URL |
|----------|-----|
| OpenGem test (Tilburg) | https://open-zaak.test.tilburg.opengem.nl/ |

## Related Component Documentation

| Component | Documentation URL |
|-----------|------------------|
| Open Formulieren | https://open-forms.readthedocs.io/ |
| Open Inwoner | https://docs.openinwoner.nl/ |
| Open Archiefbeheer | https://opengem.nl/producten/open-archiefbeheer/ |
| Objects API | https://objects-and-objecttypes-api.readthedocs.io/ |
