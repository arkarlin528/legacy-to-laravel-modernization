# ADR-0004: Why Laravel (and PostgreSQL) for the new system

- **Status:** Accepted
- **Date:** 2026-10-02

## Context
The team maintaining the order system works mostly in PHP, and the company's other internal tools are Laravel + Filament. Hosting is moving to Linux containers. The legacy service is .NET + SQL Server on Windows.

## Options considered
1. **Rewrite in modern .NET (ASP.NET Core + EF Core):** the smallest language jump from legacy, strong typing, excellent performance.
2. **Laravel + PostgreSQL:** matches the team and the rest of the internal stack; Filament gives a back office almost for free; no SQL Server licence.

## Decision
**Laravel 13 + PostgreSQL**, because the deciding factors were **people and operations**, not raw performance:
- The team that will own it for years writes PHP daily.
- **Filament** delivered the back office the legacy system never had (orders, customers, invoices, cancel action, data-quality filter) in a few hundred lines. The ops team previously ran SQL queries.
- Form Requests, Eloquent and Sanctum cover validation, data access and per-user tokens with little custom code.
- PostgreSQL removes the SQL Server licence and the Windows dependency.

Performance was not a constraint at this volume (hundreds of orders a day). With OPcache in the container image, PHP response times are well within the SLA.

## Consequences
- ✅ One place for business rules (`OrderService`), shared by v1, v2 and the admin panel.
- ✅ A modern v2 API alongside the frozen v1 contract.
- ⚠️ The team had to learn some PostgreSQL specifics (session time zone, `timestamptz` precision, non-transactional sequences). All are captured in tests and in the case study.
- 🔁 If the domain grows into heavy, CPU-bound processing, .NET remains the better fit for that piece, behind the same facade.
