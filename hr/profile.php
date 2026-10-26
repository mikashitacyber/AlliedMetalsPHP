<?php
require __DIR__ . '/../config/config.php';
requireRole('HR');
$sidebarInclude = __DIR__ . '/_sidebar.php';
$roleLabel = 'Human Resources';
require __DIR__ . '/../includes/staff_profile_core.php';
