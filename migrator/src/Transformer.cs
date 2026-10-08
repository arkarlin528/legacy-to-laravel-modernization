namespace DataMigrator;

/// <summary>
/// Pure functions from legacy rows to new rows, plus the data-quality findings they surface.
/// No I/O here, so every rule is unit tested (see DataMigrator.Tests).
/// </summary>
internal static class Transformer
{
    /// <summary>The legacy app wrote local Bangkok time (UTC+7, no DST) without an offset.</summary>
    public static readonly TimeZoneInfo LegacyZone = FindBangkok();

    public static DateTimeOffset ToUtc(DateTime legacyLocal)
    {
        var unspecified = DateTime.SpecifyKind(legacyLocal, DateTimeKind.Unspecified);
        var utc = new DateTimeOffset(unspecified, LegacyZone.GetUtcOffset(unspecified)).ToUniversalTime();
        // SQL Server DATETIME ticks in 1/300 s (e.g. .1233333), PostgreSQL keeps microseconds.
        // Truncate here, explicitly, so the verifier compares like with like.
        return new DateTimeOffset(utc.Ticks - utc.Ticks % 10, TimeSpan.Zero);
    }

    public static string? NormalizeEmail(string? email)
    {
        if (string.IsNullOrWhiteSpace(email))
            return null;
        return email.Trim().ToLowerInvariant();
    }

    public static bool ParseActiveFlag(string flag) => flag.Trim().Equals("Y", StringComparison.OrdinalIgnoreCase);

    public static string MapStatus(byte legacyStatus) => legacyStatus switch
    {
        1 => "open",
        2 => "confirmed",
        3 => "shipped",
        4 => "cancelled",
        _ => throw new InvalidDataException($"Unknown legacy order status {legacyStatus}"),
    };

    public static (TargetSnapshot Rows, List<Finding> Findings) Transform(LegacySnapshot legacy)
    {
        var findings = new List<Finding>();

        var customers = legacy.Customers.Select(c => new CustomerRow(
            c.CustID, c.CustCode.Trim().ToUpperInvariant(), c.CustNm.Trim(), NormalizeEmail(c.Email),
            string.IsNullOrWhiteSpace(c.Phone) ? null : c.Phone.Trim(), c.Country.Trim().ToUpperInvariant(),
            ParseActiveFlag(c.IsActive), ToUtc(c.CreatedDt))).ToList();

        var dirtyEmails = legacy.Customers.Where(c => c.Email is not null && c.Email != NormalizeEmail(c.Email)).ToList();
        if (dirtyEmails.Count > 0)
            findings.Add(new Finding("email-normalized",
                "Emails with surrounding spaces or upper case were trimmed and lower-cased. Visible to v1 clients as a change in case/whitespace only.",
                dirtyEmails.Select(c => $"customer {c.CustID}: '{c.Email}' → '{NormalizeEmail(c.Email)}'").Take(5).ToList(), dirtyEmails.Count));

        var oddFlags = legacy.Customers.Where(c => c.IsActive is not ("Y" or "N")).ToList();
        if (oddFlags.Count > 0)
            findings.Add(new Finding("active-flag-case",
                "IsActive held values other than 'Y'/'N'. The legacy API upper-cased before comparing, so these map to the same booleans.",
                oddFlags.Select(c => $"customer {c.CustID}: IsActive='{c.IsActive}'").Take(5).ToList(), oddFlags.Count));

        var orders = legacy.Orders.Select(o => new OrderRow(
            o.OrderID, o.OrderNo.Trim(), o.CustID, ToUtc(o.OrderDt), MapStatus(o.Status), o.Origin.Trim().ToUpperInvariant(),
            o.Dest.Trim().ToUpperInvariant(), string.IsNullOrWhiteSpace(o.Remarks) ? null : o.Remarks, o.TotalAmt,
            o.Curr.Trim().ToUpperInvariant())).ToList();

        var lineSums = legacy.Lines.GroupBy(l => l.OrderID).ToDictionary(g => g.Key, g => g.Sum(l => l.LineAmt));
        var badTotals = legacy.Orders
            .Where(o => lineSums.TryGetValue(o.OrderID, out var sum) && sum != o.TotalAmt)
            .ToList();
        if (badTotals.Count > 0)
            findings.Add(new Finding("order-total-mismatch",
                "Order total differs from the sum of its lines. Totals were kept exactly as stored (they were invoiced at that amount); flagged for finance to review.",
                badTotals.Select(o => $"order {o.OrderID} ({o.OrderNo}): total {o.TotalAmt:0.00}, lines {lineSums[o.OrderID]:0.00}").ToList(),
                badTotals.Count));

        var lines = legacy.Lines.Select(l => new OrderLineRow(
            l.LineID, l.OrderID, l.LineNum, l.Descr.Trim(), l.Qty, l.UnitPrice, l.LineAmt)).ToList();

        var invoices = legacy.Invoices.Select(i => new InvoiceRow(
            i.InvID, i.InvNo.Trim(), i.OrderID, ToUtc(i.InvDt), ToUtc(i.DueDt), i.Amount, i.PaidFlag,
            i.PaidDt is { } paid ? ToUtc(paid) : null)).ToList();

        var paidNoDate = legacy.Invoices.Where(i => i.PaidFlag && i.PaidDt is null).ToList();
        if (paidNoDate.Count > 0)
            findings.Add(new Finding("paid-without-date",
                "Invoices flagged paid with no payment date. Migrated as is_paid = true, paid_at = null; listed under the 'Paid but no payment date' filter in the admin panel.",
                paidNoDate.Select(i => $"invoice {i.InvID} ({i.InvNo})").ToList(), paidNoDate.Count));

        return (new TargetSnapshot(customers, orders, lines, invoices), findings);
    }

    private static TimeZoneInfo FindBangkok()
    {
        foreach (var id in new[] { "Asia/Bangkok", "SE Asia Standard Time" })
        {
            if (TimeZoneInfo.TryFindSystemTimeZoneById(id, out var zone))
                return zone;
        }
        return TimeZoneInfo.CreateCustomTimeZone("UTC+07", TimeSpan.FromHours(7), "UTC+07", "UTC+07");
    }
}
