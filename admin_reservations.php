<?php
session_start();
include "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// Handle approve action
if (isset($_POST['approve'])) {
    $reservation_id = intval($_POST['reservation_id']);

    // Get reservation details
    $resStmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
    $resStmt->bind_param("i", $reservation_id);
    $resStmt->execute();
    $reservation = $resStmt->get_result()->fetch_assoc();
    $resStmt->close();

    if ($reservation && $reservation['status'] === 'Pending') {
        // Update reservation status
        $updateStmt = $conn->prepare("UPDATE reservations SET status = 'Approved' WHERE id = ?");
        $updateStmt->bind_param("i", $reservation_id);
        $updateStmt->execute();
        $updateStmt->close();

        // Check student still has sessions
        $checkStmt = $conn->prepare("SELECT sessions_remaining FROM students WHERE id_number = ?");
        $checkStmt->bind_param("s", $reservation['id_number']);
        $checkStmt->execute();
        $student = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($student && $student['sessions_remaining'] > 0) {
            // Create sit-in record
            $dateTimeIn = $reservation['date'] . ' ' . $reservation['time_in'];
            $insertStmt = $conn->prepare("
                INSERT INTO sitin_records (id_number, purpose, lab, computer_number, status, time_in)
                VALUES (?, ?, ?, ?, 'Active', ?)
            ");
            $insertStmt->bind_param("sssss", $reservation['id_number'], $reservation['purpose'], $reservation['lab'], $reservation['computer_number'], $dateTimeIn);
            $insertStmt->execute();
            $insertStmt->close();
        }

        // Create notification for user
        $notifMessage = "Your reservation for " . $reservation['lab'] . " lab on " . date("M d, Y", strtotime($reservation['date'])) . " has been APPROVED.";
        $notifStmt = $conn->prepare("INSERT INTO notifications (id_number, message, type) VALUES (?, ?, 'success')");
        $notifStmt->bind_param("ss", $reservation['id_number'], $notifMessage);
        $notifStmt->execute();
        $notifStmt->close();

        $success = "Reservation #" . $reservation_id . " approved. Sit-in record created.";
    }
}

// Handle reject action
if (isset($_POST['reject'])) {
    $reservation_id = intval($_POST['reservation_id']);

    // Get reservation details for notification
    $resStmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
    $resStmt->bind_param("i", $reservation_id);
    $resStmt->execute();
    $reservation = $resStmt->get_result()->fetch_assoc();
    $resStmt->close();

    $updateStmt = $conn->prepare("UPDATE reservations SET status = 'Rejected' WHERE id = ? AND status = 'Pending'");
    $updateStmt->bind_param("i", $reservation_id);
    $updateStmt->execute();
    $updateStmt->close();

    // Create notification for user
    if ($reservation) {
        $notifMessage = "Your reservation for " . $reservation['lab'] . " lab on " . date("M d, Y", strtotime($reservation['date'])) . " has been REJECTED.";
        $notifStmt = $conn->prepare("INSERT INTO notifications (id_number, message, type) VALUES (?, ?, 'error')");
        $notifStmt->bind_param("ss", $reservation['id_number'], $notifMessage);
        $notifStmt->execute();
        $notifStmt->close();
    }

    $success = "Reservation #" . $reservation_id . " rejected.";
}

// Fetch all reservations
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
if ($filter === 'pending') {
    $resStmt = $conn->prepare("SELECT r.*, st.sessions_remaining FROM reservations r LEFT JOIN students st ON r.id_number = st.id_number WHERE r.status = 'Pending' ORDER BY r.created_at DESC");
} elseif ($filter === 'approved') {
    $resStmt = $conn->prepare("SELECT r.*, st.sessions_remaining FROM reservations r LEFT JOIN students st ON r.id_number = st.id_number WHERE r.status = 'Approved' ORDER BY r.created_at DESC");
} elseif ($filter === 'rejected') {
    $resStmt = $conn->prepare("SELECT r.*, st.sessions_remaining FROM reservations r LEFT JOIN students st ON r.id_number = st.id_number WHERE r.status = 'Rejected' ORDER BY r.created_at DESC");
} else {
    $resStmt = $conn->prepare("SELECT r.*, st.sessions_remaining FROM reservations r LEFT JOIN students st ON r.id_number = st.id_number ORDER BY r.created_at DESC");
}
$resStmt->execute();
$reservations = $resStmt->get_result();

// Lab room status - 20 computers each
$labs = ['517', '518', '519', '520', '521', '524', '526'];
$labStatus = [];

foreach ($labs as $lab) {
    // Get occupied computers by computer_number
    $occupiedQuery = $conn->prepare("SELECT computer_number FROM sitin_records WHERE lab = ? AND status = 'Active'");
    $occupiedQuery->bind_param("s", $lab);
    $occupiedQuery->execute();
    $occupiedResult = $occupiedQuery->get_result();
    
    $occupiedComputers = [];
    while ($row = $occupiedResult->fetch_assoc()) {
        if (!empty($row['computer_number'])) {
            $occupiedComputers[] = $row['computer_number'];
        }
    }
    $occupiedQuery->close();
    
    // Get total count of active sit-ins
    $countQuery = $conn->prepare("SELECT COUNT(*) as cnt FROM sitin_records WHERE lab = ? AND status = 'Active'");
    $countQuery->bind_param("s", $lab);
    $countQuery->execute();
    $countResult = $countQuery->get_result()->fetch_assoc();
    $totalOccupied = $countResult['cnt'] ?? 0;
    $countQuery->close();
    
    // If we have fewer identified than total, add fallback (old records without computer_number)
    $identifiedCount = count($occupiedComputers);
    $unidentifiedCount = $totalOccupied - $identifiedCount;
    
    // For unidentified, assume first few computers are taken (1, 2, 3, etc.)
    for ($i = 1; $i <= $unidentifiedCount; $i++) {
        $pcNum = str_pad($i, 2, '0', STR_PAD_LEFT);
        if (!in_array($pcNum, $occupiedComputers)) {
            $occupiedComputers[] = $pcNum;
        }
    }
    
    $occupied = count($occupiedComputers);
    $vacant = max(0, 20 - $occupied);
    $labStatus[] = [
        'lab' => $lab, 
        'occupied' => $occupied, 
        'vacant' => $vacant,
        'occupied_computers' => $occupiedComputers
    ];
}

// Count stats
$pendingCount = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status='Pending'")->fetch_assoc()['total'];
$approvedCount = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status='Approved'")->fetch_assoc()['total'];
$rejectedCount = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status='Rejected'")->fetch_assoc()['total'];

/* SEARCH STUDENT */
$searchResults = null;

if(isset($_POST['search'])){
    $keyword = trim($_POST['keyword']);

    // Validate search input
    if (strlen($keyword) > 100) {
        $keyword = substr($keyword, 0, 100);
    }

    if (!preg_match('/^[a-zA-Z0-9 .\-_]+$/', $keyword)) {
        $keyword = preg_replace('/[^a-zA-Z0-9 .\-_]/', '', $keyword);
    }

    $keyword = "%" . $keyword . "%";

    $stmt = $conn->prepare("
        SELECT * FROM students
        WHERE
            (LOWER(id_number) LIKE LOWER(?)
            OR LOWER(course) LIKE LOWER(?)
            OR LOWER(CONCAT(first_name, ' ', middle_name, ' ', last_name)) LIKE LOWER(?))
            AND role = 'student'
    ");
    $stmt->bind_param("sss", $keyword, $keyword, $keyword);
    $stmt->execute();
    $searchResults = $stmt->get_result();
}

/* SIT-IN SUBMIT HANDLER */
if(isset($_POST['sit_in_submit'])){
    $id_number = trim($_POST['id_number']);
    $purpose = trim($_POST['purpose']);
    $lab = trim($_POST['lab']);
    $computer = trim($_POST['computer']);

    $checkStmt = $conn->prepare("SELECT sessions_remaining FROM students WHERE id_number = ?");
    $checkStmt->bind_param("s", $id_number);
    $checkStmt->execute();
    $student = $checkStmt->get_result()->fetch_assoc();

    if($student && $student['sessions_remaining'] > 0){
        $insertStmt = $conn->prepare("
            INSERT INTO sitin_records (id_number, purpose, lab, computer_number, status, time_in)
            VALUES (?, ?, ?, ?, 'Active', NOW())
        ");
        $insertStmt->bind_param("ssss", $id_number, $purpose, $lab, $computer);
        $insertStmt->execute();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Reservations</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

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
            border-radius: 50%;
            transition: transform 0.4s ease;
            box-shadow: 0 0 15px rgba(255,255,255,0.3);
        }

        #uc:hover {
            transform: rotate(360deg) scale(1.1);
        }

        .topnavInside ul {
            display: flex;
            list-style: none;
            gap: 5px;
        }

        .topnavInside ul li a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .topnavInside ul li a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .topnavInside ul li a:hover::before {
            width: 80%;
        }

        .topnavInside ul li a:hover {
            color: white;
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        .topnavInside ul li a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .topnavInside ul li a.active::before {
            display: none;
        }

        .container {
            width: 95%;
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

        .success-msg {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
        }

        .error-msg {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(235, 51, 73, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            cursor: pointer;
            text-decoration: none;
            display: block;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
        }

        .stat-card .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 13px;
            font-weight: 500;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card.pending .stat-number { color: #f5a623; }
        .stat-card.approved .stat-number { color: #11998e; }
        .stat-card.rejected .stat-number { color: #eb3349; }

        .stat-card.active-card {
            border: 3px solid #667eea;
        }

        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }

        .filter-btn {
            padding: 10px 22px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .filter-btn.default {
            background: white;
            color: #333;
        }

        .filter-btn.default:hover {
            background: #667eea;
            color: white;
        }

        .filter-btn.active-filter {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        table td {
            padding: 14px 12px;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
            font-size: 13px;
            transition: all 0.3s ease;
        }

        table tbody tr {
            transition: all 0.3s ease;
        }

        table tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        table tbody tr:last-child td { border-bottom: none; }

        .status-pending {
            color: #f5a623;
            font-weight: 700;
            background: rgba(245, 166, 35, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
        }

        .status-approved {
            color: #11998e;
            font-weight: 700;
            background: rgba(17, 153, 142, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
        }

        .status-rejected {
            color: #eb3349;
            font-weight: 700;
            background: rgba(235, 51, 73, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
        }

        .btn-approve {
            padding: 8px 16px;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(56, 239, 125, 0.3);
            margin: 2px;
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(56, 239, 125, 0.4);
        }

        .btn-reject {
            padding: 8px 16px;
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            box-shadow: 0 3px 10px rgba(235, 51, 73, 0.3);
            margin: 2px;
        }

        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(235, 51, 73, 0.4);
        }

        @media (max-width: 768px) {
            .topnav {
                padding: 10px 20px;
                flex-direction: column;
                gap: 15px;
            }

            .topnavInside ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 8px;
            }

            .topnavInside ul li a {
                padding: 8px 12px;
                font-size: 12px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-bar {
                flex-wrap: wrap;
            }

            table th, table td {
                font-size: 11px;
                padding: 10px 5px;
            }

            .search-form {
                flex-direction: column;
            }

            .sit-in-form {
                grid-template-columns: 1fr;
            }

            .sit-in-form .btn-submit {
                grid-column: span 1;
            }
        }

        /* ========================= */
        /* MODAL OVERLAY (BLUR BG)   */
        /* ========================= */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal.show {
            display: flex;
        }

        /* ========================= */
        /* MODAL CARD                */
        /* ========================= */
        .modal-content {
            background: #f4f4f6;
            width: 90%;
            max-width: 700px;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        /* ========================= */
        /* HEADER                    */
        /* ========================= */
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .close {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.3s;
            border: none;
            font-size: 20px;
            color: #333;
        }

        .close:hover {
            background: #bbb;
        }

        /* ========================= */
        /* SEARCH BAR                */
        /* ========================= */
        .search-form {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }

        .search-form input {
            flex: 1;
            padding: 12px 15px;
            border-radius: 12px;
            border: 1px solid #ddd;
            outline: none;
            font-size: 14px;
            transition: 0.3s;
        }

        .search-form input:focus {
            border-color: #7b6cf6;
        }

        /* ========================= */
        /* BUTTON (PURPLE GRADIENT)  */
        /* ========================= */
        .btn-search {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: 0.3s;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }

        /* ========================= */
        /* NO RECORDS                */
        /* ========================= */
        .no-records {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 15px;
        }

        /* ========================= */
        /* SIT-IN FORM CARD          */
        /* ========================= */
        .sit-in-form {
            margin-top: 15px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .sit-in-form label {
            font-size: 13px;
            font-weight: 500;
        }

        .sit-in-form input,
        .sit-in-form select {
            padding: 10px;
            border-radius: 10px;
            border: 1px solid #ddd;
            width: 100%;
            font-size: 14px;
            transition: 0.3s;
        }

        .sit-in-form input:focus,
        .sit-in-form select:focus {
            border-color: #7b6cf6;
            outline: none;
        }

        .sit-in-form input[readonly] {
            background: #eee;
        }

        .sit-in-form .btn-submit {
            grid-column: span 2;
            margin-top: 10px;
            padding: 12px;
            background: #1cc88a;
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: 0.3s;
        }

        .sit-in-form .btn-submit:hover {
            background: #17a673;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="topnav">
    <div id="title">
        <img src="uclogo.png" id="uc">
        <span>College of Computer Studies Sit-in Monitoring System</span>
    </div>
    <div class="topnavInside">
        <ul>
            <li><a href="admin_dashboard.php">Home</a></li>
            <li><a href="#" onclick="openSearch(); return false;">Search</a></li>
            <li><a href="students.php">Students</a></li>
            <li><a href="sit_in.php">Sit-in</a></li>
            <li><a href="view_sitin_records.php">View Sit-in Records</a></li>
            <li><a href="#">Sit-in Reports</a></li>
            <li><a href="feedback_reports.php">Feedback Reports</a></li>
            <li><a class="active" href="admin_reservations.php">Reservation</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
</div>

<div class="container">
    <h2>Reservation Requests</h2>

    <!-- Lab Room Status -->
    <h3 style="color: white; text-align: center; margin-bottom: 15px;">Lab Room Status</h3>
    <div class="lab-status-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 30px;">
        <?php foreach ($labStatus as $labInfo): ?>
            <div class="lab-card" onclick="openLabDetail('<?php echo $labInfo['lab']; ?>')" style="background: white; padding: 15px; border-radius: 15px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.3s ease;">
                <h4 style="margin: 0 0 10px 0; color: #333;">Lab <?php echo $labInfo['lab']; ?></h4>
                <div style="display: flex; justify-content: space-around; gap: 10px;">
                    <div style="background: rgba(235, 51, 73, 0.1); padding: 8px 12px; border-radius: 10px;">
                        <div style="font-size: 18px; font-weight: 700; color: #eb3349;"><?php echo $labInfo['occupied']; ?></div>
                        <div style="font-size: 11px; color: #666;">Occupied</div>
                    </div>
                    <div style="background: rgba(17, 153, 142, 0.1); padding: 8px 12px; border-radius: 10px;">
                        <div style="font-size: 18px; font-weight: 700; color: #11998e;"><?php echo $labInfo['vacant']; ?></div>
                        <div style="font-size: 11px; color: #666;">Vacant</div>
                    </div>
                </div>
                <div style="font-size: 10px; color: #667eea; margin-top: 8px;">Click to view computers</div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Lab Detail Modal -->
    <div id="labDetailModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 id="labDetailTitle" style="color: black;">Lab Details</h3>
                <button class="close" onclick="closeLabDetail()">&times;</button>
            </div>
            <div id="labDetailContent" style="padding: 20px 0;">
                <!-- Computer grid will be loaded here -->
            </div>
        </div>
    </div>

    <script>
    const labStatusData = <?php echo json_encode($labStatus); ?>;
    
    function openLabDetail(lab) {
        const labInfo = labStatusData.find(l => l.lab === lab);
        if (!labInfo) return;
        
        document.getElementById('labDetailTitle').textContent = 'Lab ' + lab + ' - Computer Status';
        
        let html = '<div style="display: flex; justify-content: space-around; margin-bottom: 20px;">';
        html += '<div style="text-align: center;"><span style="font-size: 24px; font-weight: 700; color: #eb3349;">' + labInfo.occupied + '</span><br><small>Occupied</small></div>';
        html += '<div style="text-align: center;"><span style="font-size: 24px; font-weight: 700; color: #11998e;">' + labInfo.vacant + '</span><br><small>Vacant</small></div>';
        html += '</div>';
        
        html += '<div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px;">';
        for (let i = 1; i <= 20; i++) {
            const pcNum = String(i).padStart(2, '0');
            const isOccupied = labInfo.occupied_computers.includes(pcNum);
            const statusClass = isOccupied ? 'occupied' : 'vacant';
            const statusLabel = isOccupied ? 'Occupied' : 'Vacant';
            const bgColor = isOccupied ? '#9ca3af' : '#10b981';
            const textColor = isOccupied ? '#fff' : '#fff';
            html += '<div style="background: ' + bgColor + '; color: ' + textColor + '; padding: 15px; border-radius: 10px; text-align: center; font-weight: 600;">';
            html += '<div style="font-size: 18px;">' + pcNum + '</div>';
            html += '<div style="font-size: 10px;">' + statusLabel + '</div>';
            html += '</div>';
        }
        html += '</div>';
        
        html += '<div style="display: flex; justify-content: center; gap: 30px; padding: 15px; background: #f3f4f6; border-radius: 10px;">';
        html += '<div style="display: flex; align-items: center; gap: 8px;"><div style="width: 20px; height: 20px; background: #10b981; border-radius: 5px;"></div><span>Vacant</span></div>';
        html += '<div style="display: flex; align-items: center; gap: 8px;"><div style="width: 20px; height: 20px; background: #9ca3af; border-radius: 5px;"></div><span>Occupied</span></div>';
        html += '</div>';
        
        document.getElementById('labDetailContent').innerHTML = html;
        document.getElementById('labDetailModal').classList.add('show');
    }
    
    function closeLabDetail() {
        document.getElementById('labDetailModal').classList.remove('show');
    }
    </script>

    <?php if(isset($success)): ?>
        <div class="success-msg"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
        <a href="admin_reservations.php?filter=pending" class="stat-card pending <?php echo ($filter === 'pending') ? 'active-card' : ''; ?>">
            <div class="stat-number"><?php echo $pendingCount; ?></div>
            <div class="stat-label">Pending</div>
        </a>
        <a href="admin_reservations.php?filter=approved" class="stat-card approved <?php echo ($filter === 'approved') ? 'active-card' : ''; ?>">
            <div class="stat-number"><?php echo $approvedCount; ?></div>
            <div class="stat-label">Approved</div>
        </a>
        <a href="admin_reservations.php?filter=rejected" class="stat-card rejected <?php echo ($filter === 'rejected') ? 'active-card' : ''; ?>">
            <div class="stat-number"><?php echo $rejectedCount; ?></div>
            <div class="stat-label">Rejected</div>
        </a>
    </div>

    <!-- FILTER -->
    <div class="filter-bar">
        <a href="admin_reservations.php?filter=all" class="filter-btn <?php echo ($filter === 'all') ? 'active-filter' : 'default'; ?>">All</a>
        <a href="admin_reservations.php?filter=pending" class="filter-btn <?php echo ($filter === 'pending') ? 'active-filter' : 'default'; ?>">Pending</a>
        <a href="admin_reservations.php?filter=approved" class="filter-btn <?php echo ($filter === 'approved') ? 'active-filter' : 'default'; ?>">Approved</a>
        <a href="admin_reservations.php?filter=rejected" class="filter-btn <?php echo ($filter === 'rejected') ? 'active-filter' : 'default'; ?>">Rejected</a>
    </div>

    <!-- RESERVATIONS TABLE -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>ID Number</th>
                <th>Student Name</th>
                <th>Purpose</th>
                <th>Lab</th>
                <th>Date</th>
                <th>Time In</th>
                <th>Sessions</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($reservations && $reservations->num_rows > 0): ?>
                <?php while ($row = $reservations->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($row['lab']); ?></td>
                        <td><?php echo date("M d, Y", strtotime($row['date'])); ?></td>
                        <td><?php echo date("h:i A", strtotime($row['time_in'])); ?></td>
                        <td><?php echo $row['sessions_remaining'] ?? $row['remaining_sessions']; ?></td>
                        <td>
                            <?php
                                $statusClass = 'status-pending';
                                if ($row['status'] === 'Approved') $statusClass = 'status-approved';
                                elseif ($row['status'] === 'Rejected') $statusClass = 'status-rejected';
                            ?>
                            <span class="<?php echo $statusClass; ?>"><?php echo $row['status']; ?></span>
                        </td>
                        <td><?php echo date("M d, Y", strtotime($row['created_at'])); ?></td>
                        <td>
                            <?php if ($row['status'] === 'Pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="approve" class="btn-approve">Approve</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="reject" class="btn-reject">Reject</button>
                                </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" class="no-records">No reservations found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- SEARCH MODAL -->
<div id="searchModal" class="modal">
    <div class="modal-content">

        <!-- HEADER -->
        <div class="modal-header">
            <h3 style="color: black;">Search Student</h3>
            <button class="close" onclick="closeSearch()">&times;</button>
        </div>

        <!-- SEARCH FORM -->
        <form method="POST" class="search-form">
            <input type="text" name="keyword" placeholder="Enter ID Number, Name, or Course..."
                value="<?php echo isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : ''; ?>"
                required>
            <button type="submit" name="search" class="btn-search">Search</button>
        </form>

        <!-- SEARCH RESULTS -->
        <?php if ($searchResults !== null): ?>

            <?php if ($searchResults->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Number</th>
                            <th>Name</th>
                            <th>Course</th>
                            <th>Sessions</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $searchResults->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                                <td><?php echo htmlspecialchars(
                                    $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']
                                ); ?></td>
                                <td><?php echo htmlspecialchars($row['course']); ?></td>
                                <td><?php echo htmlspecialchars($row['sessions_remaining'] ?? 30); ?></td>
                                <td>
                                    <button type="button" class="btn-search"
                                        onclick="selectStudent('<?php echo htmlspecialchars($row['id_number']); ?>', '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']); ?>', '<?php echo htmlspecialchars($row['sessions_remaining'] ?? 30); ?>')">
                                        Sit In
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-records">No students found.</p>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<!-- SIT-IN MODAL -->
<div id="sitInModal" class="modal">
    <div class="modal-content">

        <div class="modal-header">
            <h3 stlye="color: black;">Sit-In Form</h3>
            <button class="close" onclick="closeSitIn()">&times;</button>
        </div>

        <form method="POST" action="sit_in.php" class="sit-in-form">
            <input type="hidden" name="id_number" id="form_id">

            <label>ID Number</label>
            <input type="text" id="form_id_display" readonly>

            <label>Student Name</label>
            <input type="text" id="form_name" readonly>

            <label>Purpose *</label>
            <select name="purpose" required>
                <option value="">Select Purpose</option>
                <option value="C">C</option>
                <option value="C#">C#</option>
                <option value="Java">Java</option>
                <option value="PHP">PHP</option>
                <option value="ASP.Net">ASP.Net</option>
            </select>

            <label>Lab *</label>
            <select name="lab" id="form_lab" required onchange="updateComputerOptions()">
                <option value="">Select Lab</option>
                <option value="517">Lab 517</option>
                <option value="518">Lab 518</option>
                <option value="519">Lab 519</option>
                <option value="520">Lab 520</option>
                <option value="521">Lab 521</option>
                <option value="524">Lab 524</option>
                <option value="526">Lab 526</option>
            </select>

            <label>Computer</label>
            <select name="computer" id="form_computer" required>
                <option value="">Select Computer</option>
            </select>

            <label>Sessions Left</label>
            <input type="text" id="form_sessions" readonly>

            <button type="submit" name="sit_in_submit" class="btn-submit">✓ Sit In</button>
        </form>

    </div>
</div>

<script>
function openSearch(){
    document.getElementById("searchModal").classList.add("show");
}

function closeSearch(){
    document.getElementById("searchModal").classList.remove("show");
}

function openSitIn(){
    document.getElementById("sitInModal").classList.add("show");
}

function closeSitIn(){
    document.getElementById("sitInModal").classList.remove("show");
}

function selectStudent(id, name, session){
    closeSearch();

    document.getElementById("form_id").value = id;
    document.getElementById("form_id_display").value = id;
    document.getElementById("form_name").value = name;
    document.getElementById("form_sessions").value = session;
    document.getElementById("form_lab").value = "";
    document.getElementById("form_computer").innerHTML = '<option value="">Select Computer</option>';

    document.getElementById("sitInModal").classList.add("show");
}

function updateComputerOptions() {
    const lab = document.getElementById("form_lab").value;
    const computerSelect = document.getElementById("form_computer");
    
    if (!lab) {
        computerSelect.innerHTML = '<option value="">Select Computer</option>';
        return;
    }

    const labInfo = labStatusData.find(l => l.lab === lab);
    if (!labInfo) return;

    let html = '<option value="">Select Computer</option>';
    for (let i = 1; i <= 20; i++) {
        const pcNum = String(i).padStart(2, '0');
        if (!labInfo.occupied_computers.includes(pcNum)) {
            html += '<option value="' + pcNum + '">' + pcNum + '</option>';
        }
    }
    computerSelect.innerHTML = html;
}

window.onclick = function(event){
    const searchModal = document.getElementById("searchModal");
    const sitInModal = document.getElementById("sitInModal");

    if(event.target === searchModal){
        closeSearch();
    }

    if(event.target === sitInModal){
        closeSitIn();
    }
}

<?php if ($searchResults !== null): ?>
openSearch();
<?php endif; ?>
</script>

</body>
</html>