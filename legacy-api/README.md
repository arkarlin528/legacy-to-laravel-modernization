# Legacy order API (the "before")

**Intentionally old-fashioned.** A re-creation of a typical 10-year-old ASP.NET Web API 2 service, so the
modernization has something realistic to migrate:
- ADO.NET through a static `SqlHelper`, with business rules in stored procedures ([`db/02-procedures.sql`](db/02-procedures.sql))
- Fat controllers with hand-written validation
- SQL Server schema with `tbl` prefixes, `CHAR(1)` flags, `money`, local-time `DATETIME`, and a counter table
- Fictional seed data with planted data-quality problems ([`db/generate_seed.py`](db/generate_seed.py))

It runs on .NET 8 only so that it's easy to start; the code style is the point. Don't copy it.

```bash
dotnet run          # http://localhost:5100, needs the LegacyOrders database (see ../README.md)
```
