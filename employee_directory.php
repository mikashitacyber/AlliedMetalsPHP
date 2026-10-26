<?php
require __DIR__ . '/config/config.php';
requireRole('Customer');

header('Location: ' . BASE_URL . '/messages.php');
exit;
