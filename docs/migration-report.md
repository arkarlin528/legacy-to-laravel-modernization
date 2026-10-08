# Data migration report

- **Run:** 2026-10-08 12:12:19 UTC, took 1.5s
- **Source:** legacy SQL Server (`tblCustomer`, `tblOrder`, `tblOrderLine`, `tblInvoice`)
- **Target:** PostgreSQL (`customers`, `orders`, `order_lines`, `invoices`)
- **Order numbering continues at:** 27
- **Result:** ✅ PASSED: every migrated row matches field by field

## Verification

| Table | Legacy rows | Migrated rows | Legacy amount | Migrated amount | Row mismatches | |
|---|---:|---:|---:|---:|---:|---|
| `customers` | 25 | 25 | — | — | 0 | ✅ |
| `orders` | 160 | 160 | 309,244.76 | 309,244.76 | 0 | ✅ |
| `order_lines` | 395 | 395 | 309,214.76 | 309,214.76 | 0 | ✅ |
| `invoices` | 147 | 147 | 271,578.10 | 271,578.10 | 0 | ✅ |

## Data-quality findings

### `email-normalized` (3)

Emails with surrounding spaces or upper case were trimmed and lower-cased. Visible to v1 clients as a change in case/whitespace only.

- customer 4: '  OPS@SGCT.EXAMPLE.COM ' → 'ops@sgct.example.com'
- customer 13: '  OPS@HCMT.EXAMPLE.COM ' → 'ops@hcmt.example.com'
- customer 22: '  OPS@HAIP.EXAMPLE.COM ' → 'ops@haip.example.com'

### `active-flag-case` (3)

IsActive held values other than 'Y'/'N'. The legacy API upper-cased before comparing, so these map to the same booleans.

- customer 3: IsActive='y'
- customer 13: IsActive='y'
- customer 23: IsActive='y'

### `order-total-mismatch` (3)

Order total differs from the sum of its lines. Totals were kept exactly as stored (they were invoiced at that amount); flagged for finance to review.

- order 17 (ORD-2023-000006): total 643.45, lines 633.45
- order 58 (ORD-2023-000020): total 5301.59, lines 5291.59
- order 121 (ORD-2023-000038): total 226.96, lines 216.96

### `paid-without-date` (2)

Invoices flagged paid with no payment date. Migrated as is_paid = true, paid_at = null; listed under the 'Paid but no payment date' filter in the admin panel.

- invoice 9 (INV-2023-00009)
- invoice 44 (INV-2025-00044)

