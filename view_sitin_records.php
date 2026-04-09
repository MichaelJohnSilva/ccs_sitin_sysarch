   <?php
session_start();
include "config.php";

/* TIMEOUT HANDLER */
if(isset($_POST['timeout'])){
    $id = $_POST['record_id'];

    $stmt = $conn->prepare("
        UPDATE sitin_records 
        SET time_out = NOW(),
            status = 'Ended'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    // Also update student's sessions_remaining
    $stmt2 = $conn->prepare("
        SELECT id_number FROM sitin_records WHERE id = ?
    ");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $record = $stmt2->get_result()->fetch_assoc();
    
    if($record){
        $stmt3 = $conn->prepare("
            UPDATE students SET sessions_remaining = sessions_remaining - 1 
            WHERE id_number = ? AND sessions_remaining > 0
        ");
        $stmt3->bind_param("s", $record['id_number']);
        $stmt3->execute();
    }
}

/* SIT-IN SUBMISSION HANDLER */
if(isset($_POST['sit_in_submit'])){
    $id_number = trim($_POST['id_number']);
    $purpose = trim($_POST['purpose']);
    $lab = trim($_POST['lab']);
    
    // Check if student exists and has sessions
    $checkStmt = $conn->prepare("SELECT sessions_remaining FROM students WHERE id_number = ?");
    $checkStmt->bind_param("s", $id_number);
    $checkStmt->execute();
    $student = $checkStmt->get_result()->fetch_assoc();
    
    if($student && $student['sessions_remaining'] > 0){
        // Insert sit-in record
        $insertStmt = $conn->prepare("
            INSERT INTO sitin_records (id_number, purpose, lab, status, time_in) 
            VALUES (?, ?, ?, 'Active', NOW())
        ");
        $insertStmt->bind_param("sss", $id_number, $purpose, $lab);
        $insertStmt->execute();
        
        // Optionally decrement sessions
        // $updateStmt = $conn->prepare("UPDATE students SET sessions_remaining = sessions_remaining - 1 WHERE id_number = ?");
        // $updateStmt->bind_param("s", $id_number);
        // $updateStmt->execute();
    }
}


/* FETCH PROGRAMMING LANGUAGE DATA FOR PIE CHART */
$languageResult = $conn->query("
    SELECT purpose, COUNT(*) as count
    FROM sitin_records
    WHERE purpose IS NOT NULL AND purpose != ''
    GROUP BY purpose
    ORDER BY count DESC
");

$languageData = [];
while($row = $languageResult->fetch_assoc()){
    $languageData[] = $row;
}

/* FETCH LAB ROOM DATA FOR PIE CHART */
$labResult = $conn->query("
    SELECT lab, COUNT(*) as count
    FROM sitin_records
    WHERE lab IS NOT NULL AND lab != ''
    GROUP BY lab
    ORDER BY count DESC
");

$labData = [];
while($row = $labResult->fetch_assoc()){
    $labData[] = $row;
}

    /* SEARCH HANDLER */
    $searchResults = null;

    if(isset($_POST['search_modal'])){
        $keyword = trim($_POST['keyword']);
        
        // Validate search input - limit length and allow only safe characters
        if (strlen($keyword) > 100) {
            $keyword = substr($keyword, 0, 100);
        }
        
        // Allow alphanumeric, spaces, and basic punctuation
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

/* AJAX HANDLER */
if(isset($_GET['ajax'])){
    // Re-query for AJAX to avoid exhausted result set
    $ajaxResult = $conn->query(" 
        SELECT s.id, s.id_number, st.first_name, st.middle_name, st.last_name, 
               s.purpose, s.lab, st.sessions_remaining, 
               s.status, s.time_in, s.time_out
        FROM sitin_records s
        LEFT JOIN students st ON s.id_number = st.id_number
        ORDER BY s.time_in DESC
    ");
    
    while($row = $ajaxResult->fetch_assoc()){
?>
<tr>
    <td><?php echo $row['id']; ?></td>
    <td><?php echo $row['id_number']; ?></td>
    <td><?php echo $row['first_name'].' '.$row['middle_name'].' '.$row['last_name']; ?></td>
    <td><?php echo $row['purpose']; ?></td>
    <td><?php echo $row['lab']; ?></td>

    <td><?php echo date("h:i A", strtotime($row['time_in'])); ?></td>

    <td>
        <?php echo $row['time_out'] 
            ? date("h:i A", strtotime($row['time_out'])) 
            : '-'; ?>
    </td>

    <td><?php echo date("M d, Y", strtotime($row['time_in'])); ?></td>
</tr>
<?php
    }
    exit();
}


$isSearch = false;

if(isset($_GET['q']) && trim($_GET['q']) != ''){
    $isSearch = true;

    $keyword = "%" . trim($_GET['q']) . "%";

    $stmt = $conn->prepare("
        SELECT s.id, s.id_number, 
            st.first_name, st.middle_name, st.last_name,
            s.purpose, s.lab, s.time_in, s.time_out
        FROM sitin_records s
        LEFT JOIN students st ON s.id_number = st.id_number
        WHERE s.id_number LIKE ?
        ORDER BY s.time_in DESC
    ");
    $stmt->bind_param("s", $keyword);
    $stmt->execute();
    $tableResult = $stmt->get_result();

} else {
    $tableResult = $conn->query("
        SELECT s.id, s.id_number, 
            st.first_name, st.middle_name, st.last_name,
            s.purpose, s.lab, s.time_in, s.time_out
        FROM sitin_records s
        LEFT JOIN students st ON s.id_number = st.id_number
        ORDER BY s.time_in DESC
    ");
}

?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>View Sit-in Records</title>
        <link rel="stylesheet" href="styles.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* RESET */
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

/* NAVBAR */
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

/* CONTAINER */
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

/* TABLE */
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
    padding: 18px 15px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
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

/* STATUS */
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

.modal.show { display: flex; }

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

/* SIT IN BUTTON */
.btn-sitin {
    padding: 15px 40px;
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    border: none;
    border-radius: 30px;
    cursor: pointer;
    font-weight: 700;
    font-size: 16px;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px rgba(56, 239, 125, 0.4);
}

.btn-sitin:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(56, 239, 125, 0.5);
}

/* RESPONSIVE */
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
</style>
    </head>

    <body>

    <!-- NAVBAR -->
    <div class="topnav">
        <div id="title">
            <img src="uclogo.png" id="uc" style="height:45px;">
            <span>College of Computer Studies Sit-in Monitoring System</span>
        </div>
        <div class="topnavInside">
            <ul>
                <li><a href="admin_dashboard.php">Home</a></li>
                <li><a href="#" onclick="openSearch()">Search Student</a></li>
                <li><a href="students.php">Students</a></li>
                <li><a href="sit_in.php">Sit-in</a></li>
                <li><a class="active" href="view_sitin_records.php">View Sit-in Records</a></li>
                <li><a href="#">Sit-in Reports</a></li>
                <li><a href="feedback_reports.php">Feedback Reports</a></li>
                <li><a href="admin_reservations.php">Reservation</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>

    <!-- CHARTS SECTION -->
    <div class="container">
        <h2>Current Sit-in Records</h2>
        
        <!-- ROW 1: Two Pie Charts -->
        <div style="display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; margin-bottom: 30px;">
            <!-- Programming Language Pie Chart -->
            <div style="background: white; border-radius: 20px; padding: 25px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); width: 450px;">
                <h3 style="text-align: center; color: #333; margin-bottom: 20px; font-size: 18px;">Programming Languages Used</h3>
                <canvas id="languageChart"></canvas>
            </div>
            <!-- Lab Room Pie Chart -->
            <div style="background: white; border-radius: 20px; padding: 25px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); width: 450px;">
                <h3 style="text-align: center; color: #333; margin-bottom: 20px; font-size: 18px;">Lab Rooms Used</h3>
                <canvas id="labChart"></canvas>
            </div>
        </div>
        
        <!-- ROW 2: Search Bar (Right Side) -->
        <div style="display: flex; justify-content: flex-end; margin-bottom: 30px;">
            <form method="GET" style="display: flex; gap: 10px; align-items: center; width: 450px;">
                <input type="text" name="q" placeholder="Search by ID Number..." 
                    value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>"
                    style="flex: 1; padding: 14px 18px; border-radius: 30px; border: none; outline: none; font-size: 14px; background: white; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <button type="submit" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 14px 25px; border-radius: 30px; cursor: pointer; font-weight: 600; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); transition: 0.3s;">Search</button>
                <?php if(isset($_GET['q']) && $_GET['q'] != ''): ?>
                    <a href="view_sitin_records.php" style="background: #6c757d; color: white; text-decoration: none; padding: 14px 20px; border-radius: 30px; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">Clear</a>
                <?php endif; ?>
            </form>
        </div>


    </div>

    <!-- SIT-IN RECORDS TABLE -->
    <div class="container">
        <table style="width: 100%; border-collapse: separate; border-spacing: 0; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.15);">
            <thead>
            <tr>
                <th>Sit-in #</th>
                <th>ID</th>
                <th>Name</th>
                <th>Purpose</th>
                <th>Lab</th>
                <th>Login</th>
                <th>Logout</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody id="sitInTableBody">
            <?php
            if ($tableResult && $tableResult->num_rows > 0) {
                while($row = $tableResult->fetch_assoc()) {
            ?>
            <tr>
                <td><?php echo $row['id'] ?? '-'; ?></td>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td>
                    <?php echo htmlspecialchars(
                        ($row['first_name'] ?? '') . ' ' . 
                        ($row['middle_name'] ?? '') . ' ' . 
                        ($row['last_name'] ?? '')
                    ); ?>
                </td>
                <td><?php echo $row['purpose'] ?? '-'; ?></td>
                <td><?php echo $row['lab'] ?? '-'; ?></td>
                <td>
                    <?php echo isset($row['time_in']) 
                        ? date("h:i A", strtotime($row['time_in'])) 
                        : '-'; ?>
                </td>
                <td>
                    <?php echo (!empty($row['time_out']))
                        ? date("h:i A", strtotime($row['time_out'])) 
                        : '-'; ?>
                </td>
                <td>
                    <?php echo isset($row['time_in']) 
                        ? date("M d, Y", strtotime($row['time_in'])) 
                        : '-'; ?>
                </td>
            </tr>
            <?php
                }
            } else {
            ?>
            <tr>
                <td colspan="8" class="no-records">
                    <?php echo $isSearch ? "No records found." : "No data available."; ?>
                </td>
            </tr>
            <?php
            }
            ?>
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
                value="<?php echo isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : ''; ?>">
            <button type="submit" name="search_modal" class="btn-search">Search</button>
        </form>

        <!-- SEARCH RESULTS -->
        <?php if (isset($searchResults)): ?>

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
                                        onclick="openSitInDirect('<?php echo htmlspecialchars($row['id_number']); ?>', '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']); ?>', '<?php echo htmlspecialchars($row['sessions_remaining'] ?? 30); ?>')">
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
            <h3 stlye="color: black">Sit-In Form</h3>
            <button class="close" onclick="closeSitInForm()">&times;</button>
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

            <label>Lab</label>
            <input type="text" name="lab" placeholder="Enter Lab" required>

            <label>Sessions Left</label>
            <input type="text" id="form_sessions" readonly>

            <button type="submit" name="sit_in_submit" class="btn-submit">✓ Sit In</button>
        </form>

    </div>
</div>
    </body>
<script>
    function openSearch() {
        document.getElementById("searchModal").classList.add("show");
    }

    function closeSearch() {
        document.getElementById("searchModal").classList.remove("show");
    }
    function openSitInForm() {
        document.getElementById("sitInModal").classList.add("show");
    }

    function closeSitInForm() {
        document.getElementById("sitInModal").classList.remove("show");
    }

    function openSitInDirect(id, name, session) {
        // Close search modal first
        closeSearch();
        // Set values in the form
        document.getElementById("form_id").value = id;
        document.getElementById("form_id_display").value = id;
        document.getElementById("form_name").value = name;
        document.getElementById("form_sessions").value = session;
        // Open the sit-in modal
        document.getElementById("sitInModal").classList.add("show");
    }

    function selectStudent(id, name, session){
        // Show the sit-in section below the table
        const sitInSection = document.getElementById('sitInSection');
        const studentInfo = document.getElementById('selectedStudentInfo');
        
        studentInfo.innerHTML = '<strong>Selected:</strong> ' + name + ' (ID: ' + id + ') - Sessions: ' + session;
        sitInSection.style.display = 'block';
        
        // Store the values for the sit-in form
        document.getElementById("form_id").value = id;
        document.getElementById("form_id_display").value = id;
        document.getElementById("form_name").value = name;
        document.getElementById("form_sessions").value = session;
    }

    window.onclick = function(event) {
        const searchModal = document.getElementById("searchModal");
        const sitInModal = document.getElementById("sitInModal");

        if (event.target === searchModal) {
            closeSearch();
        }

        if (event.target === sitInModal) {
            closeSitInForm();
        }
    };

    // Function to reload sit-in table
function reloadSitInRecords() {
    fetch('view_sitin_records.php?ajax=1')
      .then(response => response.text())
        .then(data => {
            document.getElementById('sitInTableBody').innerHTML = data;
        });
}

// Refresh every 10 seconds
// Smart refresh (only when no modal is open)

let isSearching = <?php echo (isset($_GET['q']) && $_GET['q'] != '') ? 'true' : 'false'; ?>;

setInterval(() => {
    if (!document.querySelector('.modal.show') && !isSearching) {
        reloadSitInRecords();
    }
}, 10000);

// Optional: refresh immediately after page load
window.addEventListener('load', () => {
    if (!isSearching) {
        reloadSitInRecords();
    }
}); 

// Pie Charts Data from PHP
const languageData = <?php echo json_encode($languageData); ?>;
const labData = <?php echo json_encode($labData); ?>;

// Color palette for charts
const chartColors = [
    '#667eea', '#764ba2', '#11998e', '#38ef7d', '#eb3349',
    '#f45c43', '#007bff', '#28a745', '#ffc107', '#17a2b8',
    '#6610f2', '#e83e8c', '#20c997', '#fd7e14', '#6c757d'
];

// Programming Language Pie Chart
const languageCtx = document.getElementById('languageChart').getContext('2d');
new Chart(languageCtx, {
    type: 'pie',
    data: {
        labels: languageData.map(item => item.purpose),
        datasets: [{
            data: languageData.map(item => item.count),
            backgroundColor: chartColors.slice(0, languageData.length),
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Lab Room Pie Chart
const labCtx = document.getElementById('labChart').getContext('2d');
new Chart(labCtx, {
    type: 'pie',
    data: {
        labels: labData.map(item => item.lab),
        datasets: [{
            data: labData.map(item => item.count),
            backgroundColor: chartColors.slice(0, labData.length),
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Reopen search modal if search was submitted
<?php if(isset($_POST['search_modal'])): ?>
document.getElementById('searchModal').classList.add('show');
<?php endif; ?>
</script>

    </html> 