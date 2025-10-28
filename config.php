<?php
// config.php
// Ubah nilai sesuai kredensial MySQL kamu
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'expert_gizi';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    die("Connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");
?>
