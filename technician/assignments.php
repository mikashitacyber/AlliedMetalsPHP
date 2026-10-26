<?php
require __DIR__ . '/../config/config.php';
requireRole('Technician');
$query = [];
if (($_GET['show'] ?? '') === 'done') {
    $query['show'] = 'done';
}
header('Location: ' . BASE_URL . '/employee_work.php' . ($query ? '?' . http_build_query($query) : ''));
exit;
