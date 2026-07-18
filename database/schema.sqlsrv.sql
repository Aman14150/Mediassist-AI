SET XACT_ABORT ON;
GO

IF OBJECT_ID(N'dbo.departments', N'U') IS NULL
BEGIN
    CREATE TABLE departments (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_departments PRIMARY KEY,
        slug NVARCHAR(100) NOT NULL CONSTRAINT UQ_departments_slug UNIQUE,
        [name] NVARCHAR(150) NOT NULL CONSTRAINT UQ_departments_name UNIQUE,
        consultation_fee_inr INT NOT NULL CONSTRAINT CK_departments_fee CHECK (consultation_fee_inr >= 0),
        active BIT NOT NULL CONSTRAINT DF_departments_active DEFAULT 1
    );
END;
GO

IF OBJECT_ID(N'dbo.doctors', N'U') IS NULL
BEGIN
    CREATE TABLE doctors (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_doctors PRIMARY KEY,
        [name] NVARCHAR(150) NOT NULL CONSTRAINT UQ_doctors_name UNIQUE,
        active BIT NOT NULL CONSTRAINT DF_doctors_active DEFAULT 1
    );
END;
GO

IF OBJECT_ID(N'dbo.doctor_departments', N'U') IS NULL
BEGIN
    CREATE TABLE doctor_departments (
        doctor_id INT NOT NULL,
        department_id INT NOT NULL,
        specialty_note NVARCHAR(200) NULL,
        CONSTRAINT PK_doctor_departments PRIMARY KEY (doctor_id, department_id),
        CONSTRAINT FK_doctor_departments_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        CONSTRAINT FK_doctor_departments_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    );
END;
GO

IF OBJECT_ID(N'dbo.availability', N'U') IS NULL
BEGIN
    CREATE TABLE availability (
        doctor_id INT NOT NULL,
        weekday TINYINT NOT NULL CONSTRAINT CK_availability_weekday CHECK (weekday BETWEEN 1 AND 6),
        slot_time CHAR(5) NOT NULL,
        active BIT NOT NULL CONSTRAINT DF_availability_active DEFAULT 1,
        CONSTRAINT PK_availability PRIMARY KEY (doctor_id, weekday, slot_time),
        CONSTRAINT FK_availability_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    );
END;
GO

IF OBJECT_ID(N'dbo.availability_overrides', N'U') IS NULL
BEGIN
    CREATE TABLE availability_overrides (
        doctor_id INT NOT NULL,
        appointment_date DATE NOT NULL,
        slot_time CHAR(5) NOT NULL,
        is_available BIT NOT NULL,
        CONSTRAINT PK_availability_overrides PRIMARY KEY (doctor_id, appointment_date, slot_time),
        CONSTRAINT FK_availability_overrides_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
    );
END;
GO

IF OBJECT_ID(N'dbo.patients', N'U') IS NULL
BEGIN
    CREATE TABLE patients (
        id INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_patients PRIMARY KEY,
        full_name NVARCHAR(100) NOT NULL,
        phone VARCHAR(16) NOT NULL CONSTRAINT UQ_patients_phone UNIQUE,
        email NVARCHAR(254) NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_patients_created DEFAULT SYSUTCDATETIME(),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_patients_updated DEFAULT SYSUTCDATETIME()
    );
END;
GO

IF OBJECT_ID(N'dbo.appointments', N'U') IS NULL
BEGIN
    CREATE TABLE appointments (
        id BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_appointments PRIMARY KEY,
        booking_reference VARCHAR(40) NOT NULL CONSTRAINT UQ_appointments_reference UNIQUE,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        department_id INT NOT NULL,
        booked_fee_inr INT NOT NULL CONSTRAINT CK_appointments_fee CHECK (booked_fee_inr >= 0),
        appointment_date DATE NOT NULL,
        slot_time CHAR(5) NOT NULL,
        [status] VARCHAR(10) NOT NULL CONSTRAINT DF_appointments_status DEFAULT 'confirmed',
        [source] VARCHAR(20) NOT NULL,
        patient_confirmed_at DATETIME2(0) NOT NULL,
        notes NVARCHAR(500) NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_appointments_created DEFAULT SYSUTCDATETIME(),
        cancelled_at DATETIME2(0) NULL,
        CONSTRAINT CK_appointments_status CHECK ([status] IN ('confirmed', 'cancelled')),
        CONSTRAINT CK_appointments_source CHECK ([source] IN ('website', 'elevenlabs', 'staff')),
        CONSTRAINT FK_appointments_patient FOREIGN KEY (patient_id) REFERENCES patients(id),
        CONSTRAINT FK_appointments_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id),
        CONSTRAINT FK_appointments_department FOREIGN KEY (department_id) REFERENCES departments(id)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.appointments') AND [name] = N'one_patient_per_time')
    CREATE UNIQUE INDEX one_patient_per_time ON appointments(patient_id, appointment_date, slot_time) WHERE [status] = 'confirmed';
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.appointments') AND [name] = N'one_doctor_per_time')
    CREATE UNIQUE INDEX one_doctor_per_time ON appointments(doctor_id, appointment_date, slot_time) WHERE [status] = 'confirmed';
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.appointments') AND [name] = N'appointments_by_date')
    CREATE INDEX appointments_by_date ON appointments(appointment_date, slot_time);
GO

IF OBJECT_ID(N'dbo.rate_limits', N'U') IS NULL
BEGIN
    CREATE TABLE rate_limits (
        [key] CHAR(64) NOT NULL CONSTRAINT PK_rate_limits PRIMARY KEY,
        window_started_at BIGINT NOT NULL,
        request_count INT NOT NULL
    );
END;
GO

MERGE departments AS target
USING (VALUES
    (N'general-physician', N'General Physician', 300),
    (N'opd-consultation', N'OPD Consultation', 400),
    (N'telemedicine', N'Telemedicine', 300),
    (N'general-medicine', N'General Medicine', 500),
    (N'dental-care', N'Dental Care', 500),
    (N'cardiology', N'Cardiology', 700),
    (N'neurology', N'Neurology', 700),
    (N'orthopedics', N'Orthopedics', 600),
    (N'physiotherapy', N'Physiotherapy', 400),
    (N'nutrition-dietetics', N'Nutrition & Dietetics', 400),
    (N'dermatology', N'Dermatology', 500),
    (N'pediatrics', N'Pediatrics', 500),
    (N'oncology', N'Oncology', 800),
    (N'radiology', N'Radiology', 500),
    (N'emergency-medicine', N'Emergency Medicine', 800)
) AS source(slug, [name], fee) ON target.slug = source.slug
WHEN MATCHED THEN UPDATE SET [name] = source.[name], consultation_fee_inr = source.fee
WHEN NOT MATCHED THEN INSERT (slug, [name], consultation_fee_inr) VALUES (source.slug, source.[name], source.fee);
GO

MERGE doctors AS target
USING (VALUES
    (N'Dr. Nitin Patel'), (N'Dr. Anita Rao'), (N'Dr. Rohan Mehta'),
    (N'Dr. Lakshmi Iyer'), (N'Dr. Pooja Mishra'), (N'Dr. Sameer Kulkarni'),
    (N'Dr. Farah Ansari'), (N'Dr. Rahul Deshmukh'), (N'Dr. Kavita Menon'),
    (N'Dr. Neha Kapoor'), (N'Dr. Arjun Nair'), (N'Dr. Priya Desai'),
    (N'Dr. Marcus Johnson'), (N'Dr. Priya Sharma'), (N'Dr. Amit Verma'),
    (N'Dr. Sarah Williams'), (N'Dr. Karan Malhotra'), (N'Dr. Meera Iyer'),
    (N'Dr. Michael Chen'), (N'Dr. Sandeep Kulkarni'), (N'Dr. Nisha Bhatia'),
    (N'Dr. Rhea Kapoor'), (N'Dr. Vikram Nair'), (N'Dr. Aditi Sharma'),
    (N'Dr. Meera Joshi'), (N'Dr. Anjali Rao'), (N'Dr. Kabir Sen'),
    (N'Dr. David Thompson'), (N'Dr. Ayesha Khan'), (N'Dr. Rahul Sethi'),
    (N'Dr. Emily Rodriguez'), (N'Dr. Sneha Kulkarni'), (N'Dr. Vivek Rao'),
    (N'Dr. Lisa Anderson'), (N'Dr. Nandita Shah'), (N'Dr. Harish Menon'),
    (N'Dr. Jennifer Lee'), (N'Dr. Sanjay Nair'), (N'Dr. Anika Bose'),
    (N'Dr. Robert Martinez'), (N'Dr. Farah Siddiqui'), (N'Dr. Manoj Patel')
) AS source([name]) ON target.[name] = source.[name]
WHEN NOT MATCHED THEN INSERT ([name]) VALUES (source.[name]);
GO

MERGE doctor_departments AS target
USING (
    SELECT d.id doctor_id, dep.id department_id, x.specialty_note
    FROM (VALUES
        (N'Dr. Nitin Patel', N'General Physician', NULL), (N'Dr. Anita Rao', N'General Physician', NULL), (N'Dr. Rohan Mehta', N'General Physician', NULL),
        (N'Dr. Nitin Patel', N'OPD Consultation', N'Physician'), (N'Dr. Lakshmi Iyer', N'OPD Consultation', N'Pediatrician'), (N'Dr. Pooja Mishra', N'OPD Consultation', N'Gynecologist'),
        (N'Dr. Sameer Kulkarni', N'Telemedicine', N'Video Consultation'), (N'Dr. Farah Ansari', N'Telemedicine', N'Phone Consultation'), (N'Dr. Rahul Deshmukh', N'Telemedicine', N'Video or Phone Consultation'),
        (N'Dr. Anita Rao', N'General Medicine', NULL), (N'Dr. Rohan Mehta', N'General Medicine', NULL), (N'Dr. Kavita Menon', N'General Medicine', NULL),
        (N'Dr. Neha Kapoor', N'Dental Care', NULL), (N'Dr. Arjun Nair', N'Dental Care', NULL), (N'Dr. Priya Desai', N'Dental Care', NULL),
        (N'Dr. Marcus Johnson', N'Cardiology', NULL), (N'Dr. Priya Sharma', N'Cardiology', NULL), (N'Dr. Amit Verma', N'Cardiology', NULL),
        (N'Dr. Sarah Williams', N'Neurology', NULL), (N'Dr. Karan Malhotra', N'Neurology', NULL), (N'Dr. Meera Iyer', N'Neurology', NULL),
        (N'Dr. Michael Chen', N'Orthopedics', NULL), (N'Dr. Sandeep Kulkarni', N'Orthopedics', NULL), (N'Dr. Nisha Bhatia', N'Orthopedics', NULL),
        (N'Dr. Rhea Kapoor', N'Physiotherapy', NULL), (N'Dr. Vikram Nair', N'Physiotherapy', NULL), (N'Dr. Aditi Sharma', N'Physiotherapy', NULL),
        (N'Dr. Meera Joshi', N'Nutrition & Dietetics', NULL), (N'Dr. Anjali Rao', N'Nutrition & Dietetics', NULL), (N'Dr. Kabir Sen', N'Nutrition & Dietetics', NULL),
        (N'Dr. David Thompson', N'Dermatology', NULL), (N'Dr. Ayesha Khan', N'Dermatology', NULL), (N'Dr. Rahul Sethi', N'Dermatology', NULL),
        (N'Dr. Emily Rodriguez', N'Pediatrics', NULL), (N'Dr. Sneha Kulkarni', N'Pediatrics', NULL), (N'Dr. Vivek Rao', N'Pediatrics', NULL),
        (N'Dr. Lisa Anderson', N'Oncology', NULL), (N'Dr. Nandita Shah', N'Oncology', NULL), (N'Dr. Harish Menon', N'Oncology', NULL),
        (N'Dr. Jennifer Lee', N'Radiology', NULL), (N'Dr. Sanjay Nair', N'Radiology', NULL), (N'Dr. Anika Bose', N'Radiology', NULL),
        (N'Dr. Robert Martinez', N'Emergency Medicine', NULL), (N'Dr. Farah Siddiqui', N'Emergency Medicine', NULL), (N'Dr. Manoj Patel', N'Emergency Medicine', NULL)
    ) x(doctor_name, department_name, specialty_note)
    JOIN doctors d ON d.[name] = x.doctor_name
    JOIN departments dep ON dep.[name] = x.department_name
) AS source ON target.doctor_id = source.doctor_id AND target.department_id = source.department_id
WHEN MATCHED THEN UPDATE SET specialty_note = source.specialty_note
WHEN NOT MATCHED THEN INSERT (doctor_id, department_id, specialty_note) VALUES (source.doctor_id, source.department_id, source.specialty_note);
GO

MERGE availability AS target
USING (
    SELECT d.id doctor_id, weekdays.weekday, times.slot_time
    FROM doctors d
    CROSS JOIN (VALUES (1), (2), (3), (4), (5), (6)) weekdays(weekday)
    CROSS JOIN (VALUES ('10:00'), ('10:15'), ('10:30'), ('11:00'), ('11:15'), ('12:00'), ('15:00'), ('15:15'), ('16:00'), ('16:15'), ('17:00')) times(slot_time)
) AS source ON target.doctor_id = source.doctor_id AND target.weekday = source.weekday AND target.slot_time = source.slot_time
WHEN NOT MATCHED THEN INSERT (doctor_id, weekday, slot_time) VALUES (source.doctor_id, source.weekday, source.slot_time);
GO

IF OBJECT_ID(N'dbo.app_metadata', N'U') IS NULL
BEGIN
    CREATE TABLE app_metadata (
        [name] NVARCHAR(100) NOT NULL CONSTRAINT PK_app_metadata PRIMARY KEY,
        [value] NVARCHAR(100) NOT NULL
    );
END;
GO

MERGE app_metadata AS target
USING (VALUES (N'schema_version', N'2')) AS source([name], [value]) ON target.[name] = source.[name]
WHEN MATCHED THEN UPDATE SET [value] = source.[value]
WHEN NOT MATCHED THEN INSERT ([name], [value]) VALUES (source.[name], source.[value]);
GO
