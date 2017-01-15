<?php

/* Functions to support the setup.php page.

setupForm - function to generate the form to fill out

setupRtn - function to handle the return a POST of SetupForm

*/

namespace aqctrl;

include 'mysql.ini';

// Db functions

function setupForm($mysqli, $nextUrl)
{

    // Show the form
    echo "<h2>Hardware</h2>";

    // Iterate through each device found
    $res = mysqli_query($mysqli, "SELECT ALL FROM host");

    
}



function setupRtn($mysqli, $postRet)
{

    // Handle our form
    echo "<p>Ret</p>";

}

