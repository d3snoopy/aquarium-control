<?php

// index.php

set_include_path("template:model");

$title = "Summary";

// Add the header.
include 'header.php';

// Pull in the models.
include 'model.php';

//Test the db connection and connect
$mysqli = \aqctrl\db_connect();

if(!$mysqli) {
    header("Location: quickstart.php");
    return;
}


//Test to see if we made it through the quickstart
// Number of quickstart steps plus one
$numQuickstart = 3; //Make sure to update this appropriately

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

