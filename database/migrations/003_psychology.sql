BEGIN IMMEDIATE;

INSERT OR IGNORE INTO departments (slug, name, consultation_fee_inr)
VALUES ('psychology', 'Psychology', 600);

INSERT OR IGNORE INTO doctors (name) VALUES
('Dr. Maya Kapoor'),
('Dr. Arvind Shah'),
('Dr. Neelam Joshi');

INSERT OR IGNORE INTO doctor_departments (doctor_id, department_id, specialty_note)
SELECT d.id, p.id, x.note
FROM (
    SELECT 'Dr. Maya Kapoor' doctor, 'Counselling Psychologist' note UNION ALL
    SELECT 'Dr. Arvind Shah', 'Clinical Psychologist' UNION ALL
    SELECT 'Dr. Neelam Joshi', 'Counselling Psychologist'
) x
JOIN doctors d ON d.name = x.doctor
JOIN departments p ON p.slug = 'psychology';

INSERT OR IGNORE INTO availability (doctor_id, weekday, slot_time)
SELECT d.id, weekdays.value, times.value
FROM doctors d
CROSS JOIN (SELECT 1 value UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) weekdays
CROSS JOIN (SELECT '10:00' value UNION ALL SELECT '10:15' UNION ALL SELECT '10:30' UNION ALL SELECT '11:00' UNION ALL SELECT '11:15' UNION ALL SELECT '12:00' UNION ALL SELECT '15:00' UNION ALL SELECT '15:15' UNION ALL SELECT '16:00' UNION ALL SELECT '16:15' UNION ALL SELECT '17:00') times
WHERE d.name IN ('Dr. Maya Kapoor', 'Dr. Arvind Shah', 'Dr. Neelam Joshi');

PRAGMA user_version = 3;
COMMIT;
