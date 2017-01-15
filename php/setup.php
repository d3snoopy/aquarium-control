<?php

set_include_path("template:model");

include 'model.php';
include 'setupFn.php';

// Test the dB connection
$sqconnect = \aqctrl\db_test();

$title = "Setup";

// Add the header
include 'header.php';

// Do my content here
if(!$sqconnect) {
    //Complain about not being able to connect and give up.
    echo "<p>Could not connect to dB, check mysql.ini</p>";
    echo "<p>Or, try going to the <a href=quickstart.php>quickstart page.</a>";
    return;
}

include 'mysql.ini';

$mysqli = mysqli_connect($aqctrl_sql_hostname, $aqctrl_sql_user,
    $aqctrl_sql_pass, $aqctrl_sql_db);

echo "<h1>Setup</h1>";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    //Do the form fcn
    \aqctrl\setupRtn($mysqli, $_POST);

    header("Location: setup.php");

} else {
    //Show the form
    \aqctrl\setupForm($mysqli, "setup.php");
}


include 'footer.php';

