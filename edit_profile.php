<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

/* FETCH USER DATA */
$stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

/* UPDATE PROFILE */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $firstName = trim($_POST['firstName']);
    $middle_name = trim($_POST['middle_name']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    $photoPath = $user['photo'] ?? '';

    if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0){

        $uploadDir = "uploads/";

        if(!is_dir($uploadDir)){
            mkdir($uploadDir,0777,true);
        }

        $fileName = time()."_".basename($_FILES['photo']['name']);
        $targetFile = $uploadDir.$fileName;

        if(move_uploaded_file($_FILES['photo']['tmp_name'],$targetFile)){
            $photoPath = $targetFile;
        }
    }

    $update = $conn->prepare("UPDATE students SET first_name=?, middle_name=?, last_name=?, email=?, address=?, photo=? WHERE id=?");
    $update->bind_param("ssssssi",
        $firstName,
        $middle_name,
        $lastName,
        $email,
        $address,
        $photoPath,
        $user_id
    );

    if($update->execute()){
        echo "<script>alert('Profile Updated');window.location='dashboard.php';</script>";
        exit();
    }

    $update->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="styles.css">
<style>
/* ===== GLOBAL ===== */
body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #eef2f7, #e3e9f1);
    opacity: 0;
    animation: fadeIn 0.8s ease forwards;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ===== NAVBAR ===== */
.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #1f1f1f, #3a3a3a);
    padding: 12px 28px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    transition: all 0.4s ease;
}

.navbar:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.navbar .logo {
    color: white;
    font-weight: bold;
    font-size: 24px;
    transition: transform 0.3s;
}

.navbar .logo:hover {
    transform: scale(1.05);
}

.navbar ul {
    list-style: none;
    display: flex;
    gap: 10px;
    margin: 0;
    padding: 0;
}

.navbar ul li a {
    text-decoration: none;
    color: white;
    font-weight: 500;
    padding: 8px 14px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.navbar ul li a:hover {
    background: #007bff;
    transform: translateY(-2px) scale(1.02);
}

/* ===== DROPDOWN / NOTIFICATIONS ===== */
.dropdown {
    position: relative;
    perspective: 800px; /* Enable 3D */
    z-index: 9999;
}

.dropdown > a {
    display: inline-block;
    transition: transform 0.35s ease, color 0.35s ease;
    transform-style: preserve-3d;
}

.dropdown:hover > a {
    transform: rotateX(0deg) translateZ(8px) scale(1.05);
}

.dropdown-content {
    display: none;
    position: absolute;
    top: 38px;
    right: 0;
    background: #fff;
    min-width: 180px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    border-radius: 10px;
    overflow: hidden;
    z-index: 10000;
    transform: rotateX(-20deg) translateY(-6px);
    transform-origin: top center;
    transition: transform 0.35s ease, opacity 0.35s ease;
    opacity: 0;
    pointer-events: none;
}

.dropdown:hover .dropdown-content {
    display: block;
    transform: rotateX(0deg) translateY(0) translateZ(12px);
    opacity: 1;
    pointer-events: auto;
}

.dropdown-content p {
    padding: 10px;
    margin: 0;
    font-size: 14px;
    color: #333;
    transition: background 0.3s ease, transform 0.3s ease;
    cursor: default;
}

.dropdown-content p:hover {
    background: #f1f1f1;
    transform: translateZ(8px);
}

/* ===== EDIT PROFILE CARD ===== */
.edit-container {
    max-width: 800px;
    margin: 30px auto;
    background: linear-gradient(135deg, #ffffff, #eef4ff);
    padding: 30px 25px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    transition: all 0.4s ease;
    opacity: 0;
    transform: translateY(20px);
    animation: fadeUp 0.7s forwards;
}

@keyframes fadeUp {
    to { opacity: 1; transform: translateY(0); }
}

.edit-container:hover {
    transform: translateY(-6px);
    box-shadow: 0 18px 35px rgba(0,0,0,0.15);
}

/* ===== FORM GRID ===== */
form {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px 25px;
}

form label {
    display: block;
    font-weight: 600;
    margin-top: 8px;
    color: #333;
    opacity: 0;
    transform: translateY(10px);
    animation: labelUp 0.6s forwards;
}

@keyframes labelUp {
    to { opacity: 1; transform: translateY(0); }
}

form input[type="text"],
form input[type="email"],
form input[type="file"] {
    width: 100%;
    padding: 12px;
    margin-top: 6px;
    border: 1px solid #ccc;
    border-radius: 8px;
    transition: all 0.4s ease;
}

form input:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 10px rgba(0,123,255,0.4);
    transform: translateY(-2px);
}

/* ID Number Full Width */
form input[disabled] {
    grid-column: 1 / -1;
    background: #f0f0f0;
}

/* Profile Photo Full Width */
form input[type="file"] {
    grid-column: 1 / -1;
}

/* Buttons Full Width on Bottom */
.btn-group {
    grid-column: 1 / -1;
    display: flex;
    justify-content: space-between;
    gap: 15px;
    margin-top: 15px;
}

.btn-group button {
    flex: 1;
    padding: 14px;
    font-size: 16px;
    font-weight: 500;
    border-radius: 8px;
    cursor: pointer;
    border: none;
    color: #fff;
    transition: all 0.35s ease;
}

.btn-group button[type="submit"] {
    background: linear-gradient(135deg, #007bff, #0056b3);
}

.btn-group button[type="submit"]:hover {
    background: linear-gradient(135deg, #0056b3, #003f7f);
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 8px 20px rgba(0,123,255,0.4);
}

.btn-group .cancel-btn {
    background: #6c757d;
}

.btn-group .cancel-btn:hover {
    background: #5a6268;
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 6px 18px rgba(0,0,0,0.25);
}

/* Profile Preview */
.profile-preview {
    grid-column: 1 / -1;
    margin: 15px auto;
    display: block;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #007bff;
    transition: all 0.4s ease;
}

.profile-preview:hover {
    transform: scale(1.12) rotate(2deg);
    box-shadow: 0 12px 30px rgba(0,123,255,0.45);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    form {
        display: block;
    }

    .btn-group {
        flex-direction: column;
    }

    .btn-group button {
        width: 100%;
    }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="logo">Dashboard</div>
    <ul>
         <li class="dropdown">
            <a href="javascript:void(0)">Notifications &#9662;</a>
            <div class="dropdown-content">
                <p>No new notifications</p>
            </div>
        </li>
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="edit_profile.php">Edit Profile</a></li>
        <li><a href="history.php">History Reservation</a></li>
        <li><a href="logout.php">Logout</a></li>
    
    </ul>
</div>

<!-- EDIT PROFILE FORM -->
<div class="edit-container">
    <h2>Edit Profile</h2>
    <form method="POST" enctype="multipart/form-data">

        <label>ID Number</label>
        <input type="text" value="<?php echo htmlspecialchars($user['id_number']); ?>" disabled>

        <label>First Name</label>
        <input type="text" name="firstName" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>

        <label>Middle Name</label>
        <input type="text" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name']); ?>">

        <label>Last Name</label>
        <input type="text" name="lastName" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>

        <label>Email</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

        <label>Address</label>
        <input type="text" name="address" value="<?php echo htmlspecialchars($user['address']); ?>">

        <label>Profile Photo</label>
        <input type="file" name="photo">

        <?php if(!empty($user['photo'])): ?>
            <img src="<?php echo htmlspecialchars($user['photo']); ?>" class="profile-preview">
        <?php endif; ?>

        <div class="btn-group">
            <button type="submit">Update Profile</button>
            <button type="button" class="cancel-btn" onclick="window.location='dashboard.php'">Cancel</button>
        </div>

    </form>
</div>

</body>
</html>