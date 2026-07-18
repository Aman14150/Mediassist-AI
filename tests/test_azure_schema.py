import pathlib
import re
import unittest


ROOT = pathlib.Path(__file__).parents[1]
AZURE_SCHEMA = ROOT / "database" / "schema.sqlsrv.sql"


class AzureSqlSchemaTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.sql = AZURE_SCHEMA.read_text(encoding="utf-8")

    def test_all_application_tables_and_indexes_are_declared(self):
        for table in (
            "departments",
            "doctors",
            "doctor_departments",
            "availability",
            "availability_overrides",
            "patients",
            "appointments",
            "rate_limits",
            "app_metadata",
        ):
            self.assertRegex(self.sql, rf"CREATE TABLE\s+{table}\s*\(")
        for index in ("one_patient_per_time", "one_doctor_per_time", "appointments_by_date"):
            self.assertIn(index, self.sql)

    def test_schema_is_idempotent_and_marks_version(self):
        self.assertGreaterEqual(self.sql.count("IF OBJECT_ID"), 9)
        self.assertIn("MERGE departments", self.sql)
        self.assertIn("MERGE doctors", self.sql)
        self.assertIn("N'schema_version', N'2'", self.sql)

    def test_seed_counts_match_sqlite_roster(self):
        department_batch = re.search(r"MERGE departments.*?AS source\(slug", self.sql, re.S).group(0)
        doctor_batch = re.search(r"MERGE doctors.*?AS source\(\[name\]\)", self.sql, re.S).group(0)
        assignment_batch = re.search(r"MERGE doctor_departments.*?\) x\(doctor_name", self.sql, re.S).group(0)
        self.assertEqual(department_batch.count("(N'"), 15)
        self.assertEqual(doctor_batch.count("(N'Dr."), 42)
        self.assertEqual(assignment_batch.count("(N'Dr."), 45)

    def test_azure_connection_requires_encryption(self):
        database_php = (ROOT / "app" / "database.php").read_text(encoding="utf-8")
        self.assertIn("Encrypt=yes", database_php)
        self.assertIn("TrustServerCertificate=no", database_php)

    def test_sql_server_rate_limit_avoids_reserved_time_aliases(self):
        security_php = (ROOT / "app" / "security.php").read_text(encoding="utf-8")
        self.assertIn("source.current_epoch", security_php)
        self.assertIn("source.cutoff_epoch", security_php)
        self.assertNotIn("source.current_time", security_php)
        self.assertNotIn("source.cutoff_time", security_php)


if __name__ == "__main__":
    unittest.main()
