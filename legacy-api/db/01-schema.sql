/*
  "Legacy" order system schema (SQL Server).

  INTENTIONALLY OLD-STYLE. This is a re-creation of the kind of schema typically found in a
  10+ year-old line-of-business system, so the modernization has something realistic to migrate:
    - tbl prefixes, abbreviated column names (CustNm, Curr, Descr)
    - business rules inside stored procedures
    - 'Y'/'N' char flags next to bit flags
    - datetime columns in *local* (Bangkok, UTC+7) time with no offset
    - money columns, a redundant PaidFlag next to PaidDt
  All data is fictional. No real company's code or data is used.
*/
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
GO

IF OBJECT_ID('dbo.tblInvoice', 'U') IS NOT NULL DROP TABLE dbo.tblInvoice;
IF OBJECT_ID('dbo.tblOrderLine', 'U') IS NOT NULL DROP TABLE dbo.tblOrderLine;
IF OBJECT_ID('dbo.tblOrder', 'U') IS NOT NULL DROP TABLE dbo.tblOrder;
IF OBJECT_ID('dbo.tblCustomer', 'U') IS NOT NULL DROP TABLE dbo.tblCustomer;
IF OBJECT_ID('dbo.tblSequence', 'U') IS NOT NULL DROP TABLE dbo.tblSequence;
GO

CREATE TABLE dbo.tblCustomer (
    CustID      INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblCustomer PRIMARY KEY,
    CustCode    VARCHAR(10)   NOT NULL,
    CustNm      NVARCHAR(100) NOT NULL,
    Email       NVARCHAR(200) NULL,
    Phone       VARCHAR(30)   NULL,
    Country     CHAR(2)       NOT NULL CONSTRAINT DF_tblCustomer_Country DEFAULT ('TH'),
    IsActive    CHAR(1)       NOT NULL CONSTRAINT DF_tblCustomer_IsActive DEFAULT ('Y'),
    CreatedDt   DATETIME      NOT NULL CONSTRAINT DF_tblCustomer_CreatedDt DEFAULT (GETDATE())
);
CREATE UNIQUE INDEX UX_tblCustomer_CustCode ON dbo.tblCustomer (CustCode);
GO

CREATE TABLE dbo.tblOrder (
    OrderID     INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblOrder PRIMARY KEY,
    OrderNo     VARCHAR(20)   NOT NULL,
    CustID      INT           NOT NULL CONSTRAINT FK_tblOrder_Cust REFERENCES dbo.tblCustomer (CustID),
    OrderDt     DATETIME      NOT NULL,
    Status      TINYINT       NOT NULL,  -- 1=Open 2=Confirmed 3=Shipped 4=Cancelled
    Origin      VARCHAR(5)    NOT NULL,
    Dest        VARCHAR(5)    NOT NULL,
    Remarks     NVARCHAR(MAX) NULL,
    TotalAmt    MONEY         NOT NULL,
    Curr        CHAR(3)       NOT NULL CONSTRAINT DF_tblOrder_Curr DEFAULT ('USD')
);
CREATE UNIQUE INDEX UX_tblOrder_OrderNo ON dbo.tblOrder (OrderNo);
CREATE INDEX IX_tblOrder_CustID ON dbo.tblOrder (CustID);
GO

CREATE TABLE dbo.tblOrderLine (
    LineID      INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblOrderLine PRIMARY KEY,
    OrderID     INT           NOT NULL CONSTRAINT FK_tblOrderLine_Order REFERENCES dbo.tblOrder (OrderID),
    LineNum     INT           NOT NULL,
    Descr       NVARCHAR(200) NOT NULL,
    Qty         INT           NOT NULL,
    UnitPrice   MONEY         NOT NULL,
    LineAmt     AS (CONVERT(MONEY, Qty * UnitPrice)) PERSISTED
);
GO

CREATE TABLE dbo.tblInvoice (
    InvID       INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblInvoice PRIMARY KEY,
    OrderID     INT           NOT NULL CONSTRAINT FK_tblInvoice_Order REFERENCES dbo.tblOrder (OrderID),
    InvNo       VARCHAR(20)   NOT NULL,
    InvDt       DATETIME      NOT NULL,
    DueDt       DATETIME      NOT NULL,
    Amount      MONEY         NOT NULL,
    PaidFlag    BIT           NOT NULL CONSTRAINT DF_tblInvoice_PaidFlag DEFAULT (0),
    PaidDt      DATETIME      NULL
);
GO

-- Hand-rolled counter table: the classic pre-SEQUENCE way to number orders.
CREATE TABLE dbo.tblSequence (
    SeqName     VARCHAR(30)   NOT NULL CONSTRAINT PK_tblSequence PRIMARY KEY,
    NextVal     INT           NOT NULL
);
GO
