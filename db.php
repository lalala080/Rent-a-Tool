<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "projects";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed.");
}

$conn->set_charset("utf8mb4");
?>