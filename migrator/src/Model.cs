namespace DataMigrator;

// Rows as read from the legacy SQL Server database (raw, untouched).
internal sealed record LegacyCustomer(int CustID, string CustCode, string CustNm, string? Email, string? Phone,
    string Country, string IsActive, DateTime CreatedDt);

internal sealed record LegacyOrder(int OrderID, string OrderNo, int CustID, DateTime OrderDt, byte Status, string Origin,
    string Dest, string? Remarks, decimal TotalAmt, string Curr);

internal sealed record LegacyOrderLine(int LineID, int OrderID, int LineNum, string Descr, int Qty, decimal UnitPrice,
    decimal LineAmt);

internal sealed record LegacyInvoice(int InvID, int OrderID, string InvNo, DateTime InvDt, DateTime DueDt, decimal Amount,
    bool PaidFlag, DateTime? PaidDt);

// Rows in the shape of the new PostgreSQL schema (see laravel-app/database/migrations).
internal sealed record CustomerRow(long Id, string Code, string Name, string? Email, string? Phone, string Country,
    bool IsActive, DateTimeOffset CreatedAt);

internal sealed record OrderRow(long Id, string OrderNo, long CustomerId, DateTimeOffset OrderedAt, string Status,
    string Origin, string Destination, string? Remarks, decimal Total, string Currency);

internal sealed record OrderLineRow(long Id, long OrderId, int LineNo, string Description, int Quantity, decimal UnitPrice,
    decimal Amount);

internal sealed record InvoiceRow(long Id, string InvoiceNo, long OrderId, DateTimeOffset IssuedAt, DateTimeOffset DueAt,
    decimal Amount, bool IsPaid, DateTimeOffset? PaidAt);

internal sealed record LegacySnapshot(
    IReadOnlyList<LegacyCustomer> Customers,
    IReadOnlyList<LegacyOrder> Orders,
    IReadOnlyList<LegacyOrderLine> Lines,
    IReadOnlyList<LegacyInvoice> Invoices,
    int NextOrderNumber);

internal sealed record TargetSnapshot(
    IReadOnlyList<CustomerRow> Customers,
    IReadOnlyList<OrderRow> Orders,
    IReadOnlyList<OrderLineRow> Lines,
    IReadOnlyList<InvoiceRow> Invoices);

/// <summary>Something about the legacy data a human should know about. The migration still succeeds.</summary>
internal sealed record Finding(string Rule, string Detail, IReadOnlyList<string> Examples, int Count);
