<?php
require_once('application/loader.php');

// Test để kiểm tra dữ liệu user
$seal = new Seal();
$employees = $seal->get_employees();

echo "<h3>Employee Data:</h3>";
echo "<pre>";
print_r($employees);
echo "</pre>";

// Kiểm tra cấu trúc bảng user
$query = "DESCRIBE " . DB_PREFIX . "user";
$result = $seal->query($query);
echo "<h3>User Table Structure:</h3>";
echo "<pre>";
print_r($result);
echo "</pre>";
?>
