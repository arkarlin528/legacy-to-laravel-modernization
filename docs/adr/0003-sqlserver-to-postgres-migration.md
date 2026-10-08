# ADR-0003: SQL Server → PostgreSQL with a re-runnable, self-verifying migrator

- **Status:** Accepted
- **Date:** 2026-10-02

## Context
The data must move with **no loss and no silent change**, possibly many times (bulk load, then catch-up syncs until cut-over). Type systems differ: `money`, `DATETIME` (1/300 s, no time zone), `CHAR(1)` flags, `TINYINT` codes, and a counter table for numbering.

## Options considered
1. **Generic tools** (pgloader, AWS DMS): fast, but type decisions are implicit and there's no domain-level verification or data-quality report.
2. **Laravel artisan command reading SQL Server:** needs the `pdo_sqlsrv` driver in the PHP image just for a one-off job.
3. **A small .NET console app:** the SQL Server team already knows the legacy schema, the drivers are first-class for both databases, and the transformation rules are unit-testable.

## Decision
**Option 3** ([`migrator/`](../../migrator)), in four steps:
1. **Read:** the whole legacy database in one `REPEATABLE READ` transaction (a consistent snapshot).
2. **Transform:** pure functions (`Transformer`, 18 unit tests). Bangkok time → UTC, truncated to µs; flags and codes mapped; emails normalised; **totals kept as stored**. Data-quality findings are collected, not hidden.
3. **Load:** binary `COPY` into temp tables, then `INSERT … ON CONFLICT (id) DO UPDATE`, all in one transaction. Ids are preserved, identity sequences moved past the max, and `order_number_seq` set to the legacy counter.
4. **Verify:** read everything back; compare counts, money totals and **every row field by field**; write [`migration-report.md`](../migration-report.md); exit non-zero on any mismatch.

## Consequences
- ✅ Re-running is safe and doubles as the catch-up sync. The verifier ignores rows created in the new system after cut-over, but flags any legacy row that differs.
- ✅ The verifier caught Laravel's default `timestamptz(0)` rounding before any client did.
- ⚠️ Every run reads all rows. Fine for thousands of rows; at millions, add a change-tracking column or CDC and sync deltas.
- ⚠️ Not implemented: reverse sync (PostgreSQL → SQL Server) for rollback after cut-over. It's listed as a must-have in the case study's risks.
