# Open VTB GitHub README (Fetched 2026-03-13)

Source: https://github.com/maykinmedia/open-vtb

## Project Identity

**Open Verzoeken, Taken en Berichten (VTB)** -- version 0.1.0, developed by Maykin B.V. for the "Platform Dienstverlening werkgroep."

## Three Core Components

### 1. Verzoeken (Requests)
Decoupling between applications and case management systems. A request ("verzoek") captures the citizen's submission data, allowing downstream applications to determine the case type based on the request data. This separates the intake process from case registration.

### 2. Taken (Tasks)
Government inquiries directed at citizens or entrepreneurs. Examples: document submission requests, payment settlement requests. Tasks are typically displayed in citizen portals ("mijn-omgeving") and are created by case handling components.

### 3. Berichten (Messages)
Digital communication channels between residents, businesses, and municipalities. Messages maintain a searchable history accessible through citizen portals or customer service applications.

## API Specifications

Three distinct OpenAPI specifications are maintained:
- **Verzoeken API v0.1.0**: ReDoc + Swagger UI
- **Taken API v0.1.0**: ReDoc + Swagger UI
- **Berichten API v0.1.0**: ReDoc + Swagger UI

Previous versions receive 6 months of support following the next release.

## Quick Start

```bash
wget https://raw.githubusercontent.com/maykinmedia/open-vtb/main/docker-compose.yml
docker-compose up -d --no-build
docker-compose exec web src/manage.py loaddata verzoeken taken berichten
docker-compose exec web src/manage.py createsuperuser
```

Access at `http://localhost:8000/` for admin and API.

## Technology

- Python 89.6%, SCSS 4.2%, HTML 3.6%, Shell 1.1%
- Django, Docker, Node.js
- Python 3.12+ required
- Ruff for linting
- GitHub Actions CI/CD
- Code coverage monitoring

## Repository Stats (as of fetch date)

- 169 commits on main branch
- 2 stars, 0 forks
- 22 open issues, 1 active PR
- 0 security issues
- License: EUPL (European Union Public Licence)
- Last significant activity: January 2025

## Resources

- Documentation: https://open-vtb.readthedocs.io/
- Docker: https://hub.docker.com/r/maykinmedia/open-vtb
- Issues: https://github.com/maykinmedia/open-vtb/issues
