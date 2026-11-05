<?php

session_start();

$hadStudent = isset($_SESSION["student_id"]);
$hadAdmin = isset($_SESSION["admin_id"]);

session_unset();
session_destroy();

if ($hadStudent && !$hadAdmin)
{
    header("Location: login.php?role=student");
}
else
{
    header("Location: login.php");
}

exit();

?>
