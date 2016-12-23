<?php

set_include_path("template:model");

$title = "Quickstart";

// Add the header & model info
include 'header.php';
include 'model.php';

// Do my content here
$sqconnect = \aqctrl\db_test();

// Test for an "okay" confirmation
if($_SERVER['REQUEST_METHOD'] == "GET" && $sqconnect) {
    //This is our initial hit on the page; if the db exists we need to warn
    // & double check that the user wants to completely reset the settings.

    ?>

    <form action="quickstart.php" method="post">
        Initiating a quickstart will erase ALL of your settings and saved data, COMPLETELY resetting EVERYTHING.<br>
        Are you sure that you REALLY want to do this?<br>
        <input type="checkbox" name="okay" />Yes, I'm sure I want to do this.<br>
        <input type="submit" name="submit" value="Okay" />
        <input type="submit" name="cancel" value="Cancel" />
    </form>
        
    <?php
    include 'footer.php';
    return;
}


if($_SERVER['REQUEST_METHOD'] == "POST" && (!$_POST['okay'] || isset($_POST['cancel']))) {
    //The user didn't confirm
    header("Location: index.php");
    return;
}

// We got the okay to really clear the db and restart

// Create the db
echo "<p>";
\aqctrl\db_create();


echo "</p>";


// End do the footer
include 'footer.php';
?>

