# Objects API — GitHub README (Cleaned)

- **Version**: 3.6.0
- **Source**: https://github.com/maykinmedia/objects-api
- **Keywords**: objects, assets, zaakobjecten
- **License**: EUPL-1.2
- **Language**: Python
- **Stars**: 7 | **Forks**: 14 | **Open Issues**: 59
- **Created**: 2020-04-14 | **Last Push**: 2026-03-12
- **Docker Image**: https://hub.docker.com/r/maykinmedia/objects-api
- **Python**: 3.12+

## Introduction

The Objects API aims to easily store various objects and make them available in standardized format. It can be used by any organization to manage relevant objects. An organization can also choose to use it to expose objects to the public as Open Data.

To define the format of objects (object types), organizations can use a national and/or local Objecttypes API.

## Developed By

Maykin Media B.V., commissioned by the Municipality of Utrecht.

## Quickstart

```bash
wget https://raw.githubusercontent.com/maykinmedia/objects-api/master/docker-compose.yml
docker compose up -d --no-build
docker compose exec web src/manage.py loaddata demodata
docker compose exec web src/manage.py createsuperuser
```

Navigate to http://localhost:8000/ to access admin and API.

## References

- Documentation: https://objects-and-objecttypes-api.readthedocs.io/
- Docker image: https://hub.docker.com/r/maykinmedia/objects-api
- Issues: https://github.com/maykinmedia/objects-api/issues
- Community: https://commonground.nl/groups/view/54477963/objecten-en-objecttypen-api
