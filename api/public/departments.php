<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

run_api(function (): array {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        throw new ApiException(405, 'Use GET for this endpoint.', 'method_not_allowed');
    }
    apply_public_cors();
    rate_limit('public-departments', 60, 60);
    $rows = db()->query('SELECT p.slug, p.name, p.consultation_fee_inr, d.id doctor_id, d.name doctor_name, dd.specialty_note
        FROM departments p JOIN doctor_departments dd ON dd.department_id = p.id JOIN doctors d ON d.id = dd.doctor_id
        WHERE p.active = 1 AND d.active = 1
        ORDER BY p.id, CASE WHEN d.name = \'Dr. Nitin Patel\' THEN 0 ELSE 1 END, d.name')->fetchAll();
    $departments = [];
    foreach ($rows as $row) {
        $slug = $row['slug'];
        if (!isset($departments[$slug])) {
            $departments[$slug] = ['slug' => $slug, 'name' => $row['name'], 'consultation_fee_inr' => (int) $row['consultation_fee_inr'], 'doctors' => []];
        }
        $departments[$slug]['doctors'][] = ['id' => (int) $row['doctor_id'], 'name' => $row['doctor_name'], 'specialty_note' => $row['specialty_note']];
    }
    return ['departments' => array_values($departments)];
});
