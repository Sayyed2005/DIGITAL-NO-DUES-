<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "config.php";

if(!$conn)
{
    die("❌ Database Connection Failed: " . mysqli_connect_error());
}
else
{
    echo "✅ Database Connected Successfully<br>";
}

// Test query
$result = mysqli_query($conn, "SHOW TABLES");

if(!$result)
{
    echo "❌ Query Failed: " . mysqli_error($conn);
}
else
{
    echo "✅ Tables Found:<br>";

    while($row = mysqli_fetch_array($result))
    {
        echo $row[0] . "<br>";
    }
}
?>