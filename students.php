<?php
session_start();
include "config.php";

/* FETCH ALL STUDENTS (DEFAULT VIEW) */
$students = $conn->query("SELECT * FROM students WHERE role != 'admin'");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: login.html");
    exit();
}

/* FETCH STUDENTS */
/* SEARCH MODAL FUNCTION (SAFE ADD) */
$searchResults = null;

if(isset($_GET['keyword'])){
    $keyword = "%" . trim($_GET['keyword']) . "%";

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
?>

<!DOCTYPE html>
<html>
<head>
<title>Students</title>

<link rel="stylesheet" href="styles.css">

<style>

/* RESET */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #f4f6f9;
    color: #333;
}

/* ========================= */
/* NAVBAR (MATCH DASHBOARD) */
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
    list-style: none;
    display: flex;
    gap: 15px;
}

.topnavInside ul li a {
    color: white;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 6px;
    transition: 0.3s;
    font-size: 14px;
}

.topnavInside ul li a:hover {
    background: rgba(255,255,255,0.2);
}

.topnavInside ul li a.active {
    background: #0d6efd;
}

/* ========================= */
/* PAGE CONTAINER            */
/* ========================= */
.container {
    width: 90%;
    margin: 40px auto 20px;
    text-align: center;
}

.container h1 {
    margin-bottom: 15px;
}

/* ========================= */
/* BUTTONS                   */
/* ========================= */
.btn {
    padding: 8px 14px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    color: white;
    transition: 0.2s;
}

.btn-add {
    background: #0d6efd;
}

.btn-add:hover {
    background: #0b5ed7;
    transform: translateY(-2px);
}

.btn-reset {
    background: #dc3545;
}

.btn-reset:hover {
    background: #bb2d3b;
    transform: translateY(-2px);
}

/* ========================= */
/* TABLE                     */
/* ========================= */
table {
    width: 90%;
    margin: 20px auto;
    border-collapse: collapse;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
}

table th {
    background: linear-gradient(135deg, #0d6efd, #0a58ca);
    color: white;
    padding: 12px;
    font-size: 14px;
}

table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: center;
    font-size: 14px;
}

table tr:hover {
    background: #f8fbff;
}

/* ========================= */
/* ACTION BUTTONS            */
/* ========================= */
.edit {
    background: #0d6efd;
}

.delete {
    background: #dc3545;
}

.edit:hover {
    background: #0b5ed7;
}

.delete:hover {
    background: #bb2d3b;
}

/* ========================= */
/* SEARCH MODAL              */
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
    width: 70%;
    max-width: 900px;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    animation: fadeIn 0.3s ease;
}

/* ========================= */
/* MODAL HEADER              */
/* ========================= */
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    flex: 1;
    text-align: center;
    margin: 0;
}

.close {
    font-size: 24px;
    cursor: pointer;
}

.close:hover {
    color: red;
}

/* ========================= */
/* SEARCH FORM               */
/* ========================= */
.modal-content form {
    display: flex;
    gap: 10px;
    margin: 15px 0;
}

.modal-content input {
    flex: 1;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
}

.modal-content input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 5px rgba(13,110,253,0.3);
    outline: none;
}

/* ========================= */
/* SIT-IN MODAL              */
/* ========================= */
#sitInModal .modal-content {
    display: flex;
    flex-direction: column;
    align-items: center;
}

#sitInModal form {
    width: 60%;
    max-width: 500px;
    display: flex;
    flex-direction: column;
}

/* LABEL */
#sitInModal label {
    margin-top: 12px;
    font-weight: 600;
}

/* INPUTS */
#sitInModal input,
#sitInModal select {
    margin-top: 5px;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
}

/* FOCUS */
#sitInModal input:focus,
#sitInModal select:focus {
    border-color: #198754;
    box-shadow: 0 0 5px rgba(25,135,84,0.3);
    outline: none;
}

/* BUTTON */
.submit-btn {
    margin-top: 20px;
    padding: 10px;
    background: #198754;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.submit-btn:hover {
    background: #157347;
}

/* ========================= */
/* SEARCH BUTTON IN MODAL    */
/* ========================= */
.search-btn {
    padding: 6px 12px;
    background: #0d6efd;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.search-btn:hover {
    background: #0b5ed7;
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

/* ========================= */
/* SIT-IN FORM HEADER FIX    */
/* ========================= */
#sitInModal .modal-header {
    position: relative;
    width: 100%;
    display: flex;
    justify-content: center; /* centers the title */
    align-items: center;
    margin-bottom: 15px;
}

/* Title stays centered */
#sitInModal .modal-header h2 {
    margin: 0;
    text-align: center;
}

/* X (close) stays on the right */
#sitInModal .modal-header .close {
    position: absolute;
    right: 0;
    top: 0;
    font-size: 24px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.2s;
}

#sitInModal .modal-header .close:hover {
    color: red;
}
</style>
</head>

<body>

<div class="topnav">
  <div id="title">
        <img src="uclogo.png" id="uc">
        <span>College of Computer Studies Sit-in Monitoring System</span>
  </div>
  <div class="topnavInside">
    <ul>
        <li><a class="<?php echo basename($_SERVER['PHP_SELF'])=='admin_dashboard.php'?'active':'' ?>" href="admin_dashboard.php">Home</a></li>
        <li><a href="#" onclick="openSearch(); return false;">Search</a></li>
        <li><a class="<?php echo basename($_SERVER['PHP_SELF'])=='students.php'?'active':'' ?>" href="students.php">Students</a></li>
        <li><a href="#" onclick="openSitIn()">Sit-in</a></li>
        <li><a class="<?php echo basename($_SERVER['PHP_SELF'])=='view_sitin_records.php'?'active':'' ?>" href="view_sitin_records.php">View Sit-in Records</a></li>
        <li><a href="#">Sit-in Reports</a></li>
        <li><a href="#">Feedback Reports</a></li>
        <li><a href="#">Reservation</a></li>
        <li><a class="logout" href="logout.php">Log out</a></li>
    </ul>
  </div>
</div>


<div class="container">
  <h1>Students Information</h1>
    <button class="btn btn-add">Add Students</button>
    <button class="btn btn-reset">Reset All Session</button>
</div>
  <table>
    <tr>
      <th>ID Number</th>
      <th>Name</th>
      <th>Year Level</th>
      <th>Course</th>
      <th>Remaining Session</th>
      <th>Actions</th>
  </tr>

  <?php 
  $data = $searchResults ? $searchResults : $students;
  while($row = $data->fetch_assoc()){ 
  ?>
  <tr>
    <td><?php echo $row['id_number']; ?></td>
    <td>
    <?php
    echo $row['first_name']." ".
    $row['middle_name']." ".
    $row['last_name'];
    ?>
   </td>
    <td><?php echo $row['year_level'] ?? "-"; ?></td>
    <td><?php echo $row['course']; ?></td>
    <td><?php echo $row['sessions_remaining'] ?? 30; ?></td>
    <td>
    <button class="btn edit">Edit</button>
    <button class="btn delete">Delete</button>
    </td>
  </tr>
  <?php } ?>
  </table>
</div>

<!-- SEARCH MODAL -->
<div id="searchModal" class="modal">
  <div class="modal-content">

    <!-- HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h2>Search Student</h2>
      <span class="close" onclick="closeSearch()">×</span>
    </div>

    <hr>

    <!-- SEARCH BAR -->
    <form method="GET" action="students.php" style="display:flex; gap:10px; margin:15px 0;">
      <input type="text" name="keyword" placeholder="Search..."
        value="<?php echo isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : ''; ?>"
        style="flex:1; padding:10px; border:1px solid #ccc; border-radius:6px;">

      <button type="submit" class="btn btn-add">Search</button>
    </form>

    <hr>

    <!-- RESULTS -->
    <?php if (isset($_GET['keyword'])): ?>

    <?php if ($searchResults) $searchResults->data_seek(0); ?>

    <h3>Search Results:</h3>

      <?php if ($searchResults && $searchResults->num_rows > 0): ?>
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
                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                <td><?php echo htmlspecialchars($row['middle_name']); ?></td>
                <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                <td><?php echo htmlspecialchars($row['course']); ?></td>
                <td><?php echo htmlspecialchars($row['sessions_remaining'] ?? 30); ?></td>

                <td>
                 <button class="search-btn"
                  onclick="selectStudent(
                  '<?php echo $row['id_number']; ?>',
                  '<?php echo $row['first_name'].' '.$row['middle_name'].' '.$row['last_name']; ?>',
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
        <p>No students found.</p>
      <?php endif; ?>
    <?php endif; ?>

        <?php if ($searchResults) $searchResults->data_seek(0); ?>
  </div>
</div>

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

      <button type="submit" class="submit-btn">Sit In</button>

    </form>

  </div>
</div>
<script>
  
function selectStudent(id, name, session){
    closeSearch();
    openSitIn();

    document.querySelector('#sitInModal input[name="id_number"]').value = id;
    document.querySelector('#sitInModal input[name="student_name"]').value = name;
    document.querySelector('#sitInModal input[name="remaining_session"]').value = session;
}

/* SEARCH MODAL */
function openSearch() {
    document.getElementById('searchModal').classList.add('show');
}

function closeSearch() {
    document.getElementById('searchModal').classList.remove('show');
}

/* SIT-IN MODAL */
function openSitIn() {
    document.getElementById('sitInModal').classList.add('show');
}

function closeSitIn() {
    document.getElementById('sitInModal').classList.remove('show');
}

/* CLOSE WHEN CLICK OUTSIDE */
window.onclick = function(event) {
    const searchModal = document.getElementById('searchModal');
    const sitInModal = document.getElementById('sitInModal');

    if (event.target === searchModal) {
        closeSearch();
    }

    if (event.target === sitInModal) {
        closeSitIn();
    }
};

function closeSitInForm(){
    closeSitIn();
}

<?php if ($searchResults !== null): ?>
document.getElementById('searchModal').classList.add('show');
<?php endif; ?>

</script>
</body>
</html>