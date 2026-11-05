<?php

if (PHP_SAPI !== "cli")
{
    http_response_code(404);
    exit("Not found.");
}

require_once __DIR__ . "/config/database.php";

$demoPassword = getenv("COLLEGE_DEMO_PASSWORD");
if (!is_string($demoPassword) || strlen($demoPassword) < 12)
{
    fwrite(STDERR, "Set COLLEGE_DEMO_PASSWORD to a password of at least 12 characters before seeding." . PHP_EOL);
    exit(1);
}

$studentPassword = password_hash($demoPassword, PASSWORD_DEFAULT);
$teacherPassword = password_hash($demoPassword, PASSWORD_DEFAULT);

$students = array(
    array("DS26-001", "Aarav Nair", "CSE", "1", "0000000001", 8.71, "Accepted"),
    array("DS26-002", "Diya Sharma", "CSE", "2", "0000000002", 9.14, "Accepted"),
    array("DS26-003", "Ishan Rao", "CSE", "3", "0000000003", 7.82, "Accepted"),
    array("DS26-004", "Mira Das", "CSE", "4", "0000000004", 8.46, "Accepted"),
    array("DS26-005", "Neel Menon", "CSE", "1", "0000000005", 9.02, "Accepted"),
    array("DS26-006", "Riya Shah", "CSE", "1", "0000000006", 7.54, "Pending"),
    array("DS26-007", "Kabir Sen", "CSE", "2", "0000000007", 8.11, "Pending"),
    array("DS26-008", "Tara Iyer", "CSE", "3", "0000000008", 7.96, "Pending"),
    array("DS26-009", "Dev Patel", "CSE", "4", "0000000009", 8.32, "Pending"),
    array("DS26-010", "Anya Roy", "CSE", "1", "0000000010", 8.04, "Pending")
);

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
if (!$createConnections)
{
    fwrite(STDERR, "Unable to prepare student network table: " . $conn->error . PHP_EOL);
    exit(1);
}

$teachers = array(
    array("DS-TEACH-001", "Sahana Mehta", "sahana.mehta@example.test", "CSE", "Assistant Professor", "0000000101"),
    array("DS-TEACH-002", "Anil Kumar", "anil.kumar@example.test", "IT", "Associate Professor", "0000000102"),
    array("DS-TEACH-003", "Leena Joseph", "leena.joseph@example.test", "MCA", "Assistant Professor", "0000000103")
);

$adminResult = $conn->query("SELECT id FROM admins ORDER BY id ASC LIMIT 1");
$verifiedBy = null;
if ($adminResult && $adminResult->num_rows > 0)
{
    $adminRow = $adminResult->fetch_assoc();
    $verifiedBy = (int)$adminRow["id"];
}

$conn->begin_transaction();

try
{
    $studentIds = array();
    $studentInsert = $conn->prepare(
        "INSERT INTO students
            (roll_no, name, department, semester, phone, cgpa, password, role)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'student')"
    );

    if (!$studentInsert)
    {
        throw new RuntimeException("Unable to prepare student insert: " . $conn->error);
    }

    foreach ($students as $student)
    {
        list($rollNo, $name, $department, $semester, $phone, $cgpa, $status) = $student;
        $lookup = $conn->prepare("SELECT id, name FROM students WHERE roll_no = ? LIMIT 1");
        if (!$lookup)
        {
            throw new RuntimeException("Unable to check sample student: " . $conn->error);
        }
        $lookup->bind_param("s", $rollNo);
        if (!$lookup->execute())
        {
            throw new RuntimeException("Unable to check sample student: " . $lookup->error);
        }
        $lookupResult = $lookup->get_result();
        $existing = $lookupResult ? $lookupResult->fetch_assoc() : null;
        $lookup->close();

        if ($existing)
        {
            if ($existing["name"] !== $name)
            {
                throw new RuntimeException("Roll number collision for " . $rollNo . "; no existing record was changed.");
            }
            $studentId = (int)$existing["id"];
        }
        else
        {
            $studentInsert->bind_param(
                "sssssds",
                $rollNo,
                $name,
                $department,
                $semester,
                $phone,
                $cgpa,
                $studentPassword
            );
            if (!$studentInsert->execute())
            {
                throw new RuntimeException("Unable to add sample student " . $rollNo . ": " . $studentInsert->error);
            }
            $studentId = (int)$studentInsert->insert_id;
        }

        $studentIds[$rollNo] = $studentId;

        $applicationNumber = "DS26-APP-" . substr($rollNo, -3);
        $applicationLookup = $conn->prepare(
            "SELECT id, student_id, application_status
             FROM student_applications
             WHERE application_number = ?
             ORDER BY id ASC LIMIT 1"
        );
        if (!$applicationLookup)
        {
            throw new RuntimeException("Unable to check sample application: " . $conn->error);
        }
        $applicationLookup->bind_param("s", $applicationNumber);
        if (!$applicationLookup->execute())
        {
            throw new RuntimeException("Unable to check sample application: " . $applicationLookup->error);
        }
        $applicationResult = $applicationLookup->get_result();
        $application = $applicationResult ? $applicationResult->fetch_assoc() : null;
        $applicationLookup->close();

        if ($application)
        {
            if ((int)$application["student_id"] !== $studentId || $application["application_status"] !== $status)
            {
                throw new RuntimeException("Sample application mismatch for " . $applicationNumber . "; no existing record was changed.");
            }
            $applicationId = (int)$application["id"];
        }
        else
        {
            $applicationType = "New Admission";
            $course = "BCA";
            $percentage = "85.00";
            $remarks = ($status === "Accepted")
                ? "Approved sample application for the data structures demonstration."
                : "Sample application awaiting admin review.";
            $verifiedAt = ($status === "Accepted") ? date("Y-m-d H:i:s") : null;

            $applicationInsert = $conn->prepare(
                "INSERT INTO student_applications
                    (student_id, application_type, full_name, phone, course, department,
                     application_status, admin_remarks, verified_at, verified_by,
                     program_type, course_name, previous_qualification,
                     previous_institution, previous_percentage, application_number)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'UG', ?, 'Higher Secondary', 'Demo College', ?, ?)"
            );
            if (!$applicationInsert)
            {
                throw new RuntimeException("Unable to prepare sample application insert: " . $conn->error);
            }
            $applicationInsert->bind_param(
                "issssssssisss",
                $studentId,
                $applicationType,
                $name,
                $phone,
                $course,
                $department,
                $status,
                $remarks,
                $verifiedAt,
                $verifiedBy,
                $course,
                $percentage,
                $applicationNumber
            );
            if (!$applicationInsert->execute())
            {
                throw new RuntimeException("Unable to add sample application " . $applicationNumber . ": " . $applicationInsert->error);
            }
            $applicationId = (int)$applicationInsert->insert_id;
            $applicationInsert->close();
        }

        if ($status === "Accepted")
        {
            $processingLookup = $conn->prepare(
                "SELECT id FROM admission_processing WHERE application_id = ? LIMIT 1"
            );
            if (!$processingLookup)
            {
                throw new RuntimeException("Unable to check admission processing: " . $conn->error);
            }
            $processingLookup->bind_param("i", $applicationId);
            if (!$processingLookup->execute())
            {
                throw new RuntimeException("Unable to check admission processing: " . $processingLookup->error);
            }
            $processingResult = $processingLookup->get_result();
            $hasProcessing = $processingResult && $processingResult->num_rows > 0;
            $processingLookup->close();

            if (!$hasProcessing)
            {
                $processingInsert = $conn->prepare(
                    "INSERT INTO admission_processing
                        (student_id, application_id, application_status, admission_status)
                     VALUES (?, ?, 'Accepted', 'Pending')"
                );
                if (!$processingInsert)
                {
                    throw new RuntimeException("Unable to prepare admission processing insert: " . $conn->error);
                }
                $processingInsert->bind_param("ii", $studentId, $applicationId);
                if (!$processingInsert->execute())
                {
                    throw new RuntimeException("Unable to add admission processing record: " . $processingInsert->error);
                }
                $processingInsert->close();
            }
        }
    }
    $studentInsert->close();

    $teacherInsert = $conn->prepare(
        "INSERT INTO teachers
            (teacher_id, teacher_code, name, email, password, department, designation, phone, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE')"
    );
    if (!$teacherInsert)
    {
        throw new RuntimeException("Unable to prepare teacher insert: " . $conn->error);
    }

    foreach ($teachers as $teacher)
    {
        list($code, $name, $email, $department, $designation, $phone) = $teacher;
        $lookup = $conn->prepare(
            "SELECT id, name FROM teachers WHERE teacher_id = ? OR email = ? LIMIT 1"
        );
        if (!$lookup)
        {
            throw new RuntimeException("Unable to check sample teacher: " . $conn->error);
        }
        $lookup->bind_param("ss", $code, $email);
        if (!$lookup->execute())
        {
            throw new RuntimeException("Unable to check sample teacher: " . $lookup->error);
        }
        $lookupResult = $lookup->get_result();
        $existing = $lookupResult ? $lookupResult->fetch_assoc() : null;
        $lookup->close();

        if ($existing)
        {
            if ($existing["name"] !== $name)
            {
                throw new RuntimeException("Teacher code or email collision for " . $code . "; no existing record was changed.");
            }
            continue;
        }

        $teacherInsert->bind_param(
            "ssssssss",
            $code,
            $code,
            $name,
            $email,
            $teacherPassword,
            $department,
            $designation,
            $phone
        );
        if (!$teacherInsert->execute())
        {
            throw new RuntimeException("Unable to add sample teacher " . $code . ": " . $teacherInsert->error);
        }
    }
    $teacherInsert->close();

    $sampleEdges = array(
        array("DS26-001", "DS26-002"),
        array("DS26-002", "DS26-003"),
        array("DS26-003", "DS26-004"),
        array("DS26-004", "DS26-005"),
        array("DS26-001", "DS26-005")
    );
    $edgeInsert = $conn->prepare(
        "INSERT INTO student_connections (student1_id, student2_id)
         SELECT ?, ?
         WHERE NOT EXISTS (
             SELECT id FROM student_connections
             WHERE (student1_id = ? AND student2_id = ?)
                OR (student1_id = ? AND student2_id = ?)
         )"
    );
    if (!$edgeInsert)
    {
        throw new RuntimeException("Unable to prepare sample graph relationships: " . $conn->error);
    }

    foreach ($sampleEdges as $edge)
    {
        $firstId = $studentIds[$edge[0]];
        $secondId = $studentIds[$edge[1]];
        $edgeInsert->bind_param(
            "iiiiii",
            $firstId,
            $secondId,
            $firstId,
            $secondId,
            $secondId,
            $firstId
        );
        if (!$edgeInsert->execute())
        {
            throw new RuntimeException("Unable to add sample student relationship: " . $edgeInsert->error);
        }
    }
    $edgeInsert->close();

    $conn->commit();
    echo "Sample records are ready.\n";
    echo "Sample student login password: " . $demoPassword . "\n";
    echo "Sample teacher login password: " . $demoPassword . "\n";
    echo "Use student roll numbers or teacher email/teacher code as the username.\n";
}
catch (Throwable $error)
{
    $conn->rollback();
    fwrite(STDERR, "Demo seed failed; database transaction rolled back: " . $error->getMessage() . PHP_EOL);
    exit(1);
}

$conn->close();
