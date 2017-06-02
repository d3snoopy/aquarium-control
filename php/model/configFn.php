<?php

/*
Copyright 2017 Stuart Asp: d3snoopy AT gmail

This file is part of Aqctrl.

Aqctrl is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Aqctrl is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Aqctrl.  If not, see <http://www.gnu.org/licenses/>.
*/

/*
configFn.php


Functions to support the setup.php page.

configForm - function to generate the form to fill out

configRtn - function to handle the return a POST of SetupForm

*/

namespace aqctrl;

// Db functions

function configForm($mysqli, $nextUrl)
{

    // Show the form
    echo "<h2>Configuration</h2>";

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



function configRtn($mysqli, $postRet)
{

    // Handle our form

}

