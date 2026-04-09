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

$userIdNumber = $user['id_number'];
$studentName = $user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name'];
$sessionsRemaining = $user['sessions_remaining'] ?? 30;

// Handle reservation submission
if (isset($_POST['submit_reservation'])) {
    $purpose = trim($_POST['purpose']);
    $lab = trim($_POST['lab']);
    $computer = trim($_POST['computer'] ?? '');
    $date = trim($_POST['date']);
    $time_in = trim($_POST['time_in']);

    if (empty($purpose) || empty($lab) || empty($date) || empty($time_in)) {
        $error = "Please fill in all fields.";
    } elseif (empty($computer)) {
        $error = "Please select a computer from the lab availability modal.";
    } elseif ($sessionsRemaining <= 0) {
        $error = "You have no remaining sessions.";
    } else {
        // Check if user has an active sit-in
        $activeSitInStmt = $conn->prepare("SELECT id FROM sitin_records WHERE id_number = ? AND status = 'Active'");
        $activeSitInStmt->bind_param("s", $userIdNumber);
        $activeSitInStmt->execute();
        $activeSitInResult = $activeSitInStmt->get_result();
        
        if ($activeSitInResult->num_rows > 0) {
            $error = "You currently have an active sit-in session. Please end your current session before making a reservation.";
        } else {
            // Check if computer is already occupied in sitin_records
            $checkComputerStmt = $conn->prepare("SELECT id FROM sitin_records WHERE lab = ? AND computer_number = ? AND status = 'Active'");
            $checkComputerStmt->bind_param("ss", $lab, $computer);
            $checkComputerStmt->execute();
            $computerResult = $checkComputerStmt->get_result();
            
            if ($computerResult->num_rows > 0) {
                $error = "Computer " . $computer . " in Lab " . $lab . " is currently occupied. Please choose another computer.";
            } else {
                // Check for duplicate pending reservation on same date
                $checkStmt = $conn->prepare("SELECT id FROM reservations WHERE id_number = ? AND date = ? AND status = 'Pending'");
                $checkStmt->bind_param("ss", $userIdNumber, $date);
                $checkStmt->execute();
                $dupResult = $checkStmt->get_result();

                if ($dupResult->num_rows > 0) {
                    $error = "You already have a pending reservation for this date.";
                } else {
                    $insertStmt = $conn->prepare("
                        INSERT INTO reservations (id_number, student_name, purpose, lab, computer_number, date, time_in, remaining_sessions, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
                    ");
                    $insertStmt->bind_param("sssssssi", $userIdNumber, $studentName, $purpose, $lab, $computer, $date, $time_in, $sessionsRemaining);
                    $insertStmt->execute();
                    $insertStmt->close();
                    $success = "Reservation submitted successfully! Please wait for admin approval.";
                }
                $checkStmt->close();
            }
            $checkComputerStmt->close();
        }
        $activeSitInStmt->close();
    }
}

// Fetch user's reservations
$resStmt = $conn->prepare("SELECT * FROM reservations WHERE id_number = ? ORDER BY created_at DESC");
$resStmt->bind_param("s", $userIdNumber);
$resStmt->execute();
$reservations = $resStmt->get_result();
$resStmt->close();

// Lab room status - 20 computers each
$labs = ['517', '518', '519', '520', '521', '524', '526'];
$labStatus = [];

foreach ($labs as $lab) {
    $occupiedQuery = $conn->prepare("SELECT COUNT(*) as count FROM sitin_records WHERE lab = ? AND status = 'Active'");
    $occupiedQuery->bind_param("s", $lab);
    $occupiedQuery->execute();
    $occupiedResult = $occupiedQuery->get_result()->fetch_assoc();
    $occupied = $occupiedResult['count'] ?? 0;
    $vacant = 20 - $occupied;
    
    // Get reserved computers for this lab on current date
    $reservedQuery = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE lab = ? AND date = CURDATE() AND status = 'Approved'");
    $reservedQuery->bind_param("s", $lab);
    $reservedQuery->execute();
    $reservedResult = $reservedQuery->get_result()->fetch_assoc();
    $reserved = $reservedResult['count'] ?? 0;
    $reservedQuery->close();
    
    $labStatus[] = [
        'lab' => $lab, 
        'occupied' => $occupied, 
        'vacant' => $vacant,
        'reserved' => $reserved
    ];
    $occupiedQuery->close();
}

// Fetch notifications for the user
$notifQuery = "SELECT * FROM notifications WHERE id_number = ? ORDER BY created_at DESC LIMIT 10";
$notifStmt = $conn->prepare($notifQuery);
$notifStmt->bind_param("s", $userIdNumber);
$notifStmt->execute();
$notifications = $notifStmt->get_result();

// Store notifications in array before closing
$notifications_data = [];
if($notifications && $notifications->num_rows > 0){
    while($n = $notifications->fetch_assoc()){
        $notifications_data[] = $n;
    }
}
$notifStmt->close();

$conn->close();
unset($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

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
            font-family: 'Poppins', sans-serif;
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

        .dropdown {
            position: relative;
            z-index: 9999;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 50px;
            right: 0;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-width: 280px;
            max-height: 350px;
            overflow-y: auto;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            border-radius: 12px;
            overflow: hidden;
            z-index: 10000;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            opacity: 0;
            pointer-events: none;
        }

        .notif-badge {
            background: #eb3349;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }

        .notif-item {
            display: block;
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            cursor: default;
        }

        .notif-item:last-child {
            border-bottom: none;
        }

        .notif-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .notif-item.success { border-left: 3px solid #38ef7d; }
        .notif-item.error { border-left: 3px solid #eb3349; }
        .notif-item.info { border-left: 3px solid #667eea; }

        .notif-icon {
            font-weight: 700;
            margin-right: 8px;
        }

        .notif-item.success .notif-icon { color: #38ef7d; }
        .notif-item.error .notif-icon { color: #eb3349; }
        .notif-item.info .notif-icon { color: #667eea; }

        .notif-time {
            display: block;
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            margin-top: 5px;
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
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .container {
            width: 95%;
            max-width: 1200px;
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
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            border-left: 4px solid #059669;
        }

        .error-msg {
            background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
            border-left: 4px solid #dc2626;
        }

        .reservation-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08), 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            border: 1px solid #e5e7eb;
        }

        .reservation-card h3 {
            border-left: 4px solid #6366f1;
            padding-left: 14px;
            margin: 0 0 30px 0;
            color: #111827;
            font-size: 20px;
            font-weight: 700;
        }

        .button-group {
            display: flex;
            gap: 16px;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
        }

        .button-group .back-btn,
        .button-group .btn-submit {
            padding: 14px 32px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            letter-spacing: 0.3px;
        }

        .button-group .back-btn:hover,
        .button-group .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.35);
        }

        .button-group .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

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

        table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
            font-size: 14px;
        }

        table tbody tr {
            transition: all 0.3s ease;
        }

        table tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            transform: scale(1.01);
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

        .no-records {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
        }

        .lab-container {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 30px;
        }

        .lab-room {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            flex: 1;
            min-width: 300px;
            max-width: 400px;
        }

        .lab-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .lab-header h4 {
            margin: 0;
            color: #1a1a2e;
            font-size: 20px;
        }

        .lab-status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .lab-status-badge.available {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .lab-status-badge.busy {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .lab-status-badge.full {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .lab-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }

        .lab-stat {
            text-align: center;
            padding: 15px;
        }

        .lab-stat .count {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .lab-stat .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        .lab-stat.occupied .count { color: #667eea; }
        .lab-stat.vacant .count { color: #10b981; }
        .lab-stat.reserved .count { color: #f59e0b; }

        .computer-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-top: 15px;
        }

        .computer-pc {
            aspect-ratio: 1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            padding: 8px;
        }

        .computer-pc.vacant {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }

        .computer-pc.occupied {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            color: #4338ca;
        }

        .computer-pc.reserved {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
        }

        .computer-pc:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 35px 45px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 24px;
            position: relative;
        }

        .form-group label {
            margin-bottom: 10px;
            color: #374151;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select {
            padding: 14px 18px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            background: #fafafa;
            color: #1f2937;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }

        .form-group input:hover,
        .form-group select:hover {
            border-color: #9ca3af;
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1), inset 0 1px 2px rgba(0,0,0,0.05);
        }

        .form-group input[readonly] {
            background: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
            border-color: #e5e7eb;
        }

        .form-group::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .form-group:focus-within::after {
            width: 100%;
        }

        .search-lab-btn {
            padding: 14px 20px;
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            color: #374151;
            border: 2px dashed #d1d5db;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.25s ease;
            width: 100%;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .search-lab-btn:hover {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
            border-color: #6366f1;
            color: #6366f1;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }

            .navbar .right {
                flex-wrap: wrap;
                justify-content: center;
            }

            .form-grid {
                display: grid;
                column-gap: 60px;
                row-gap: 25px;
                grid-template-columns: repeat(2, 1fr);
            }

            .button-group {
                flex-direction: row;
                align-items: center;
                justify-content: center;
            }

            table th, table td {
                font-size: 11px;
                padding: 10px 5px;
            }
        }

        @media (orientation: landscape) and (max-height: 500px) {
            .button-group {
                flex-direction: row;
                align-items: center;
                justify-content: center;
            }
        }

        /* Lab Modal Styles */
        .lab-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .lab-modal-overlay.show {
            display: flex;
        }

        .lab-modal {
            background: white;
            border-radius: 24px;
            width: 100%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
        }

        .lab-modal-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .lab-modal-header h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }

        .lab-modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 28px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lab-modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .lab-modal-body {
            padding: 40px;
            max-height: calc(90vh - 100px);
            overflow-y: auto;
        }

        .lab-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

.lab-summary-item {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .lab-summary-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .lab-summary-item.has-vacant {
            border-color: #10b981;
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        }

        .lab-summary-item.no-vacant {
            border-color: #ef4444;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        }

        .lab-summary-item .lab-num {
            font-size: 28px;
            font-weight: 800;
            color: #1a1a2e;
        }

        .lab-summary-item.has-vacant .lab-num { color: #059669; }
        .lab-summary-item.no-vacant .lab-num { color: #dc2626; }

        .lab-summary-item .lab-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 10px;
            font-size: 13px;
        }

        .lab-summary-item .lab-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 10px;
            font-size: 12px;
        }

        .lab-summary-item .occupied { color: #667eea; }
        .lab-summary-item .vacant { color: #10b981; }

        .lab-detail-view {
            display: none;
            background: #f8fafc;
            border-radius: 20px;
            padding: 30px;
            margin-top: 20px;
        }

        .lab-detail-view.show {
            display: block;
        }

        .lab-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .lab-detail-header h4 {
            margin: 0;
            color: #1a1a2e;
            font-size: 22px;
        }

        .computer-grid-modal {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
        }

        .computer-pc-modal {
            aspect-ratio: 1;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.3s ease;
            cursor: pointer;
            padding: 5px;
            position: relative;
        }

        .computer-pc-modal.vacant {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: #fff;
            border: 2px solid #059669;
        }

        .computer-pc-modal.occupied {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
            color: #fff;
            border: 2px solid #4b5563;
        }

        .computer-pc-modal:hover {
            transform: scale(1.08);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .computer-legend {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
        }

        .computer-legend .legend-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
        }

        .computer-legend .legend-item.vacant {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }

        .computer-legend .legend-item.occupied {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            color: #4b5563;
        }

        .computer-legend span {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #64748b;
        }

        .computer-legend .legend-dot {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }

        .computer-legend .legend-dot.vacant {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        }

        .computer-legend .legend-dot.occupied {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
        }

        .select-lab-btn {
            margin-top: 20px;
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .select-lab-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="left">Reservation</div>
    <div class="right">
        <!-- Notification Dropdown -->
        <div class="dropdown">
            <button class="dropdown-btn">
                Notifications 
                <?php 
                $unreadCount = 0;
                foreach($notifications_data as $n){
                    if(!$n['is_read']) $unreadCount++;
                }
                if($unreadCount > 0) echo '<span class="notif-badge">' . $unreadCount . '</span>';
                ?>
            </button>
            <div class="dropdown-content">
                <?php 
                if(count($notifications_data) > 0): 
                    foreach($notifications_data as $notif): 
                        $targetPage = ($notif['type'] === 'success') ? 'history.php' : 'reservation.php';
                ?>
                    <p class="notif-item <?php echo htmlspecialchars($notif['type']); ?>" onclick="markRead('<?php echo $userIdNumber; ?>', '<?php echo $targetPage; ?>')" style="cursor:pointer;">
                        <span class="notif-icon"><?php echo $notif['type'] === 'success' ? '✓' : ($notif['type'] === 'error' ? '✗' : 'ℹ'); ?></span>
                        <?php echo htmlspecialchars($notif['message']); ?>
                        <small class="notif-time"><?php echo date("M d, h:i A", strtotime($notif['created_at'])); ?></small>
                    </p>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <p>No new notifications</p>
                <?php endif; ?>
            </div>
        </div>
        <a href="dashboard.php">Home</a>
        <a href="edit_profile.php">Edit Profile</a>
        <a href="history.php">History</a>
        <a href="reservation.php" class="active">Reservation</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<script>
function markRead(idNumber, redirect) {
    fetch('mark_notif_read.php?id_number=' + idNumber)
        .then(() => { window.location.href = redirect; });
}
</script>

<div class="container">
    <h2>Reserve a Sit-in</h2>

    <?php if(isset($success)): ?>
        <div class="success-msg"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Lab Availability Modal -->
    <div class="lab-modal-overlay" id="labModal">
        <div class="lab-modal">
            <div class="lab-modal-header">
                <h3>🔍 Lab Room Availability</h3>
                <button class="lab-modal-close" onclick="closeLabModal()">&times;</button>
            </div>
            <div class="lab-modal-body">
                <p style="text-align: center; color: #64748b; margin-bottom: 25px;">
                    Click on a lab room to view available computers
                </p>
                <div class="lab-summary">
                    <?php foreach ($labStatus as $labInfo): 
                        $occupancy = $labInfo['occupied'] / 20 * 100;
                        $statusClass = $labInfo['occupied'] >= 20 ? 'no-vacant' : 'has-vacant';
                    ?>
                    <div class="lab-summary-item <?php echo $statusClass; ?>" onclick="showLabDetail('<?php echo $labInfo['lab']; ?>')" data-lab="<?php echo $labInfo['lab']; ?>">
                        <div class="lab-num">Lab <?php echo $labInfo['lab']; ?></div>
                        <div class="lab-stats">
                            <span class="occupied"><?php echo $labInfo['occupied']; ?> occ</span>
                            <span class="vacant"><?php echo $labInfo['vacant']; ?> free</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Lab Detail View -->
                <?php foreach ($labStatus as $labInfo): ?>
                <div class="lab-detail-view" id="lab-detail-<?php echo $labInfo['lab']; ?>">
                    <div class="lab-detail-header">
                        <h4>Lab <?php echo $labInfo['lab']; ?></h4>
                        <span class="lab-status-badge <?php echo $labInfo['occupied'] >= 20 ? 'full' : ($labInfo['occupied'] >= 10 ? 'busy' : 'available'); ?>">
                            <?php echo $labInfo['occupied'] >= 20 ? 'Full' : ($labInfo['occupied'] >= 10 ? 'Busy' : 'Available'); ?>
                        </span>
                    </div>
                    <div style="display: flex; justify-content: space-around; margin-bottom: 20px;">
                        <div class="lab-stat occupied">
                            <div class="count"><?php echo $labInfo['occupied']; ?></div>
                            <div class="label">Occupied</div>
                        </div>
                        <div class="lab-stat vacant">
                            <div class="count"><?php echo $labInfo['vacant']; ?></div>
                            <div class="label">Vacant</div>
                        </div>
                    </div>
                    <div class="computer-grid-modal">
                        <?php 
                        for ($i = 1; $i <= 20; $i++) {
                            $status = $i <= $labInfo['occupied'] ? 'occupied' : 'vacant';
                            $pcNum = str_pad($i, 2, '0', STR_PAD_LEFT);
                        ?>
                        <div class="computer-pc-modal <?php echo $status; ?>" data-pc="<?php echo $pcNum; ?>" onclick="selectComputer('<?php echo $labInfo['lab']; ?>', '<?php echo $pcNum; ?>')">
                            <span style="font-size: 16px;">💻</span><br><?php echo $pcNum; ?>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="computer-legend">
                        <div class="legend-item vacant">
                            <span style="font-size: 18px;">✓</span> Vacant Computers
                        </div>
                        <div class="legend-item occupied">
                            <span style="font-size: 18px;">✗</span> Occupied Computers
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    function selectLab(lab) {
        document.querySelector('input[name="lab"]').value = lab;
    }

    function openLabModal() {
        document.getElementById('labModal').classList.add('show');
    }

    function closeLabModal() {
        document.getElementById('labModal').classList.remove('show');
    }

    function showLabDetail(lab) {
        // Hide all lab details
        document.querySelectorAll('.lab-detail-view').forEach(el => el.classList.remove('show'));
        // Show selected lab detail
        document.getElementById('lab-detail-' + lab).classList.add('show');
        // Update selected state
        document.querySelectorAll('.lab-summary-item').forEach(el => el.classList.remove('selected'));
        document.querySelector('.lab-summary-item[data-lab="' + lab + '"]').classList.add('selected');
    }

        function selectComputer(lab, computer) {
        const pcElement = document.querySelector('#lab-detail-' + lab + ' .computer-pc-modal[data-pc="' + computer + '"]');
        if (pcElement && pcElement.classList.contains('occupied')) {
            alert('Computer ' + computer + ' is currently occupied. Please choose another computer.');
            return;
        }
        
        document.getElementById('selectedLab').value = lab;
        document.getElementById('selectedComputer').value = computer;
        
        document.querySelectorAll('.computer-pc-modal').forEach(el => el.style.border = 'none');
        pcElement.style.border = '3px solid #667eea';
        
        const displayDiv = document.getElementById('selectedComputerDisplay');
        displayDiv.textContent = 'Lab ' + lab + ' - Computer ' + computer;
        displayDiv.style.display = 'block';
        
        closeLabModal();
    }

    function selectLabFromModal(lab) {
        document.getElementById('selectedLab').value = lab;
        showLabDetail(lab);
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('labModal');
        if (event.target === modal) {
            closeLabModal();
        }
    }

    // Auto-refresh lab status every 30 seconds
    setInterval(() => {
        location.reload();
    }, 30000);
    </script>

    <!-- RESERVATION FORM -->
    <div class="reservation-card">
        <h3>New Reservation</h3>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>ID Number</label>
                    <input type="text" value="<?php echo htmlspecialchars($userIdNumber); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($studentName); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Purpose *</label>
                    <select name="purpose" required>
                        <option value="">Select Purpose</option>
                        <option value="C">C</option>
                        <option value="C#">C#</option>
                        <option value="Java">Java</option>
                        <option value="PHP">PHP</option>
                        <option value="ASP.Net">ASP.Net</option>
                    </select>
                </div>

                <input type="hidden" name="lab" id="selectedLab">
                <input type="hidden" name="computer" id="selectedComputer">

                <div class="form-group">
                    <label>Select Lab/Computer</label>
                    <button type="button" class="search-lab-btn" onclick="openLabModal()">
                        <span>🔍</span> Search Lab
                    </button>
                </div>

                <div class="form-group" id="selectedComputerDisplay" style="display: none; grid-column: span 2; padding: 15px; background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: 12px; text-align: center; color: #065f46; font-weight: 600;">
                </div>

                <div class="form-group">
                    <label>Time In *</label>
                    <input type="time" name="time_in" required>
                </div>

                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label>Remaining Sessions</label>
                    <input type="text" value="<?php echo htmlspecialchars($sessionsRemaining); ?> / 30" readonly>
                </div>

                <div class="form-group button-group" style="grid-column: span 2; display: flex; flex-direction: row; justify-content: center; align-items: center; gap: 15px;">
                    <a href="dashboard.php" class="back-btn">&#8592; Back</a>
                    <button type="submit" name="submit_reservation" class="btn-submit" <?php echo ($sessionsRemaining <= 0) ? 'disabled' : ''; ?>>
                        Submit Reservation
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- RESERVATION HISTORY -->
    <h2>My Reservations</h2>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ID Number</th>
                    <th>Purpose</th>
                    <th>Lab</th>
                    <th>Date</th>
                    <th>Time In</th>
                    <th>Sessions</th>
                    <th>Status</th>
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reservations && $reservations->num_rows > 0): ?>
                    <?php while ($row = $reservations->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                            <td><?php echo htmlspecialchars($row['lab']); ?></td>
                            <td><?php echo date("M d, Y", strtotime($row['date'])); ?></td>
                            <td><?php echo date("h:i A", strtotime($row['time_in'])); ?></td>
                            <td><?php echo $row['remaining_sessions']; ?></td>
                            <td>
                                <?php
                                    $statusClass = 'status-pending';
                                    if ($row['status'] === 'Approved') $statusClass = 'status-approved';
                                    elseif ($row['status'] === 'Rejected') $statusClass = 'status-rejected';
                                ?>
                                <span class="<?php echo $statusClass; ?>"><?php echo $row['status']; ?></span>
                            </td>
                            <td><?php echo date("M d, Y h:i A", strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="no-records">No reservations found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>