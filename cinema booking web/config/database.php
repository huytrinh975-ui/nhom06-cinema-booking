<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "cinema_booking_db";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>