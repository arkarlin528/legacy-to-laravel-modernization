/* Stored procedures of the legacy system. Business rules live here, as they often do in older systems. */

CREATE OR ALTER PROCEDURE dbo.usp_GetCustomers
AS
BEGIN
    SET NOCOUNT ON;
    SELECT CustID, CustCode, CustNm, Email, Phone, Country, IsActive, CreatedDt
    FROM dbo.tblCustomer
    ORDER BY CustID;
END
GO

CREATE OR ALTER PROCEDURE dbo.usp_GetCustomerById @CustID INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT CustID, CustCode, CustNm, Email, Phone, Country, IsActive, CreatedDt
    FROM dbo.tblCustomer
    WHERE CustID = @CustID;
END
GO

CREATE OR ALTER PROCEDURE dbo.usp_InsertCustomer
    @CustCode VARCHAR(10), @CustNm NVARCHAR(100), @Email NVARCHAR(200), @Phone VARCHAR(30), @Country CHAR(2)
AS
BEGIN
    SET NOCOUNT ON;
    IF EXISTS (SELECT 1 FROM dbo.tblCustomer WHERE CustCode = @CustCode)
    BEGIN
        RAISERROR('Customer code already exists', 16, 1);
        RETURN;
    END

    INSERT INTO dbo.tblCustomer (CustCode, CustNm, Email, Phone, Country, IsActive, CreatedDt)
    VALUES (UPPER(@CustCode), @CustNm, @Email, @Phone, UPPER(@Country), 'Y', GETDATE());

    SELECT CAST(SCOPE_IDENTITY() AS INT) AS CustID;
END
GO

CREATE OR ALTER PROCEDURE dbo.usp_GetOrders @CustID INT = NULL, @Status TINYINT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SELECT OrderID, OrderNo, CustID, OrderDt, Status, Origin, Dest, TotalAmt, Curr
    FROM dbo.tblOrder
    WHERE (@CustID IS NULL OR CustID = @CustID)
      AND (@Status IS NULL OR Status = @Status)
    ORDER BY OrderID;
END
GO

-- Returns two result sets: the header, then the lines.
CREATE OR ALTER PROCEDURE dbo.usp_GetOrderById @OrderID INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT OrderID, OrderNo, CustID, OrderDt, Status, Origin, Dest, Remarks, TotalAmt, Curr
    FROM dbo.tblOrder WHERE OrderID = @OrderID;

    SELECT LineNum, Descr, Qty, UnitPrice, LineAmt
    FROM dbo.tblOrderLine WHERE OrderID = @OrderID ORDER BY LineNum;
END
GO

CREATE OR ALTER PROCEDURE dbo.usp_InsertOrderHeader
    @CustID INT, @Origin VARCHAR(5), @Dest VARCHAR(5), @Remarks NVARCHAR(MAX), @Curr CHAR(3)
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @n INT;
    UPDATE dbo.tblSequence WITH (UPDLOCK) SET @n = NextVal, NextVal = NextVal + 1 WHERE SeqName = 'ORDER';

    INSERT INTO dbo.tblOrder (OrderNo, CustID, OrderDt, Status, Origin, Dest, Remarks, TotalAmt, Curr)
    VALUES ('ORD-' + CAST(YEAR(GETDATE()) AS VARCHAR(4)) + '-' + RIGHT('000000' + CAST(@n AS VARCHAR(6)), 6),
            @CustID, GETDATE(), 1, UPPER(@Origin), UPPER(@Dest), @Remarks, 0, UPPER(@Curr));

    SELECT CAST(SCOPE_IDENTITY() AS INT) AS OrderID;
END
GO

CREATE OR ALTER PROCEDURE dbo.usp_InsertOrderLine
    @OrderID INT, @LineNum INT, @Descr NVARCHAR(200), @Qty INT, @UnitPrice MONEY
AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO dbo.tblOrderLine (OrderID, LineNum, Descr, Qty, UnitPrice)
    VALUES (@OrderID, @LineNum, @Descr, @Qty, @UnitPrice);

    UPDATE dbo.tblOrder
    SET TotalAmt = (SELECT SUM(LineAmt) FROM dbo.tblOrderLine WHERE OrderID = @OrderID)
    WHERE OrderID = @OrderID;
END
GO

CREATE OR ALTER PROCEDURE dbo.usp_CancelOrder @OrderID INT
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @Status TINYINT = (SELECT Status FROM dbo.tblOrder WHERE OrderID = @OrderID);
    IF @Status IS NULL
    BEGIN
        RAISERROR('Order not found', 16, 1);
        RETURN;
    END
    IF @Status = 3
    BEGIN
        RAISERROR('Shipped orders cannot be cancelled', 16, 1);
        RETURN;
    END
    IF @Status = 4
    BEGIN
        RAISERROR('Order is already cancelled', 16, 1);
        RETURN;
    END
    UPDATE dbo.tblOrder SET Status = 4 WHERE OrderID = @OrderID;
END
GO

CREATE OR ALTER PROCEDURE dbo.usp_GetInvoicesByCustomer @CustID INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT i.InvID, i.InvNo, i.OrderID, i.InvDt, i.DueDt, i.Amount, i.PaidFlag, i.PaidDt
    FROM dbo.tblInvoice i
    JOIN dbo.tblOrder o ON o.OrderID = i.OrderID
    WHERE o.CustID = @CustID
    ORDER BY i.InvID;
END
GO
