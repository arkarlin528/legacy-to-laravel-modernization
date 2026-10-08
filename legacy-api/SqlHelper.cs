using System.Data;
using Microsoft.Data.SqlClient;

namespace LegacyOrderApi
{
    // Classic static data-access helper. Every controller calls stored procedures through here.
    public static class SqlHelper
    {
        public static string ConnectionString;

        public static DataSet ExecuteDataSet(string procName, params SqlParameter[] parameters)
        {
            using (var conn = new SqlConnection(ConnectionString))
            using (var cmd = new SqlCommand(procName, conn))
            {
                cmd.CommandType = CommandType.StoredProcedure;
                cmd.Parameters.AddRange(parameters);
                var ds = new DataSet();
                using (var da = new SqlDataAdapter(cmd))
                {
                    da.Fill(ds);
                }
                return ds;
            }
        }

        public static DataTable ExecuteDataTable(string procName, params SqlParameter[] parameters)
        {
            var ds = ExecuteDataSet(procName, parameters);
            return ds.Tables.Count > 0 ? ds.Tables[0] : new DataTable();
        }

        public static object ExecuteScalar(SqlConnection conn, SqlTransaction tx, string procName, params SqlParameter[] parameters)
        {
            using (var cmd = new SqlCommand(procName, conn, tx))
            {
                cmd.CommandType = CommandType.StoredProcedure;
                cmd.Parameters.AddRange(parameters);
                return cmd.ExecuteScalar();
            }
        }

        public static SqlParameter P(string name, object value)
        {
            return new SqlParameter(name, value ?? DBNull.Value);
        }
    }
}
