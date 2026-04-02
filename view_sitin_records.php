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


if(isset($_POST['search']) && !empty($_POST['keyword'])){

    $keyword = "%" . trim($_POST['keyword']) . "%";

    $stmt = $conn->prepare("
        SELECT s.id, s.id_number, 
            st.first_name, st.middle_name, st.last_name,
            s.purpose, s.lab, s.time_in, s.time_out
        FROM sitin_records s
        LEFT JOIN students st ON s.id_number = st.id_number
        WHERE 
            s.id_number LIKE ? OR
            LOWER(CONCAT(st.first_name, ' ', st.middle_name, ' ', st.last_name)) LIKE LOWER(?)
        ORDER BY s.time_in DESC
    ");

    $stmt->bind_param("ss", $keyword, $keyword);
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

/* MODAL */
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

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    width: 90%;
    max-width: 800px;
    border-radius: 25px;
    padding: 35px;
    box-shadow: 0 25px 80px rgba(0,0,0,0.3);
    animation: scaleIn 0.4s ease;
    max-height: 85vh;
    overflow-y: auto;
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

.modal-header h2 {
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

/* FORMS */
.search-form {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
}

.search-form input {
    flex: 1;
    padding: 15px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 15px;
    font-size: 15px;
    transition: all 0.3s ease;
}

.search-form input:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    outline: none;
}

.btn-search {
    padding: 15px 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 15px;
    cursor: pointer;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-search:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
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
                <li><a href="#" onclick="openSearch()">Search</a></li>
                <li><a href="students.php">Students</a></li>
                <li><a href="sit_in.php">Sit-in</a></li>
                <li><a class="active" href="view_sitin_records.php">View Sit-in Records</a></li>
                <li><a href="#">Sit-in Reports</a></li>
                <li><a href="#">Feedback Reports</a></li>
                <li><a href="#">Reservation</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>

    <!-- CHARTS SECTION -->
    <div class="container">
        <h2>Current Sit-in Records</h2>
        <div style="display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; margin-bottom: 40px;">
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
    </div>

    <div class="container" style="margin-top: -10px;">

        <form method="POST" style="display:flex; justify-content:center; gap:10px; margin-bottom:20px;">
            
            <input type="text" name="keyword" placeholder="Enter ID Number..."
                value="<?php echo isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : ''; ?>"
                style="width:300px; padding:12px; border-radius:10px; border:1px solid #ccc;">

            <button type="submit" name="search" class="btn-search">
                Search
            </button>

        </form>

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
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td>
                    <?php echo htmlspecialchars(
                        $row['first_name'].' '.$row['middle_name'].' '.$row['last_name']
                    ); ?>
                </td>
                <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                <td><?php echo htmlspecialchars($row['lab']); ?></td>
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
            } else {
            ?>
            <tr>
                <td colspan="8">No records found.</td>
            </tr>
            <?php
            }
            ?>
            </tbody>
        </table>
    </div>

    <div id="searchModal" class="modal">
  <div class="modal-content">

    <!-- HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h3 style="color: black;">Search Student</h3>
      <span class="close" onclick="closeSearch()">×</span>
    </div>

    <hr>

    <!-- SEARCH BAR -->
    <form method="POST" style="display:flex; gap:10px; margin:15px 0;">
      <div style="display:flex; gap:10px; flex:1;">
        <input type="text" name="keyword" placeholder="Search..."
          value="<?php echo isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : ''; ?>"
          style="flex:1; padding:12px; border:1px solid #ccc; border-radius:6px;">

        <button type="submit" name="search_modal" class="search-btn">Search</button>
      </div>
    </form>

    <hr>

    <!-- RESULTS -->    
    <?php if (isset($searchResults)): ?>
      <h3>Search Results:</h3>

      <?php if ($searchResults->num_rows > 0): ?>
        <table>
          <tbody>
            <?php while ($row = $searchResults->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                <td><?php echo htmlspecialchars($row['middle_name']); ?></td>
                <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                <td><?php echo htmlspecialchars($row['course']); ?></td>
                <td><?php echo htmlspecialchars($row['sessions_remaining'] ?? 30); ?></td>

                <td>
                  <button type="button" style="padding: 8px 18px; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border: none; border-radius: 20px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease; box-shadow: 0 3px 10px rgba(56, 239, 125, 0.3);" onclick="openSitInDirect('<?php echo htmlspecialchars($row['id_number']); ?>', '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']); ?>', '<?php echo htmlspecialchars($row['sessions_remaining'] ?? 30); ?>')">
                    Sit In
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>



      <?php else: ?>
        <p>No students found.</p>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>


    <!-- SIT-IN MODAL -->
<div id="sitInModal" class="modal">
  <div class="modal-content">

    <div class="modal-header">
      <h2>Sit In Form</h2>
      <span class="close" onclick="closeSitInForm()">×</span>
    </div>

    <form method="POST" action="sit_in.php" class="form-container">

      <label>ID Number</label>
      <input type="text" name="id_number" placeholder="Enter student ID" required>

      <label>Student Name</label>
      <input type="text" name="student_name">

      <label>Purpose</label>
      <select name="purpose" required>
        <option value="">Select Language</option>
        <option value="C">C</option>
        <option value="C#">C#</option>
        <option value="Java">Java</option>
        <option value="PHP">PHP</option>
        <option value="ASP.Net">ASP.Net</option>
      </select>

      <label>Lab</label>
      <input type="text" name="lab" required>

      <label>Remaining Session</label>
      <input type="text" name="remaining_session">

      <button type="submit" name="sit_in_submit" class="submit-btn">Sit In</button>

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
        // Set values in the form
        document.querySelector('#sitInModal input[name="id_number"]').value = id;
        document.querySelector('#sitInModal input[name="student_name"]').value = name;
        document.querySelector('#sitInModal input[name="remaining_session"]').value = session;
        // Open the modal
        document.getElementById("sitInModal").classList.add("show");
    }

    function selectStudent(id, name, session){
        // Show the sit-in section below the table
        const sitInSection = document.getElementById('sitInSection');
        const studentInfo = document.getElementById('selectedStudentInfo');
        
        studentInfo.innerHTML = '<strong>Selected:</strong> ' + name + ' (ID: ' + id + ') - Sessions: ' + session;
        sitInSection.style.display = 'block';
        
        // Store the values for the sit-in form
        document.querySelector('#sitInModal input[name="id_number"]').value = id;
        document.querySelector('#sitInModal input[name="student_name"]').value = name;
        document.querySelector('#sitInModal input[name="remaining_session"]').value = session;
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

let isSearching = <?php echo (isset($_POST['search']) ? 'true' : 'false'); ?>;

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

</script>

    </html> 