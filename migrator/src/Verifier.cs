using Npgsql;

namespace DataMigrator;

internal sealed record TableCheck(string Table, int Expected, int Actual, decimal? ExpectedSum, decimal? ActualSum, int RowMismatches)
{
    public bool Passed => Expected == Actual && ExpectedSum == ActualSum && RowMismatches == 0;
}

/// <summary>
/// Reads everything back from PostgreSQL and compares it with what we meant to write: row counts,
/// money totals, and every row field by field. A migration that can't prove itself didn't happen.
/// </summary>
internal static class Verifier
{
    public static async Task<List<TableCheck>> VerifyAsync(string connectionString, TargetSnapshot expected, CancellationToken ct)
    {
        await using var conn = new NpgsqlConnection(connectionString);
        await conn.OpenAsync(ct);

        var customers = await ReadAsync(conn,
            "SELECT id, code, name, email, phone, country, is_active, created_at FROM customers",
            r => new CustomerRow(r.GetInt64(0), r.GetString(1), r.GetString(2), Str(r, 3), Str(r, 4), r.GetString(5),
                r.GetBoolean(6), r.GetFieldValue<DateTimeOffset>(7)), ct);
        var orders = await ReadAsync(conn,
            "SELECT id, order_no, customer_id, ordered_at, status, origin, destination, remarks, total, currency FROM orders",
            r => new OrderRow(r.GetInt64(0), r.GetString(1), r.GetInt64(2), r.GetFieldValue<DateTimeOffset>(3), r.GetString(4),
                r.GetString(5), r.GetString(6), Str(r, 7), r.GetDecimal(8), r.GetString(9)), ct);
        var lines = await ReadAsync(conn,
            "SELECT id, order_id, line_no, description, quantity, unit_price, amount FROM order_lines",
            r => new OrderLineRow(r.GetInt64(0), r.GetInt64(1), r.GetInt16(2), r.GetString(3), r.GetInt32(4),
                r.GetDecimal(5), r.GetDecimal(6)), ct);
        var invoices = await ReadAsync(conn,
            "SELECT id, invoice_no, order_id, issued_at, due_at, amount, is_paid, paid_at FROM invoices",
            r => new InvoiceRow(r.GetInt64(0), r.GetString(1), r.GetInt64(2), r.GetFieldValue<DateTimeOffset>(3),
                r.GetFieldValue<DateTimeOffset>(4), r.GetDecimal(5), r.GetBoolean(6),
                r.IsDBNull(7) ? null : r.GetFieldValue<DateTimeOffset>(7)), ct);

        return
        [
            Compare("customers", expected.Customers, customers, c => c.Id, null),
            Compare("orders", expected.Orders, orders, o => o.Id, o => o.Total),
            Compare("order_lines", expected.Lines, lines, l => l.Id, l => l.Amount),
            Compare("invoices", expected.Invoices, invoices, i => i.Id, i => i.Amount),
        ];
    }

    /// <summary>
    /// Rows written by the new system after cut-over (ids the legacy side never had) aren't counted
    /// as mismatches; only rows that came from legacy must match exactly.
    /// </summary>
    internal static TableCheck Compare<T>(string table, IReadOnlyList<T> expected, IReadOnlyList<T> actual,
        Func<T, long> id, Func<T, decimal>? money) where T : notnull
    {
        var actualById = actual.ToDictionary(id);
        var mismatches = expected.Count(e => !actualById.TryGetValue(id(e), out var a) || !Equal(e, a));
        var migrated = expected.Select(id).ToHashSet();
        var actualMigrated = actual.Where(a => migrated.Contains(id(a))).ToList();

        return new TableCheck(table, expected.Count, actualMigrated.Count,
            money is null ? null : expected.Sum(money), money is null ? null : actualMigrated.Sum(money), mismatches);
    }

    // Records compare by value. Timestamps come back from PostgreSQL in UTC with the same instant,
    // so normalise them before comparing.
    private static bool Equal<T>(T expected, T actual) where T : notnull =>
        Normalize(expected).Equals(Normalize(actual));

    private static object Normalize(object row) => row switch
    {
        CustomerRow c => c with { CreatedAt = c.CreatedAt.ToUniversalTime() },
        OrderRow o => o with { OrderedAt = o.OrderedAt.ToUniversalTime() },
        InvoiceRow i => i with { IssuedAt = i.IssuedAt.ToUniversalTime(), DueAt = i.DueAt.ToUniversalTime(), PaidAt = i.PaidAt?.ToUniversalTime() },
        _ => row,
    };

    private static async Task<List<T>> ReadAsync<T>(NpgsqlConnection conn, string sql, Func<NpgsqlDataReader, T> map,
        CancellationToken ct)
    {
        await using var cmd = new NpgsqlCommand(sql, conn);
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        var rows = new List<T>();
        while (await reader.ReadAsync(ct))
            rows.Add(map(reader));
        return rows;
    }

    private static string? Str(NpgsqlDataReader r, int i) => r.IsDBNull(i) ? null : r.GetString(i);
}
