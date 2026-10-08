using LegacyOrderApi;
using Microsoft.AspNetCore.Mvc;

var builder = WebApplication.CreateBuilder(args);

builder.Services
    .AddControllers(o => o.Filters.Add<ApiKeyFilter>())
    // Web API 2 serialized with PascalCase property names; existing clients depend on it.
    .AddJsonOptions(o => o.JsonSerializerOptions.PropertyNamingPolicy = null)
    // Keep model errors in our own legacy format instead of ASP.NET Core's ProblemDetails.
    .ConfigureApiBehaviorOptions(o => o.SuppressModelStateInvalidFilter = true);

SqlHelper.ConnectionString = builder.Configuration.GetConnectionString("Legacy");

var app = builder.Build();

app.UseExceptionHandler(errorApp => errorApp.Run(async context =>
{
    context.Response.StatusCode = 500;
    await context.Response.WriteAsJsonAsync(new Dictionary<string, object> { ["Message"] = "An error has occurred." });
}));

app.MapGet("/health", () => "Healthy");
app.MapControllers();
app.Run();

/// <summary>Static API key per client, like the original system. The new system accepts the same key on /api/*.</summary>
internal sealed class ApiKeyFilter(IConfiguration config) : Microsoft.AspNetCore.Mvc.Filters.IActionFilter
{
    public void OnActionExecuting(Microsoft.AspNetCore.Mvc.Filters.ActionExecutingContext context)
    {
        var expected = config["ApiKey"];
        var provided = context.HttpContext.Request.Headers["X-Api-Key"].ToString();
        if (string.IsNullOrEmpty(expected) || provided != expected)
        {
            context.Result = new ObjectResult(new Dictionary<string, object>
            {
                ["Message"] = "Authorization has been denied for this request.",
            })
            { StatusCode = 401 };
        }
    }

    public void OnActionExecuted(Microsoft.AspNetCore.Mvc.Filters.ActionExecutedContext context) { }
}
