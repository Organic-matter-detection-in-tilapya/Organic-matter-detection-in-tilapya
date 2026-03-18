<?php
session_start();
require_once "../config/config.php";

if($_SESSION['role'] != 'staff'){
    header("Location: login.php");
}

if(isset($_POST['save'])){

    $temp = $_POST['temperature'];
    $ph = $_POST['ph'];
    $organic = $_POST['organic'];

    $pond = $_SESSION['assigned_pond'];
    $user = $_SESSION['user_id'];

    $sql = "INSERT INTO readings
            (pond_name, temperature, ph, organic, staff_id)
            VALUES ('$pond','$temp','$ph','$organic','$user')";

    mysqli_query($conn,$sql);

    echo "<script>alert('Simulated Data Inserted');</script>";
}
?>

<form method="POST">
Temperature:
<input type="number" step="0.1" name="temperature"><br>

pH:
<input type="number" step="0.1" name="ph"><br>

Organic:
<input type="number" step="0.1" name="organic"><br>

<button name="save">Simulate Insert</button>
</form>