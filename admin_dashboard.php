<?php
session_start();
include "config.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.html");
    exit();
}

/* TOTAL STUDENTS */
$result = $conn->query("SELECT COUNT(*) as total FROM students WHERE role != 'admin'");
$data = $result->fetch_assoc();
$total_students = $data['total'];


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
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_dashboard.php");
    exit();
}

/* GET ANNOUNCEMENTS */
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");

/* SEARCH STUDENT */
$searchResults = null;

if(isset($_POST['search'])){
    $keyword = trim($_POST['keyword']);
    $keyword = "%$keyword%";

    $stmt = $conn->prepare("
        SELECT * FROM students 
        WHERE role != 'admin' AND (
            id_number LIKE ? 
            OR first_name LIKE ? 
            OR middle_name LIKE ?
            OR last_name LIKE ?
            OR CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?
        )
    ");

    $stmt->bind_param("sssss", $keyword, $keyword, $keyword, $keyword, $keyword);
    $stmt->execute();
    $searchResults = $stmt->get_result();
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

<style>
/* ========================= */
/* RESET & BASE              */
/* ========================= */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background: #f4f6f9;
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
    background: linear-gradient(135deg, #4a4a4a, #2e2e2e);
    padding: 12px 25px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}

#title {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    font-size: 18px;
    font-weight: bold;
}

#uc {
    height: 45px;
}

.topnavInside ul {
    display: flex;
    list-style: none;
    gap: 15px;
}

.topnavInside ul li a {
    text-decoration: none;
    color: #fff;
    padding: 8px 12px;
    border-radius: 6px;
    transition: 0.3s;
    font-size: 14px;
    position: relative;
}

.topnavInside ul li a:hover {
    background: rgba(255,255,255,0.2);
}

.topnavInside ul li a.active {
    background: #0d6efd;
}

/* ========================= */
/* DASHBOARD LAYOUT          */
/* ========================= */
.dashboard-container {
    max-width: 1200px;
    margin: 40px auto;
    display: flex;
    gap: 20px;
}

/* ========================= */
/* CARDS                     */
/* ========================= */
.dashboard-card {
    flex: 1;
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    transition: 0.3s;
    border: 1px solid #eee;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.12);
}

.dashboard-title {
    background: linear-gradient(135deg, #6c757d, #495057);
    color: white;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-weight: bold;
}

/* ========================= */
/* MODALS                    */
/* ========================= */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    inset: 0;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    width: 90%;
    max-width: 900px;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    animation: fadeIn 0.3s ease;
}

/* ========================= */
/* MODAL HEADER              */
/* ========================= */
.modal-header-clean {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header-clean h2,
.modal-header-clean h3 {
    flex: 1;
    text-align: center;
}

.close {
    font-size: 24px;
    cursor: pointer;
    transition: 0.2s;
}

.close:hover {
    color: red;
}

/* ========================= */
/* SEARCH                    */
/* ========================= */
.search-bar {
    display: flex;
    gap: 12px;
    margin: 15px 0;
}

.search-bar input {
    flex: 1;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    transition: 0.2s;
}

.search-bar input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 5px rgba(13,110,253,0.3);
    outline: none;
}

.btn-search {
    background: #0d6efd;
    color: white;
    border: none;
    padding: 10px 18px;
    border-radius: 6px;
    transition: 0.2s;
}

.btn-search:hover {
    background: #0b5ed7;
    transform: translateY(-2px);
}

/* ========================= */
/* SIT-IN FORM               */
/* ========================= */
#sitInModal form {
    width: 100%;
    max-width: 450px;
    margin: auto;
    display: flex;
    flex-direction: column;
}

#sitInModal label {
    margin-top: 10px;
    font-weight: 600;
}

#sitInModal input,
#sitInModal select {
    margin-top: 5px;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    transition: 0.2s;
}

#sitInModal input:focus,
#sitInModal select:focus {
    border-color: #198754;
    box-shadow: 0 0 5px rgba(25,135,84,0.3);
    outline: none;
}

#sitInModal button {
    margin-top: 20px;
    padding: 10px;
    background: #198754;
    color: white;
    border: none;
    border-radius: 6px;
    transition: 0.2s;
}

#sitInModal button:hover {
    background: #157347;
}

/* ========================= */
/* TABLE                     */
/* ========================= */
table {
    width: 100%;
    margin-top: 15px;
    border-collapse: collapse;
    border-radius: 8px;
    overflow: hidden;
}

table th {
    background: #0d6efd;
    color: white;
    padding: 10px;
}

table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

table tr {
    transition: 0.2s;
}

table tr:hover {
    background: #f8fbff;
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
    padding: 6px 12px;
    background: #dc3545;
    color: white;
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
        <li><a href="#" onclick="openSitIn()">Sit-in</a></li>
        <li><a href="view_sitin_records.php">View Sit-in Records</a></li>
        <li><a href="#">Sit-in Reports</a></li>
        <li><a href="#">Feedback Reports</a></li>
        <li><a href="#">Reservation</a></li>
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
      <input type="text" name="keyword" placeholder="Search..."
        value="<?php echo isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : ''; ?>"
        required>

      <button type="submit" name="search" class="btn-search">Search</button>
    </form>

    <hr>

    <!-- RESULTS -->
    <?php if ($searchResults !== null): ?>
      <h3>Search Results:</h3>

      <?php if ($searchResults->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>ID Number</th>
              <th>First Name</th>
              <th>Middle Name</th>
              <th>Last Name</th>
              <th>Course</th>
              <th>Remaining Session</th>
              <th>Action</th>
            </tr>
          </thead>

          <tbody>
            <?php while ($row = $searchResults->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['id_number']); ?></td>
                <td><?= htmlspecialchars($row['first_name']); ?></td>
                <td><?= htmlspecialchars($row['middle_name']); ?></td>
                <td><?= htmlspecialchars($row['last_name']); ?></td>
                <td><?= htmlspecialchars($row['course']); ?></td>
                <td><?= htmlspecialchars($row['sessions_remaining'] ?? 30); ?></td>

                <td>
                  <button type="button" class="btn-search"
                    onclick="selectStudent(
                      '<?= $row['id_number']; ?>',
                      '<?= $row['first_name'].' '.$row['middle_name'].' '.$row['last_name']; ?>',
                      '<?= $row['sessions_remaining'] ?? 30; ?>'
                    )">
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
      <input type="text" name="id_number" required placeholder="Enter student ID">

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
      <input type="text" name="lab" required>

      <label>Remaining Session</label>
      <input type="text" name="remaining_session">

      <button type="submit" class="btn-search" style="margin-top:15px;">
        Sit In
      </button>

    </form>

  </div>
</div>

<!-- DASHBOARD -->
<div class="dashboard-container">

<div class="dashboard-card">

<div class="dashboard-title">Statistics</div>

<p><b>Students Registered:</b> <?php echo $total_students; ?></p>
<?php
$current = $conn->query("SELECT COUNT(*) as total FROM sitin_records WHERE status='Active'");
$total   = $conn->query("SELECT COUNT(*) as total FROM sitin_records");

$current_count = $current->fetch_assoc()['total'];
$total_count   = $total->fetch_assoc()['total'];
?>

<p><b>Currently Sit-in:</b> <?php echo $current_count; ?></p>
<p><b>Total Sit-in:</b> <?php echo $total_count; ?></p>

<canvas id="chart"></canvas>

</div>

<div class="dashboard-card">

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

<button class="delete-btn"
onclick="deleteAnnouncement(<?php echo $row['id']; ?>)">
Delete
</button>

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
function deleteAnnouncement(id){
    if(confirm("Delete this announcement?")){
        window.location = "admin_dashboard.php?delete=" + id;
    }
}

/* CHART */
const ctx = document.getElementById('chart');

const labels = <?php echo json_encode($chart_labels); ?>;
const data = <?php echo json_encode($chart_data); ?>;

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
</script>
</body>
</html>