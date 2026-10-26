<?php
require __DIR__ . '/../config/config.php';
requireRole('Admin');
$sidebarInclude = __DIR__ . '/_sidebar.php';
$roleLabel = 'Administrator';
require __DIR__ . '/../includes/staff_profile_core.php';
