<?php

require_once __DIR__ . "/config/database.php";

function cms_table_exists($conn, $tableName)
{
    $tableName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return ($result && $result->num_rows > 0);
}

function cms_column_exists($conn, $tableName, $columnName)
{
    if (!cms_table_exists($conn, $tableName))
    {
        return false;
    }

    $tableName = $conn->real_escape_string($tableName);
    $columnName = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
    return ($result && $result->num_rows > 0);
}

function cms_ensure_column($conn, $tableName, $columnName, $definition)
{
    if (!cms_table_exists($conn, $tableName))
    {
        return;
    }

    if (!cms_column_exists($conn, $tableName, $columnName))
    {
        $conn->query("ALTER TABLE `$tableName` ADD COLUMN $definition");
    }
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

cms_ensure_column($conn, "admins", "role", "role VARCHAR(20) NOT NULL DEFAULT 'admin'");

$conn->query(
    "CREATE TABLE IF NOT EXISTS students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        roll_no VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(20) NOT NULL,
        department VARCHAR(3) NOT NULL,
        semester VARCHAR(10) DEFAULT NULL,
        phone VARCHAR(10) DEFAULT NULL,
        cgpa DECIMAL(4,2) DEFAULT NULL,
        password VARCHAR(255) DEFAULT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'student',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

cms_ensure_column($conn, "students", "password", "password VARCHAR(255) DEFAULT NULL");
cms_ensure_column($conn, "students", "role", "role VARCHAR(20) NOT NULL DEFAULT 'student'");

$conn->query(
    "CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_code VARCHAR(3) NOT NULL UNIQUE,
        department_name VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$conn->query(
    "CREATE TABLE IF NOT EXISTS student_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        application_type VARCHAR(20) DEFAULT NULL,
        full_name VARCHAR(100) DEFAULT NULL,
        email VARCHAR(150) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        course VARCHAR(100) DEFAULT NULL,
        department VARCHAR(100) DEFAULT NULL,
        application_status VARCHAR(30) NOT NULL DEFAULT 'Pending',
        admin_remarks TEXT DEFAULT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        verified_at DATETIME DEFAULT NULL,
        verified_by INT DEFAULT NULL,
        program_type VARCHAR(10) DEFAULT NULL,
        course_name VARCHAR(120) DEFAULT NULL,
        previous_qualification VARCHAR(120) DEFAULT NULL,
        previous_institution VARCHAR(150) DEFAULT NULL,
        previous_percentage VARCHAR(20) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        city VARCHAR(80) DEFAULT NULL,
        state VARCHAR(80) DEFAULT NULL,
        pincode VARCHAR(10) DEFAULT NULL,
        application_number VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

cms_ensure_column($conn, "student_applications", "application_status", "application_status VARCHAR(30) NOT NULL DEFAULT 'Pending'");
cms_ensure_column($conn, "student_applications", "admin_remarks", "admin_remarks TEXT");
cms_ensure_column($conn, "student_applications", "verified_at", "verified_at DATETIME DEFAULT NULL");
cms_ensure_column($conn, "student_applications", "verified_by", "verified_by INT DEFAULT NULL");
cms_ensure_column($conn, "student_applications", "program_type", "program_type VARCHAR(10) DEFAULT NULL");
cms_ensure_column($conn, "student_applications", "course_name", "course_name VARCHAR(120) DEFAULT NULL");
cms_ensure_column($conn, "student_applications", "previous_qualification", "previous_qualification VARCHAR(120) DEFAULT NULL");
cms_ensure_column($conn, "student_applications", "previous_institution", "previous_institution VARCHAR(150) DEFAULT NULL");
cms_ensure_column($conn, "student_applications", "previous_percentage", "previous_percentage VARCHAR(20) DEFAULT NULL");
cms_ensure_column($conn, "student_applications", "address", "address TEXT");
cms_ensure_column($conn, "student_applications", "city", "city VARCHAR(80) DEFAULT NULL");
cms_ensure_column($conn, "student_applications", "state", "state VARCHAR(80) DEFAULT NULL");
cms_ensure_column($conn, "student_applications", "pincode", "pincode VARCHAR(10) DEFAULT NULL");
cms_ensure_column($conn, "student_applications", "application_number", "application_number VARCHAR(50) DEFAULT NULL");

$conn->query(
    "CREATE TABLE IF NOT EXISTS student_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        application_id INT DEFAULT NULL,
        document_type VARCHAR(50) NOT NULL,
        document_name VARCHAR(255) NOT NULL,
        document_path VARCHAR(500) NOT NULL,
        verification_status VARCHAR(30) DEFAULT 'Pending',
        admin_remarks TEXT DEFAULT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        verified_at TIMESTAMP DEFAULT NULL,
        remarks TEXT DEFAULT NULL,
        file_name VARCHAR(255) DEFAULT NULL,
        file_path VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

cms_ensure_column($conn, "student_documents", "application_id", "application_id INT DEFAULT NULL");
cms_ensure_column($conn, "student_documents", "verification_status", "verification_status VARCHAR(30) NOT NULL DEFAULT 'Pending'");
cms_ensure_column($conn, "student_documents", "admin_remarks", "admin_remarks TEXT");
cms_ensure_column($conn, "student_documents", "uploaded_at", "uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
cms_ensure_column($conn, "student_documents", "verified_at", "verified_at DATETIME DEFAULT NULL");
cms_ensure_column($conn, "student_documents", "remarks", "remarks TEXT");
cms_ensure_column($conn, "student_documents", "file_name", "file_name VARCHAR(255) DEFAULT NULL");
cms_ensure_column($conn, "student_documents", "file_path", "file_path VARCHAR(255) DEFAULT NULL");

$conn->query(
    "CREATE TABLE IF NOT EXISTS admission_processing (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        application_id INT DEFAULT NULL,
        application_status VARCHAR(30) DEFAULT 'Pending',
        document_status VARCHAR(30) DEFAULT 'Pending',
        admission_status VARCHAR(30) DEFAULT 'Pending',
        admin_remarks TEXT DEFAULT NULL,
        processed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        admission_number VARCHAR(50) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

cms_ensure_column($conn, "admission_processing", "admission_status", "admission_status VARCHAR(30) NOT NULL DEFAULT 'Pending'");
cms_ensure_column($conn, "admission_processing", "admission_number", "admission_number VARCHAR(50) DEFAULT NULL");
cms_ensure_column($conn, "admission_processing", "admin_remarks", "admin_remarks TEXT");
cms_ensure_column($conn, "admission_processing", "processed_at", "processed_at TIMESTAMP NULL DEFAULT NULL");
cms_ensure_column($conn, "admission_processing", "application_id", "application_id INT DEFAULT NULL");

$conn->query(
    "CREATE TABLE IF NOT EXISTS student_connections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student1_id INT NOT NULL,
        student2_id INT NOT NULL,
        UNIQUE(student1_id, student2_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

header("Location: index.php");
exit();
