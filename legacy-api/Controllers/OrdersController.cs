using System.Data;
using System.Text.Json;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Data.SqlClient;

namespace LegacyOrderApi.Controllers
{
    [Route("api/orders")]
    public class OrdersController : LegacyControllerBase
    {
        private static readonly string[] StatusText = { "", "Open", "Confirmed", "Shipped", "Cancelled" };

        [HttpGet("")]
        public IActionResult GetAll(int? customerId = null, int? status = null)
        {
            var dt = SqlHelper.ExecuteDataTable("usp_GetOrders",
                SqlHelper.P("@CustID", customerId), SqlHelper.P("@Status", status));
            var list = new List<object>();
            foreach (DataRow r in dt.Rows)
            {
                list.Add(MapHeader(r, includeRemarks: false));
            }
            return Ok(list);
        }

        [HttpGet("{id:int}")]
        public IActionResult Get(int id)
        {
            var result = LoadOrder(id);
            if (result == null) return Error(404, "Order not found");
            return Ok(result);
        }

        [HttpPost("")]
        public IActionResult Create([FromBody] JsonElement body)
        {
            // Manual parsing and validation, the way this controller has always done it.
            var modelState = new Dictionary<string, string[]>();
            int customerId = body.TryGetProperty("CustomerId", out var c) && c.ValueKind == JsonValueKind.Number ? c.GetInt32() : 0;
            string origin = body.TryGetProperty("Origin", out var o) ? o.GetString() : null;
            string dest = body.TryGetProperty("Destination", out var d) ? d.GetString() : null;
            string currency = body.TryGetProperty("Currency", out var cur) ? cur.GetString() : "USD";
            string remarks = body.TryGetProperty("Remarks", out var rem) && rem.ValueKind == JsonValueKind.String ? rem.GetString() : null;

            if (customerId <= 0) modelState["CustomerId"] = new[] { "CustomerId is required." };
            if (origin == null || origin.Length != 5) modelState["Origin"] = new[] { "Origin must be a 5-letter port code." };
            if (dest == null || dest.Length != 5) modelState["Destination"] = new[] { "Destination must be a 5-letter port code." };
            if (currency == null || currency.Length != 3) modelState["Currency"] = new[] { "Currency must be a 3-letter code." };

            var lines = new List<(string Descr, int Qty, decimal Price)>();
            if (!body.TryGetProperty("Lines", out var linesEl) || linesEl.ValueKind != JsonValueKind.Array || linesEl.GetArrayLength() == 0)
            {
                modelState["Lines"] = new[] { "At least one line is required." };
            }
            else
            {
                foreach (var l in linesEl.EnumerateArray())
                {
                    var descr = l.TryGetProperty("Description", out var de) ? de.GetString() : null;
                    var qty = l.TryGetProperty("Quantity", out var q) && q.ValueKind == JsonValueKind.Number ? q.GetInt32() : 0;
                    var price = l.TryGetProperty("UnitPrice", out var p) && p.ValueKind == JsonValueKind.Number ? p.GetDecimal() : 0m;
                    if (string.IsNullOrWhiteSpace(descr) || qty <= 0 || price <= 0)
                    {
                        modelState["Lines"] = new[] { "Each line needs a Description, a Quantity > 0 and a UnitPrice > 0." };
                        break;
                    }
                    lines.Add((descr, qty, price));
                }
            }
            if (modelState.Count > 0) return Invalid(modelState);

            var cust = SqlHelper.ExecuteDataTable("usp_GetCustomerById", SqlHelper.P("@CustID", customerId));
            if (cust.Rows.Count == 0) return Invalid(new Dictionary<string, string[]> { ["CustomerId"] = new[] { "Customer does not exist." } });

            int orderId;
            using (var conn = new SqlConnection(SqlHelper.ConnectionString))
            {
                conn.Open();
                using (var tx = conn.BeginTransaction())
                {
                    orderId = Convert.ToInt32(SqlHelper.ExecuteScalar(conn, tx, "usp_InsertOrderHeader",
                        SqlHelper.P("@CustID", customerId), SqlHelper.P("@Origin", origin), SqlHelper.P("@Dest", dest),
                        SqlHelper.P("@Remarks", remarks), SqlHelper.P("@Curr", currency)));
                    var n = 1;
                    foreach (var line in lines)
                    {
                        SqlHelper.ExecuteScalar(conn, tx, "usp_InsertOrderLine",
                            SqlHelper.P("@OrderID", orderId), SqlHelper.P("@LineNum", n++), SqlHelper.P("@Descr", line.Descr),
                            SqlHelper.P("@Qty", line.Qty), SqlHelper.P("@UnitPrice", line.Price));
                    }
                    tx.Commit();
                }
            }

            Response.Headers["Location"] = "/api/orders/" + orderId;
            return StatusCode(201, LoadOrder(orderId));
        }

        [HttpPost("{id:int}/cancel")]
        public IActionResult Cancel(int id)
        {
            try
            {
                SqlHelper.ExecuteDataTable("usp_CancelOrder", SqlHelper.P("@OrderID", id));
            }
            catch (SqlException ex)
            {
                if (ex.Message.Contains("not found")) return Error(404, "Order not found");
                return Error(409, ex.Message);
            }
            return Ok(LoadOrder(id));
        }

        private Dictionary<string, object> LoadOrder(int id)
        {
            var ds = SqlHelper.ExecuteDataSet("usp_GetOrderById", SqlHelper.P("@OrderID", id));
            if (ds.Tables[0].Rows.Count == 0) return null;

            var order = MapHeader(ds.Tables[0].Rows[0], includeRemarks: true);
            var lines = new List<object>();
            foreach (DataRow r in ds.Tables[1].Rows)
            {
                lines.Add(new Dictionary<string, object>
                {
                    ["LineNo"] = r["LineNum"],
                    ["Description"] = r["Descr"],
                    ["Quantity"] = r["Qty"],
                    ["UnitPrice"] = Money(r["UnitPrice"]),
                    ["Amount"] = Money(r["LineAmt"]),
                });
            }
            order["Lines"] = lines;
            return order;
        }

        private static Dictionary<string, object> MapHeader(DataRow r, bool includeRemarks)
        {
            var status = Convert.ToInt32(r["Status"]);
            var result = new Dictionary<string, object>
            {
                ["OrderId"] = r["OrderID"],
                ["OrderNo"] = r["OrderNo"],
                ["CustomerId"] = r["CustID"],
                ["OrderDate"] = Date(r["OrderDt"]),
                ["Status"] = status,
                ["StatusText"] = StatusText[status],
                ["Origin"] = r["Origin"],
                ["Destination"] = r["Dest"],
                ["Total"] = Money(r["TotalAmt"]),
                ["Currency"] = r["Curr"],
            };
            if (includeRemarks)
            {
                result["Remarks"] = r["Remarks"] == DBNull.Value ? null : r["Remarks"];
            }
            return result;
        }
    }
}
