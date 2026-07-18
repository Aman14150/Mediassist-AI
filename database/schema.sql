CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL UNIQUE,
    consultation_fee_inr INTEGER NOT NULL CHECK (consultation_fee_inr >= 0),
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1))
);

CREATE TABLE IF NOT EXISTS doctors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1))
);

CREATE TABLE IF NOT EXISTS doctor_departments (
    doctor_id INTEGER NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
    department_id INTEGER NOT NULL REFERENCES departments(id) ON DELETE CASCADE,
    specialty_note TEXT,
    PRIMARY KEY (doctor_id, department_id)
);

CREATE TABLE IF NOT EXISTS availability (
    doctor_id INTEGER NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
    weekday INTEGER NOT NULL CHECK (weekday BETWEEN 1 AND 6),
    slot_time TEXT NOT NULL CHECK (slot_time GLOB '[0-2][0-9]:[0-5][0-9]'),
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
    PRIMARY KEY (doctor_id, weekday, slot_time)
);

CREATE TABLE IF NOT EXISTS availability_overrides (
    doctor_id INTEGER NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
    appointment_date TEXT NOT NULL,
    slot_time TEXT NOT NULL,
    is_available INTEGER NOT NULL CHECK (is_available IN (0, 1)),
    PRIMARY KEY (doctor_id, appointment_date, slot_time)
);

CREATE TABLE IF NOT EXISTS patients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    phone TEXT NOT NULL UNIQUE,
    email TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_reference TEXT NOT NULL UNIQUE,
    patient_id INTEGER NOT NULL REFERENCES patients(id),
    doctor_id INTEGER NOT NULL REFERENCES doctors(id),
    department_id INTEGER NOT NULL REFERENCES departments(id),
    booked_fee_inr INTEGER NOT NULL CHECK (booked_fee_inr >= 0),
    appointment_date TEXT NOT NULL,
    slot_time TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'confirmed' CHECK (status IN ('confirmed', 'cancelled')),
    source TEXT NOT NULL CHECK (source IN ('website', 'elevenlabs', 'staff')),
    patient_confirmed_at TEXT NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS one_patient_per_time
    ON appointments(patient_id, appointment_date, slot_time)
    WHERE status = 'confirmed';
CREATE UNIQUE INDEX IF NOT EXISTS one_doctor_per_time
    ON appointments(doctor_id, appointment_date, slot_time)
    WHERE status = 'confirmed';
CREATE INDEX IF NOT EXISTS appointments_by_date ON appointments(appointment_date, slot_time);

CREATE TABLE IF NOT EXISTS rate_limits (
    key TEXT PRIMARY KEY,
    window_started_at INTEGER NOT NULL,
    request_count INTEGER NOT NULL
);

INSERT OR IGNORE INTO departments (slug, name, consultation_fee_inr) VALUES
('general-physician', 'General Physician', 300),
('opd-consultation', 'OPD Consultation', 400),
('telemedicine', 'Telemedicine', 300),
('general-medicine', 'General Medicine', 500),
('dental-care', 'Dental Care', 500),
('cardiology', 'Cardiology', 700),
('neurology', 'Neurology', 700),
('psychology', 'Psychology', 600),
('orthopedics', 'Orthopedics', 600),
('physiotherapy', 'Physiotherapy', 400),
('nutrition-dietetics', 'Nutrition & Dietetics', 400),
('dermatology', 'Dermatology', 500),
('pediatrics', 'Pediatrics', 500),
('oncology', 'Oncology', 800),
('radiology', 'Radiology', 500),
('emergency-medicine', 'Emergency Medicine', 800);

INSERT OR IGNORE INTO doctors (name) VALUES
('Dr. Nitin Patel'), ('Dr. Anita Rao'), ('Dr. Rohan Mehta'),
('Dr. Lakshmi Iyer'), ('Dr. Pooja Mishra'),
('Dr. Sameer Kulkarni'), ('Dr. Farah Ansari'), ('Dr. Rahul Deshmukh'),
('Dr. Kavita Menon'),
('Dr. Neha Kapoor'), ('Dr. Arjun Nair'), ('Dr. Priya Desai'),
('Dr. Marcus Johnson'), ('Dr. Priya Sharma'), ('Dr. Amit Verma'),
('Dr. Sarah Williams'), ('Dr. Karan Malhotra'), ('Dr. Meera Iyer'),
('Dr. Michael Chen'), ('Dr. Sandeep Kulkarni'), ('Dr. Nisha Bhatia'),
('Dr. Rhea Kapoor'), ('Dr. Vikram Nair'), ('Dr. Aditi Sharma'),
('Dr. Meera Joshi'), ('Dr. Anjali Rao'), ('Dr. Kabir Sen'),
('Dr. David Thompson'), ('Dr. Ayesha Khan'), ('Dr. Rahul Sethi'),
('Dr. Emily Rodriguez'), ('Dr. Sneha Kulkarni'), ('Dr. Vivek Rao'),
('Dr. Lisa Anderson'), ('Dr. Nandita Shah'), ('Dr. Harish Menon'),
('Dr. Jennifer Lee'), ('Dr. Sanjay Nair'), ('Dr. Anika Bose'),
('Dr. Robert Martinez'), ('Dr. Farah Siddiqui'), ('Dr. Manoj Patel'),
('Dr. Maya Kapoor'), ('Dr. Arvind Shah'), ('Dr. Neelam Joshi');

INSERT OR IGNORE INTO doctor_departments (doctor_id, department_id, specialty_note)
SELECT d.id, p.id, x.note FROM (
    SELECT 'Dr. Nitin Patel' doctor, 'General Physician' department, NULL note UNION ALL
    SELECT 'Dr. Anita Rao', 'General Physician', NULL UNION ALL SELECT 'Dr. Rohan Mehta', 'General Physician', NULL UNION ALL
    SELECT 'Dr. Nitin Patel', 'OPD Consultation', 'Physician' UNION ALL SELECT 'Dr. Lakshmi Iyer', 'OPD Consultation', 'Pediatrician' UNION ALL SELECT 'Dr. Pooja Mishra', 'OPD Consultation', 'Gynecologist' UNION ALL
    SELECT 'Dr. Sameer Kulkarni', 'Telemedicine', 'Video Consultation' UNION ALL SELECT 'Dr. Farah Ansari', 'Telemedicine', 'Phone Consultation' UNION ALL SELECT 'Dr. Rahul Deshmukh', 'Telemedicine', 'Video or Phone Consultation' UNION ALL
    SELECT 'Dr. Anita Rao', 'General Medicine', NULL UNION ALL SELECT 'Dr. Rohan Mehta', 'General Medicine', NULL UNION ALL SELECT 'Dr. Kavita Menon', 'General Medicine', NULL UNION ALL
    SELECT 'Dr. Neha Kapoor', 'Dental Care', NULL UNION ALL SELECT 'Dr. Arjun Nair', 'Dental Care', NULL UNION ALL SELECT 'Dr. Priya Desai', 'Dental Care', NULL UNION ALL
    SELECT 'Dr. Marcus Johnson', 'Cardiology', NULL UNION ALL SELECT 'Dr. Priya Sharma', 'Cardiology', NULL UNION ALL SELECT 'Dr. Amit Verma', 'Cardiology', NULL UNION ALL
    SELECT 'Dr. Sarah Williams', 'Neurology', NULL UNION ALL SELECT 'Dr. Karan Malhotra', 'Neurology', NULL UNION ALL SELECT 'Dr. Meera Iyer', 'Neurology', NULL UNION ALL
    SELECT 'Dr. Maya Kapoor', 'Psychology', 'Counselling Psychologist' UNION ALL SELECT 'Dr. Arvind Shah', 'Psychology', 'Clinical Psychologist' UNION ALL SELECT 'Dr. Neelam Joshi', 'Psychology', 'Counselling Psychologist' UNION ALL
    SELECT 'Dr. Michael Chen', 'Orthopedics', NULL UNION ALL SELECT 'Dr. Sandeep Kulkarni', 'Orthopedics', NULL UNION ALL SELECT 'Dr. Nisha Bhatia', 'Orthopedics', NULL UNION ALL
    SELECT 'Dr. Rhea Kapoor', 'Physiotherapy', NULL UNION ALL SELECT 'Dr. Vikram Nair', 'Physiotherapy', NULL UNION ALL SELECT 'Dr. Aditi Sharma', 'Physiotherapy', NULL UNION ALL
    SELECT 'Dr. Meera Joshi', 'Nutrition & Dietetics', NULL UNION ALL SELECT 'Dr. Anjali Rao', 'Nutrition & Dietetics', NULL UNION ALL SELECT 'Dr. Kabir Sen', 'Nutrition & Dietetics', NULL UNION ALL
    SELECT 'Dr. David Thompson', 'Dermatology', NULL UNION ALL SELECT 'Dr. Ayesha Khan', 'Dermatology', NULL UNION ALL SELECT 'Dr. Rahul Sethi', 'Dermatology', NULL UNION ALL
    SELECT 'Dr. Emily Rodriguez', 'Pediatrics', NULL UNION ALL SELECT 'Dr. Sneha Kulkarni', 'Pediatrics', NULL UNION ALL SELECT 'Dr. Vivek Rao', 'Pediatrics', NULL UNION ALL
    SELECT 'Dr. Lisa Anderson', 'Oncology', NULL UNION ALL SELECT 'Dr. Nandita Shah', 'Oncology', NULL UNION ALL SELECT 'Dr. Harish Menon', 'Oncology', NULL UNION ALL
    SELECT 'Dr. Jennifer Lee', 'Radiology', NULL UNION ALL SELECT 'Dr. Sanjay Nair', 'Radiology', NULL UNION ALL SELECT 'Dr. Anika Bose', 'Radiology', NULL UNION ALL
    SELECT 'Dr. Robert Martinez', 'Emergency Medicine', NULL UNION ALL SELECT 'Dr. Farah Siddiqui', 'Emergency Medicine', NULL UNION ALL SELECT 'Dr. Manoj Patel', 'Emergency Medicine', NULL
) x JOIN doctors d ON d.name = x.doctor JOIN departments p ON p.name = x.department;

INSERT OR IGNORE INTO availability (doctor_id, weekday, slot_time)
SELECT d.id, weekdays.value, times.value
FROM doctors d
CROSS JOIN (SELECT 1 value UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) weekdays
CROSS JOIN (SELECT '10:00' value UNION ALL SELECT '10:15' UNION ALL SELECT '10:30' UNION ALL SELECT '11:00' UNION ALL SELECT '11:15' UNION ALL SELECT '12:00' UNION ALL SELECT '15:00' UNION ALL SELECT '15:15' UNION ALL SELECT '16:00' UNION ALL SELECT '16:15' UNION ALL SELECT '17:00') times;

PRAGMA user_version = 3;
