<?php
declare(strict_types=1);

function validate_appointment_date(string $date): DateTimeImmutable
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new ApiException(422, 'Date must use YYYY-MM-DD.', 'validation_error');
    }
    $today = new DateTimeImmutable('today');
    if ($parsed < $today || $parsed > $today->modify('+180 days')) {
        throw new ApiException(422, 'Choose a date from today through the next 180 days.', 'invalid_date');
    }
    if ((int) $parsed->format('N') === 7) {
        throw new ApiException(422, 'Appointments are not available on Sunday.', 'closed_day');
    }
    return $parsed;
}

function resolve_department(string $value): array
{
    $stmt = db()->prepare('SELECT id, slug, name, consultation_fee_inr FROM departments WHERE active = 1 AND (lower(slug) = lower(:slug_value) OR lower(name) = lower(:name_value))');
    $stmt->execute(['slug_value' => trim($value), 'name_value' => trim($value)]);
    $department = $stmt->fetch();
    if (!$department) {
        throw new ApiException(422, 'The selected department is not available.', 'unknown_department');
    }
    return $department;
}

function department_doctors(int $departmentId, ?string $doctorValue = null): array
{
    $sql = 'SELECT d.id, d.name, dd.specialty_note FROM doctors d JOIN doctor_departments dd ON dd.doctor_id = d.id WHERE d.active = 1 AND dd.department_id = :department';
    $params = ['department' => $departmentId];
    if ($doctorValue !== null && trim($doctorValue) !== '') {
        $sql .= ' AND (CAST(d.id AS VARCHAR(20)) = :doctor_id OR lower(d.name) = lower(:doctor_name))';
        $params['doctor_id'] = trim($doctorValue);
        $params['doctor_name'] = trim($doctorValue);
    }
    $sql .= ' ORDER BY CASE WHEN d.name = \'Dr. Nitin Patel\' THEN 0 ELSE 1 END, d.name';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $doctors = array_slice($stmt->fetchAll(), 0, 3);
    if ($doctorValue !== null && !$doctors) {
        throw new ApiException(422, 'That doctor is not available in the selected department.', 'unknown_doctor');
    }
    return $doctors;
}

function available_slots_for_doctor(int $doctorId, DateTimeImmutable $date): array
{
    $stmt = db()->prepare('SELECT a.slot_time
        FROM availability a
        LEFT JOIN availability_overrides o ON o.doctor_id = a.doctor_id AND o.appointment_date = :override_date AND o.slot_time = a.slot_time
        LEFT JOIN appointments ap ON ap.doctor_id = a.doctor_id AND ap.appointment_date = :appointment_date AND ap.slot_time = a.slot_time AND ap.status = \'confirmed\'
        WHERE a.doctor_id = :doctor AND a.weekday = :weekday AND a.active = 1
          AND COALESCE(o.is_available, 1) = 1 AND ap.id IS NULL
        ORDER BY a.slot_time');
    $stmt->execute(['override_date' => $date->format('Y-m-d'), 'appointment_date' => $date->format('Y-m-d'), 'doctor' => $doctorId, 'weekday' => (int) $date->format('N')]);
    $slots = [];
    $now = new DateTimeImmutable();
    foreach ($stmt->fetchAll() as $row) {
        $time = $row['slot_time'];
        $slotDateTime = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $time);
        if ($slotDateTime <= $now) {
            continue;
        }
        $slots[] = [
            'time' => $time,
            'display_time' => $slotDateTime->format('g:i A'),
            'confirmation_token' => slot_token($doctorId, $date->format('Y-m-d'), $time),
        ];
    }
    return $slots;
}

function check_availability(array $data): array
{
    require_fields($data, ['department', 'date']);
    $date = validate_appointment_date(trim($data['date']));
    $department = resolve_department($data['department']);
    $doctorValue = isset($data['doctor']) && is_string($data['doctor']) ? $data['doctor'] : null;
    $doctors = department_doctors((int) $department['id'], $doctorValue);
    $result = [];
    foreach ($doctors as $doctor) {
        $result[] = [
            'id' => (int) $doctor['id'],
            'name' => $doctor['name'],
            'specialty_note' => $doctor['specialty_note'],
            'slots' => available_slots_for_doctor((int) $doctor['id'], $date),
        ];
    }
    return [
        'department' => ['slug' => $department['slug'], 'name' => $department['name'], 'consultation_fee_inr' => (int) $department['consultation_fee_inr']],
        'date' => $date->format('Y-m-d'),
        'timezone' => date_default_timezone_get(),
        'doctors' => $result,
        'instruction' => 'Read the options to the patient. Do not call create_appointment until the patient explicitly confirms one doctor, date, and time.',
    ];
}

function normalize_phone(string $phone): string
{
    $phone = trim($phone);
    $hasPlus = str_starts_with($phone, '+');
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) < 7 || strlen($digits) > 15) {
        throw new ApiException(422, 'Enter a valid phone number with 7 to 15 digits.', 'validation_error');
    }
    return ($hasPlus ? '+' : '') . $digits;
}

function clean_text(string $value, int $maxLength, string $field): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($value === '' || strlen($value) > $maxLength || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
        throw new ApiException(422, "Field '{$field}' is invalid or too long.", 'validation_error');
    }
    return $value;
}

function create_appointment(array $data, string $source): array
{
    require_fields($data, ['patient_name', 'phone', 'department', 'doctor', 'date', 'time', 'confirmation_token']);
    if (($data['confirmed'] ?? null) !== true) {
        throw new ApiException(422, 'The patient must explicitly confirm the doctor, date, and time before booking.', 'confirmation_required');
    }

    $name = clean_text($data['patient_name'], 100, 'patient_name');
    $phone = normalize_phone($data['phone']);
    $email = null;
    if (isset($data['email']) && is_string($data['email']) && trim($data['email']) !== '') {
        $email = filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL);
        if ($email === false || strlen((string) $email) > 254) {
            throw new ApiException(422, 'Enter a valid email address.', 'validation_error');
        }
    }
    $notes = null;
    if (isset($data['notes']) && is_string($data['notes']) && trim($data['notes']) !== '') {
        $notes = clean_text($data['notes'], 500, 'notes');
    }

    $date = validate_appointment_date(trim($data['date']));
    $time = trim($data['time']);
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
        throw new ApiException(422, 'Time must use 24-hour HH:MM format.', 'validation_error');
    }
    $department = resolve_department($data['department']);
    $doctors = department_doctors((int) $department['id'], $data['doctor']);
    $doctor = $doctors[0];
    verify_slot_token($data['confirmation_token'], (int) $doctor['id'], $date->format('Y-m-d'), $time);

    $pdo = db();
    begin_booking_transaction($pdo);
    try {
        $slotCheck = $pdo->prepare('SELECT 1 FROM availability a
            LEFT JOIN availability_overrides o ON o.doctor_id = a.doctor_id AND o.appointment_date = :date AND o.slot_time = a.slot_time
            WHERE a.doctor_id = :doctor AND a.weekday = :weekday AND a.slot_time = :time AND a.active = 1 AND COALESCE(o.is_available, 1) = 1');
        $slotCheck->execute(['date' => $date->format('Y-m-d'), 'doctor' => $doctor['id'], 'weekday' => (int) $date->format('N'), 'time' => $time]);
        if (!$slotCheck->fetchColumn()) {
            throw new ApiException(409, 'That appointment time is not available.', 'slot_unavailable');
        }

        $patientId = save_patient($pdo, $name, $phone, $email === false ? null : $email);
        $reference = 'MED-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $insert = $pdo->prepare('INSERT INTO appointments
            (booking_reference, patient_id, doctor_id, department_id, booked_fee_inr, appointment_date, slot_time, source, patient_confirmed_at, notes)
            VALUES (:reference, :patient, :doctor, :department, :fee, :date, :time, :source, CURRENT_TIMESTAMP, :notes)');
        $insert->execute([
            'reference' => $reference, 'patient' => $patientId, 'doctor' => $doctor['id'], 'department' => $department['id'],
            'fee' => $department['consultation_fee_inr'], 'date' => $date->format('Y-m-d'), 'time' => $time, 'source' => $source, 'notes' => $notes,
        ]);
        $pdo->commit();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((string) $error->getCode() === '23000' || str_contains($error->getMessage(), 'UNIQUE constraint failed')) {
            throw new ApiException(409, 'That slot was just booked, or this patient already has an appointment at that time. Please choose another slot.', 'duplicate_booking');
        }
        throw $error;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    return [
        'appointment' => [
            'booking_reference' => $reference,
            'patient_name' => $name,
            'department' => $department['name'],
            'consultation_fee_inr' => (int) $department['consultation_fee_inr'],
            'doctor' => $doctor['name'],
            'date' => $date->format('Y-m-d'),
            'time' => $time,
            'display_time' => (new DateTimeImmutable($date->format('Y-m-d') . ' ' . $time))->format('g:i A'),
            'status' => 'confirmed',
        ],
        'message' => "Appointment confirmed. The booking reference is {$reference}. No payment has been collected; the Rs. {$department['consultation_fee_inr']} consultation fee can be paid at the hospital.",
    ];
}
