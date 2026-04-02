<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch sit-in history records for the logged-in user only
$userIdNumber = $user['id_number'];

$historyQuery = "
    SELECT s.id, s.id_number, st.first_name, st.middle_name, st.last_name, 
           s.purpose, s.lab, s.status, s.time_in, s.time_out
    FROM sitin_records s
    LEFT JOIN students st ON s.id_number = st.id_number
    WHERE s.id_number = ?
    ORDER BY s.time_in DESC
";

$stmt = $conn->prepare($historyQuery);
$stmt->bind_param("s", $userIdNumber);
$stmt->execute();
$historyResult = $stmt->get_result();
$stmt->close();

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - Sit-in Records</title>
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

        /* ===== CONTAINER ===== */
        .container {
            width: 95%;
            max-width: 1400px;
            margin: 40px auto;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            font-size: 32px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        /* ===== TABLE ===== */
        .table-wrapper {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 15px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        table th:first-child { border-radius: 20px 0 0 0; }
        table th:last-child { border-radius: 0 20px 0 0; }

        table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        table tbody tr {
            transition: all 0.3s ease;
        }

        table tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            transform: scale(1.01);
        }

        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr:last-child td:first-child { border-radius: 0 0 0 20px; }
        table tbody tr:last-child td:last-child { border-radius: 0 0 20px 0; }

        /* ===== STATUS ===== */
        .status-active {
            color: #11998e;
            font-weight: 700;
            background: rgba(17, 153, 142, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
        }

        .status-ended {
            color: #eb3349;
            font-weight: 700;
            background: rgba(235, 51, 73, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
        }

        /* ===== BUTTONS ===== */
        .btn-feedback {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-feedback:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }

        .modal.show { display: flex; }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            border-radius: 25px;
            padding: 35px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3);
            animation: scaleIn 0.4s ease;
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }

        .close {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f0f0f0 0%, #e0e0e0 100%);
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close:hover {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            transform: rotate(90deg);
        }

        /* ===== FORM ===== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* ===== NO RECORDS ===== */
        .no-records {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 900px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }
            
            .navbar .right {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            table th, table td {
                font-size: 11px;
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="left">History Reservation</div>
    <div class="right">
        <!-- Notification Dropdown -->
        <div class="dropdown">
            <button class="dropdown-btn">Notifications &#9662;</button>
            <div class="dropdown-content">
                <p>No new notifications</p>
            </div>
        </div>
        <a href="dashboard.php" class="home-link">Home</a>
        <a href="edit_profile.php">Edit Profile</a>
        <a href="history.php">History Reservation</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="container">
    <h2>Sit-in History</h2>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Full Name</th>
                    <th>Purpose</th>
                    <th>Laboratory</th>
                    <th>Login</th>
                    <th>Logout</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($historyResult && $historyResult->num_rows > 0): ?>
                    <?php while ($row = $historyResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                            <td><?php echo htmlspecialchars($row['lab']); ?></td>
                            <td><?php echo date("h:i A", strtotime($row['time_in'])); ?></td>
                            <td>
                                <?php echo $row['time_out'] 
                                    ? date("h:i A", strtotime($row['time_out'])) 
                                    : '-'; ?>
                            </td>
                            <td><?php echo date("M d, Y", strtotime($row['time_in'])); ?></td>
                            <td>
                                <button class="btn-feedback" onclick="openFeedback(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['id_number']); ?>')">
                                    Feedback
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="no-records">No sit-in records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- FEEDBACK MODAL -->
<div id="feedbackModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Submit Feedback</h3>
            <span class="close" onclick="closeFeedback()">×</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="record_id" id="feedbackRecordId">
            <input type="hidden" name="id_number" id="feedbackIdNumber">
            
            <div class="form-group">
                <label>Rating</label>
                <select name="rating" required>
                    <option value="">Select Rating</option>
                    <option value="5">Excellent</option>
                    <option value="4">Good</option>
                    <option value="3">Average</option>
                    <option value="2">Poor</option>
                    <option value="1">Very Poor</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Comments</label>
                <textarea name="comments" rows="4" placeholder="Enter your feedback here..."></textarea>
            </div>
            
            <button type="submit" name="submit_feedback" class="submit-btn">Submit Feedback</button>
        </form>
    </div>
</div>

<script>
    function openFeedback(recordId, idNumber) {
        document.getElementById('feedbackRecordId').value = recordId;
        document.getElementById('feedbackIdNumber').value = idNumber;
        document.getElementById('feedbackModal').classList.add('show');
    }

    function closeFeedback() {
        document.getElementById('feedbackModal').classList.remove('show');
    }

    window.onclick = function(event) {
        const modal = document.getElementById('feedbackModal');
        if (event.target === modal) {
            closeFeedback();
        }
    };
</script>

</body>
</html>
