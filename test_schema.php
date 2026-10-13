<?php
require_once "db_connect.php";

$res = $conn->query("SHOW COLUMNS FROM loans");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
