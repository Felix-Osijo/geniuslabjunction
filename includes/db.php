<?php

$host = "localhost";
$username = "root";
$dbname = "genius_lab";
$password = "";



$conn = new mysqli(
    $host,
    $username,
    $password,
    $dbname
);



if ($conn->connect_error) {

    die(
        "Database Connection Failed: "
        . $conn->connect_error
    );
}

?>
