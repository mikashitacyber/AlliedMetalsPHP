<?php
require __DIR__ . '/../config/config.php';
requireRole('Staff');
$sidebarInclude = __DIR__ . '/_sidebar.php';
$roleLabel = 'Staff';
require __DIR__ . '/../includes/staff_profile_core.php';
