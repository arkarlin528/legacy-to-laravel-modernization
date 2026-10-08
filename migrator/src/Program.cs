using System.Diagnostics;
using DataMigrator;

// Legacy SQL Server → PostgreSQL. Idempotent: safe to run again (each run upserts and re-verifies).
//
//   dotnet run --project src -- --legacy "<sql server conn>" --target "<npgsql conn>" [--report path.md]
//
// Connection strings can also come from LEGACY_DB / TARGET_DB environment variables.

var options = ParseArgs(args);
var legacy = options.GetValueOrDefault("legacy") ?? Environment.GetEnvironmentVariable("LEGACY_DB");
var target = options.GetValueOrDefault("target") ?? Environment.GetEnvironmentVariable("TARGET_DB");
var reportPath = options.GetValueOrDefault("report") ?? "migration-report.md";

if (string.IsNullOrWhiteSpace(legacy) || string.IsNullOrWhiteSpace(target))
{
    Console.Error.WriteLine("Usage: DataMigrator --legacy <sqlserver-conn> --target <postgres-conn> [--report file.md]");
    return 2;
}

using var cts = new CancellationTokenSource();
Console.CancelKeyPress += (_, e) => { e.Cancel = true; cts.Cancel(); };

var started = DateTimeOffset.UtcNow;
var clock = Stopwatch.StartNew();

Console.WriteLine("1/4 Reading legacy database…");
var snapshot = await LegacyReader.ReadAsync(legacy, cts.Token);
Console.WriteLine($"    {snapshot.Customers.Count} customers, {snapshot.Orders.Count} orders, {snapshot.Lines.Count} lines, {snapshot.Invoices.Count} invoices");

Console.WriteLine("2/4 Transforming…");
var (rows, findings) = Transformer.Transform(snapshot);
foreach (var f in findings)
    Console.WriteLine($"    finding: {f.Rule} ({f.Count})");

Console.WriteLine("3/4 Loading into PostgreSQL (COPY + upsert, one transaction)…");
await TargetWriter.WriteAsync(target, rows, snapshot.NextOrderNumber, cts.Token);

Console.WriteLine("4/4 Verifying…");
var checks = await Verifier.VerifyAsync(target, rows, cts.Token);
foreach (var c in checks)
    Console.WriteLine($"    {(c.Passed ? "OK  " : "FAIL")} {c.Table,-12} {c.Actual}/{c.Expected} rows, {c.RowMismatches} mismatches");

var markdown = Report.ToMarkdown(started, clock.Elapsed, checks, findings, snapshot.NextOrderNumber);
await File.WriteAllTextAsync(reportPath, markdown, cts.Token);
Console.WriteLine($"Report written to {Path.GetFullPath(reportPath)}");

return checks.All(c => c.Passed) ? 0 : 1;

static Dictionary<string, string> ParseArgs(string[] args)
{
    var result = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);
    for (var i = 0; i < args.Length - 1; i++)
    {
        if (args[i].StartsWith("--"))
            result[args[i][2..]] = args[++i];
    }
    return result;
}
