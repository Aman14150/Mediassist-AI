BEGIN IMMEDIATE;

ALTER TABLE departments
    ADD COLUMN consultation_fee_inr INTEGER NOT NULL DEFAULT 300 CHECK (consultation_fee_inr >= 0);

UPDATE departments SET consultation_fee_inr = CASE slug
    WHEN 'general-physician' THEN 300
    WHEN 'opd-consultation' THEN 400
    WHEN 'telemedicine' THEN 300
    WHEN 'general-medicine' THEN 500
    WHEN 'dental-care' THEN 500
    WHEN 'cardiology' THEN 700
    WHEN 'neurology' THEN 700
    WHEN 'orthopedics' THEN 600
    WHEN 'physiotherapy' THEN 400
    WHEN 'nutrition-dietetics' THEN 400
    WHEN 'dermatology' THEN 500
    WHEN 'pediatrics' THEN 500
    WHEN 'oncology' THEN 800
    WHEN 'radiology' THEN 500
    WHEN 'emergency-medicine' THEN 800
    ELSE consultation_fee_inr
END;

ALTER TABLE appointments
    ADD COLUMN booked_fee_inr INTEGER NOT NULL DEFAULT 0 CHECK (booked_fee_inr >= 0);

UPDATE appointments
SET booked_fee_inr = (
    SELECT consultation_fee_inr FROM departments WHERE departments.id = appointments.department_id
)
WHERE booked_fee_inr = 0;

PRAGMA user_version = 2;
COMMIT;
