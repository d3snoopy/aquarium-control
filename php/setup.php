<?php

// setup.php

set_include_path("template:model");

include 'model.php';
include 'setupFn.php';

// Connect to the db.
$mysqli = \aqctrl\db_connect();

$title = "Setup";

// Do my content here
if(!$mysqli) {
    //Complain about not being able to connect and give up.
    include 'header.php';
    echo "<p>Could not connect to dB, check /etc/aqctrl.ini</p>";
    echo "<p>Or, try going to the <a href=quickstart.php>quickstart page.</a></p>";
    include 'footer.php';
    return;
}

echo "<h1>Setup</h1>";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    //Do the form fcn
    \aqctrl\setupRtn($mysqli, $_POST);

    header("Location: setup.php");

} else {
    include 'header.php';
    //Show the form
    \aqctrl\setupForm($mysqli, "setup.php");
    include 'footer.php';
}

