<?php
session_start();
include "config.php";

if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {

    $currentTime = date("Y-m-d H:i:s");

    // Update sit-in records that have no time_out
    $update = $conn->prepare("UPDATE sitin_records SET time_out = ? WHERE time_out IS NULL");
    $update->bind_param("s", $currentTime);
    $update->execute();
}

// Destroy session
session_destroy();
header("Location: login.html");
exit();
?>