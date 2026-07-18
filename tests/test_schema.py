import pathlib
import sqlite3
import unittest


SCHEMA = pathlib.Path(__file__).parents[1] / "database" / "schema.sql"


class BookingSchemaTests(unittest.TestCase):
    def setUp(self):
        self.db = sqlite3.connect(":memory:")
        self.db.executescript(SCHEMA.read_text(encoding="utf-8"))

    def tearDown(self):
        self.db.close()

    def test_seed_is_complete_and_idempotent(self):
        self.db.executescript(SCHEMA.read_text(encoding="utf-8"))
        counts = {
            table: self.db.execute(f"SELECT count(*) FROM {table}").fetchone()[0]
            for table in ("departments", "doctors", "doctor_departments", "availability")
        }
        self.assertEqual(counts, {
            "departments": 16,
            "doctors": 45,
            "doctor_departments": 48,
            "availability": 2970,
        })
        self.assertEqual(self.db.execute("PRAGMA user_version").fetchone()[0], 3)
        fees = dict(self.db.execute("SELECT slug, consultation_fee_inr FROM departments"))
        self.assertEqual(fees["general-physician"], 300)
        self.assertEqual(fees["cardiology"], 700)
        self.assertEqual(fees["psychology"], 600)

    def test_doctor_and_patient_duplicates_are_blocked(self):
        self.db.execute("INSERT INTO patients (full_name, phone) VALUES (?, ?)", ("Patient A", "+9111111111"))
        self.db.execute("INSERT INTO patients (full_name, phone) VALUES (?, ?)", ("Patient B", "+9222222222"))
        department = self.db.execute("SELECT id FROM departments WHERE slug = ?", ("general-physician",)).fetchone()[0]
        doctors = [row[0] for row in self.db.execute(
            "SELECT doctor_id FROM doctor_departments WHERE department_id = ? ORDER BY doctor_id LIMIT 2", (department,)
        )]
        sql = """INSERT INTO appointments
            (booking_reference, patient_id, doctor_id, department_id, booked_fee_inr, appointment_date, slot_time, source, patient_confirmed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'website', CURRENT_TIMESTAMP)"""
        self.db.execute(sql, ("ONE", 1, doctors[0], department, 300, "2026-07-20", "10:00"))

        with self.assertRaises(sqlite3.IntegrityError):
            self.db.execute(sql, ("TWO", 2, doctors[0], department, 300, "2026-07-20", "10:00"))
        with self.assertRaises(sqlite3.IntegrityError):
            self.db.execute(sql, ("THREE", 1, doctors[1], department, 300, "2026-07-20", "10:00"))


if __name__ == "__main__":
    unittest.main()
