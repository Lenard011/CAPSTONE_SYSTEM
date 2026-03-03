<?php 
    $hostname = "localhost";
    $dbuser = "u420482914_paluan_hrms";
    $dbpass = "Hrms_Paluan01";
    $dbname = "u420482914_hrms_paluan";
    $conn = mysqli_connect($hostname, $dbuser, $dbpass, $dbname);
    if (!$conn){
        die("Something went wrong!");
    }
?>
