<?php

set_include_path("template:model");

$title = "Summary";

// Add the header.
include 'header.php';

// Pull in the ini file.
include 'mysql.ini';

// Pull in the models.
include 'model.php';

// Do my content here

//Test the db connection
$sqconnect = \aqctrl\db_test();

if(!$sqconnect) {
    header("Location: quickstart.php");
    return;
}


//Test to see if we made it through the quickstart
// Number of quickstart steps plus one
$numQuickstart = 5; //Make sure to update this appropriately

// Connect to our db.
$mysqli = mysqli_connect($aqctrl_sql_hostname, $aqctrl_sql_user,
    $aqctrl_sql_pass, $aqctrl_sql_db);

$res = mysqli_query($mysqli, "SELECT step FROM quickstart WHERE id = 1");

if(mysqli_fetch_row($res)[0] != $numQuickstart) {
    mysqli_close($mysqli);
    header("Location: quickstart.php");
    return;
}



?>
<p>
<?php

for ($i = 1; $i <= 200; $i++) {
    echo "<br>$i";
}
?>
</p>

<?php



// End do the footer
include 'footer.php';
?>

