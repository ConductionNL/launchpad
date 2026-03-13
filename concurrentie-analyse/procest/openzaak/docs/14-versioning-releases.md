# OpenZaak Versioning and Releases

## Release Policy

- New version releases every **two months**, at the start of the month
- Major releases occur every **two years**
- Only the latest version is actively maintained and under development
- Older versions receive patches only

## Support Policy

- Each major version supported until **24 months after release of next major version**
- Most recent major version: no fixed end date (until next major release)
- Within each supported major version: **only two most recent minor versions supported**
- Oldest of the two supported minors: at most **6 months** after its release

## Version History

| Version | Release Date | Supported Until |
|---------|-------------|----------------|
| **1.27.x** | 2026-02-06 | Current |
| 1.26.x | 2025-12-01 | 2026-06-01 |
| 1.25.x | 2025-10-03 | 2026-02-06 |
| 1.24.x | 2025-09-02 | 2025-12-01 |
| 1.23.x | 2025-08-05 | 2025-10-03 |
| 1.22.x | 2025-07-22 | 2025-09-02 |
| 1.21.x | 2025-05-13 | 2025-08-05 |
| 1.20.x | 2025-04-03 | 2025-07-22 |
| 1.19.x | 2025-03-04 | 2025-05-13 |
| 1.18.x | 2025-02-14 | 2025-04-03 |
| 1.17.x | 2025-01-17 | 2025-03-04 |
| 1.16.x | 2024-11-25 | 2025-02-14 |
| 1.15.x | 2024-10-04 | 2025-01-17 |
| 1.14.x | 2024-09-02 | 2024-11-25 |
| 1.13.x | 2024-05-15 | 2024-10-04 |
| 1.12.x | 2024-03-25 | 2024-09-02 |
| 1.11.x | 2024-02-01 | 2024-05-15 |
| 1.10.x | 2023-11-01 | 2024-03-25 |
| 1.9.x | 2023-07-17 | 2024-02-01 |
| 1.8.x | 2023-01-09 | 2023-11-01 |
| 1.7.x | 2022-07-08 | 2023-07-17 |
| 1.6.x | 2022-03-31 | 2023-01-09 |
| 1.5.x | 2021-08-10 | 2022-07-08 |
| 1.4.x | 2021-05-15 | 2022-03-31 |
| 1.3.x | 2021-03-25 | 2021-08-10 |
| 1.2.x | 2020-04-20 | 2021-05-15 |
| 1.1.x | 2020-03-10 | 2021-03-25 |
| 1.0.x | 2020-02-06 | 2020-04-20 |

## Release Frequency Analysis

Approximately **12 releases per year** since 2024, indicating active development and rapid iteration. This is significantly faster than most Dutch government software projects.

## Technology Stack Versions

- Python: 3.12
- Django: current LTS
- PostgreSQL: 14+
- PostGIS: 3.2+
- Node.js: 24+
- Docker: 29.2.1+
- uWSGI: latest
