<?php
session_start();
include "config.php";

/* ===== LOGOUT HANDLER ===== */
if (isset($_POST['logout'])) {

    $record_id = $_POST['record_id'];

    // Update sit-in record
    $stmt = $conn->prepare("
        UPDATE sitin_records 
        SET time_out = NOW(),
            status = 'Ended'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $record_id);
    $stmt->execute();

    // Get student ID
    $stmt2 = $conn->prepare("SELECT id_number FROM sitin_records WHERE id = ?");
    $stmt2->bind_param("i", $record_id);
    $stmt2->execute();
    $record = $stmt2->get_result()->fetch_assoc();

    // Deduct session
    if ($record) {
        $stmt3 = $conn->prepare("
            UPDATE students 
            SET sessions_remaining = sessions_remaining - 1 
            WHERE id_number = ? AND sessions_remaining > 0
        ");
        $stmt3->bind_param("s", $record['id_number']);
        $stmt3->execute();
    }

    // Optional: success message
    $success = "Student logged out successfully!";
}

/* ===== SEARCH HANDLER ===== */
$searchResults = null;
if(isset($_POST['search_student'])){
    $keyword = trim($_POST['keyword']);
    if(strlen($keyword) > 0){
        $stmt = $conn->prepare("
            SELECT * FROM students
            WHERE id_number = ?
        ");
        $stmt->bind_param("s", $keyword);
        $stmt->execute();
        $searchResults = $stmt->get_result();
    }
}

/* ===== SIT-IN SUBMIT HANDLER ===== */
if(isset($_POST['sit_in_submit'])){
    $id_number = trim($_POST['id_number']);
    $purpose = trim($_POST['purpose']);
    $lab = trim($_POST['lab']);
    $computer = trim($_POST['computer']);
    
    if (!preg_match('/^[a-zA-Z0-9]+$/', $id_number)) {
        $error = "Invalid ID number.";
    } elseif (!preg_match('/^[a-zA-Z0-9 .#]+$/', $purpose)) {
        $error = "Invalid purpose.";
    } elseif (!preg_match('/^[0-9]+$/', $lab)) {
        $error = "Invalid lab.";
    } elseif (!preg_match('/^[0-9]+$/', $computer)) {
        $error = "Invalid computer.";
    }
    
    if (!isset($error)) {
        $checkStmt = $conn->prepare("SELECT sessions_remaining FROM students WHERE id_number = ?");
        $checkStmt->bind_param("s", $id_number);
        $checkStmt->execute();
        $student = $checkStmt->get_result()->fetch_assoc();
        
        if (!$student) {
            $error = "Student not found.";
        } elseif ($student['sessions_remaining'] <= 0) {
            $error = "No remaining sessions.";
        }
    }
    
    if (!isset($error)) {
        $stmt = $conn->prepare("
            INSERT INTO sitin_records (id_number, purpose, lab, computer_number, status, time_in)
            VALUES (?, ?, ?, ?, 'Active', NOW())
        ");
        $stmt->bind_param("ssss", $id_number, $purpose, $lab, $computer);
        $stmt->execute();
        
        $success = "Sit-in recorded successfully!";
    }
}

/* ===== FETCH SIT-IN RECORDS ===== */
$records = $conn->query(" 
    SELECT s.id, s.id_number, st.first_name, st.middle_name, st.last_name, 
           s.purpose, s.lab, st.sessions_remaining, 
           s.status, s.time_in, s.time_out
    FROM sitin_records s
    LEFT JOIN students st ON s.id_number = st.id_number
    ORDER BY s.time_in DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Records | CCS Monitoring</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="uclogo.png" type="image/png">
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

        /* Navbar */
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

        /* Container */
        .container {
            width: 95%;
            margin: 40px auto;
            animation: fadeInUp 0.6s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h2 {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            font-size: 32px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
            letter-spacing: 1px;
        }

        /* Messages */
        .success-msg {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
            animation: slideIn 0.4s ease;
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
            animation: slideIn 0.4s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* ========================= */
        /* TABLE CARD STYLE          */
        /* ========================= */
        table {
            width: 100%;
            margin-top: 15px;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border-radius: 15px;
        }

        table thead {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        table th {
            color: white;
            padding: 12px;
            font-size: 12px;
            text-transform: uppercase;
        }

        table td {
            background: #f9f9fb;
            padding: 12px;
            text-align: center;
            font-size: 13px;
        }

        table tr:hover td {
            background: #eef1ff;
        }

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

        .btn-logout {
            padding: 10px 20px;
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(235, 51, 73, 0.3);
        }

        .btn-logout:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(235, 51, 73, 0.4);
        }

        .no-records {
            text-align: center;
            padding: 60px;
            color: #999;
            font-size: 16px;
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

        /* ========================= */
        /* HEADER                    */
        /* ========================= */
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
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
            transition: 0.3s;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        /* ========================= */
        /* SIT-IN FORM CARD          */
        /* ========================= */
        .sit-in-form {
            margin-top: 20px;
        }

        .sit-in-form h3 {
            text-align: center;
            margin-bottom: 15px;
        }

        .sit-in-form form {
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
        }

        .sit-in-form input:focus,
        .sit-in-form select:focus {
            border-color: #7b6cf6;
            outline: none;
        }

        /* FULL WIDTH ELEMENTS */
        .sit-in-form input[readonly] {
            background: #eee;
        }

        .btn-submit {
            grid-column: span 2;
            margin-top: 10px;
            padding: 12px;
            background: #1cc88a;
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-submit:hover {
            background: #17a673;
        }
        /* ========================= */
        /* ANIMATION                 */
        /* ========================= */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        /* Responsive */
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

            table th, table td {
                font-size: 11px;
                padding: 10px 5px;
            }

            .search-form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <div class="topnav">
        <div id="title">
            <img src="uclogo.png" id="uc">
            <span>CCS Sit-in Monitoring</span>
        </div>
        <div class="topnavInside">
            <ul>
                <li><a href="admin_dashboard.php">Home</a></li>
                <li><a href="#" onclick="openSearch()">Search</a></li>
                <li><a href="students.php">Students</a></li>
                <li><a href="sit_in.php" class="active">Sit-in</a></li>
                <li><a href="view_sitin_records.php">View Records</a></li>
                <li><a href="#">Sit-in Reports</a></li>
                <li><a href="feedback_reports.php">Feedback Reports</a></li>
                <li><a href="admin_reservations.php">Reservation</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>

    <div class="container">
        <!-- Success/Error Messages -->
        <?php if(isset($success)): ?>
            <div class="success-msg">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="error-msg">✗ <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Sit-In Records Table -->
        <h2>📋 Sit-In Records</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Purpose</th>
                    <th>Lab</th>
                    <th>Sessions</th>
                    <th>Status</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($records->num_rows > 0): ?>
                    <?php while($row = $records->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                            <td><?php echo htmlspecialchars($row['lab']); ?></td>
                            <td><?php echo $row['sessions_remaining']; ?></td>
                            <td>
                                <span class="<?php echo ($row['status'] == 'Active') ? 'status-active' : 'status-ended'; ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date("M d, Y h:i A", strtotime($row['time_in'])); ?></td>
                            <td>
                                <?php echo $row['time_out'] 
                                    ? date("M d, Y h:i A", strtotime($row['time_out'])) 
                                    : '-'; ?>
                            </td>
                            <td>
                                <?php if($row['status'] == 'Active'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="record_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="logout" class="btn-logout">Logout</button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="no-records">No sit-in records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
<!-- ========================= -->
<!-- SEARCH MODAL (PLACE AT BOTTOM OF BODY) -->
<!-- ========================= -->
<div id="searchModal" class="modal">
    <div class="modal-content">

        <!-- HEADER -->
        <div class="modal-header">
            <h3 style="color: black;">Search Results:</h3>
            <button class="close" onclick="closeSearch()">&times;</button>
        </div>

        <!-- SEARCH FORM -->
        <form method="POST" class="search-form">
            <input type="text" name="keyword" placeholder="Enter ID Number..." required>
            <button type="submit" name="search_student" class="btn-search">Search</button>
        </form>

        <!-- ========================= -->
        <!-- SEARCH RESULTS -->
        <!-- ========================= -->
        <?php if($searchResults !== null): ?>
            
            <?php if($searchResults->num_rows > 0): ?>
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
                        <?php while($row = $searchResults->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars(
                                        $row['first_name'] . ' ' . 
                                        $row['middle_name'] . ' ' . 
                                        $row['last_name']
                                    ); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['course']); ?></td>
                                <td><?php echo $row['sessions_remaining']; ?></td>
                                <td>
                                    <!-- SIT IN BUTTON -->
                                    <button type="button" class="btn-search"
                                    onclick="openSitInForm(
                                        '<?php echo $row['id_number']; ?>',
                                        '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']); ?>',
                                        '<?php echo $row['sessions_remaining']; ?>'
                                    )">
                                        Sit In
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

            <?php else: ?>
                <p class="no-records">No student found.</p>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

            <!-- Sit-In Modal -->
            <div id="sitInModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 style="color: black;">Sit-In Form</h3>
                        <button class="close" onclick="closeSitIn()">&times;</button>
                    </div>

                    <form method="POST" class="sit-in-form">
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
function openSearch() {
    document.getElementById("searchModal").classList.add("show");
}

function closeSearch() {
    document.getElementById("searchModal").classList.remove("show");
}

function openSitInForm(id, name, sessions) {
    closeSearch();

    document.getElementById("form_id").value = id;
    document.getElementById("form_id_display").value = id;
    document.getElementById("form_name").value = name;
    document.getElementById("form_sessions").value = sessions;
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
    
    // In a real scenario, you'd fetch occupied computers via AJAX
    // For now, show all 20 as available
    let html = '<option value="">Select Computer</option>';
    for (let i = 1; i <= 20; i++) {
        const pcNum = String(i).padStart(2, '0');
        html += '<option value="' + pcNum + '">' + pcNum + '</option>';
    }
    computerSelect.innerHTML = html;
}

function closeSitIn() {
    document.getElementById("sitInModal").classList.remove("show");
}

// CLICK OUTSIDE TO CLOSE
window.onclick = function(event) {
    const searchModal = document.getElementById("searchModal");
    const sitInModal = document.getElementById("sitInModal");

    if (event.target === searchModal) closeSearch();
    if (event.target === sitInModal) closeSitIn();
};

// Lab status data - fetched from PHP
const labStatusData = <?php
$labs = ['517', '518', '519', '520', '521', '524', '526'];
$labStatusSimple = [];
foreach ($labs as $lab) {
    // Get computers with computer_number
    $q = $conn->prepare("SELECT computer_number FROM sitin_records WHERE lab = ? AND status = 'Active'");
    $q->bind_param("s", $lab);
    $q->execute();
    $r = $q->get_result();
    $occupied = [];
    while ($row = $r->fetch_assoc()) {
        if (!empty($row['computer_number'])) $occupied[] = $row['computer_number'];
    }
    $q->close();
    
    // Get total active sit-ins
    $cntQ = $conn->prepare("SELECT COUNT(*) as cnt FROM sitin_records WHERE lab = ? AND status = 'Active'");
    $cntQ->bind_param("s", $lab);
    $cntQ->execute();
    $cntResult = $cntQ->get_result()->fetch_assoc();
    $totalOccupied = $cntResult['cnt'] ?? 0;
    $cntQ->close();
    
    // Add fallback for old records without computer_number
    $identified = count($occupied);
    $unidentified = $totalOccupied - $identified;
    for ($i = 1; $i <= $unidentified; $i++) {
        $pcNum = str_pad($i, 2, '0', STR_PAD_LEFT);
        if (!in_array($pcNum, $occupied)) {
            $occupied[] = $pcNum;
        }
    }
    
    $labStatusSimple[] = ['lab' => $lab, 'occupied_computers' => $occupied];
}
echo json_encode($labStatusSimple);
?>;

function updateComputerOptions() {
    const lab = document.getElementById("form_lab").value;
    const computerSelect = document.getElementById("form_computer");
    
    if (!lab) {
        computerSelect.innerHTML = '<option value="">Select Computer</option>';
        return;
    }
    
    const labInfo = labStatusData.find(l => l.lab === lab);
    let html = '<option value="">Select Computer</option>';
    for (let i = 1; i <= 20; i++) {
        const pcNum = String(i).padStart(2, '0');
        if (!labInfo.occupied_computers.includes(pcNum)) {
            html += '<option value="' + pcNum + '">' + pcNum + '</option>';
        }
    }
    computerSelect.innerHTML = html;
}
</script>

<?php if(isset($_POST['search_student'])): ?>
<script>
    openSearch();
</script>
<?php endif; ?>

</body>
</html>