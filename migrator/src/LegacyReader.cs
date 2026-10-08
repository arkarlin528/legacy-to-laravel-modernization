using Microsoft.Data.SqlClient;

namespace DataMigrator;

/// <summary>Reads the whole legacy database in one snapshot transaction, so a busy legacy app can't give us a torn read.</summary>
internal static class LegacyReader
{
    public static async Task<LegacySnapshot> ReadAsync(string connectionString, CancellationToken ct)
    {
        await using var conn = new SqlConnection(connectionString);
        await conn.OpenAsync(ct);
        // SNAPSHOT needs ALLOW_SNAPSHOT_ISOLATION on the database; REPEATABLE READ is the safe fallback.
        await using var tx = (SqlTransaction)await conn.BeginTransactionAsync(System.Data.IsolationLevel.RepeatableRead, ct);

        var customers = await ReadAsync(conn, tx,
            "SELECT CustID, CustCode, CustNm, Email, Phone, Country, IsActive, CreatedDt FROM dbo.tblCustomer ORDER BY CustID",
            r => new LegacyCustomer(r.GetInt32(0), r.GetString(1), r.GetString(2), Nullable(r, 3), Nullable(r, 4),
                r.GetString(5), r.GetString(6), r.GetDateTime(7)), ct);

        var orders = await ReadAsync(conn, tx,
            "SELECT OrderID, OrderNo, CustID, OrderDt, Status, Origin, Dest, Remarks, TotalAmt, Curr FROM dbo.tblOrder ORDER BY OrderID",
            r => new LegacyOrder(r.GetInt32(0), r.GetString(1), r.GetInt32(2), r.GetDateTime(3), r.GetByte(4), r.GetString(5),
                r.GetString(6), Nullable(r, 7), r.GetDecimal(8), r.GetString(9)), ct);

        var lines = await ReadAsync(conn, tx,
            "SELECT LineID, OrderID, LineNum, Descr, Qty, UnitPrice, LineAmt FROM dbo.tblOrderLine ORDER BY LineID",
            r => new LegacyOrderLine(r.GetInt32(0), r.GetInt32(1), r.GetInt32(2), r.GetString(3), r.GetInt32(4),
                r.GetDecimal(5), r.GetDecimal(6)), ct);

        var invoices = await ReadAsync(conn, tx,
            "SELECT InvID, OrderID, InvNo, InvDt, DueDt, Amount, PaidFlag, PaidDt FROM dbo.tblInvoice ORDER BY InvID",
            r => new LegacyInvoice(r.GetInt32(0), r.GetInt32(1), r.GetString(2), r.GetDateTime(3), r.GetDateTime(4),
                r.GetDecimal(5), r.GetBoolean(6), r.IsDBNull(7) ? null : r.GetDateTime(7)), ct);

        await using var seq = new SqlCommand("SELECT NextVal FROM dbo.tblSequence WHERE SeqName = 'ORDER'", conn, tx);
        var next = Convert.ToInt32(await seq.ExecuteScalarAsync(ct));

        await tx.CommitAsync(ct);
        return new LegacySnapshot(customers, orders, lines, invoices, next);
    }

    private static async Task<List<T>> ReadAsync<T>(SqlConnection conn, SqlTransaction tx, string sql,
        Func<SqlDataReader, T> map, CancellationToken ct)
    {
        await using var cmd = new SqlCommand(sql, conn, tx);
        await using var reader = await cmd.ExecuteReaderAsync(ct);
        var rows = new List<T>();
        while (await reader.ReadAsync(ct))
            rows.Add(map(reader));
        return rows;
    }

    private static string? Nullable(SqlDataReader r, int i) => r.IsDBNull(i) ? null : r.GetString(i);
}
