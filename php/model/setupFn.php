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


/* setupFn.php

Functions to support the setup.php page.

setupForm - function to generate the form to fill out

setupRtn - function to handle the return a POST of SetupForm

*/

// TODO: Color-code the "last ping" & make it a delta, not a date.
// TODO: Convert to prepared statements

namespace aqctrl;



function setupForm($mysqli, $debug_mode)
{

  // Show the form
  echo "<h2>Hardware Hosts</h2>\n";

  // Iterate through each host found
  $knownHosts = mysqli_query($mysqli, "SELECT id,ident,name,auth,UNIX_TIMESTAMP(lastPing),inInterval,outInterval,pingInterval,status FROM host");

  $numHosts = mysqli_num_rows($knownHosts);

  if(!$numHosts) {
    echo "<p>No Hosts Configured.</p>\n";
    $host= gethostname();
    $ip = gethostbyname($host);
    echo "<p>Configure your hosts to point to ";
    echo $ip;
    echo " and turn them on now then \n";

    echo "<a href=".">refresh</a>\n the page.\n</p>\n";
        
  } else {
    ?>
<table>
<tr>
<th>Name</th>
<th>ID</th>
<th>Key</th>
<th>Last Ping</th>
<th>Last Status</th>
<th>Read Interval (sec)</th>
<th>Write Interval (sec)</th>
<th>Ping Interval (sec)</th>
</tr>
    <?php

    for($i=0; $i < $numHosts; $i++) {
      $row = mysqli_fetch_array($knownHosts);
        
      echo "<tr>\n";
      echo "<td>" . $row["name"] . "</td>\n";
      echo "<td>" . $row["ident"] . "</td>\n";
      echo "<td><input type='text' name='auth" . $i . "' value='" . $row["auth"] . "'></td>\n";
      echo "<td>" . time_elapsed_string($row["UNIX_TIMESTAMP(lastPing)"], $row["pingInterval"]) .
        "</td>\n";
      echo "<td>" . $row["status"] . "</td>\n";
      echo "<td><input type='number' min='1' name='inInt" . $i . "' value='" . $row["inInterval"] . "'></td>\n";
      echo "<td><input type='number' min='1' name='outInt" . $i . "' value='" . $row["outInterval"] . "'></td>\n";
      echo "<td><input type='number' min='1' name='pingInt" . $i . "' value='" . $row["pingInterval"] . "'></td>\n";
      echo "</tr>\n";
      echo "<input type='hidden' name='Id$i' value=" . $row["id"] . ">\n";

    }

  echo "</table>";
    
  //Add some csrf/replay protection.
  echo \aqctrl\token_insert($mysqli, $debug_mode);
  }
}


function setupRtn($mysqli, $debug_mode)
{
  // Handle our form

  // Check the token
  if(!\aqctrl\token_check($mysqli, $debug_mode)) {
    //We don't have a good token
    if ($debug_mode) echo "<p>Error: token not accepted</p>\n";
    return;
  }

  // Prepare our statement to update hosts
  $stmt = $mysqli->prepare("UPDATE host SET auth = ?, inInterval = ?, outInterval = ?,
    pingInterval = ? WHERE id = ?");

  $stmt-> bind_param("siiii", $authStr, $inInt, $outInt, $pingInt, $hostID);

  for ($i=0; isset($_POST["Id$i"]); $i++) {
    // Update the values
    $authStr = $_POST["auth$i"];
    $inInt = $_POST["inInt$i"];
    $outInt = $_POST["outInt$i"];
    $pingInt = $_POST["pingInt$i"];
    $hostID = $_POST["Id$i"];

    if(!$stmt->execute() && $debug_mode) {
      echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    }

  }
}


function time_elapsed_string($tm, $pInt) {
  $cur_tm = time();
  $dif = $cur_tm - $tm;

  if($dif > 4*$pInt) {
    $x = "<p style='color:red'>";
  } elseif($dif > $pInt) {
    $x = "<p style='color:yellow'>";
  } else {
    $x = "<p style='color:green'>";
  }

  $pds = array('second','minute','hour','day','week','month','year','decade');
  $lngh = array(1,60,3600,86400,604800,2630880,31570560,315705600);

  for($v = sizeof($lngh)-1; ($v >= 0)&&(($no = $dif/$lngh[$v])<=1); $v--); if($v < 0) $v = 0; $_tm = $cur_tm-($dif%$lngh[$v]);
  $no = floor($no);
  if($no <> 1)
    $pds[$v] .='s';
  $x .= sprintf("%d %s ",$no,$pds[$v]);
  $x .= " ago</p>";
  return $x;
}



