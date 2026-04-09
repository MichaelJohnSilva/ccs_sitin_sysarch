<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.html');
    exit();
}

$feedbackQuery = "
    SELECT f.id, f.record_id, f.id_number, f.rating, f.comments, f.created_at,
           s.first_name, s.last_name, s.middle_name
    FROM feedback f
    LEFT JOIN students s ON f.id_number = s.id_number
    ORDER BY f.created_at DESC
";

$feedbackResult = $conn->query($feedbackQuery);

$statsQuery = "
    SELECT 
        COUNT(*) as total_feedback,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
    FROM feedback
";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Reports - Admin</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
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
            font-size: 20px;
            font-weight: 700;
        }

        #title img {
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 0 15px rgba(255,255,255,0.3);
            transition: transform 0.4s ease;
        }

        #title img:hover {
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

        .container {
            width: 95%;
            max-width: 1400px;
            margin: 40px auto;
            animation: fadeInUp 0.6s ease;
        }

        h2 {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            font-size: 32px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
        }

        .stat-card .number {
            font-size: 42px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            margin-top: 5px;
        }

        .stat-card.avg .number {
            color: #f5b301;
        }

        .rating-bars {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .rating-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            padding: 10px 20px;
            border-radius: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .rating-bar .stars {
            font-size: 18px;
            color: #f5b301;
        }

        .rating-bar .count {
            font-weight: 600;
            color: #333;
        }

        .rating-bar .bar {
            width: 100px;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }

        .rating-bar .bar-fill {
            height: 100%;
            background: linear-gradient(135deg, #f5b301 0%, #f0932b 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
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
        }

        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr:last-child td:first-child { border-radius: 0 0 0 20px; }
        table tbody tr:last-child td:last-child { border-radius: 0 0 20px 0; }

        .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }

        .rating-5 {
            background: rgba(46, 204, 113, 0.15);
            color: #27ae60;
        }

        .rating-4 {
            background: rgba(52, 152, 219, 0.15);
            color: #2980b9;
        }

        .rating-3 {
            background: rgba(241, 196, 15, 0.15);
            color: #f39c12;
        }

        .rating-2 {
            background: rgba(230, 126, 34, 0.15);
            color: #e67e22;
        }

        .rating-1 {
            background: rgba(231, 76, 60, 0.15);
            color: #c0392b;
        }

        .no-comments {
            color: #999;
            font-style: italic;
        }

        .no-records {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: white;
            color: #667eea;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        @media (max-width: 900px) {
            .topnav {
                flex-direction: column;
                gap: 15px;
                padding: 15px 20px;
            }
            
            #title img { display: none; }
            
            .topnavInside ul {
                flex-wrap: wrap;
                justify-content: center;
            }

            .rating-bars {
                flex-direction: column;
            }
            
            table th, table td {
                font-size: 11px;
                padding: 10px 5px;
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header-clean {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header-clean h2 {
            color: #333;
            margin: 0;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }

        .close:hover {
            color: #333;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }

        .search-bar input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
        }

        .btn-search {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
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
                <li><a href="#" onclick="openSearch()">Search</a></li>
                <li><a href="students.php">Students</a></li>
                <li><a href="sit_in.php">Sit-in</a></li>
                <li><a href="view_sitin_records.php">View Sit-in Records</a></li>
        <li><a href="#">Sit-in Reports</a></li>
        <li><a href="feedback_reports.php" class="active">Feedback Reports</a></li>
        <li><a href="admin_reservations.php">Reservation</a></li>
        <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
</div>

    <!-- SEARCH MODAL -->
    <div id="searchModal" class="modal">
        <div class="modal-content">
            <div class="modal-header-clean">
                <h2>Search Student</h2>
                <span class="close" onclick="closeSearch()">&times;</span>
            </div>
            <form method="POST" class="search-bar">
                <input type="text" name="keyword" placeholder="Enter ID Number..." required>
                <button type="submit" name="search" class="btn-search">Search</button>
            </form>
            <?php
            if (isset($_POST['search'])) {
                $keyword = trim($_POST['keyword']);
                $stmt = $conn->prepare("SELECT * FROM students WHERE role != 'admin' AND id_number = ?");
                $stmt->bind_param("s", $keyword);
                $stmt->execute();
                $searchResult = $stmt->get_result();
                
                if ($searchResult->num_rows > 0) {
                    echo '<table><thead><tr><th>ID Number</th><th>Name</th><th>Course</th><th>Sessions</th></tr></thead><tbody>';
                    while ($row = $searchResult->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['course']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['sessions_remaining'] ?? 30) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p>No students found.</p>';
                }
                $stmt->close();
            }
            ?>
        </div>
    </div>

    <script>
    function openSearch() {
        document.getElementById("searchModal").classList.add("show");
    }

    function closeSearch() {
        document.getElementById("searchModal").classList.remove("show");
    }

    window.onclick = function(event) {
        const searchModal = document.getElementById("searchModal");
        if (event.target === searchModal) {
            closeSearch();
        }
    };
    </script>

    <div class="container">
        <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h2>Feedback Reports</h2>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $stats['total_feedback']; ?></div>
                <div class="label">Total Feedback</div>
            </div>
            <div class="stat-card avg">
                <div class="number"><?php echo $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '0'; ?></div>
                <div class="label">Average Rating</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['five_star'] + $stats['four_star']; ?></div>
                <div class="label">Positive (4-5 Stars)</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $stats['one_star'] + $stats['two_star']; ?></div>
                <div class="label">Negative (1-2 Stars)</div>
            </div>
        </div>

        <div class="rating-bars">
            <?php 
            $total = max($stats['total_feedback'], 1);
            $ratings = [
                ['stars' => '★★★★★', 'count' => $stats['five_star'], 'pct' => ($stats['five_star'] / $total) * 100],
                ['stars' => '★★★★', 'count' => $stats['four_star'], 'pct' => ($stats['four_star'] / $total) * 100],
                ['stars' => '★★★', 'count' => $stats['three_star'], 'pct' => ($stats['three_star'] / $total) * 100],
                ['stars' => '★★', 'count' => $stats['two_star'], 'pct' => ($stats['two_star'] / $total) * 100],
                ['stars' => '★', 'count' => $stats['one_star'], 'pct' => ($stats['one_star'] / $total) * 100],
            ];
            foreach ($ratings as $r): 
            ?>
            <div class="rating-bar">
                <span class="stars"><?php echo $r['stars']; ?></span>
                <span class="count"><?php echo $r['count']; ?></span>
                <div class="bar">
                    <div class="bar-fill" style="width: <?php echo $r['pct']; ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>ID Number</th>
                        <th>Rating</th>
                        <th>Comments</th>
                        <th>Date Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($feedbackResult && $feedbackResult->num_rows > 0): ?>
                        <?php while ($row = $feedbackResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                                <td>
                                    <span class="rating-badge rating-<?php echo $row['rating']; ?>">
                                        <?php for($i=0; $i<$row['rating']; $i++) echo '★'; ?>
                                        <?php echo $row['rating']; ?>/5
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($row['comments'])): ?>
                                        <?php echo htmlspecialchars($row['comments']); ?>
                                    <?php else: ?>
                                        <span class="no-comments">No comments</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date("M d, Y h:i A", strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="no-records">No feedback submissions yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>