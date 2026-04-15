<?php
session_start();
include "config.php";

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM students WHERE email=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1){
        $user = $result->fetch_assoc();
       if(password_verify($password, $user['password'])){
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];

                if($user['role'] === 'admin'){
                    header("Location: admin_dashboard.php");
                }else{
                header("Location: dashboard.php");           
                }

                exit();
            } else {
            $stmt->close();
            $conn->close();
            $error_msg = 'Incorrect password';
        }
    } else {
        $stmt->close();
        $conn->close();
        $error_msg = 'User not found';
    }
} 
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CCS | Login</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="icon" href="uclogo.png" type="image/png" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet">
  <style>
/* ===== GENERAL STYLES ===== */
body {
  margin: 0;
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  min-height: 100vh;
  position: relative;
  overflow-x: hidden;
  color: #333;
}

/* ===== NAVBAR ===== */
.topnav {
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

#title {
  display: flex;
  align-items: center;
  gap: 15px;
  color: white;
  font-weight: 700;
  font-size: 20px;
}

#uc {
  height: 50px;
  width: 50px;
  border-radius: 50%;
  transition: transform 0.4s ease;
  box-shadow: 0 0 15px rgba(255,255,255,0.3);
}

#uc:hover {
  transform: rotate(360deg) scale(1.1);
}

#uc:hover {
  transform: rotate(15deg) scale(1.05);
}

.topnavInside ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  gap: 25px;
}

.topnavInside li {
  position: relative;
  perspective: 600px;
}

.topnavInside a {
  display: block;
  color: white;
  padding: 14px 18px;
  text-decoration: none;
  font-size: 16px;
  font-weight: 500;
  border-radius: 6px;
  transition: 
    color 0.3s ease, 
    background-color 0.3s ease,
    box-shadow 0.3s ease,
    transform 0.3s ease;
  transform-style: preserve-3d;
}

.topnavInside a:hover {
  background-color: #0056b3;
  color: white;
  transform: translateY(-3px);
  box-shadow: 0 6px 15px rgba(0, 86, 179, 0.6);
}

.topnavInside a.active {
  background-color: #003f7f;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 63, 127, 0.5);
}

/* ===== DROPDOWN MENU ===== */
.dropdown-content {
  display: none;
  position: absolute;
  top: 40px;
  right: 0;
  background: #fff;
  min-width: 180px;
  box-shadow: 0 15px 35px rgba(0,0,0,0.25);
  border-radius: 10px;
  overflow: hidden;
  opacity: 0;
  transform: translateY(-10px);
  transition: transform 0.35s ease, opacity 0.35s ease;
  pointer-events: none;
  list-style: none;
  padding: 0;
  z-index: 1100;
}

.dropdown:hover .dropdown-content {
  display: block;
  transform: translateY(0);
  opacity: 1;
  pointer-events: auto;
}

.dropdown-content li a {
  display: block;
  padding: 12px 18px;
  font-size: 14px;
  color: #333;
  text-decoration: none;
  transition: background 0.3s, transform 0.3s, color 0.3s;
  border-left: 3px solid transparent;
}

.dropdown-content li a:hover {
  background: #f1f1f1;
  color: #007bff;
  transform: translateX(5px);
  border-left-color: #007bff;
}

/* ===== MAIN CONTENT ===== */
.content {
  max-width: 500px;
  margin: 70px auto;
  padding: 50px 35px;
  background: rgba(255,255,255,0.95);
  border-radius: 20px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.15);
  text-align: center;
  position: relative;
  z-index: 1;
  animation: fadeInContent 1s ease forwards;
}

@keyframes fadeInContent {
  0% { opacity: 0; transform: translateY(-15px); }
  100% { opacity: 1; transform: translateY(0); }
}

.content h1 {
  font-size: 32px;
  color: #222;
  margin-bottom: 20px;
  font-weight: 700;
  transition: transform 0.3s ease-in-out;
}

.content h1:hover {
  transform: translateY(-5px);
}

.content p {
  font-size: 16px;
  color: #666;
  margin-bottom: 35px;
  user-select: none;
}

.error-message {
  background: #f8d7da;
  color: #721c24;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #f5c6cb;
  margin-bottom: 20px;
  font-weight: 500;
  animation: shake 0.5s ease-in-out;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-5px); }
  75% { transform: translateX(5px); }
}

/* ===== FORM STYLES ===== */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  text-align: left;
}

.form-group {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  width: 100%;
}

.form-group input {
  width: 100%;
  padding: 14px 16px;
  font-size: 16px;
  border-radius: 10px;
  border: 1.8px solid #ccc;
  background: #fefefe;
  box-sizing: border-box;
}

.form-group input:focus {
  border-color: #007bff;
  box-shadow: 0 0 10px rgba(0,123,255,0.4);
  outline: none;
}

.form-group label {
  margin-bottom: 6px;
  font-size: 14px;
  color: #555;
  font-weight: 600;
  text-align: left;
}

/* ===== BUTTONS ===== */
.button-group {
  display: flex;
  gap: 15px;
  justify-content: flex-end;
  margin-top: 25px;
}

.back-btn {
  padding: 12px 20px;
  background: #6c757d;
  color: white;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  box-shadow: 0 4px 8px rgba(108, 117, 125, 0.5);
  transition: all 0.3s ease;
  user-select: none;
}

.back-btn:hover {
  background: #5a6268;
  box-shadow: 0 6px 12px rgba(90, 98, 104, 0.7);
  transform: translateY(-3px) scale(1.05);
}

.login-button {
  padding: 12px 22px;
  background: linear-gradient(135deg, #007bff, #004aad);
  color: white;
  font-size: 16px;
  font-weight: 700;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  box-shadow: 0 5px 15px rgba(0, 123, 255, 0.6);
  transition: all 0.3s ease;
  user-select: none;
}

.login-button:hover {
  background: linear-gradient(135deg, #0056b3, #003f7f);
  box-shadow: 0 7px 20px rgba(0, 86, 179, 0.8);
  transform: translateY(-3px) scale(1.05);
}

/* ===== FOOTER ===== */
.footer {
  text-align: center;
  padding: 20px 0;
  background: linear-gradient(135deg, #1f1f1f, #2e2e2e);
  color: white;
  margin-top: 50px;
  box-shadow: 0 -4px 10px rgba(0,0,0,0.2);
  user-select: none;
}

/* ===== WATERMARK ===== */
body::before {
  content: "";
  position: fixed;
  top: 50%;
  left: 50%;
  width: 400px;
  height: 400px;
  background: url('ucmainccslogo.png') no-repeat center center;
  background-size: contain;
  opacity: 0.05;
  transform: translate(-50%, -50%);
  pointer-events: none;
  z-index: 0;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .content {
    width: 90%;
    padding: 35px 25px;
  }
  .button-group {
    flex-direction: column;
    gap: 15px;
    justify-content: center;
  }
  .topnavInside ul {
    flex-direction: column;
    gap: 12px;
  }
  .topnav {
    flex-direction: column;
    padding: 15px;
  }
}
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <div class="topnav">
    <div id="title">
      <img src="uclogo.png" alt="uclogo" id="uc">
      <span>College of Computer Studies Sit-in Monitoring System</span>
    </div>
    <div class="topnavInside">
      <ul>
        <li><a href="index.html">Home</a></li>
        <li class="dropdown">
          <a href="#">Community &amp;#9662;</a>
          <ul class="dropdown-content">
            <li><a href="events.html">Events</a></li>
            <li><a href="clubs.html">Clubs</a></li>
            <li><a href="forum.html">Forum</a></li>
          </ul>
        </li>
        <li><a href="#">About</a></li>
        <li><a class="active" href="login.php">Login</a></li>
        <li><a href="register.html">Register</a></li>
      </ul>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="content">
    <h1>Login to CCS Sit-in Monitoring System</h1>
    <p>Enter your credentials to access the system.</p>
    
    <?php if (!empty($error_msg)): ?>
    <div class="error-message"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>
    
    <!-- LOGIN FORM -->
    <form class="login-form" action="" method="POST">
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>

      <!-- BUTTONS CONTAINER -->
      <div class="button-group">
        <a href="index.html" class="back-btn">Back</a>
        <button type="submit" class="login-button">Login</button>
      </div>
    </form>
  </div>

  <!-- FOOTER -->
  <div class="footer">
    <p>&amp;copy; 2026 College of Computer Studies. All rights reserved.</p>
  </div>

</body>
</html>

