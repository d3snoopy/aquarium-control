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

