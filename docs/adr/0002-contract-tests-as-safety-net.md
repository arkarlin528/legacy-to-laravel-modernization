# ADR-0002: Black-box contract and parity tests are the safety net

- **Status:** Accepted
- **Date:** 2026-10-02

## Context
"Behaves the same" has to be proven, not assumed. The legacy system has no tests and no written API spec. Its real contract includes things nobody documented: PascalCase keys and their order, `{"Message": …}` errors, Web API 2's `ModelState` shape, local timestamps without an offset, and decimals like `958.00`.

## Decision
A standalone test project (`contract-tests/`, Pest + Guzzle) that only speaks HTTP:
1. **Contract suite (24 tests):** written and made green against the **legacy** API first, as its executable specification. It then runs unchanged against Laravel and through the facade, selected by `BASE_URL`. It covers success shapes, known records, errors, validation, creates, the `Location` header, and business-rule conflicts.
2. **Parity suite (216 cases):** calls every read endpoint on both systems and requires identical status and JSON, compared strictly (types included). The only exception is email normalisation, agreed with the business and coded as an explicit `normalise()`.
3. **CI runs both** after a real migration on every push.

The suite is in PHP so the team that owns the new system owns its safety net. It still tests .NET just fine, because it only sees HTTP.

## Consequences
- ✅ It caught real defects during the build. The contract suite found a 7-hour time zone shift on writes, and parity found `958` vs `958.0`. Together with the migrator's verifier, which found the `timestamptz(0)` rounding, see the [case study](../case-study.md#bugs-the-safety-nets-caught-during-this-build).
- ✅ Every routing phase is checked end to end through the facade.
- ⚠️ Write tests create data in whichever system they hit, so the parity check runs on a fresh sync (in CI: before the contract suite).
- ⚠️ Black-box tests can't see internal state. The migrator's row-by-row verification covers the data side.
