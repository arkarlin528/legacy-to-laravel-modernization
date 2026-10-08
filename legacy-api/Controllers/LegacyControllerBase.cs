using Microsoft.AspNetCore.Mvc;

namespace LegacyOrderApi.Controllers
{
    // Reproduces ASP.NET Web API 2 conventions that existing clients parse:
    //   errors:      { "Message": "..." }
    //   validation:  { "Message": "The request is invalid.", "ModelState": { "Field": ["..."] } }
    //   dates:       local time, no offset, e.g. "2024-03-01T09:30:00"
    [ApiController]
    public abstract class LegacyControllerBase : ControllerBase
    {
        protected IActionResult Error(int status, string message)
        {
            return StatusCode(status, new Dictionary<string, object> { ["Message"] = message });
        }

        protected IActionResult Invalid(Dictionary<string, string[]> modelState)
        {
            return StatusCode(400, new Dictionary<string, object>
            {
                ["Message"] = "The request is invalid.",
                ["ModelState"] = modelState,
            });
        }

        protected static string Str(Dictionary<string, object> body, string key)
        {
            return body.TryGetValue(key, out var v) && v != null ? v.ToString() : null;
        }

        protected static string Date(object value)
        {
            return ((DateTime)value).ToString("yyyy-MM-ddTHH:mm:ss");
        }

        protected static decimal Money(object value)
        {
            return Math.Round(Convert.ToDecimal(value), 2);
        }
    }
}
