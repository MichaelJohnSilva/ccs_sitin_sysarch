<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle photo upload
$uploadError = "";
if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK){
    $fileTmpPath = $_FILES['profile_photo']['tmp_name'];
    $fileName = $_FILES['profile_photo']['name'];
    $fileSize = $_FILES['profile_photo']['size'];
    $fileType = $_FILES['profile_photo']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    if(in_array($fileExtension, $allowedExtensions)){
        $newFileName = 'uploads/profile_' . $user_id . '.' . $fileExtension;
        if(!is_dir('uploads')){
            mkdir('uploads', 0755, true); // create uploads folder if not exists
        }

        if(move_uploaded_file($fileTmpPath, $newFileName)){
            // Update in database
            $stmt = $conn->prepare("UPDATE students SET photo=? WHERE id=?");
            $stmt->bind_param("si", $newFileName, $user_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $uploadError = "Error moving uploaded file.";
        }
    } else {
        $uploadError = "Only jpg, jpeg, png, gif files are allowed.";
    }
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch announcements from database
$announcement_query = "SELECT * FROM announcements ORDER BY created_at DESC";
$announcement_result = $conn->query($announcement_query);

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <style>
/* ===== GLOBAL ===== */
body {
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #eef2f7, #e3e9f1);
    opacity: 0;
    animation: fadeIn 0.7s ease forwards;
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
    transition: 0.3s;
    position: relative;
    z-index: 10;
}

.navbar:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}

.navbar .left {
    color: white;
    font-size: 24px;
    font-weight: 700;
}

.navbar .right {
    display: flex;
    gap: 10px;
}

.navbar a, .dropdown-btn {
    color: white;
    padding: 8px 14px;
    border-radius: 6px;
    transition: all 0.25s ease;
    text-decoration: none;
    background: transparent;
    border: none;
    cursor: pointer;
}

.navbar a:hover, .dropdown-btn:hover {
    background: #007bff;
    transform: translateY(-2px);
}

/* ===== NOTIFICATION DROPDOWN ===== */
.dropdown {
    position: relative;
    perspective: 800px; /* enables 3D depth */
    z-index: 9999; /* ensure on top */
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
    min-width: 200px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    border-radius: 12px;
    overflow: hidden;
    z-index: 10000; /* highest to be in front */
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
    padding: 12px 16px;
    margin: 0;
    font-size: 14px;
    color: #333;
    transition: background 0.3s ease, transform 0.3s ease;
    cursor: default;
}

.dropdown-content p:hover {
    background: #f0f8ff;
    transform: translateZ(8px);
}

/* ===== CONTENT GRID ===== */
.content {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 16px;
    padding: 20px;
    margin: 20px auto;
    max-width: 1000px;
    align-items: start;
}

/* Header full width */
.content h1,
.content > p {
    grid-column: 1 / -1;
    text-align: center;
    margin: 3px 0 10px 0;
}

/* ===== PROFILE CARD ===== */
.profile-card {
    background: linear-gradient(135deg, #ffffff, #eef4ff);
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 12px 25px rgba(0,0,0,0.08);
    border-left: 5px solid #007bff;
    position: sticky;
    top: 15px;
    transition: all 0.3s ease;
}

.profile-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 18px 35px rgba(0,0,0,0.15);
}

.profile-card img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 3px solid #007bff;
    object-fit: cover;
    transition: 0.3s;
}

.profile-card img:hover {
    transform: scale(1.1);
}

.profile-info p {
    margin: 4px 0;
    font-size: 14px;
}

/* ===== UPLOAD ===== */
.upload-btn input {
    width: 100%;
    padding: 7px;
    margin-top: 6px;
    border-radius: 6px;
    border: 1px solid #ccc;
}

.upload-btn button {
    margin-top: 6px;
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border: none;
    transition: all 0.25s ease;
    cursor: pointer;
}

.upload-btn button:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 6px 20px rgba(0,123,255,0.3);
}

/* ===== RIGHT SIDE WRAPPER ===== */
.right-side {
    display: flex;
    flex-direction: column;
    gap: 12px; /* Tight gap between announcement and rules */
}

/* ===== ANNOUNCEMENTS ===== */
.announcement {
    background: linear-gradient(135deg, #ffffff, #f8fbff);
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    max-height: 250px;
    overflow-y: auto;
}

.announcement:hover {
    transform: translateY(-3px) scale(1.01);
    box-shadow: 0 18px 35px rgba(0,0,0,0.15);
}

.announcement h3 {
    border-left: 5px solid #007bff;
    padding-left: 10px;
    margin: 0 0 6px 0;
}

.announcement p {
    margin: 3px 0;
    font-size: 14px;
}

/* Scrollbar */
.announcement::-webkit-scrollbar {
    width: 5px;
}

.announcement::-webkit-scrollbar-thumb {
    background: #007bff;
    border-radius: 10px;
}

/* ===== RULES ===== */
.rules {
    background: linear-gradient(135deg, #ffffff, #f8fbff);
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.rules:hover {
    transform: translateY(-3px) scale(1.01);
    box-shadow: 0 18px 35px rgba(0,0,0,0.15);
}

.rules h3 {
    border-left: 5px solid #007bff;
    padding-left: 10px;
    margin: 0 0 6px 0;
}

.rules ol {
    padding-left: 18px;
    margin: 0;
}

.rules ol li {
    margin-bottom: 5px;
    font-size: 14px;
    transition: 0.2s;
}

.rules ol li:hover {
    transform: translateX(4px);
    color: #007bff;
}

/* ===== ERROR ===== */
.error {
    color: red;
    margin-top: 6px;
    animation: shake 0.25s;
}

@keyframes shake {
    0% { transform: translateX(0);}
    25% { transform: translateX(-3px);}
    50% { transform: translateX(3px);}
    75% { transform: translateX(-3px);}
    100% { transform: translateX(0);}
}

/* ===== ENTRY ANIMATION ===== */
.profile-card,
.announcement,
.rules {
    opacity: 0;
    transform: translateY(12px);
    animation: fadeUp 0.6s ease forwards;
}

.profile-card { animation-delay: 0.1s; }
.announcement { animation-delay: 0.2s; }
.rules { animation-delay: 0.3s; }

@keyframes fadeUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
    .content {
        display: block;
        padding: 15px;
    }

    .profile-card {
        position: relative;
        margin-bottom: 20px;
    }

    .right-side {
        display: block;
    }

    .announcement,
    .rules {
        max-height: none;
        overflow: visible;
        margin-top: 10px;
    }
}
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="left">Dashboard</div>
    <div class="right">
        <!-- Notification Dropdown -->
        <div class="dropdown">
            <button class="dropdown-btn">Notifications &#9662;</button>
            <div class="dropdown-content">
                <p>No new notifications</p>
            </div>
        </div>
        <a href="dashboard.php" class="home-link">Home</a>  <!-- Updated Home link -->
        <a href="edit_profile.php">Edit Profile</a>
        <a href="history.php">History Reservation</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="content">
    <!-- LEFT: Profile Card -->
    <div class="profile-card">
        <img src="<?php echo !empty($user['photo']) ? $user['photo'] : 'default_avatar.png'; ?>" alt="Profile Photo">
        <h2><?php echo htmlspecialchars($user['first_name'].' '.$user['middle_name'].' '.$user['last_name']); ?></h2>
        <div class="profile-info">
            <p><strong>Course:</strong> <?php echo htmlspecialchars($user['course']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($user['address']); ?></p>
            <p><strong>Sessions:</strong> <?php echo htmlspecialchars($user['sessions_remaining'] ?? 30); ?> / 30</p>
        </div>

        <!-- Photo Upload -->
        <form action="dashboard.php" method="POST" enctype="multipart/form-data" class="upload-btn">
            <label for="profile_photo">Choose Photo</label>
            <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required>
            <button type="submit">Upload</button>
        </form>
        <?php if(!empty($uploadError)): ?>
            <div class="error"><?php echo htmlspecialchars($uploadError); ?></div>
        <?php endif; ?>
    </div>

    <!-- RIGHT SIDE: Announcements + Rules -->
    <div class="right-side">
        <!-- Announcements -->
        <div class="announcement">
            <h3>Announcements</h3>
            <?php if($announcement_result && $announcement_result->num_rows > 0): ?>
                <?php while($row = $announcement_result->fetch_assoc()): ?>
                    <p><strong>CCS Admin | <?php echo date("Y-M-d", strtotime($row['created_at'])); ?></strong></p>
                    <p><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                    <hr>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No announcements yet.</p>
            <?php endif; ?>
        </div>

        <!-- Rules & Regulations -->
        <div class="rules">
            <h3>Rules and Regulations</h3>
            <p><strong>University of Cebu</strong><br>College of Information & Computer Studies</p>
            <p><strong>Laboratory Rules and Regulations</strong></p>
            <ol>
                <li>Maintain silence, proper decorum, and discipline inside the laboratory.</li>
                <li>Games are not allowed inside the lab.</li>
                <li>Surfing the Internet is allowed only with the permission of the instructor.</li>
            </ol>
        </div>
    </div>
</div>

</body>
</html>