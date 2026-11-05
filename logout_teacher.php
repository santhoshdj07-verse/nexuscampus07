<?php

session_start();

unset($_SESSION["teacher_id"]);
unset($_SESSION["teacher_login_id"]);
unset($_SESSION["teacher_name"]);
unset($_SESSION["teacher_email"]);
unset($_SESSION["teacher_department"]);

header(
    "Location: teacher_login.php"
);

exit();

?>