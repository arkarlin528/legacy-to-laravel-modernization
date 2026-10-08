using System.Data;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Data.SqlClient;

namespace LegacyOrderApi.Controllers
{
    [Route("api/customers")]
    public class CustomersController : LegacyControllerBase
    {
        [HttpGet("")]
        public IActionResult GetAll()
        {
            var dt = SqlHelper.ExecuteDataTable("usp_GetCustomers");
            var list = new List<object>();
            foreach (DataRow r in dt.Rows)
            {
                list.Add(MapCustomer(r));
            }
            return Ok(list);
        }

        [HttpGet("{id:int}")]
        public IActionResult Get(int id)
        {
            var dt = SqlHelper.ExecuteDataTable("usp_GetCustomerById", SqlHelper.P("@CustID", id));
            if (dt.Rows.Count == 0)
            {
                return Error(404, "Customer not found");
            }
            return Ok(MapCustomer(dt.Rows[0]));
        }

        [HttpPost("")]
        public IActionResult Create([FromBody] Dictionary<string, object> body)
        {
            if (body == null) return Error(400, "The request is invalid.");

            var code = Str(body, "Code");
            var name = Str(body, "Name");
            var email = Str(body, "Email");
            var phone = Str(body, "Phone");
            var country = Str(body, "Country") ?? "TH";

            var modelState = new Dictionary<string, string[]>();
            if (string.IsNullOrWhiteSpace(code) || code.Length > 10) modelState["Code"] = new[] { "Code is required (max 10 characters)." };
            if (string.IsNullOrWhiteSpace(name) || name.Length > 100) modelState["Name"] = new[] { "Name is required (max 100 characters)." };
            if (country.Length != 2) modelState["Country"] = new[] { "Country must be a 2-letter code." };
            if (email != null && !email.Contains("@")) modelState["Email"] = new[] { "Email is not valid." };
            if (modelState.Count > 0) return Invalid(modelState);

            int newId;
            try
            {
                var dt = SqlHelper.ExecuteDataTable("usp_InsertCustomer",
                    SqlHelper.P("@CustCode", code), SqlHelper.P("@CustNm", name), SqlHelper.P("@Email", email),
                    SqlHelper.P("@Phone", phone), SqlHelper.P("@Country", country));
                newId = Convert.ToInt32(dt.Rows[0]["CustID"]);
            }
            catch (SqlException ex) when (ex.Message.Contains("already exists"))
            {
                return Error(409, ex.Message);
            }

            var created = SqlHelper.ExecuteDataTable("usp_GetCustomerById", SqlHelper.P("@CustID", newId));
            Response.Headers["Location"] = "/api/customers/" + newId;
            return StatusCode(201, MapCustomer(created.Rows[0]));
        }

        [HttpGet("{id:int}/invoices")]
        public IActionResult Invoices(int id)
        {
            var cust = SqlHelper.ExecuteDataTable("usp_GetCustomerById", SqlHelper.P("@CustID", id));
            if (cust.Rows.Count == 0) return Error(404, "Customer not found");

            var dt = SqlHelper.ExecuteDataTable("usp_GetInvoicesByCustomer", SqlHelper.P("@CustID", id));
            var list = new List<object>();
            foreach (DataRow r in dt.Rows)
            {
                list.Add(new Dictionary<string, object>
                {
                    ["InvoiceId"] = r["InvID"],
                    ["InvoiceNo"] = r["InvNo"],
                    ["OrderId"] = r["OrderID"],
                    ["InvoiceDate"] = Date(r["InvDt"]),
                    ["DueDate"] = Date(r["DueDt"]),
                    ["Amount"] = Money(r["Amount"]),
                    ["IsPaid"] = (bool)r["PaidFlag"],
                    ["PaidDate"] = r["PaidDt"] == DBNull.Value ? null : Date(r["PaidDt"]),
                });
            }
            return Ok(list);
        }

        private static object MapCustomer(DataRow r)
        {
            return new Dictionary<string, object>
            {
                ["CustomerId"] = r["CustID"],
                ["Code"] = r["CustCode"],
                ["Name"] = r["CustNm"],
                ["Email"] = r["Email"] == DBNull.Value ? null : r["Email"],
                ["Phone"] = r["Phone"] == DBNull.Value ? null : r["Phone"],
                ["Country"] = r["Country"],
                ["IsActive"] = r["IsActive"].ToString().ToUpper() == "Y",
                ["CreatedDate"] = Date(r["CreatedDt"]),
            };
        }
    }
}
