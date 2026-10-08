FROM mcr.microsoft.com/dotnet/sdk:8.0 AS build
WORKDIR /src
COPY global.json ./
COPY src/DataMigrator.csproj src/
RUN dotnet restore src/DataMigrator.csproj
COPY src/ src/
RUN dotnet publish src/DataMigrator.csproj -c Release -o /app --no-restore /p:UseAppHost=false

# Runtime image needs ICU for the Asia/Bangkok time zone lookup (the default image has it).
FROM mcr.microsoft.com/dotnet/runtime:8.0
WORKDIR /app
COPY --from=build /app .
USER $APP_UID
# Connection strings come from LEGACY_DB and TARGET_DB.
ENTRYPOINT ["dotnet", "DataMigrator.dll", "--report", "/reports/migration-report.md"]
