<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/staff-auth.php';
require_local_staff_access();
require_staff_login();

$date = (string) ($_GET['date'] ?? date('Y-m-d'));
$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$parsed || $parsed->format('Y-m-d') !== $date) {
    $date = date('Y-m-d');
}
$stmt = db()->prepare('SELECT a.booking_reference, a.appointment_date, a.slot_time, a.booked_fee_inr, a.status, a.source, a.created_at,
    p.full_name patient_name, p.phone, p.email, d.name doctor_name, dep.name department_name
    FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN doctors d ON d.id = a.doctor_id JOIN departments dep ON dep.id = a.department_id
    WHERE a.appointment_date = :date ORDER BY a.slot_time, d.name');
$stmt->execute(['date' => $date]);
$appointments = $stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Appointments | Medicare Hospital</title><link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f5f8fa}.shell{max-width:1200px}.card{border:0;box-shadow:0 5px 24px rgba(30,65,85,.08)}th{white-space:nowrap}</style></head>
<body><nav class="navbar bg-white border-bottom"><div class="container shell"><span class="navbar-brand fw-semibold">Medicare Hospital · Appointments</span>
<form action="logout.php" method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-outline-secondary btn-sm">Sign out</button></form></div></nav>
<main class="container shell py-4"><div class="d-flex flex-wrap gap-3 align-items-end justify-content-between mb-4"><div><h1 class="h3 mb-1">Appointment schedule</h1><p class="text-secondary mb-0"><?= count($appointments) ?> booking<?= count($appointments) === 1 ? '' : 's' ?> for selected date</p></div>
<form class="d-flex gap-2 align-items-end" method="get"><div><label class="form-label small" for="date">Appointment date</label><input class="form-control" type="date" id="date" name="date" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>"></div><button class="btn btn-primary">View</button></form></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Time</th><th>Patient</th><th>Contact</th><th>Doctor</th><th>Department</th><th>Fee</th><th>Reference</th><th>Source</th><th>Status</th></tr></thead><tbody>
<?php if (!$appointments): ?><tr><td colspan="9" class="text-center text-secondary py-5">No appointments on this date.</td></tr><?php endif; ?>
<?php foreach ($appointments as $item): ?><tr><td class="fw-semibold"><?= htmlspecialchars((new DateTimeImmutable($item['appointment_date'].' '.$item['slot_time']))->format('g:i A'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($item['patient_name'], ENT_QUOTES, 'UTF-8') ?></td><td><a href="tel:<?= htmlspecialchars($item['phone'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['phone'], ENT_QUOTES, 'UTF-8') ?></a><?php if ($item['email']): ?><br><small><?= htmlspecialchars($item['email'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td><td><?= htmlspecialchars($item['doctor_name'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($item['department_name'], ENT_QUOTES, 'UTF-8') ?></td><td>Rs. <?= (int) $item['booked_fee_inr'] ?></td><td><code><?= htmlspecialchars($item['booking_reference'], ENT_QUOTES, 'UTF-8') ?></code></td><td><?= htmlspecialchars(ucfirst($item['source']), ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge text-bg-<?= $item['status'] === 'confirmed' ? 'success' : 'secondary' ?>"><?= htmlspecialchars(ucfirst($item['status']), ENT_QUOTES, 'UTF-8') ?></span></td></tr><?php endforeach; ?>
</tbody></table></div></div></main></body></html>
