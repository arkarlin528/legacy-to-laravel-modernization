using DataMigrator;

namespace DataMigrator.Tests;

public class TransformerTests
{
    [Fact]
    public void Bangkok_local_time_becomes_utc()
    {
        var utc = Transformer.ToUtc(new DateTime(2023, 2, 18, 8, 58, 0));

        Assert.Equal(new DateTimeOffset(2023, 2, 18, 1, 58, 0, TimeSpan.Zero), utc);
        Assert.Equal(TimeSpan.Zero, utc.Offset);
    }

    [Fact]
    public void Times_just_after_local_midnight_land_on_the_previous_utc_day()
    {
        var utc = Transformer.ToUtc(new DateTime(2024, 1, 1, 3, 0, 0));

        Assert.Equal(new DateTimeOffset(2023, 12, 31, 20, 0, 0, TimeSpan.Zero), utc);
    }

    [Fact]
    public void Sql_server_datetime_fractions_are_truncated_to_microseconds()
    {
        // DATETIME stores 1/300 s, so 10:30:12.123 comes back as .1233333 (100 ns ticks).
        var legacy = new DateTime(2026, 10, 2, 10, 30, 12).AddTicks(1_233_333);

        var utc = Transformer.ToUtc(legacy);

        Assert.Equal(0, utc.Ticks % 10);
        Assert.Equal(new DateTimeOffset(2026, 10, 2, 3, 30, 12, TimeSpan.Zero).AddTicks(1_233_330), utc);
    }

    [Theory]
    [InlineData("  OPS@SGCT.EXAMPLE.COM ", "ops@sgct.example.com")]
    [InlineData("ops@cpre.example.com", "ops@cpre.example.com")]
    [InlineData("   ", null)]
    [InlineData(null, null)]
    public void Emails_are_trimmed_and_lower_cased(string? input, string? expected)
    {
        Assert.Equal(expected, Transformer.NormalizeEmail(input));
    }

    [Theory]
    [InlineData("Y", true)]
    [InlineData("y", true)]
    [InlineData("N", false)]
    [InlineData(" Y", true)]
    public void Active_flags_parse_like_the_legacy_api(string flag, bool expected)
    {
        Assert.Equal(expected, Transformer.ParseActiveFlag(flag));
    }

    [Theory]
    [InlineData(1, "open")]
    [InlineData(2, "confirmed")]
    [InlineData(3, "shipped")]
    [InlineData(4, "cancelled")]
    public void Status_codes_map_to_names(byte code, string expected)
    {
        Assert.Equal(expected, Transformer.MapStatus(code));
    }

    [Fact]
    public void Unknown_status_stops_the_migration()
    {
        Assert.Throws<InvalidDataException>(() => Transformer.MapStatus(9));
    }

    [Fact]
    public void Dirty_data_is_reported_but_totals_are_kept()
    {
        var snapshot = new LegacySnapshot(
            [new LegacyCustomer(1, "acme ", "Acme ", " A@B.COM", null, "th", "y", new DateTime(2020, 1, 1, 9, 0, 0))],
            [new LegacyOrder(10, "ORD-2024-000001", 1, new DateTime(2024, 5, 1, 10, 0, 0), 3, "thlch", "SGSIN", "", 110m, "usd")],
            [new LegacyOrderLine(100, 10, 1, "Freight", 1, 100m, 100m)],
            [new LegacyInvoice(1000, 10, "INV-1", new DateTime(2024, 5, 2), new DateTime(2024, 6, 1), 110m, true, null)],
            NextOrderNumber: 2);

        var (rows, findings) = Transformer.Transform(snapshot);

        var customer = Assert.Single(rows.Customers);
        Assert.Equal(("ACME", "Acme", "a@b.com", "TH", true), (customer.Code, customer.Name, customer.Email, customer.Country, customer.IsActive));
        var order = Assert.Single(rows.Orders);
        Assert.Equal(110m, order.Total);              // kept as invoiced
        Assert.Equal("THLCH", order.Origin);
        Assert.Null(order.Remarks);                    // empty string -> null
        Assert.Equal("shipped", order.Status);
        Assert.Equal(["email-normalized", "active-flag-case", "order-total-mismatch", "paid-without-date"],
            findings.Select(f => f.Rule));
    }

    [Fact]
    public void Verifier_flags_changed_and_missing_rows_but_ignores_new_ones()
    {
        var at = DateTimeOffset.UnixEpoch;
        var expected = new[] { new CustomerRow(1, "A", "A", null, null, "TH", true, at), new CustomerRow(2, "B", "B", null, null, "TH", true, at) };
        var actual = new[]
        {
            new CustomerRow(1, "A", "A changed", null, null, "TH", true, at), // differs
            new CustomerRow(3, "C", "C", null, null, "TH", true, at),         // created in the new system after cut-over
        };

        var check = Verifier.Compare("customers", expected, actual, c => c.Id, null);

        Assert.Equal(2, check.RowMismatches); // id 1 changed, id 2 missing
        Assert.False(check.Passed);
    }
}
