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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
/* ===== GLOBAL ===== */
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    position: relative;
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
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    padding: 15px 40px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.navbar:hover {
    box-shadow: 0 6px 25px rgba(0,0,0,0.4);
}

.navbar .left {
    color: white;
    font-size: 24px;
    font-weight: 700;
}

.navbar .right {
    display: flex;
    gap: 5px;
}

.navbar a, .dropdown-btn {
    color: white;
    padding: 10px 18px;
    border-radius: 8px;
    transition: all 0.3s ease;
    text-decoration: none;
    background: transparent;
    border: none;
    cursor: pointer;
    position: relative;
}

.navbar a::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 3px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transition: all 0.3s ease;
    transform: translateX(-50%);
    border-radius: 2px;
}

.navbar a:hover, .dropdown-btn:hover {
    background: rgba(255,255,255,0.1);
    transform: translateY(-2px);
}

.navbar a:hover::before {
    width: 80%;
}

/* ===== NOTIFICATION DROPDOWN ===== */
.dropdown {
    position: relative;
    z-index: 9999;
}

.dropdown > a {
    display: inline-block;
    transition: all 0.3s ease;
}

.dropdown:hover > a {
    transform: translateY(-2px);
}

.dropdown-content {
    display: none;
    position: absolute;
    top: 50px;
    right: 0;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    min-width: 220px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.4);
    border-radius: 12px;
    overflow: hidden;
    z-index: 10000;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    opacity: 0;
    pointer-events: none;
}

.dropdown:hover .dropdown-content {
    display: block;
    transform: translateY(0);
    opacity: 1;
    pointer-events: auto;
}

.dropdown-content p {
    padding: 12px 16px;
    margin: 0;
    font-size: 14px;
    color: rgba(255,255,255,0.9);
    transition: all 0.3s ease;
    cursor: default;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.dropdown-content p:last-child {
    border-bottom: none;
}

.dropdown-content p:hover {
    background: rgba(255,255,255,0.1);
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
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    position: sticky;
    top: 15px;
    transition: all 0.3s ease;
}

.profile-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 70px rgba(0,0,0,0.4);
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
    background: white;
    padding: 20px;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
    max-height: 300px;
    overflow-y: auto;
}

.announcement:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 70px rgba(0,0,0,0.4);
}

.announcement h3 {
    border-left: 4px solid #667eea;
    padding-left: 12px;
    margin: 0 0 15px 0;
    color: #1a1a2e;
}

.announcement p {
    margin: 5px 0;
    font-size: 14px;
    color: #555;
}

/* Scrollbar */
.announcement::-webkit-scrollbar {
    width: 6px;
}

.announcement::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
}

/* ===== RULES ===== */
.rules {
    background: white;
    padding: 20px;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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
                <li>Maintain silence, proper decorum, and discipline inside the laboratory. Mobile phones, walkmans and other personal pieces of equipment must be switched off.</li>
                <li>Games are not allowed inside the lab. This includes computer-related games, card games and other games that may disturb the operation of the lab.</li>
                <li>Surfing the Internet is allowed only with the permission of the instructor. Downloading and installing of software are strictly prohibited.</li>
                <li>Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.</li>
                <li>Deleting computer files and changing the set-up of the computer is a major offense.</li>
                <li>Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to “sit-in”.</li>
                <li>Observe proper decorum while inside the laboratory.
            a. Do not get inside the lab unless the instructor is present.
            b. All bags, knapsacks, and the likes must be deposited at the counter.
            c. Follow the seating arrangement of your instructor.
            d. At the end of class, all software programs must be closed.
            e. Return all chairs to their proper places after using.
            </li>
                <li>Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.</li>
                <li>Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.</li>
                <li>Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.</li>
                <li>For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.</li>
                <li>Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.</li>

            </ol>
        </div>
    </div>
</div>

<?php if(isset($_SESSION['last_logout_time'])): ?>
<div style="padding:10px; background:#d4edda; color:#155724; margin:10px auto; max-width:600px; border-radius:5px; text-align:center;">
    Admin last logged out at: <strong><?= $_SESSION['last_logout_time']; ?></strong>
</div>
<?php unset($_SESSION['last_logout_time']); endif; ?>

</body>
</html>