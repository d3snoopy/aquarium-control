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



function configForm($mysqli, $debug_mode)
{

  // Show the form
  echo "<h2>Sources</h2>";

  // Grab queries first.
  $src_res = mysqli_query($mysqli, "SELECT id,name,profile FROM source");
  $chan_res = mysqli_query($mysqli, "SELECT id,name,type,variable,active,max,
    min,color,units,lastping,host FROM channel");
  $CS_res = mysqli_query($mysqli, "SELECT id,scale,channel,source FROM cs ORDER BY source, channel");
  $prof_res = mysqli_query($mysqli, "SELECT id,name,type,start,stop,scheduler FROM profile");
  $react_res = mysqli_query($mysqli, "");


    if(!$res) {
        echo "<p>No Sources Configured.</p>";
        
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

