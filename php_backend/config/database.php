<?php

$host = "localhost";
$dbname = "necc_dashboard";
$username = "root";
$password = "sunfra@123";

try {

    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname",
        $username,
        $password
    );

    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {

    die("Connection Failed : " . $e->getMessage());

}