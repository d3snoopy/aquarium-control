<?php

/* setupFn.php

Functions to support the setup.php page.

setupForm - function to generate the form to fill out

setupRtn - function to handle the return a POST of SetupForm

*/

namespace aqctrl;

// Db functions

function setupForm($mysqli, $nextUrl)
{

    // Show the form
    echo "<h2>Hardware Hosts</h2>";

    // Iterate through each host found
    $res = mysqli_query($mysqli, "SELECT ALL FROM host");

    if(!$res) {
        echo "<p>No Hosts Configured.</p>";
        $host= gethostname();
        $ip = gethostbyname($host);
        echo "<p>Configure your hosts to point to ";
        echo $ip;
        echo " and turn them on now.<br><br><br><br></p>";
        
    } else {

//        for i in res {
//            echo "<h3>$TableName</h3>";
            ?>
            <table>
            <tr>

        
            <?php

//        }
    }
}



function setupRtn($mysqli, $postRet)
{

    // Handle our form

}

