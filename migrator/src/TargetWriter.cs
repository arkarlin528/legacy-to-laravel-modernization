using Npgsql;
using NpgsqlTypes;

namespace DataMigrator;

/// <summary>
/// Loads rows into PostgreSQL. Each table is COPY'd (binary, fast) into a temp table and then upserted
/// by id, so the migrator can be re-run any number of times: the first run is the bulk load, later
/// runs are catch-up syncs while the legacy system is still taking writes. All in one transaction.
/// </summary>
internal static class TargetWriter
{
    public static async Task WriteAsync(string connectionString, TargetSnapshot rows, int nextOrderNumber, CancellationToken ct)
    {
        await using var conn = new NpgsqlConnection(connectionString);
        await conn.OpenAsync(ct);
        await using var tx = await conn.BeginTransactionAsync(ct);

        await UpsertAsync(conn, "customers",
            ["id", "code", "name", "email", "phone", "country", "is_active", "created_at", "updated_at"],
            [NpgsqlDbType.Bigint, NpgsqlDbType.Varchar, NpgsqlDbType.Varchar, NpgsqlDbType.Varchar, NpgsqlDbType.Varchar,
             NpgsqlDbType.Char, NpgsqlDbType.Boolean, NpgsqlDbType.TimestampTz, NpgsqlDbType.TimestampTz],
            rows.Customers.Select(c => new object?[]
                { c.Id, c.Code, c.Name, c.Email, c.Phone, c.Country, c.IsActive, c.CreatedAt, c.CreatedAt }), ct);

        await UpsertAsync(conn, "orders",
            ["id", "order_no", "customer_id", "ordered_at", "status", "origin", "destination", "remarks", "total", "currency", "created_at", "updated_at"],
            [NpgsqlDbType.Bigint, NpgsqlDbType.Varchar, NpgsqlDbType.Bigint, NpgsqlDbType.TimestampTz, NpgsqlDbType.Varchar,
             NpgsqlDbType.Char, NpgsqlDbType.Char, NpgsqlDbType.Text, NpgsqlDbType.Numeric, NpgsqlDbType.Char,
             NpgsqlDbType.TimestampTz, NpgsqlDbType.TimestampTz],
            rows.Orders.Select(o => new object?[]
                { o.Id, o.OrderNo, o.CustomerId, o.OrderedAt, o.Status, o.Origin, o.Destination, o.Remarks, o.Total, o.Currency, o.OrderedAt, o.OrderedAt }), ct);

        await UpsertAsync(conn, "order_lines",
            ["id", "order_id", "line_no", "description", "quantity", "unit_price", "amount"],
            [NpgsqlDbType.Bigint, NpgsqlDbType.Bigint, NpgsqlDbType.Smallint, NpgsqlDbType.Varchar, NpgsqlDbType.Integer,
             NpgsqlDbType.Numeric, NpgsqlDbType.Numeric],
            rows.Lines.Select(l => new object?[]
                { l.Id, l.OrderId, (short)l.LineNo, l.Description, l.Quantity, l.UnitPrice, l.Amount }), ct);

        await UpsertAsync(conn, "invoices",
            ["id", "invoice_no", "order_id", "issued_at", "due_at", "amount", "is_paid", "paid_at", "created_at", "updated_at"],
            [NpgsqlDbType.Bigint, NpgsqlDbType.Varchar, NpgsqlDbType.Bigint, NpgsqlDbType.TimestampTz, NpgsqlDbType.TimestampTz,
             NpgsqlDbType.Numeric, NpgsqlDbType.Boolean, NpgsqlDbType.TimestampTz, NpgsqlDbType.TimestampTz, NpgsqlDbType.TimestampTz],
            rows.Invoices.Select(i => new object?[]
                { i.Id, i.InvoiceNo, i.OrderId, i.IssuedAt, i.DueAt, i.Amount, i.IsPaid, i.PaidAt, i.IssuedAt, i.IssuedAt }), ct);

        // Ids were copied explicitly, so move every identity sequence past the highest id,
        // and continue order numbering exactly where the legacy counter table left off.
        foreach (var table in new[] { "customers", "orders", "order_lines", "invoices" })
        {
            await ExecAsync(conn,
                $"SELECT setval(pg_get_serial_sequence('{table}', 'id'), GREATEST((SELECT COALESCE(MAX(id), 0) FROM {table}), 1))", ct);
        }
        await ExecAsync(conn, $"SELECT setval('order_number_seq', {nextOrderNumber}, false)", ct);

        await tx.CommitAsync(ct);
    }

    private static async Task UpsertAsync(NpgsqlConnection conn, string table, string[] columns, NpgsqlDbType[] types,
        IEnumerable<object?[]> rows, CancellationToken ct)
    {
        var temp = $"tmp_{table}";
        await ExecAsync(conn, $"CREATE TEMP TABLE {temp} (LIKE {table} INCLUDING DEFAULTS) ON COMMIT DROP", ct);

        var columnList = string.Join(", ", columns);
        await using (var importer = await conn.BeginBinaryImportAsync($"COPY {temp} ({columnList}) FROM STDIN (FORMAT BINARY)", ct))
        {
            foreach (var row in rows)
            {
                await importer.StartRowAsync(ct);
                for (var i = 0; i < columns.Length; i++)
                {
                    if (row[i] is null)
                        await importer.WriteNullAsync(ct);
                    else
                        await importer.WriteAsync(row[i], types[i], ct);
                }
            }
            await importer.CompleteAsync(ct);
        }

        var updates = string.Join(", ", columns.Where(c => c != "id").Select(c => $"{c} = EXCLUDED.{c}"));
        await ExecAsync(conn,
            $"INSERT INTO {table} ({columnList}) SELECT {columnList} FROM {temp} ON CONFLICT (id) DO UPDATE SET {updates}", ct);
    }

    private static async Task ExecAsync(NpgsqlConnection conn, string sql, CancellationToken ct)
    {
        await using var cmd = new NpgsqlCommand(sql, conn);
        await cmd.ExecuteNonQueryAsync(ct);
    }
}
