<?php
require __DIR__ . '/../config/config.php';
requireRole('Technician');
$sidebarInclude = __DIR__ . '/_sidebar.php';
$roleLabel = 'Field Technician';
require __DIR__ . '/../includes/staff_profile_core.php';
