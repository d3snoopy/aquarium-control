<?php

set_include_path("template:model");

include 'model.php';

// Test the DB connection
$sqconnect = \aqctrl\db_test();

// Test to see if we should reset the db
if(($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['okay']) && isset($_POST['reset'])) || !$sqconnect) {
    //Reset the db
    \aqctrl\db_create();
    header("Location: quickstart.php");
}

//Set the quickstart step number
include 'mysql.ini';

$mysqli = mysqli_connect($aqctrl_sql_hostname, $aqctrl_sql_user,
    $aqctrl_sql_pass, $aqctrl_sql_db);

$res = mysqli_query($mysqli, "SELECT step FROM quickstart WHERE id = 1");

$stepNum = mysqli_fetch_row($res)[0];

$title = "Quickstart step " . $stepNum;


// Add the header info
include 'header.php';

// Do our quickstart step content; essentially include the correct stuff
// Separate: By POST vs GET
// POST: read the step from the form data & do our db actions
// GET: read the step from the db and load the correct form the fill out
// In both cases: Do a quickstart dressing and pull another function to actually create the form, do the db work, etc.

switch ($stepNum) {

    case 1:
        $stepTitle = "Setup";
        $stepForm = "\aqctrl\setupForm";
        $stepRtn = "\aqctrl\setupRtn";
        $stepIncl = 'setupFn.php';
        break;
    case 2:
        $stepTitle = "Configure";
        $stepForm = "\aqctrl\configForm";
        $stepRtn = "\aqctrl\configRtn";
        $stepIncl = 'configFn.php';
        break;
}



echo "<h1>Quickstart Step " . $stepNum . ": " . $stepTitle . "</h1>";

include $stepIncl;

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    //Do the form fcn
    $procRtn = $stepRtn($mysqli, $_POST);

    //Add one to the quickstart step number and reset if ...
    if($procRtn) {
        $newStep = $stepNum+1;
        $sql = "UPDATE quickstart
            SET step = $newStep
            WHERE id = 1";

        if(!mysqli_query($mysqli, $sql)) {
            echo "<p>Error adding quickstart count " . mysqli_error($mysqli) . "</p>";
        }

        header("Location: quickstart.php");
    }


} else {
    //Show the form
    $stepForm($mysqli, "quickstart.php" . $stepNum);
}

mysqli_close($mysqli);

// At the end allow for a "reset" option
?>

<p> <details>
    <summary>Reset the DB</summary>
    <form action="quickstart.php" method="post">
    Resetting the DB will erase ALL of your settings and saved data, COMPLETELY resetting EVERYTHING.<br>
    Are you sure that you REALLY want to do this?<br>
    <input type="checkbox" name="okay" />Yes, I'm sure I want to do this.<br>
    <input type="submit" name="reset" value="Reset" />
    <input type="submit" name="cancel" value="Cancel" />
</form>
</details> </p>
        
<?php
include 'footer.php';



