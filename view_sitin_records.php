    <?php
    session_start();
    include "config.php";

    // Default to null for search results
    $searchResults = null;

    // Handle Search
    if (isset($_POST['search'])) {
        $keyword = "%" . trim($_POST['keyword']) . "%";

        // Prepare and execute search query
        $stmt = $conn->prepare("SELECT * FROM students WHERE 
                                id_number LIKE ? 
                                OR first_name LIKE ? 
                                OR middle_name LIKE ? 
                                OR last_name LIKE ? 
                                OR CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?");
        $stmt->bind_param("sssss", $keyword, $keyword, $keyword, $keyword, $keyword);
        $stmt->execute();
        $searchResults = $stmt->get_result(); // Store the result of the query
    }

    // Fetch sit-in records
    $result = $conn->query(" 
        SELECT s.id, s.id_number, st.first_name, st.middle_name, st.last_name, s.purpose, s.lab, st.sessions_remaining, s.status, s.time_in, s.time_out
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
        <title>View Sit-in Records</title>
        <link rel="stylesheet" href="styles.css">
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
    overflow-x: hidden;
    transition: background 0.3s ease;
}

/* ========================= */
/* LOADING TRANSITION        */
/* ========================= */
#loading-overlay {
    position: fixed;
    inset: 0;
    background: rgba(255,255,255,0.95);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 1;
    transition: opacity 0.6s ease;
}

#loading-overlay.hide {
    opacity: 0;
    pointer-events: none;
}

.spinner {
    border: 6px solid rgba(0,0,0,0.1);
    border-top: 6px solid #0d6efd;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg);}
    100% { transform: rotate(360deg);}
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
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    position: sticky;
    top: 0;
    z-index: 100;
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
    color: white;
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 8px;
    transition: all 0.3s ease;
    font-size: 14px;
}

.topnavInside ul li a:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
}

.topnavInside ul li a.active {
    background: #0d6efd;
    box-shadow: 0 3px 10px rgba(13,110,253,0.4);
}

/* ========================= */
/* PAGE CONTAINER            */
/* ========================= */
.container {
    width: 95%;
    margin: 40px auto;
    transition: all 0.3s ease;
}

h2 {
    text-align: center;
    margin-bottom: 20px;
    color: #0d6efd;
    letter-spacing: 1px;
}

/* ========================= */
/* TABLE STYLING             */
/* ========================= */
table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

table th {
    background: linear-gradient(135deg, #0d6efd, #0a58ca);
    color: white;
    padding: 14px;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: center;
    font-size: 14px;
    transition: all 0.2s ease;
}

table tr:hover {
    background: #f1f5ff;
    transform: scale(1.01);
}

/* STATUS COLORS */
.status-active {
    color: #198754;
    font-weight: bold;
    transition: color 0.3s ease;
}

.status-ended {
    color: #dc3545;
    font-weight: bold;
    transition: color 0.3s ease;
}

/* ========================= */
/* MODALS                    */
/* ========================= */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    justify-content: center;
    align-items: center;
    z-index: 9999;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}

.modal.show {
    display: flex;
    opacity: 1;
    pointer-events: auto;
}

.modal-content {
    background: white;
    width: 70%;
    max-width: 900px;
    border-radius: 14px;
    padding: 30px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    animation: fadeScale 0.4s ease forwards;
}

/* MODAL HEADER */
.modal-header {
    position: relative;
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 15px;
}

.modal-header h2 {
    text-align: center;
    color: #0d6efd;
}

.modal-header .close {
    position: absolute;
    right: 0;
    top: 0;
    font-size: 26px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.2s;
}

.modal-header .close:hover {
    color: red;
    transform: scale(1.2);
}

/* SEARCH FORM */
.modal-content form {
    display: flex;
    gap: 10px;
    margin: 15px 0;
}

.modal-content input {
    flex: 1;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    transition: all 0.2s ease;
}

.modal-content input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 6px rgba(13,110,253,0.3);
    outline: none;
}

.search-btn {
    padding: 10px 16px;
    background: #0d6efd;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.search-btn:hover {
    background: #0b5ed7;
    transform: translateY(-2px);
}

/* SIT-IN FORM */
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

#sitInModal label {
    margin-top: 12px;
    font-weight: 600;
    color: #495057;
}

#sitInModal input,
#sitInModal select {
    margin-top: 5px;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #ccc;
    transition: all 0.3s ease;
}

#sitInModal input:focus,
#sitInModal select:focus {
    border-color: #198754;
    box-shadow: 0 0 5px rgba(25,135,84,0.3);
    outline: none;
}

.submit-btn {
    margin-top: 20px;
    padding: 12px;
    background: linear-gradient(135deg, #198754, #146c43);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.submit-btn:hover {
    transform: translateY(-3px) scale(1.02);
    background: linear-gradient(135deg, #146c43, #0f5132);
}

/* ========================= */
/* ANIMATION                 */
/* ========================= */
@keyframes fadeScale {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

/* SCROLLBAR */
::-webkit-scrollbar {
    width: 10px;
}

::-webkit-scrollbar-thumb {
    background: #bbb;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #888;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .modal-content {
        width: 90%;
    }
    table th, table td {
        font-size: 12px;
        padding: 10px;
    }
    .topnavInside ul {
        gap: 8px;
        flex-wrap: wrap;
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
                <li><a href="javascript:void(0);" onclick="openSitInForm()">Sit-in</a></li>
                <li><a class="active" href="view_sitin_records.php">View Sit-in Records</a></li>
                <li><a href="#">Sit-in Reports</a></li>
                <li><a href="#">Feedback Reports</a></li>
                <li><a href="#">Reservation</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>

    <div id="searchModal" class="modal">
  <div class="modal-content">

    <!-- HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h2>Search Student</h2>
      <span class="close" onclick="closeSearch()">×</span>
    </div>

    <hr>

    <!-- SEARCH BAR -->
    <form method="POST" style="display:flex; gap:10px; margin:15px 0;">
      <input type="text" name="keyword" placeholder="Search..."
        value="<?php echo isset($_POST['keyword']) ? htmlspecialchars($_POST['keyword']) : ''; ?>"
        style="flex:1; padding:12px; border:1px solid #ccc; border-radius:6px;">

      <button type="submit" name="search" class="search-btn">Search</button>
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
                      '<?php echo htmlspecialchars($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']); ?>',
                      '<?php echo $row['sessions_remaining'] ?? 30; ?>'
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

    <!-- SIT-IN RECORDS -->
    <div class="container">
    <h2>Sit-in Records</h2>

    <table>
        <tr>
        <th>Sit ID</th>
        <th>ID Number</th>
        <th>Student Name</th>
        <th>Purpose</th>
        <th>Lab</th>
        <th>Remaining Sessions</th>
        <th>Status</th>
        <th>Time In</th>
        <th>Time Out</th>
        </tr>

        <?php while ($row = $result->fetch_assoc()) { ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo $row['id_number']; ?></td>
            <td><?php echo $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']; ?></td>
            <td><?php echo $row['purpose']; ?></td>
            <td><?php echo $row['lab']; ?></td>
            <td><?php echo $row['sessions_remaining']; ?></td>
            <td class="<?php echo ($row['status'] == 'Active') ? 'status-active' : 'status-ended'; ?>">
            <?php echo $row['status']; ?>
            </td>
            <td><?php echo date("M d, Y h:i A", strtotime($row['time_in'])); ?></td>
            <td><?php echo $row['time_out'] ? date("M d, Y h:i A", strtotime($row['time_out'])) : '-'; ?></td>
        </tr>
        <?php } ?>
    </table>
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

      <button type="submit" class="submit-btn">Sit In</button>

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

    function selectStudent(id, name, session){
        closeSearch();
        openSitInForm();

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
        
    <?php if ($searchResults !== null): ?>
    document.getElementById("searchModal").classList.add("show");
    <?php endif; ?>
</script>

    </html> 