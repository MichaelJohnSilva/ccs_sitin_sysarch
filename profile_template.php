<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Profile</title>
<style>
/* Page Background */
body{
    background:#f2f2f2;
    font-family: Arial, Helvetica, sans-serif;
    padding-top:40px;
}

/* Profile Card */
.profile-card{
    max-width:700px;
    margin:0 auto;
    background:#fff;
    border-radius:15px;
    box-shadow:0 8px 20px rgba(0,0,0,0.2);
    overflow:hidden;
}

/* Blue Banner */
.profile-banner{
    width:100%;
    background:#007bff;
    color:white;
    text-align:center;
    font-size:26px;
    font-weight:bold;
    padding:20px;
}

/* Content Area */
.profile-content{
    padding:40px;
}

/* Name */
.profile-content h2{
    text-align:left;
    margin-bottom:25px;
    color:#333;
}

/* Info Rows */
.profile-content p{
    font-size:16px;
    margin:12px 0;
    color:#555;
}

/* Label Alignment */
.profile-content strong{
    display:inline-block;
    width:140px;
    color:#222;
}

/* Logout Button */
.logout-btn{
    display:block;
    width:150px;
    text-align:center;
    margin:35px auto 0;
    padding:12px;
    background:#ff4b5c;
    color:white;
    font-weight:bold;
    border-radius:8px;
    text-decoration:none;
    transition:0.3s;
}

/* Logout Hover Effect */
.logout-btn:hover{
    background:#e03d4f;
}
</style>
<body>

<div class="profile-card">

    <div class="profile-banner">
        <?php echo $user['course']; ?>
    </div>

    <div class="profile-content">

        <h2><?php echo $user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']; ?></h2>

            <p><strong>ID Number:</strong> <?php echo $user['id_number']; ?></p>
            <p><strong>Course:</strong> <?php echo $user['course']; ?></p>
            <p><strong>Email:</strong> <?php echo $user['email']; ?></p>
            <p><strong>Address:</strong> <?php echo $user['address']; ?></p>
            <p><strong>Status:</strong> <?php echo isset($user['status']) ? $user['status'] : 'active'; ?></p>
            <p><strong>Registered At:</strong> <?php echo $user['created_at']; ?></p>
        <a href="logout.php" class="logout-btn">Logout</a>

    </div>

</div>
</body>
</html>