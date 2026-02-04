<?php 
    $hostname = "localhost";
    $dbuser = "root";
    $dbpass = "";
    $dbname = "hrms_paluan";
    $conn = mysqli_connect($hostname, $dbuser, $dbpass, $dbname);
    if (!$conn){
        die("Something went wrong!");
    }
?>
