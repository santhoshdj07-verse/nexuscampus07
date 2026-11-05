<?php

session_start();

if (!isset($_SESSION["admin_id"]))
{
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

$dataError = "";
$approvedStudents = array();
$activeTeachers = array();
$pendingApplications = array();
$studentEdges = array();

$studentsResult = $conn->query(
    "SELECT s.id, s.roll_no, s.name, s.department, s.semester, s.cgpa
     FROM students s
     WHERE EXISTS (
        SELECT 1
        FROM student_applications sa
        WHERE sa.student_id = s.id
          AND sa.application_status = 'Accepted'
     )
     ORDER BY s.roll_no ASC"
);

if ($studentsResult === false)
{
    $dataError = "Unable to load approved students: " . $conn->error;
}
else
{
    while ($student = $studentsResult->fetch_assoc())
    {
        $student["id"] = (int)$student["id"];
        $student["cgpa"] = (float)$student["cgpa"];
        $approvedStudents[] = $student;
    }
    $studentsResult->free();
}

$teachersResult = $conn->query(
    "SELECT id, teacher_code, teacher_id, name, department, designation
     FROM teachers
     WHERE UPPER(status) = 'ACTIVE'
     ORDER BY name ASC"
);

if ($teachersResult === false && $dataError === "")
{
    $dataError = "Unable to load active teachers: " . $conn->error;
}
elseif ($teachersResult)
{
    while ($teacher = $teachersResult->fetch_assoc())
    {
        $teacher["id"] = (int)$teacher["id"];
        $teacher["code"] = $teacher["teacher_code"] ?: $teacher["teacher_id"];
        $activeTeachers[] = $teacher;
    }
    $teachersResult->free();
}

$pendingResult = $conn->query(
    "SELECT id, application_number
     FROM student_applications
     WHERE application_status IN ('Pending', 'Processing')
     ORDER BY submitted_at ASC, id ASC"
);

if ($pendingResult === false && $dataError === "")
{
    $dataError = "Unable to load pending application queue: " . $conn->error;
}
elseif ($pendingResult)
{
    while ($application = $pendingResult->fetch_assoc())
    {
        $application["queue_label"] = trim((string)$application["application_number"]);
        if ($application["queue_label"] === "")
        {
            $application["queue_label"] = "APP-" . (int)$application["id"];
        }
        $pendingApplications[] = array(
            "id" => (int)$application["id"],
            "label" => $application["queue_label"]
        );
    }
    $pendingResult->free();
}

$approvedIdSet = array();
foreach ($approvedStudents as $student)
{
    $approvedIdSet[$student["id"]] = true;
}

$createConnections = $conn->query(
    "CREATE TABLE IF NOT EXISTS student_connections
     (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student1_id INT NOT NULL,
        student2_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (student1_id),
        INDEX (student2_id)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
if (!$createConnections && $dataError === "")
{
    $dataError = "Unable to prepare student relationship storage: " . $conn->error;
}

$edgeResult = $conn->query(
    "SELECT student1_id, student2_id
     FROM student_connections"
);

if ($edgeResult === false && $dataError === "")
{
    $dataError = "Unable to load saved student relationships: " . $conn->error;
}
elseif ($edgeResult)
{
    while ($edge = $edgeResult->fetch_assoc())
    {
        $from = (int)$edge["student1_id"];
        $to = (int)$edge["student2_id"];
        if (isset($approvedIdSet[$from]) && isset($approvedIdSet[$to]))
        {
            $studentEdges[] = array("from" => $from, "to" => $to);
        }
    }
    $edgeResult->free();
}

$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Record Workflows | Smart Campus</title>
    <style>
        * { box-sizing: border-box; }
        :root {
            color-scheme: dark;
            --bg: #070b17;
            --panel: rgba(13, 19, 36, .96);
            --border: rgba(148, 163, 184, .17);
            --text: #f8fafc;
            --muted: #94a3b8;
            --blue: #3b82f6;
            --cyan: #06b6d4;
            --green: #22c55e;
            --purple: #8b5cf6;
            --red: #f87171;
        }
        body {
            margin: 0;
            background: radial-gradient(circle at 10% 0%, rgba(59,130,246,.13), transparent 28%),
                        radial-gradient(circle at 100% 25%, rgba(139,92,246,.10), transparent 28%), var(--bg);
            color: var(--text);
            font: 14px/1.55 Inter, "Segoe UI", Arial, sans-serif;
        }
        a { color: #93c5fd; }
        code { color: #bfdbfe; }
        .container { max-width: 1220px; margin: auto; padding: 27px 20px 60px; }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 20px; }
        .eyebrow { color: #7dd3fc; font-size: 10px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
        h1 { margin: 4px 0; font-size: clamp(24px, 4vw, 34px); letter-spacing: -.5px; }
        h2 { margin: 0; font-size: 18px; }
        h3 { margin: 0 0 10px; font-size: 13px; }
        p { color: var(--muted); margin: 8px 0; }
        .toolbar, .controls { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .toolbar { justify-content: flex-end; }
        .button, button {
            display: inline-flex; justify-content: center; align-items: center;
            border: 1px solid var(--border); border-radius: 9px; padding: 9px 12px;
            color: #e2e8f0; background: #111a2e; font-weight: 700; text-decoration: none;
            cursor: pointer; font: inherit; font-size: 12px;
        }
        .button:hover, button:hover { border-color: #60a5fa; }
        button.primary { color: white; background: #1d4ed8; border-color: #2563eb; }
        button:disabled { cursor: not-allowed; opacity: .4; }
        input, select {
            min-height: 38px; min-width: 115px; max-width: 100%; padding: 7px 9px;
            color: var(--text); background: #080e1c; border: 1px solid var(--border);
            border-radius: 8px; font: inherit; font-size: 12px;
        }
        input:focus, select:focus { outline: 2px solid rgba(59,130,246,.5); outline-offset: 1px; }
        .hero, .card, .stat { background: var(--panel); border: 1px solid var(--border); border-radius: 14px; }
        .hero { padding: 16px 19px; margin-bottom: 14px; }
        .hero strong { color: #e2e8f0; }
        .error-banner { padding: 11px 14px; margin: 12px 0; color: #fecaca; background: rgba(127,29,29,.35); border: 1px solid rgba(248,113,113,.35); border-radius: 10px; }
        .stats { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 10px; margin: 14px 0 18px; }
        .stat { padding: 14px 16px; }
        .stat b { display: block; color: #bfdbfe; font-size: 23px; }
        .stat span { color: var(--muted); font-size: 11px; }
        .card { margin: 15px 0; overflow: hidden; }
        .card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 15px 18px; border-bottom: 1px solid var(--border); }
        .card-body { padding: 16px 18px; }
        .badge { padding: 4px 9px; border: 1px solid rgba(59,130,246,.22); border-radius: 99px; color: #bfdbfe; background: rgba(59,130,246,.1); font-size: 9px; font-weight: 800; letter-spacing: .09em; }
        .grid2 { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px; }
        .grid3 { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px; }
        .subpanel { min-width: 0; padding: 13px; border: 1px solid var(--border); border-radius: 11px; background: rgba(2,6,23,.25); }
        .output { min-height: 39px; padding: 9px 11px; margin-top: 10px; overflow-wrap: anywhere; color: #cbd5e1; background: rgba(2,6,23,.45); border: 1px solid var(--border); border-radius: 8px; font-size: 12px; }
        .success { color: #bbf7d0; }
        .failure { color: #fecaca; }
        .muted { color: var(--muted); font-size: 11px; }
        .node-strip { display: flex; flex-wrap: wrap; gap: 7px; min-height: 35px; margin: 9px 0; }
        .data-node { padding: 6px 9px; color: #dbeafe; background: rgba(59,130,246,.12); border: 1px solid rgba(59,130,246,.24); border-radius: 8px; font-size: 11px; }
        .stack-view { display: flex; flex-direction: column-reverse; align-items: stretch; gap: 5px; min-height: 72px; }
        .queue-view { display: flex; flex-wrap: wrap; gap: 5px; min-height: 45px; align-items: center; }
        .tree-wrap { overflow: auto; border: 1px solid rgba(148,163,184,.12); border-radius: 9px; background: rgba(2,6,23,.35); }
        .tree-svg { display: block; min-width: 640px; width: 100%; }
        .tree-edge { stroke: #475569; stroke-width: 2; }
        .tree-node { fill: #172554; stroke: #60a5fa; stroke-width: 2; }
        .bst-node { fill: #312e81; stroke: #a78bfa; stroke-width: 2; }
        .tree-label { fill: #eff6ff; font: 700 11px "Segoe UI", Arial, sans-serif; text-anchor: middle; }
        .traversal-row { display: grid; grid-template-columns: 90px 1fr; gap: 8px; padding: 6px 0; border-bottom: 1px solid rgba(148,163,184,.1); font-size: 11px; }
        .traversal-row:last-child { border-bottom: 0; }
        .traversal-row b { color: #93c5fd; }
        .scroll-table { max-height: 300px; overflow: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { padding: 7px 8px; border-bottom: 1px solid rgba(148,163,184,.13); text-align: left; white-space: nowrap; }
        th { position: sticky; top: 0; z-index: 1; color: #bfdbfe; background: #10192b; }
        .matrix { overflow: auto; max-height: 330px; }
        .matrix table { font: 10px Consolas, "Courier New", monospace; }
        .matrix td, .matrix th { min-width: 32px; padding: 5px; text-align: center; }
        .inline-links { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 10px; font-size: 11px; }
        @media (max-width: 850px) {
            .stats { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .grid3 { grid-template-columns: 1fr; }
            .header { align-items: flex-start; flex-direction: column; }
            .toolbar { justify-content: flex-start; }
        }
        @media (max-width: 620px) {
            .container { padding: 18px 12px 40px; }
            .grid2 { grid-template-columns: 1fr; }
            .card-head { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
<main class="container">
    <header class="header">
        <div>
            <div class="eyebrow">Live CollegeManagementWeb records</div>
            <h1>Student Record Workflows</h1>
            <p>Explore how the college manages accepted-student records, admission review, student connections, and teacher support planning.</p>
        </div>
        <nav class="toolbar" aria-label="Project navigation">
            <a class="button" href="search_sort.php">Search &amp; Sort</a>
            <a class="button" href="student_network.php">Student Network</a>
            <a class="button" href="dashboard.php">Dashboard</a>
        </nav>
    </header>

    <?php if ($dataError !== "") { ?>
        <div class="error-banner" role="alert"><?php echo htmlspecialchars($dataError, ENT_QUOTES, "UTF-8"); ?></div>
    <?php } ?>

    <section class="hero">
        <strong>Admission privacy:</strong>
        <p>Student names and academic records in the record views, lookup, ordering, and student connections are loaded only for applications whose admin status is <code>Accepted</code>. Pending work appears only as application-number tokens in the review queue; names and student details stay hidden. These demonstrations use read-only snapshots and never edit or delete student, application, or teacher records.</p>
        <div class="inline-links">
            <a href="admin_applications.php">Review applications</a>
            <a href="admission_processing.php">Continue approved admissions</a>
            <a href="teachers.php">Manage teachers</a>
        </div>
    </section>

    <div class="stats">
        <div class="stat"><b><?php echo count($approvedStudents); ?></b><span>Accepted student records loaded</span></div>
        <div class="stat"><b><?php echo count($pendingApplications); ?></b><span>Pending application tokens in queue</span></div>
        <div class="stat"><b><?php echo count($activeTeachers); ?></b><span>Active teachers available</span></div>
        <div class="stat"><b><?php echo count($studentEdges); ?></b><span>Saved approved-student links</span></div>
    </div>

    <section class="card" aria-labelledby="linear-title">
        <div class="card-head">
            <h2 id="linear-title">Manage student review lists and application order</h2>
            <span class="badge">LIVE RECORDS · SAFE SIMULATIONS</span>
        </div>
        <div class="card-body">
            <p>Use temporary student lists and last-in-first-out review actions while keeping pending applications in first-in-first-out order. Student nodes originate from admin-accepted applications; list changes affect only this page. The pending queue contains application numbers only and does not expose applicants' names.</p>
            <div class="grid3">
                <section class="subpanel">
                    <h3>Keep an adjustable list of accepted students</h3>
                    <div class="controls">
                        <select id="listInsertStudent" aria-label="Approved student to insert"></select>
                        <button class="primary" id="listInsert" type="button">Insert</button>
                        <select id="listDeleteStudent" aria-label="Approved student to unlink"></select>
                        <button id="listDelete" type="button">Unlink</button>
                        <button id="listTraverse" type="button">Traverse</button>
                    </div>
                    <div class="node-strip" id="linkedListView"></div>
                    <div class="output" id="linkedListOutput" aria-live="polite"></div>
                </section>
                <section class="subpanel">
                    <h3>Review the most recently selected student first</h3>
                    <div class="controls">
                        <select id="stackStudent" aria-label="Approved student to push"></select>
                        <button class="primary" id="stackPush" type="button">Push</button>
                        <button id="stackPop" type="button">Pop</button>
                    </div>
                    <div class="stack-view" id="stackView"></div>
                    <div class="output" id="stackOutput" aria-live="polite">Push an approved student to begin.</div>
                </section>
                <section class="subpanel">
                    <h3>Review pending applications in submission order</h3>
                    <div class="controls">
                        <select id="queueApplication" aria-label="Pending application to enqueue"></select>
                        <button class="primary" id="queueEnqueue" type="button">Enqueue</button>
                        <button id="queueDequeue" type="button">Dequeue next</button>
                        <button id="queueReset" type="button">Reload queue</button>
                    </div>
                    <div class="queue-view" id="queueView"></div>
                    <div class="output" id="queueOutput" aria-live="polite"></div>
                </section>
            </div>
            <section class="subpanel" style="margin-top:12px">
                <h3>Evaluate a multi-step calculation safely</h3>
                <p class="muted">The expression evaluator supports decimal numbers, +, -, *, /, and parentheses. Division by zero and malformed expressions are reported.</p>
                <div class="controls">
                    <input id="expressionInput" type="text" maxlength="100" value="(8 + 4) * 3 / 2" aria-label="Arithmetic expression">
                    <button class="primary" id="evaluateExpression" type="button">Evaluate expression</button>
                </div>
                <div class="output" id="expressionOutput" aria-live="polite"></div>
            </section>
        </div>
    </section>

    <section class="card" aria-labelledby="tree-title">
        <div class="card-head">
            <h2 id="tree-title">Review accepted-student records in structured views</h2>
            <span class="badge">APPROVED STUDENTS ONLY</span>
        </div>
        <div class="card-body">
            <p>The first view arranges accepted student records level-by-level and shows three ways to review their roll numbers. A second view orders records by comparing their actual roll numbers so a lookup can follow a shorter path. Both views are rebuilt from the database snapshot and never write back to the student table.</p>
            <div class="grid2">
                <section class="subpanel">
                    <h3>Arrange accepted students for structured review · <span id="treeCount">0</span> records</h3>
                    <div class="tree-wrap"><svg id="binaryTreeSvg" class="tree-svg" role="img" aria-label="Binary tree of approved students"></svg></div>
                    <div id="traversalResults" class="output" aria-live="polite"></div>
                </section>
                <section class="subpanel">
                    <h3>Locate a roll number through ordered student records</h3>
                    <div class="tree-wrap"><svg id="bstSvg" class="tree-svg" role="img" aria-label="Binary search tree of approved students"></svg></div>
                    <div class="controls">
                        <input id="bstRollSearch" type="text" maxlength="20" placeholder="Exact roll number" aria-label="Roll number to search in BST">
                        <button class="primary" id="bstSearch" type="button">Find student by roll number</button>
                    </div>
                    <div id="bstOutput" class="output" aria-live="polite"></div>
                </section>
            </div>
        </div>
    </section>

    <section class="card" aria-labelledby="search-title">
        <div class="card-head">
            <h2 id="search-title">Phase 3: Find and organize approved students for admission decisions</h2>
            <span class="badge">CLO2 · ADMIN WORKFLOW</span>
        </div>
        <div class="card-body">
            <p>Administrators need to find an eligible student by a partial name or roll number and organize records before review. These controls use the current database records for students with Accepted applications. Linear search supports partial matches; binary search demonstrates fast exact roll-number lookup after ordering a copy; sorting arranges the displayed records by roll number, name, or CGPA.</p>
            <p class="muted">Choose an operation below to compare the results. Linear search is O(n), binary search is O(log n) after sorting, insertion sort is O(n²), and merge sort is O(n log n). Comparison counts are for the current record snapshot; all operations leave database records unchanged. For the application's regular database search and sort page, use Search &amp; Sort.</p>
            <div class="subpanel">
                <h3>How the admission search and ordering are implemented</h3>
                <div class="scroll-table">
                    <table>
                        <thead><tr><th>Administrator's task</th><th>What the project does</th></tr></thead>
                        <tbody>
                            <tr>
                                <td>Find a student when only part of a name or roll number is known</td>
                                <td>Checks each accepted-student record in turn, compares the lower-case query with the student's name and roll number, and collects every partial match. The result reports the records inspected.</td>
                            </tr>
                            <tr>
                                <td>Confirm an exact roll number without scanning every record</td>
                                <td>Makes a copy of the accepted-student list ordered by roll number, checks the middle record, then narrows the search to the left or right half until the roll number is found or no range remains. The result reports its comparison count.</td>
                            </tr>
                            <tr>
                                <td>Arrange records for review by roll number, name, or CGPA</td>
                                <td>Compares roll numbers and names as text and CGPA as a number. One option grows an ordered prefix by shifting records into place; the other splits a copy into smaller groups and merges those groups in order. Both display a sorted copy and comparison count; neither updates the database.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="controls">
                <input id="studentSearch" type="text" maxlength="80" placeholder="Name or roll number" aria-label="Search approved students">
                <button class="primary" id="linearSearch" type="button">Linear search</button>
                <button id="binarySearch" type="button">Binary search roll no.</button>
                <select id="sortKey" aria-label="Sort approved students by">
                    <option value="roll_no">Roll number</option>
                    <option value="name">Name</option>
                    <option value="cgpa">CGPA</option>
                </select>
                <button id="insertionSort" type="button">Insertion sort</button>
                <button id="mergeSort" type="button">Merge sort</button>
                <a class="button" href="search_sort.php">Open database Search &amp; Sort</a>
            </div>
            <div id="searchSortOutput" class="output" aria-live="polite"></div>
            <div class="scroll-table">
                <table>
                    <thead><tr><th>Roll No.</th><th>Student</th><th>Department</th><th>Semester</th><th>CGPA</th></tr></thead>
                    <tbody id="studentTableBody"></tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card" aria-labelledby="graph-title">
        <div class="card-head">
            <h2 id="graph-title">Explore saved connections between accepted students</h2>
            <span class="badge">SAVED DATABASE CONNECTIONS</span>
        </div>
        <div class="card-body">
            <p>This view helps staff see which accepted students are connected and explore the reachable group from a starting student. Connections are loaded from <code>student_connections</code> through the existing Student Network workflow; the list and matrix below are two views of those same saved links.</p>
            <div class="grid2">
                <section class="subpanel">
                    <h3>Show each student and their connections</h3>
                    <div class="scroll-table"><table><thead><tr><th>Approved student</th><th>Connected to</th></tr></thead><tbody id="adjacencyList"></tbody></table></div>
                </section>
                <section class="subpanel">
                    <h3>Compare connections across the cohort <span class="muted" id="matrixCaption"></span></h3>
                    <div class="matrix" id="adjacencyMatrix"></div>
                </section>
            </div>
            <div class="controls" style="margin-top:12px">
                <label for="bfsStart">Starting student:</label>
                <select id="bfsStart" aria-label="BFS starting student"></select>
                <button class="primary" id="runBfs" type="button">Explore connected students</button>
                <a class="button" href="student_network.php">Manage saved connections</a>
            </div>
            <div id="bfsOutput" class="output" aria-live="polite"></div>
        </div>
    </section>

    <section class="card" aria-labelledby="planning-title">
        <div class="card-head">
            <h2 id="planning-title">Phase 4: Plan teacher support for accepted students</h2>
            <span class="badge">CLO3 · ACADEMIC PLANNING</span>
        </div>
        <div class="card-body">
            <p>Academic teams must distribute advising fairly, use limited tutoring time effectively, and find assignments that satisfy capacity constraints. These planning actions use accepted-student and active-teacher records from this database. Each result is a proposal only; no assignment or academic record is changed.</p>
            <div class="grid3">
                <section class="subpanel">
                    <h3>Distribute advising across active teachers</h3>
                    <p class="muted">To balance advising fairly, the planner starts each active teacher with an empty caseload, then assigns students one at a time to the least-loaded teacher in the same department when possible, or otherwise the least-loaded teacher overall. Ties are settled by teacher name. This is a greedy load-balancing approach.</p>
                    <button class="primary" id="runGreedy" type="button">Build advising plan</button>
                    <div id="greedyOutput" class="output" aria-live="polite"></div>
                </section>
                <section class="subpanel">
                    <h3>Use a limited tutoring-hour budget effectively</h3>
                    <p class="muted">To use limited tutoring time effectively, the planner validates a whole-hour budget from 1 to 50, estimates each student's hours and need score from CGPA, then compares the best score with and without each student at every remaining budget. It traces those choices backward to display the selected students. This is a 0/1 knapsack calculation; each student is selected at most once.</p>
                    <div class="controls">
                        <input id="tutoringHours" type="number" min="1" max="50" value="12" aria-label="Tutoring hour budget">
                        <button class="primary" id="runDynamic" type="button">Optimize plan</button>
                    </div>
                    <div id="dynamicOutput" class="output" aria-live="polite"></div>
                </section>
                <section class="subpanel">
                    <h3>Find adviser assignments that satisfy class constraints</h3>
                    <p class="muted">To find feasible adviser assignments, the planner caps each teacher at the average caseload rounded up and tracks semesters already assigned to that teacher. For each student it tries same-department teachers first, then less-loaded teachers; a full teacher or duplicate semester is skipped. If later students cannot fit, it removes the tentative assignment and tries the next teacher. This backtracking stops with a complete plan or reports that the constraints cannot be met.</p>
                    <button class="primary" id="runBacktracking" type="button">Find a valid advising plan</button>
                    <div id="backtrackingOutput" class="output" aria-live="polite"></div>
                </section>
            </div>
            <div class="subpanel" style="margin-top:12px">
                <h3>Active teacher records used by the plans</h3>
                <div class="scroll-table">
                    <table><thead><tr><th>Teacher code</th><th>Name</th><th>Department</th><th>Designation</th></tr></thead><tbody id="teacherTableBody"></tbody></table>
                </div>
            </div>
        </div>
    </section>
</main>
<script>
"use strict";
var liveStudents = <?php echo json_encode($approvedStudents, $jsonFlags); ?>;
var liveTeachers = <?php echo json_encode($activeTeachers, $jsonFlags); ?>;
var pendingApplications = <?php echo json_encode($pendingApplications, $jsonFlags); ?>;
var savedEdges = <?php echo json_encode($studentEdges, $jsonFlags); ?>;

function byId(id) {
    return document.getElementById(id);
}

function output(elementId, message, isError) {
    var element = byId(elementId);
    element.textContent = message;
    element.className = "output " + (isError ? "failure" : "success");
}

function recordLabel(student) {
    return student.roll_no + " · " + student.name;
}

function createOption(value, label) {
    var option = document.createElement("option");
    option.value = String(value);
    option.textContent = label;
    return option;
}

function fillStudentSelect(selectId, source, includePlaceholder) {
    var select = byId(selectId);
    var previous = select.value;
    select.replaceChildren();
    if (includePlaceholder) {
        select.appendChild(createOption("", "Choose approved student"));
    }
    source.forEach(function (student) {
        select.appendChild(createOption(student.id, recordLabel(student)));
    });
    if (previous && Array.from(select.options).some(function (option) { return option.value === previous; })) {
        select.value = previous;
    }
}

function LinkedNode(value) {
    this.value = value;
    this.next = null;
}

function LinkedList(values) {
    this.head = null;
    this.length = 0;
    (values || []).forEach(this.append.bind(this));
}

LinkedList.prototype.append = function (value) {
    var node = new LinkedNode(value);
    if (!this.head) {
        this.head = node;
    } else {
        var tail = this.head;
        while (tail.next) tail = tail.next;
        tail.next = node;
    }
    this.length++;
};

LinkedList.prototype.insertAt = function (index, value) {
    if (!Number.isInteger(index) || index < 0 || index > this.length) return false;
    var node = new LinkedNode(value);
    if (index === 0) {
        node.next = this.head;
        this.head = node;
    } else {
        var previous = this.head;
        for (var position = 1; position < index; position++) previous = previous.next;
        node.next = previous.next;
        previous.next = node;
    }
    this.length++;
    return true;
};

LinkedList.prototype.remove = function (id) {
    var previous = null;
    var current = this.head;
    while (current) {
        if (Number(current.value.id) === Number(id)) {
            if (previous) previous.next = current.next;
            else this.head = current.next;
            this.length--;
            return current.value;
        }
        previous = current;
        current = current.next;
    }
    return null;
};

LinkedList.prototype.toArray = function () {
    var result = [];
    var current = this.head;
    while (current) {
        result.push(current.value);
        current = current.next;
    }
    return result;
};

var linkedStudents = new LinkedList(liveStudents);

function refreshLinkedList() {
    var list = byId("linkedListView");
    list.replaceChildren();
    linkedStudents.toArray().forEach(function (student) {
        var chip = document.createElement("span");
        chip.className = "data-node";
        chip.textContent = student.roll_no + " → " + student.name;
        list.appendChild(chip);
    });
    var present = new Set(linkedStudents.toArray().map(function (student) { return String(student.id); }));
    fillStudentSelect("listInsertStudent", liveStudents.filter(function (student) {
        return !present.has(String(student.id));
    }), true);
    fillStudentSelect("listDeleteStudent", linkedStudents.toArray(), true);
}

byId("listInsert").addEventListener("click", function () {
    var id = byId("listInsertStudent").value;
    var record = liveStudents.find(function (student) { return String(student.id) === id; });
    if (!record) return output("linkedListOutput", "There are no approved records available to insert.", true);
    if (linkedStudents.toArray().some(function (student) { return Number(student.id) === Number(record.id); })) {
        return output("linkedListOutput", "That student is already linked in the list.", true);
    }
    linkedStudents.insertAt(linkedStudents.length, record);
    refreshLinkedList();
    output("linkedListOutput", "Inserted " + recordLabel(record) + " at the tail. The database student record was not changed.", false);
});

byId("listDelete").addEventListener("click", function () {
    var id = byId("listDeleteStudent").value;
    var removed = linkedStudents.remove(id);
    if (!removed) return output("linkedListOutput", "Choose a student currently in the linked list.", true);
    refreshLinkedList();
    output("linkedListOutput", "Unlinked " + recordLabel(removed) + ". The database student record was not deleted.", false);
});

byId("listTraverse").addEventListener("click", function () {
    var ordered = linkedStudents.toArray();
    output("linkedListOutput", ordered.length
        ? "Head → " + ordered.map(recordLabel).join(" → ") + " → null"
        : "The linked list is empty.", false);
});

function LinkedStack() {
    this.top = null;
    this.length = 0;
}
LinkedStack.prototype.push = function (value) {
    var node = new LinkedNode(value);
    node.next = this.top;
    this.top = node;
    this.length++;
};
LinkedStack.prototype.pop = function () {
    if (!this.top) return null;
    var value = this.top.value;
    this.top = this.top.next;
    this.length--;
    return value;
};
LinkedStack.prototype.toArray = function () {
    var values = [];
    var current = this.top;
    while (current) {
        values.push(current.value);
        current = current.next;
    }
    return values;
};

var studentStack = new LinkedStack();
fillStudentSelect("stackStudent", liveStudents, true);

function refreshStack() {
    var view = byId("stackView");
    view.replaceChildren();
    studentStack.toArray().forEach(function (student, index) {
        var node = document.createElement("div");
        node.className = "data-node";
        node.textContent = (index === 0 ? "TOP · " : "") + recordLabel(student);
        view.appendChild(node);
    });
}

byId("stackPush").addEventListener("click", function () {
    var record = liveStudents.find(function (student) {
        return String(student.id) === byId("stackStudent").value;
    });
    if (!record) return output("stackOutput", "Choose an approved student to push.", true);
    studentStack.push(record);
    refreshStack();
    output("stackOutput", "Pushed " + recordLabel(record) + " onto the stack.", false);
});

byId("stackPop").addEventListener("click", function () {
    var record = studentStack.pop();
    if (!record) return output("stackOutput", "Stack underflow: there are no records to pop.", true);
    refreshStack();
    output("stackOutput", "Popped " + recordLabel(record) + " (last pushed, first removed).", false);
});

function LinkedQueue(values) {
    this.front = null;
    this.rear = null;
    this.length = 0;
    (values || []).forEach(this.enqueue.bind(this));
}
LinkedQueue.prototype.enqueue = function (value) {
    var node = new LinkedNode(value);
    if (this.rear) this.rear.next = node;
    else this.front = node;
    this.rear = node;
    this.length++;
};
LinkedQueue.prototype.dequeue = function () {
    if (!this.front) return null;
    var value = this.front.value;
    this.front = this.front.next;
    this.length--;
    if (!this.front) this.rear = null;
    return value;
};
LinkedQueue.prototype.toArray = function () {
    var values = [];
    var current = this.front;
    while (current) {
        values.push(current.value);
        current = current.next;
    }
    return values;
};

var applicationQueue = new LinkedQueue(pendingApplications);

function refreshQueue() {
    var queueView = byId("queueView");
    queueView.replaceChildren();
    applicationQueue.toArray().forEach(function (application) {
        var token = document.createElement("span");
        token.className = "data-node";
        token.textContent = application.label;
        queueView.appendChild(token);
    });
    var select = byId("queueApplication");
    select.replaceChildren(createOption("", "Choose pending application"));
    pendingApplications.forEach(function (application) {
        select.appendChild(createOption(application.id, application.label));
    });
    byId("queueDequeue").disabled = applicationQueue.length === 0;
}

byId("queueEnqueue").addEventListener("click", function () {
    var id = byId("queueApplication").value;
    var application = pendingApplications.find(function (item) { return String(item.id) === id; });
    if (!application) return output("queueOutput", "Choose a pending application token.", true);
    if (applicationQueue.toArray().some(function (item) { return Number(item.id) === Number(application.id); })) {
        return output("queueOutput", "That application is already in the simulated queue.", true);
    }
    applicationQueue.enqueue(application);
    refreshQueue();
    output("queueOutput", "Enqueued " + application.label + " at the rear. Admission status was not changed.", false);
});

byId("queueDequeue").addEventListener("click", function () {
    var application = applicationQueue.dequeue();
    if (!application) return output("queueOutput", "Queue is empty.", true);
    refreshQueue();
    output("queueOutput", "Dequeued " + application.label + " from the front (FIFO). This simulation did not process the application.", false);
});

byId("queueReset").addEventListener("click", function () {
    applicationQueue = new LinkedQueue(pendingApplications);
    refreshQueue();
    output("queueOutput", "Reloaded pending application tokens from the database.", false);
});

function toPostfix(expression) {
    var tokens = expression.match(/\d+(?:\.\d+)?|[()+*/-]|\s+|./g) || [];
    var compact = tokens.filter(function (token) { return !/^\s+$/.test(token); });
    var allowed = compact.every(function (token) { return /^\d+(?:\.\d+)?$/.test(token) || /^[()+*/-]$/.test(token); });
    if (!allowed || compact.length === 0) throw new Error("Use numbers, +, -, *, /, and parentheses only.");
    var postfix = [];
    var operators = [];
    var precedence = { "+": 1, "-": 1, "*": 2, "/": 2 };
    var expectNumber = true;
    compact.forEach(function (token) {
        if (/^\d+(?:\.\d+)?$/.test(token)) {
            if (!expectNumber) throw new Error("Add an operator between numbers.");
            postfix.push(Number(token));
            expectNumber = false;
        } else if (token === "(") {
            if (!expectNumber) throw new Error("Add an operator before the opening parenthesis.");
            operators.push(token);
        } else if (token === ")") {
            if (expectNumber) throw new Error("There is an empty or incomplete parenthesis.");
            while (operators.length && operators[operators.length - 1] !== "(") postfix.push(operators.pop());
            if (!operators.length) throw new Error("Closing parenthesis has no matching opening parenthesis.");
            operators.pop();
            expectNumber = false;
        } else {
            if (expectNumber) throw new Error("An operator must follow a number or closing parenthesis.");
            while (operators.length && operators[operators.length - 1] !== "(" &&
                precedence[operators[operators.length - 1]] >= precedence[token]) {
                postfix.push(operators.pop());
            }
            operators.push(token);
            expectNumber = true;
        }
    });
    if (expectNumber) throw new Error("Expression cannot end with an operator.");
    while (operators.length) {
        var operator = operators.pop();
        if (operator === "(") throw new Error("Opening parenthesis has no matching closing parenthesis.");
        postfix.push(operator);
    }
    return postfix;
}

function evaluatePostfix(postfix) {
    var values = [];
    postfix.forEach(function (token) {
        if (typeof token === "number") values.push(token);
        else {
            if (values.length < 2) throw new Error("Invalid expression.");
            var right = values.pop();
            var left = values.pop();
            if (token === "/" && right === 0) throw new Error("Division by zero is not allowed.");
            if (token === "+") values.push(left + right);
            if (token === "-") values.push(left - right);
            if (token === "*") values.push(left * right);
            if (token === "/") values.push(left / right);
        }
    });
    if (values.length !== 1 || !Number.isFinite(values[0])) throw new Error("Invalid arithmetic expression.");
    return values[0];
}

byId("evaluateExpression").addEventListener("click", function () {
    try {
        var postfix = toPostfix(byId("expressionInput").value);
        var value = evaluatePostfix(postfix);
        output("expressionOutput", "Postfix: " + postfix.join(" ") + " · Result: " + value, false);
    } catch (error) {
        output("expressionOutput", error.message, true);
    }
});

function treeNode(student) {
    return { student: student, left: null, right: null };
}

function insertCompleteTree(root, student) {
    var newNode = treeNode(student);
    if (!root) return newNode;
    var queue = [root];
    for (var cursor = 0; cursor < queue.length; cursor++) {
        var current = queue[cursor];
        if (!current.left) {
            current.left = newNode;
            return root;
        }
        queue.push(current.left);
        if (!current.right) {
            current.right = newNode;
            return root;
        }
        queue.push(current.right);
    }
    return root;
}

function insertStudentBst(root, student) {
    if (!root) return treeNode(student);
    var comparison = student.roll_no.localeCompare(root.student.roll_no);
    if (comparison < 0) root.left = insertStudentBst(root.left, student);
    else if (comparison > 0) root.right = insertStudentBst(root.right, student);
    return root;
}

function buildTree(records, insertFunction) {
    var root = null;
    records.forEach(function (student) { root = insertFunction(root, student); });
    return root;
}

function treeDepth(root) {
    return root ? 1 + Math.max(treeDepth(root.left), treeDepth(root.right)) : 0;
}

function visitTree(root, order, result) {
    if (!root) return;
    if (order === "preorder") result.push(root.student);
    visitTree(root.left, order, result);
    if (order === "inorder") result.push(root.student);
    visitTree(root.right, order, result);
    if (order === "postorder") result.push(root.student);
}

function renderTree(svg, root, nodeClass) {
    var namespace = "http://www.w3.org/2000/svg";
    var width = 900;
    var height = Math.max(150, treeDepth(root) * 78 + 24);
    svg.replaceChildren();
    svg.setAttribute("viewBox", "0 0 " + width + " " + height);
    svg.setAttribute("height", String(height));
    if (!root) {
        var empty = document.createElementNS(namespace, "text");
        empty.setAttribute("x", width / 2);
        empty.setAttribute("y", 60);
        empty.setAttribute("class", "tree-label");
        empty.textContent = "No approved students";
        svg.appendChild(empty);
        return;
    }

    function draw(node, x, y, spread) {
        var childY = y + 75;
        if (node.left) {
            var leftX = x - spread;
            var leftLine = document.createElementNS(namespace, "line");
            leftLine.setAttribute("x1", x);
            leftLine.setAttribute("y1", y + 18);
            leftLine.setAttribute("x2", leftX);
            leftLine.setAttribute("y2", childY);
            leftLine.setAttribute("class", "tree-edge");
            svg.appendChild(leftLine);
            draw(node.left, leftX, childY, Math.max(28, spread / 2));
        }
        if (node.right) {
            var rightX = x + spread;
            var rightLine = document.createElementNS(namespace, "line");
            rightLine.setAttribute("x1", x);
            rightLine.setAttribute("y1", y + 18);
            rightLine.setAttribute("x2", rightX);
            rightLine.setAttribute("y2", childY);
            rightLine.setAttribute("class", "tree-edge");
            svg.appendChild(rightLine);
            draw(node.right, rightX, childY, Math.max(28, spread / 2));
        }
        var circle = document.createElementNS(namespace, "circle");
        circle.setAttribute("cx", x);
        circle.setAttribute("cy", y);
        circle.setAttribute("r", 22);
        circle.setAttribute("class", nodeClass);
        svg.appendChild(circle);
        var label = document.createElementNS(namespace, "text");
        label.setAttribute("x", x);
        label.setAttribute("y", y + 4);
        label.setAttribute("class", "tree-label");
        label.textContent = node.student.roll_no;
        svg.appendChild(label);
        var title = document.createElementNS(namespace, "title");
        title.textContent = recordLabel(node.student);
        label.appendChild(title);
    }

    draw(root, width / 2, 32, width / 4);
}

var binaryRoot = buildTree(liveStudents, insertCompleteTree);
var bstRoot = buildTree(liveStudents, insertStudentBst);
byId("treeCount").textContent = String(liveStudents.length);
renderTree(byId("binaryTreeSvg"), binaryRoot, "tree-node");
renderTree(byId("bstSvg"), bstRoot, "bst-node");

var traversals = ["inorder", "preorder", "postorder"];
var traversalNames = { inorder: "Inorder", preorder: "Preorder", postorder: "Postorder" };
var traversalContainer = byId("traversalResults");
traversals.forEach(function (order) {
    var ordered = [];
    visitTree(binaryRoot, order, ordered);
    var row = document.createElement("div");
    row.className = "traversal-row";
    var name = document.createElement("b");
    name.textContent = traversalNames[order];
    var values = document.createElement("span");
    values.textContent = ordered.map(function (student) {
        return student.roll_no;
    }).join(" → ") || "No approved records";
    row.appendChild(name);
    row.appendChild(values);
    traversalContainer.appendChild(row);
});

byId("bstSearch").addEventListener("click", function () {
    var key = byId("bstRollSearch").value.trim();
    if (!key) return output("bstOutput", "Enter a roll number to search.", true);
    var current = bstRoot;
    var path = [];
    while (current) {
        var roll = current.student.roll_no;
        path.push(roll);
        var compare = key.localeCompare(roll);
        if (compare === 0) {
            return output("bstOutput", "Found " + recordLabel(current.student) + ". Comparison path: " + path.join(" → "), false);
        }
        current = compare < 0 ? current.left : current.right;
    }
    output("bstOutput", "Not found among approved student records. Compared: " + (path.join(" → ") || "no nodes"), true);
});

function compareByKey(left, right, key) {
    if (key === "cgpa") return Number(left.cgpa) - Number(right.cgpa);
    return String(left[key] || "").localeCompare(String(right[key] || ""));
}

function insertionSort(records, key) {
    var result = records.slice();
    var comparisons = 0;
    for (var i = 1; i < result.length; i++) {
        var current = result[i];
        var j = i - 1;
        while (j >= 0) {
            comparisons++;
            if (compareByKey(result[j], current, key) <= 0) break;
            result[j + 1] = result[j];
            j--;
        }
        result[j + 1] = current;
    }
    return { records: result, comparisons: comparisons };
}

function mergeSort(records, key) {
    var comparisons = 0;
    function sort(values) {
        if (values.length <= 1) return values;
        var middle = Math.floor(values.length / 2);
        var left = sort(values.slice(0, middle));
        var right = sort(values.slice(middle));
        var merged = [];
        var i = 0;
        var j = 0;
        while (i < left.length && j < right.length) {
            comparisons++;
            if (compareByKey(left[i], right[j], key) <= 0) merged.push(left[i++]);
            else merged.push(right[j++]);
        }
        return merged.concat(left.slice(i), right.slice(j));
    }
    return { records: sort(records.slice()), comparisons: comparisons };
}

function renderStudentTable(records) {
    var body = byId("studentTableBody");
    body.replaceChildren();
    records.slice(0, 50).forEach(function (student) {
        var row = document.createElement("tr");
        [student.roll_no, student.name, student.department, student.semester, Number(student.cgpa).toFixed(2)].forEach(function (value) {
            var cell = document.createElement("td");
            cell.textContent = String(value == null ? "" : value);
            row.appendChild(cell);
        });
        body.appendChild(row);
    });
}

renderStudentTable(liveStudents);

byId("linearSearch").addEventListener("click", function () {
    var query = byId("studentSearch").value.trim().toLocaleLowerCase();
    if (!query) return output("searchSortOutput", "Enter a student name or roll number.", true);
    var inspected = 0;
    var matches = [];
    for (var i = 0; i < liveStudents.length; i++) {
        inspected++;
        var student = liveStudents[i];
        if (student.name.toLocaleLowerCase().indexOf(query) >= 0 ||
            student.roll_no.toLocaleLowerCase().indexOf(query) >= 0) matches.push(student);
    }
    output("searchSortOutput", "Linear search inspected " + inspected + " approved record(s); " +
        matches.length + " match(es): " + matches.map(recordLabel).join(" · "), matches.length === 0);
});

byId("binarySearch").addEventListener("click", function () {
    var key = byId("studentSearch").value.trim();
    if (!key) return output("searchSortOutput", "Enter an exact roll number for binary search.", true);
    var sorted = liveStudents.slice().sort(function (a, b) { return a.roll_no.localeCompare(b.roll_no); });
    var low = 0;
    var high = sorted.length - 1;
    var comparisons = 0;
    while (low <= high) {
        var middle = Math.floor((low + high) / 2);
        comparisons++;
        var comparison = sorted[middle].roll_no.localeCompare(key);
        if (comparison === 0) {
            return output("searchSortOutput", "Binary search found " + recordLabel(sorted[middle]) +
                " after " + comparisons + " comparison(s) on sorted roll numbers.", false);
        }
        if (comparison < 0) low = middle + 1;
        else high = middle - 1;
    }
    output("searchSortOutput", "Binary search did not find that exact roll number (" + comparisons + " comparison(s)).", true);
});

byId("insertionSort").addEventListener("click", function () {
    var result = insertionSort(liveStudents, byId("sortKey").value);
    renderStudentTable(result.records);
    output("searchSortOutput", "Insertion sort ordered " + result.records.length + " approved record(s) using " +
        result.comparisons + " comparison(s).", false);
});

byId("mergeSort").addEventListener("click", function () {
    var result = mergeSort(liveStudents, byId("sortKey").value);
    renderStudentTable(result.records);
    output("searchSortOutput", "Merge sort ordered " + result.records.length + " approved record(s) using " +
        result.comparisons + " comparison(s).", false);
});

var graph = {};
liveStudents.forEach(function (student) { graph[student.id] = []; });
savedEdges.forEach(function (edge) {
    if (graph[edge.from] && graph[edge.to] && graph[edge.from].indexOf(edge.to) < 0) {
        graph[edge.from].push(edge.to);
        graph[edge.to].push(edge.from);
    }
});
Object.keys(graph).forEach(function (id) {
    graph[id].sort(function (a, b) { return a - b; });
});

var listBody = byId("adjacencyList");
var vertices = liveStudents.length > 20 ? liveStudents.slice(0, 20) : liveStudents;
vertices.forEach(function (student) {
    var row = document.createElement("tr");
    var name = document.createElement("td");
    name.textContent = recordLabel(student);
    var neighbors = document.createElement("td");
    neighbors.textContent = (graph[student.id] || []).map(function (id) {
        var found = liveStudents.find(function (candidate) { return candidate.id === id; });
        return found ? found.roll_no : "";
    }).filter(Boolean).join(", ") || "(no saved links)";
    row.appendChild(name);
    row.appendChild(neighbors);
    listBody.appendChild(row);
});
byId("matrixCaption").textContent = liveStudents.length > 20 ? "(first 20 of " + liveStudents.length + ")" : "";

var matrix = document.createElement("table");
var matrixHead = document.createElement("tr");
var corner = document.createElement("th");
corner.textContent = "Roll";
matrixHead.appendChild(corner);
vertices.forEach(function (student) {
    var cell = document.createElement("th");
    cell.textContent = student.roll_no;
    matrixHead.appendChild(cell);
});
matrix.appendChild(matrixHead);
vertices.forEach(function (rowStudent) {
    var row = document.createElement("tr");
    var heading = document.createElement("th");
    heading.textContent = rowStudent.roll_no;
    row.appendChild(heading);
    vertices.forEach(function (columnStudent) {
        var cell = document.createElement("td");
        cell.textContent = (graph[rowStudent.id] || []).indexOf(columnStudent.id) >= 0 ? "1" : "0";
        row.appendChild(cell);
    });
    matrix.appendChild(row);
});
byId("adjacencyMatrix").appendChild(matrix);

var bfsSelect = byId("bfsStart");
bfsSelect.replaceChildren(createOption("", "Choose approved student"));
liveStudents.forEach(function (student) {
    bfsSelect.appendChild(createOption(student.id, recordLabel(student)));
});

byId("runBfs").addEventListener("click", function () {
    var start = Number(bfsSelect.value);
    if (!start || !Object.prototype.hasOwnProperty.call(graph, start)) {
        return output("bfsOutput", "Choose an approved student as the starting vertex.", true);
    }
    var visited = new Set([start]);
    var queue = [start];
    var order = [];
    for (var head = 0; head < queue.length; head++) {
        var vertex = queue[head];
        order.push(vertex);
        graph[vertex].forEach(function (neighbor) {
            if (!visited.has(neighbor)) {
                visited.add(neighbor);
                queue.push(neighbor);
            }
        });
    }
    var labels = order.map(function (id) {
        var record = liveStudents.find(function (student) { return student.id === id; });
        return record ? record.roll_no : String(id);
    });
    output("bfsOutput", "BFS order: " + (labels.join(" → ") || "none") +
        (order.length < liveStudents.length ? " · " + (liveStudents.length - order.length) +
        " approved student(s) are not reachable from this start." : ""), false);
});

function refreshTeacherTable() {
    var body = byId("teacherTableBody");
    body.replaceChildren();
    liveTeachers.forEach(function (teacher) {
        var row = document.createElement("tr");
        [teacher.code, teacher.name, teacher.department, teacher.designation || ""].forEach(function (value) {
            var cell = document.createElement("td");
            cell.textContent = String(value);
            row.appendChild(cell);
        });
        body.appendChild(row);
    });
}

refreshTeacherTable();

byId("runGreedy").addEventListener("click", function () {
    if (!liveStudents.length) return output("greedyOutput", "No approved student records are available.", true);
    if (!liveTeachers.length) return output("greedyOutput", "No active teacher records are available.", true);
    var loads = liveTeachers.map(function (teacher) { return { teacher: teacher, students: [] }; });
    liveStudents.forEach(function (student) {
        var sameDepartment = loads.filter(function (entry) {
            return String(entry.teacher.department).toLowerCase() === String(student.department).toLowerCase();
        });
        var choices = sameDepartment.length ? sameDepartment : loads;
        choices.sort(function (a, b) {
            return a.students.length - b.students.length || String(a.teacher.name).localeCompare(String(b.teacher.name));
        });
        choices[0].students.push(student);
    });
    var assignments = [];
    loads.forEach(function (entry) {
        entry.students.forEach(function (student) {
            assignments.push(student.roll_no + " → " + entry.teacher.name);
        });
    });
    output("greedyOutput", assignments.length
        ? "Greedy balanced plan (not saved): " + assignments.join(" · ")
        : "No assignments could be planned.", assignments.length === 0);
});

byId("runDynamic").addEventListener("click", function () {
    var budget = Number(byId("tutoringHours").value);
    if (!Number.isInteger(budget) || budget < 1 || budget > 50) {
        return output("dynamicOutput", "Choose an integer tutoring-hour budget from 1 to 50.", true);
    }
    if (!liveStudents.length) return output("dynamicOutput", "No approved students are available for planning.", true);
    var values = Array.from({ length: liveStudents.length + 1 }, function () {
        return new Array(budget + 1).fill(0);
    });
    var weights = liveStudents.map(function (student) {
        return Math.max(1, Math.min(10, 11 - Math.floor(Number(student.cgpa) || 0)));
    });
    var needs = liveStudents.map(function (student) {
        return Math.max(1, Math.round((10 - (Number(student.cgpa) || 0)) * 10));
    });
    for (var i = 1; i <= liveStudents.length; i++) {
        for (var capacity = 0; capacity <= budget; capacity++) {
            values[i][capacity] = values[i - 1][capacity];
            var studentIndex = i - 1;
            if (weights[studentIndex] <= capacity) {
                var candidate = values[i - 1][capacity - weights[studentIndex]] + needs[studentIndex];
                if (candidate > values[i][capacity]) {
                    values[i][capacity] = candidate;
                }
            }
        }
    }
    var chosen = [];
    var remaining = budget;
    for (var index = liveStudents.length; index > 0; index--) {
        if (values[index][remaining] > values[index - 1][remaining]) {
            var selectedIndex = index - 1;
            chosen.push(liveStudents[selectedIndex]);
            remaining -= weights[selectedIndex];
        }
    }
    chosen.reverse();
    var description = chosen.map(function (student) {
        var index = liveStudents.indexOf(student);
        return student.roll_no + " (" + weights[index] + "h, need " + needs[index] + ")";
    });
    output("dynamicOutput", chosen.length
        ? "Best support plan within " + budget + " hour(s): " + description.join(" · ") +
          " · total need score " + values[liveStudents.length][budget] + ". Not saved."
        : "No student fits this tutoring-hour budget.", chosen.length === 0);
});

byId("runBacktracking").addEventListener("click", function () {
    if (!liveStudents.length) return output("backtrackingOutput", "No approved student records are available.", true);
    if (!liveTeachers.length) return output("backtrackingOutput", "No active teachers are available.", true);
    var capacity = Math.ceil(liveStudents.length / liveTeachers.length);
    var loads = liveTeachers.map(function () { return 0; });
    var semesterLoads = liveTeachers.map(function () { return new Set(); });
    var assignment = new Array(liveStudents.length).fill(-1);

    function place(index) {
        if (index === liveStudents.length) return true;
        var student = liveStudents[index];
        var choices = liveTeachers.map(function (teacher, teacherIndex) {
            return { teacher: teacher, index: teacherIndex };
        }).sort(function (a, b) {
            var aMatch = String(a.teacher.department).toLowerCase() === String(student.department).toLowerCase();
            var bMatch = String(b.teacher.department).toLowerCase() === String(student.department).toLowerCase();
            return Number(bMatch) - Number(aMatch) || loads[a.index] - loads[b.index];
        });
        for (var i = 0; i < choices.length; i++) {
            var teacherIndex = choices[i].index;
            var semester = String(student.semester || "");
            if (loads[teacherIndex] >= capacity || semesterLoads[teacherIndex].has(semester)) continue;
            loads[teacherIndex]++;
            semesterLoads[teacherIndex].add(semester);
            assignment[index] = teacherIndex;
            if (place(index + 1)) return true;
            loads[teacherIndex]--;
            semesterLoads[teacherIndex].delete(semester);
            assignment[index] = -1;
        }
        return false;
    }

    if (!place(0)) {
        return output("backtrackingOutput", "No capacity-safe mentor assignment exists with the current active teachers.", true);
    }
    var pairs = liveStudents.map(function (student, index) {
        return student.roll_no + " → " + liveTeachers[assignment[index]].name;
    });
    output("backtrackingOutput", "Found a valid assignment (max " + capacity + " student(s) per teacher; not saved): " +
        pairs.join(" · "), false);
});

refreshLinkedList();
refreshStack();
refreshQueue();
output("linkedListOutput", linkedStudents.length + " approved student record(s) loaded from the database.", false);
output("queueOutput", pendingApplications.length + " pending application token(s) loaded; queue operations are simulations.", false);
output("expressionOutput", "Ready. Example result: 18.", false);
output("searchSortOutput", liveStudents.length + " approved student record(s) loaded from the database.", false);
output("bfsOutput", "BFS traverses saved relationship edges only; students with no links form separate components.", false);
</script>
</body>
</html>
