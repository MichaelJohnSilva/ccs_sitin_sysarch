<?php
include "config.php";

$email = "admin@ccs.com";
$password = password_hash("admin123", PASSWORD_DEFAULT);

$sql = "INSERT INTO students 
(id_number, last_name, first_name, middle_name, course, email, password, address, status, role)
VALUES 
('00000001','Admin','System','','Administrator','$email','$password','System','active','admin')";

if ($conn->query($sql)) {
    echo "Admin created successfully!";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>