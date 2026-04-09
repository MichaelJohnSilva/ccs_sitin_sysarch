<?php
include 'config.php';

$id_number = $_GET['id_number'] ?? '';

if (!empty($id_number)) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id_number = ? AND is_read = 0");
    $stmt->bind_param("s", $id_number);
    $stmt->execute();
    $stmt->close();
}

$conn->close();