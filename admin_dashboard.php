<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
header("Location: login.php");
    exit();
}

/* TOTAL STUDENTS */
$result = $conn->query("SELECT COUNT(*) as total FROM students WHERE role != 'admin'");
$data = $result->fetch_assoc();
$total_students = $data['total'];

/* LEADERBOARD DATA */
$leaderboardQuery = "
    SELECT 
        s.id_number,
        st.first_name,
        st.middle_name,
        st.last_name,
        st.course,
        st.year_level,
        COUNT(s.id) as total_sessions,
        SUM(TIMESTAMPDIFF(HOUR, s.time_in, COALESCE(s.time_out, NOW()))) as total_hours,
        s.lab as lab_visited
    FROM sitin_records s
    LEFT JOIN students st ON s.id_number = st.id_number
    WHERE (s.status = 'Ended' OR s.time_out IS NOT NULL)
    GROUP BY s.id_number, st.first_name, st.middle_name, st.last_name, st.course, st.year_level, s.lab
    ORDER BY total_hours DESC
";

$leaderboardResult = $conn->query($leaderboardQuery);

$userStats = [];
if ($leaderboardResult && $leaderboardResult->num_rows > 0) {
    while ($row = $leaderboardResult->fetch_assoc()) {
        $id = $row['id_number'];
        if (!isset($userStats[$id])) {
            $userStats[$id] = [
                'id_number' => $row['id_number'],
                'name' => $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'],
                'course' => $row['course'],
                'year_level' => $row['year_level'],
                'total_sessions' => 0,
                'total_hours' => 0,
                'labs' => []
            ];
        }
        $userStats[$id]['total_sessions'] += $row['total_sessions'];
        $userStats[$id]['total_hours'] += max(0, $row['total_hours']);
        
        if (!empty($row['lab_visited'])) {
            if (!isset($userStats[$id]['labs'][$row['lab_visited']])) {
                $userStats[$id]['labs'][$row['lab_visited']] = 0;
            }
            $userStats[$id]['labs'][$row['lab_visited']] += $row['total_sessions'];
        }
    }
}

foreach ($userStats as &$user) {
    $user['points'] = floor($user['total_sessions'] / 3);
    if (!empty($user['labs'])) {
        arsort($user['labs']);
        $user['most_visited_lab'] = key($user['labs']);
    } else {
        $user['most_visited_lab'] = 'N/A';
    }
}
unset($user);

usort($userStats, function($a, $b) {
    return $b['points'] - $a['points'];
});



/* OVERALL STATS */
$totalStudentsQuery = "SELECT COUNT(DISTINCT id_number) as total FROM sitin_records WHERE status = 'Ended' OR time_out IS NOT NULL";
$totalStudents = $conn->query($totalStudentsQuery)->fetch_assoc()['total'] ?? 0;

$totalHoursQuery = "SELECT SUM(TIMESTAMPDIFF(HOUR, time_in, COALESCE(time_out, NOW()))) as total FROM sitin_records WHERE status = 'Ended' OR time_out IS NOT NULL";
$totalHours = $conn->query($totalHoursQuery)->fetch_assoc()['total'] ?? 0;

$totalSessionsQuery = "SELECT COUNT(*) as total FROM sitin_records WHERE status = 'Ended' OR time_out IS NOT NULL";
$totalSessions = $conn->query($totalSessionsQuery)->fetch_assoc()['total'] ?? 0;

$currentSitinsQuery = "SELECT COUNT(*) as total FROM sitin_records WHERE status = 'Active'";
$currentSitins = $conn->query($currentSitinsQuery)->fetch_assoc()['total'] ?? 0;

$labStatsQuery = "SELECT lab, COUNT(*) as total_visits FROM sitin_records WHERE lab IS NOT NULL AND lab != '' GROUP BY lab ORDER BY total_visits DESC LIMIT 1";
$mostVisitedLab = $conn->query($labStatsQuery)->fetch_assoc();

/* LAB CHART DATA */
$labChartQuery = "SELECT lab, COUNT(*) as count FROM sitin_records WHERE lab IS NOT NULL AND lab != '' GROUP BY lab ORDER BY count DESC";
$labChartResult = $conn->query($labChartQuery);
$labChartLabels = [];
$labChartData = [];
while($labRow = $labChartResult->fetch_assoc()) {
    $labChartLabels[] = $labRow['lab'];
    $labChartData[] = $labRow['count'];
}


/* POST ANNOUNCEMENT */
if(isset($_POST['post_announcement'])){
    $message = trim($_POST['announcement']);

    if(!empty($message)){
        $stmt = $conn->prepare("INSERT INTO announcements(message) VALUES(?)");
        $stmt->bind_param("s",$message);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin_dashboard.php");
    exit();
}

/* DELETE ANNOUNCEMENT */
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);

    $stmt = $conn->prepare("DELETE FROM announcements WHERE id=?");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        $_SESSION['error'] = "Database prepare error.";
    } else {
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            error_log("Delete execute failed: " . $stmt->error);
            $_SESSION['error'] = "Delete failed: " . $stmt->error;
        } else {
            $affected = $stmt->affected_rows;
            if ($affected > 0) {
                $_SESSION['success'] = "Announcement deleted successfully ($affected row).";
            } else {
                $_SESSION['error'] = "No announcement found with ID $id.";
            }
        }
        $stmt->close();
    }

    header("Location: admin_dashboard.php?deleted=1");
    exit();
}

/* GET ANNOUNCEMENTS */
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");

/* SEARCH STUDENT */
$searchResults = null;

if(isset($_POST['search'])){
    $keyword = trim($_POST['keyword']);

    $stmt = $conn->prepare("
        SELECT * FROM students 
        WHERE role != 'admin' AND id_number = ?
    ");

    $stmt->bind_param("s", $keyword);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $searchResultsArray = [];
    while ($row = $result->fetch_assoc()) {
        $idNum = $row['id_number'];
        $activeCheck = $conn->prepare("SELECT id, lab, computer_number, time_in FROM sitin_records WHERE id_number = ? AND status = 'Active' AND time_out IS NULL");
        $activeCheck->bind_param("s", $idNum);
        $activeCheck->execute();
        $activeResult = $activeCheck->get_result();
        $row['active_session'] = $activeResult->fetch_assoc();
        $activeCheck->close();
        $searchResultsArray[] = $row;
    }
    $stmt->close();
    $searchResults = $searchResultsArray;
}

/* CHART DATA (LANGUAGE USAGE) */
$chart_labels = [];
$chart_data = [];

$chart_query = $conn->query("SELECT purpose, COUNT(*) as total FROM sitin_records GROUP BY purpose");

while($row = $chart_query->fetch_assoc()){
    $chart_labels[] = $row['purpose'];
    $chart_data[] = $row['total'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>

<link rel="stylesheet" href="styles.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ========================= */
/* RESET & BASE              */
/* ========================= */
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

/* ========================= */
/* LOADING TRANSITION ======= */
/* ========================= */
#loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 1;
    transition: opacity 0.5s ease;
}

#loading-overlay.hide {
    opacity: 0;
    pointer-events: none;
}

/* Spinner styles */
.spinner {
    border: 5px solid rgba(255, 255, 255, 0.3);
    border-top: 5px solid #007bff;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ========================= */
/* NAVBAR                    */
/* ========================= */
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
    font-size: 20px;
    font-weight: 700;
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
    text-decoration: none;
    color: rgba(255,255,255,0.8);
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

/* ========================= */
/* DASHBOARD LAYOUT          */
/* ========================= */
.dashboard-container {
    max-width: 1400px;
    margin: 40px auto;
    display: flex;
    flex-direction: column;
    gap: 25px;
    padding: 0 20px;
}

/* ========================= */
/* CARDS                     */
/* ========================= */
.dashboard-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    transition: 0.3s;
    width: 100%;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 50px rgba(0,0,0,0.15);
}

.dashboard-title {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: bold;
    font-size: 18px;
    text-align: center;
}

/* ========================= */
/* STATS CARDS               */
/* ========================= */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    color: white;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
}

.stat-card:nth-child(2) {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card:nth-child(3) {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card .stat-number {
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 5px;
}

.stat-card .stat-label {
    font-size: 13px;
    font-weight: 500;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 1px;
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
.modal-header-clean {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header-clean h2 {
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
.search-bar {
    display: flex;
    gap: 10px;
    margin: 15px 0;
}

.search-bar input {
    flex: 1;
    padding: 12px 15px;
    border-radius: 12px;
    border: 1px solid #ddd;
    outline: none;
}

.search-bar input:focus {
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

/* ========================= */
/* SIT-IN FORM CARD          */
/* ========================= */
#sitInModal .modal-content {
    max-width: 600px;
}

#sitInModal form {
    margin-top: 15px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

#sitInModal label {
    font-size: 13px;
    font-weight: 500;
}

#sitInModal input,
#sitInModal select {
    padding: 10px;
    border-radius: 10px;
    border: 1px solid #ddd;
    width: 100%;
}

#sitInModal input:focus,
#sitInModal select:focus {
    border-color: #7b6cf6;
    outline: none;
}

/* FULL WIDTH ELEMENTS */
#sitInModal input[name="id_number"],
#sitInModal input[name="student_name"] {
    grid-column: span 1;
}

#sitInModal button {
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

#sitInModal button:hover {
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

/* ========================= */
/* ANNOUNCEMENT              */
/* ========================= */
.announcement-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.announcement-form textarea {
    height: 100px;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    resize: none;
    transition: 0.2s;
}

.announcement-form textarea:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 5px rgba(13,110,253,0.3);
    outline: none;
}

.announcement-btn {
    align-self: flex-end;
    padding: 10px 18px;
    background: linear-gradient(135deg, #0d6efd, #0a58ca);
    color: white;
    border: none;
    border-radius: 6px;
    transition: 0.2s;
    margin-bottom: 20px;
}

.announcement-btn:hover {
    transform: translateY(-2px);
}

.announcement-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
    border-left: 5px solid #0d6efd;
    transition: 0.2s;
}

.announcement-item:hover {
    transform: scale(1.01);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.announcement-item p:first-child {
    font-size: 13px;
    color: #555;
    margin-bottom: 5px;
}

.announcement-item p:nth-child(2) {
    font-size: 15px;
    margin-bottom: 10px;
}

.delete-btn {
    display: inline-block;
    padding: 6px 12px;
    background: #dc3545;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 5px;
    font-size: 12px;
    transition: 0.2s;
}

.delete-btn:hover {
    background: #bb2d3b;
}

/* ========================= */
/* ANIMATION                 */
/* ========================= */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* ========================= */
/* SCROLLBAR                 */
/* ========================= */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-thumb {
    background: #bbb;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #888;
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
        <li><a class="active" href="admin_dashboard.php">Home</a></li>
                <li><a href="#" onclick="openSearch()">Search</a></li>
                <li><a href="students.php">Students</a></li>
                <li><a href="sit_in.php">Sit-in</a></li>
                <li><a href="view_sitin_records.php">View Sit-in Records</a></li>
        <li><a href="feedback_reports.php">Feedback Reports</a></li>
        <li><a href="admin_reservations.php">Reservation</a></li>
        <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
</div>

<!-- SEARCH MODAL -->
<div id="searchModal" class="modal">
  <div class="modal-content search-style">

    <!-- HEADER -->
    <div class="modal-header-clean">
      <h2>Search Student</h2>
      <span class="close" onclick="closeSearch()">×</span>
    </div>

    <hr>

    <!-- SEARCH BAR -->
    <form method="POST" class="search-bar">
      <input type="text" name="keyword" placeholder="Enter ID Number..."
        value="<?php echo isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : ''; ?>"
        required>

      <button type="submit" name="search" class="btn-search">Search</button>
    </form>

    <hr>

    <!-- RESULTS -->
    <?php if ($searchResults !== null): ?>
      <h3>Search Results:</h3>

      <?php if (count($searchResults) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>ID Number</th>
              <th>First Name</th>
              <th>Middle Name</th>
              <th>Last Name</th>
              <th>Course</th>
              <th>Remaining Session</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($searchResults as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['id_number']); ?></td>
                <td><?= htmlspecialchars($row['first_name']); ?></td>
                <td><?= htmlspecialchars($row['middle_name']); ?></td>
                <td><?= htmlspecialchars($row['last_name']); ?></td>
                <td><?= htmlspecialchars($row['course']); ?></td>
                <td><?= htmlspecialchars($row['sessions_remaining'] ?? 30); ?></td>
                <td>
                  <?php if (!empty($row['active_session'])): ?>
                    <span style="background: #dc3545; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                      Active (<?= htmlspecialchars($row['active_session']['lab']); ?>-<?= htmlspecialchars($row['active_session']['computer_number']); ?>)
                    </span>
                  <?php else: ?>
                    <span style="background: #28a745; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                      Available
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($row['active_session'])): ?>
                    <button type="button" class="btn-search" style="background: #dc3545; cursor: not-allowed;" disabled>
                      Active
                    </button>
                  <?php else: ?>
                    <button type="button" class="btn-search"
                      onclick="selectStudent(
                        '<?= $row['id_number']; ?>',
                        '<?= $row['first_name'].' '.$row['middle_name'].' '.$row['last_name']; ?>',
                        '<?= $row['sessions_remaining'] ?? 30; ?>'
                      )">
                      Sit In
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
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
  <div class="modal-content search-style">

    <!-- HEADER -->
    <div class="modal-header-clean">
      <h2>Sit-In Form</h2>
      <span class="close" onclick="closeSitIn()">×</span>
    </div>

    <hr>

    <!-- FORM -->
    <form method="POST" action="sit_in.php" class="search-bar" style="flex-direction: column;">

      <label>ID Number</label>
      <input type="text" name="id_number" required placeholder="Enter student ID"  readonly>

      <label>Student Name</label>
      <input type="text" name="student_name" readonly>

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
      <select name="lab" id="form_lab" required onchange="updateComputerOptions()">
        <option value="">Select Lab</option>
        <option value="524">Lab 524</option>
        <option value="526">Lab 526</option>
        <option value="528">Lab 528</option>
        <option value="530">Lab 530</option>
        <option value="542">Lab 542</option>
        <option value="544">Lab 544</option>
      </select>

      <label>Computer</label>
      <select name="computer" id="form_computer" required>
        <option value="">Select Computer</option>
      </select>

      <label>Remaining Session</label>
      <input type="text" name="remaining_session" readonly>

      <button type="submit" name="sit_in_submit" class="btn-search" style="margin-top:15px;">
        Sit In
      </button>

    </form>

  </div>
</div>

<!-- DASHBOARD -->
<div class="dashboard-container">

<div class="dashboard-card">


<?php
$current = $conn->query("SELECT COUNT(*) as total FROM sitin_records WHERE status='Active'");
$total   = $conn->query("SELECT COUNT(*) as total FROM sitin_records");

$current_count = $current->fetch_assoc()['total'];
$total_count   = $total->fetch_assoc()['total'];
?>

<div class="dashboard-title">Statistics</div>

<div class="stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
    <div class="stat-card">
        <div class="stat-number"><?php echo $total_students; ?></div>
        <div class="stat-label">Students Registered</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
        <div class="stat-number"><?php echo $currentSitins; ?></div>
        <div class="stat-label">Currently Sit-in</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
        <div class="stat-number"><?php echo $totalSessions; ?></div>
        <div class="stat-label">Sessions Completed</div>
    </div>
    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
        <div class="stat-number"><?php echo $totalSessions; ?></div>
        <div class="stat-label">Total Sit In</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 25px;">
    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
        <div class="dashboard-title" style="margin-bottom: 15px; font-size: 14px;">Programming Language Usage</div>
        <canvas id="chart"></canvas>
    </div>
    <div style="background: linear-gradient(135deg, #11998e, #38ef7d); border-radius: 20px; padding: 25px; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
        <div style="color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 500; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;">
            <i class="fas fa-desktop"></i> Most Visited Lab
        </div>
        <div style="color: white; font-size: 36px; font-weight: 700;"><?php echo htmlspecialchars($mostVisitedLab['lab'] ?? 'N/A'); ?></div>
        <div style="color: rgba(255,255,255,0.8); font-size: 14px; margin-top: 8px;"><?php echo $mostVisitedLab['total_visits'] ?? 0; ?> total visits</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px; margin-top: 25px;">
    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
        <div class="dashboard-title" style="margin-bottom: 20px;"><i class="fas fa-trophy"></i> Leaderboard - All Students</div>

<form method="GET" style="display:flex; gap:10px; margin-bottom:15px;">
    <input type="text" name="search_user" placeholder="Search student by name or ID..." value="<?php echo isset($_GET['search_user']) ? htmlspecialchars($_GET['search_user']) : ''; ?>" style="flex:1; padding:12px 15px; border:2px solid #e0e0e0; border-radius:10px; font-size:14px; outline:none; transition:0.3s;" onfocus="this.borderColor='#667eea';">
    <button type="submit" style="padding:12px 20px; background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border:none; border-radius:10px; font-weight:600; cursor:pointer; transition:0.3s;"><i class="fas fa-search"></i> Search</button>
    <?php if(isset($_GET['search_user'])): ?>
    <a href="admin_dashboard.php" style="padding:12px 20px; background:#6c757d; color:#fff; border:none; border-radius:10px; font-weight:600; text-decoration:none; text-align:center;"><i class="fas fa-times"></i> Clear</a>
    <?php endif; ?>
</form>

<?php
$filteredStats = $userStats;
if(isset($_GET['search_user']) && trim($_GET['search_user']) !== '') {
    $search = strtolower(trim($_GET['search_user']));
    $filteredStats = array_filter($userStats, function($user) use ($search) {
        return strpos(strtolower($user['name']), $search) !== false || strpos(strtolower($user['id_number']), $search) !== false;
    });
    $filteredStats = array_values($filteredStats);
}
?>

<div style="max-height: 500px; overflow-y: auto;">
    <table style="width: 100%; border-collapse: collapse; margin-top: 0; border-radius: 15px; overflow: hidden;">
        <thead style="background: linear-gradient(135deg, #667eea, #764ba2); position: sticky; top: 0; z-index: 10;">
            <tr>
                <th style="color: white; padding: 14px; font-size: 11px; text-transform: uppercase; width: 50px;">#</th>
                <th style="color: white; padding: 14px; font-size: 11px; text-transform: uppercase;">Student</th>
                <th style="color: white; padding: 14px; font-size: 11px; text-transform: uppercase;">Course</th>
                <th style="color: white; padding: 14px; font-size: 11px; text-transform: uppercase;">Points (3 Sessions=1pt)</th>
                <th style="color: white; padding: 14px; font-size: 11px; text-transform: uppercase;">Hours</th>
                <th style="color: white; padding: 14px; font-size: 11px; text-transform: uppercase;">Tasks Done</th>
                <th style="color: white; padding: 14px; font-size: 11px; text-transform: uppercase;">Favorite Lab</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($filteredStats) > 0): ?>
                <?php foreach ($filteredStats as $index => $user): ?>
                <tr style="background: <?php echo $index % 2 === 0 ? '#f9f9fb' : '#fff'; ?>; transition: 0.3s;">
                    <td style="padding: 12px; text-align: center;">
                        <?php 
                        if ($index === 0) {
                            echo '<span style="display:inline-block; width:28px; height:28px; line-height:28px; border-radius:50%; background:linear-gradient(135deg,#ffd700,#ffb700); color:#333; font-weight:bold; font-size:12px;">'.($index+1).'</span>';
                        } elseif ($index === 1) {
                            echo '<span style="display:inline-block; width:28px; height:28px; line-height:28px; border-radius:50%; background:linear-gradient(135deg,#c0c0c0,#a8a8a8); color:#333; font-weight:bold; font-size:12px;">'.($index+1).'</span>';
                        } elseif ($index === 2) {
                            echo '<span style="display:inline-block; width:28px; height:28px; line-height:28px; border-radius:50%; background:linear-gradient(135deg,#cd7f32,#b87333); color:#fff; font-weight:bold; font-size:12px;">'.($index+1).'</span>';
                        } else {
                            echo '<span style="display:inline-block; width:28px; height:28px; line-height:28px; border-radius:50%; background:#667eea; color:#fff; font-weight:600; font-size:12px;">'.($index+1).'</span>';
                        }
                        ?>
                    </td>
                    <td style="padding: 12px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; font-size:12px;"><?php echo strtoupper(substr($user['name'],0,1)); ?></div>
                            <div style="font-weight:600; color:#333; font-size:13px;"><?php echo htmlspecialchars($user['name']); ?></div>
                        </div>
                    </td>
                    <td style="padding: 12px; text-align: center; color:#666; font-size:13px;"><?php echo htmlspecialchars($user['course'] ?? 'N/A'); ?></td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; padding:4px 12px; border-radius:15px; font-weight:700; font-size:13px;"><?php echo $user['points']; ?></span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="font-weight:600; color:#333; font-size:14px;"><?php echo number_format($user['total_hours']); ?></span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="font-weight:600; color:#333; font-size:14px;"><?php echo $user['total_sessions']; ?></span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="background:linear-gradient(135deg,#11998e,#38ef7d); color:#fff; padding:4px 10px; border-radius:12px; font-size:12px; font-weight:500;"><?php echo htmlspecialchars($user['most_visited_lab']); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="padding: 40px; text-align: center; color: #888;">
                        <i class="fas fa-chart-line" style="font-size: 28px; margin-bottom: 10px; display: block; color: #ccc;"></i>
                        No data available yet
                    </td>
                </tr>
            <?php endif; ?>
</tbody>
    </table>
</div>

    <div style="background: white; border-radius: 20px; padding: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
        <div class="dashboard-title" style="margin-bottom: 20px;"><i class="fas fa-desktop"></i> Lab Statistics</div>
        <canvas id="labChart" style="max-height: 350px;"></canvas>
    </div>
</div>

<div class="dashboard-card">

<?php
// Flash messages
if (isset($_SESSION['success'])) {
    echo '<div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #28a745;"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo '<div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #dc3545;"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
if (isset($_GET['deleted'])) {
    echo '<div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #28a745;"><i class="fas fa-check-circle"></i> Announcement deleted successfully.</div>';
}
?>
<div class="dashboard-title">Announcement</div>

<form method="POST" class="announcement-form">

<textarea name="announcement" placeholder="Write announcement here..." required></textarea>

<button name="post_announcement" class="announcement-btn">Post Announcement</button>

</form>

<hr>

<h4>Posted Announcement</h4>

<?php while($row = $announcements->fetch_assoc()){ ?>

<div class="announcement-item">

<p><b>CCS Admin | <?php echo date("Y-M-d", strtotime($row['created_at'])); ?></b></p>

<p><?php echo htmlspecialchars($row['message']); ?></p>

<a href="admin_dashboard.php?delete=<?php echo $row['id']; ?>" 
   class="delete-btn" 
   onclick="return confirm('Delete this announcement?');">
Delete
</a>

</div>

<hr>

<?php } ?>

</div>

</div>

<script>
/* OPEN / CLOSE SEARCH */
function openSearch(){
    document.getElementById("searchModal").classList.add("show");
}

function closeSearch(){
    document.getElementById("searchModal").classList.remove("show");
}

/* OPEN / CLOSE SIT-IN */
function openSitIn(){
    document.getElementById("sitInModal").classList.add("show");
}

function closeSitIn(){
    document.getElementById("sitInModal").classList.remove("show");
}

/* SELECT STUDENT */
function selectStudent(id, name, session){
    closeSearch();
    openSitIn();

    document.querySelector('input[name="id_number"]').value = id;
    document.querySelector('input[name="student_name"]').value = name;
    document.querySelector('input[name="remaining_session"]').value = session;
}

/* CLOSE MODAL ON OUTSIDE CLICK */
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

/* AUTO OPEN SEARCH AFTER SUBMIT */
<?php if ($searchResults !== null): ?>
openSearch();
<?php endif; ?>

/* DELETE ANNOUNCEMENT */
function deleteAnnouncement(btn, id){
    if(confirm("Delete this announcement?")){
        var url = "admin_dashboard.php?delete=" + encodeURIComponent(id);
        window.location.href = url;
    }
}

/* CHARTS */
const ctx = document.getElementById('chart');
const labCtx = document.getElementById('labChart');

const labels = <?php echo json_encode($chart_labels); ?>;
const data = <?php echo json_encode($chart_data); ?>;
const labLabels = <?php echo json_encode($labChartLabels); ?>;
const labData = <?php echo json_encode($labChartData); ?>;

new Chart(ctx,{
    type:'pie',
    data:{
        labels: labels,
        datasets:[{
            data: data,
            backgroundColor:['#ff6384','#36a2eb','#ffce56','#4bc0c0','#9966ff']
        }]
    }
});

new Chart(labCtx,{
    type:'bar',
    data:{
        labels: labLabels,
        datasets:[{
            label: 'Visits',
            data: labData,
            backgroundColor:['#667eea','#764ba2','#11998e','#38ef7d','#f45c43']
        }]
    },
    options:{responsive:true,plugins:{legend:{display:false}}}
});

// Lab status data
const labStatusData = <?php
$labs = ['524', '526', '528', '530', '542', '544'];
$labStatusSimple = [];
foreach ($labs as $lab) {
    $q = $conn->prepare("SELECT computer_number FROM sitin_records WHERE lab = ? AND status = 'Active'");
    $q->bind_param("s", $lab);
    $q->execute();
    $r = $q->get_result();
    $occupied = [];
    while ($row = $r->fetch_assoc()) {
        if (!empty($row['computer_number'])) $occupied[] = $row['computer_number'];
    }
    $q->close();
    
    $cntQ = $conn->prepare("SELECT COUNT(*) as cnt FROM sitin_records WHERE lab = ? AND status = 'Active'");
    $cntQ->bind_param("s", $lab);
    $cntQ->execute();
    $cntResult = $cntQ->get_result()->fetch_assoc();
    $totalOccupied = $cntResult['cnt'] ?? 0;
    $cntQ->close();
    
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
    
    const labData = labStatusData.find(l => l.lab === lab);
    const occupiedComputers = labData ? labData.occupied_computers : [];
    const occupiedCount = occupiedComputers.length;
    const vacantCount = 20 - occupiedCount;
    
    let html = '<option value="">Select Computer (Vacant: ' + vacantCount + '/20)</option>';
    
    for (const pc of occupiedComputers) {
        html += '<option value="' + pc + '" disabled style="background: #ffcccc;">' + pc + ' (Occupied)</option>';
    }
    
    for (let i = 1; i <= 20; i++) {
        const pcNum = String(i).padStart(2, '0');
        if (!occupiedComputers.includes(pcNum)) {
            html += '<option value="' + pcNum + '">' + pcNum + '</option>';
        }
    }
    
    computerSelect.innerHTML = html;
}
</script>
</body>
</html>
