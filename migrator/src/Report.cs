using System.Globalization;
using System.Text;

namespace DataMigrator;

internal static class Report
{
    public static string ToMarkdown(DateTimeOffset startedAt, TimeSpan duration, IReadOnlyList<TableCheck> checks,
        IReadOnlyList<Finding> findings, int nextOrderNumber)
    {
        var ok = checks.All(c => c.Passed);
        var sb = new StringBuilder();
        sb.AppendLine("# Data migration report");
        sb.AppendLine();
        sb.AppendLine($"- **Run:** {startedAt.ToUniversalTime():yyyy-MM-dd HH:mm:ss} UTC, took {duration.TotalSeconds:0.0}s");
        sb.AppendLine($"- **Source:** legacy SQL Server (`tblCustomer`, `tblOrder`, `tblOrderLine`, `tblInvoice`)");
        sb.AppendLine($"- **Target:** PostgreSQL (`customers`, `orders`, `order_lines`, `invoices`)");
        sb.AppendLine($"- **Order numbering continues at:** {nextOrderNumber}");
        sb.AppendLine($"- **Result:** {(ok ? "✅ PASSED: every migrated row matches field by field" : "❌ FAILED")}");
        sb.AppendLine();
        sb.AppendLine("## Verification");
        sb.AppendLine();
        sb.AppendLine("| Table | Legacy rows | Migrated rows | Legacy amount | Migrated amount | Row mismatches | |");
        sb.AppendLine("|---|---:|---:|---:|---:|---:|---|");
        foreach (var c in checks)
        {
            sb.AppendLine($"| `{c.Table}` | {c.Expected} | {c.Actual} | {Money(c.ExpectedSum)} | {Money(c.ActualSum)} | {c.RowMismatches} | {(c.Passed ? "✅" : "❌")} |");
        }
        sb.AppendLine();
        sb.AppendLine("## Data-quality findings");
        sb.AppendLine();
        if (findings.Count == 0)
        {
            sb.AppendLine("None.");
        }
        foreach (var f in findings)
        {
            sb.AppendLine($"### `{f.Rule}` ({f.Count})");
            sb.AppendLine();
            sb.AppendLine(f.Detail);
            sb.AppendLine();
            foreach (var example in f.Examples)
                sb.AppendLine($"- {example}");
            if (f.Count > f.Examples.Count)
                sb.AppendLine($"- … and {f.Count - f.Examples.Count} more");
            sb.AppendLine();
        }
        return sb.ToString();
    }

    private static string Money(decimal? value) =>
        value is null ? "—" : value.Value.ToString("#,0.00", CultureInfo.InvariantCulture);
}
