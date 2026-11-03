<?php
// Copy this file to config.php and adjust BASE_URL for the folder under htdocs.
// For XAMPP defaults, MySQL is usually localhost:3306 with user root and no password.
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'allied_metals';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

define('BASE_URL', '/AlliedMetalsPHP');
