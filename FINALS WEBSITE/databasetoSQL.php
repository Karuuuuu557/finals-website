
<?php
//database to sql 56 cafe
$conn = new mysqli('mysql-1d69cd83-umak-e978.i.aivencloud.com', 'avnadmin', 'AVNS_vZ6RVEWU-0a2Jwp-Zzz', 'main',19494);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>