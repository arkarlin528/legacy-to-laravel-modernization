# ADR-0006: Cut-over with a short write freeze; legacy stays read-only as the rollback target

- **Status:** Accepted
- **Date:** 2026-10-02

## Context
Customers and orders are written through the API. Once the new system accepts writes, legacy and new data diverge. We need a cut-over that loses nothing and a rollback that is realistic.

## Options considered
1. **Dual writes** (the facade or app writes to both databases): complex failure modes (one write succeeds, one fails) and hard to verify.
2. **Change data capture both ways during the transition:** robust, but heavy infrastructure for a few thousand rows a day.
3. **A short write freeze, a final verified sync, then switch:** minutes of `503 + Retry-After` for writes only; reads never stop.

## Decision
**Option 3**, as two phase files:
- **3a-freeze:** the facade answers writes with `503` and `Retry-After: 120`; reads continue. The migrator runs a final sync and **must pass verification**.
- **3b-cutover:** all v1 routes go to the new system. Legacy keeps running **read-only** for two weeks as the rollback target.

Order numbering continues from the legacy counter (`order_number_seq` = legacy `NextVal`), so numbers never collide.

## Consequences
- ✅ No dual-write consistency problems. The cut-over is minutes long, scheduled in a quiet hour, and clients already retry on 503.
- ✅ Rollback **before** 3b is a file switch.
- ⚠️ Rollback **after** 3b needs a reverse sync of rows created in PostgreSQL. In a real engagement this is built and rehearsed before 3b. In this demo it's documented, not implemented.
