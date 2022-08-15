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

namespace aqctrl;


function setupForm($mysqli, $debug_mode)
{

  // Show the form
  echo "<h2>Hardware Hosts</h2>\n";

  // Iterate through each host found
  $knownHosts = mysqli_query($mysqli, "SELECT id,ident,name,auth,UNIX_TIMESTAMP(lastPing),inInterval,
    outInterval,pingInterval,status FROM host");
  $knownChan = mysqli_query($mysqli, "SELECT id,name,type,variable,active,input,hostChNum,max,min,color,
    units,UNIX_TIMESTAMP(lastPing),host FROM channel ORDER BY host,hostChNum");

  $numHosts = mysqli_num_rows($knownHosts);

  if(!$numHosts) {
    echo "<p>No Hosts Configured.</p>\n";
    $host= gethostname();
    $ip = gethostbyname($host);
    echo "<p>Configure your hosts to point to ";
    echo $ip;
    echo " and turn them on now then \n";

    echo "<a href=".">refresh</a>\n the page.\n</p>\n";

    return;
        
  }

  ?>
<table>
<tr>
<th>Name</th>
<th>ID</th>
<th>Last Ping</th>
<th>Last Status</th>
<th>Channels</th>
</tr>
    <?php

  foreach($knownHosts as $hostRow) {
    echo "<tr>\n";
    echo "<td>" . $hostRow["name"] . "\n";
    echo "<td>" . $hostRow["ident"] . "\n";
    echo "<td>" . \aqctrl\time_elapsed_string($hostRow["UNIX_TIMESTAMP(lastPing)"], $hostRow["pingInterval"]) .
        "</td>\n";
    echo "<td>" . $hostRow["status"] . "</td>\n";
    echo "<td>\n";

    foreach($knownChan as $chan) {
      if($chan["host"] == $hostRow["id"]) echo $chan["name"] . "\n<br>\n";
    }

    if(!(isset($_GET["edit"]) && $_GET["edit"] == $hostRow["id"])) {
      echo "<p class='alignright'>\n";
      echo "<a href='" . \aqctrl\retGen(false, $hostRow["id"], false, false, false) . "'>";
      echo "edit</a>\n";
    }

    echo "</td>\n";
    echo "</tr>\n";

  }

  echo "</table>\n";


  foreach($knownHosts as $hostRow) {
    if(isset($_GET["edit"]) && $_GET["edit"] == $hostRow["id"]) {

      ?>

<div id='edit'>
<h3>Host Edit</h3>
<table>
<tr>
<th>Name</th>
<th>ID</th>
<th>Key</th>
<th>Read Interval (sec)</th>
<th>Write Interval (sec)</th>
<th>Ping Interval (sec)</th>
</tr>
<tr>
      <?php

      //We are editing this host
      echo "<input type='hidden' name='editID' value=" . $hostRow["id"] . ">\n";
      echo "<td><input type='text' name='hostName' value='" . $hostRow["name"] . "'></td>\n";
      echo "<td>" . $hostRow["ident"] . "</td>\n";
      echo "<td><input type='text' name='hostAuth' value='" . $hostRow["auth"] . "'></td>\n";
      echo "<td><input type='number' min='1' name='inInt' value='" . $hostRow["inInterval"] . "'></td>\n";
      echo "<td><input type='number' min='1' name='outInt' value='" . $hostRow["outInterval"] . "'></td>\n";
      echo "<td><input type='number' min='1' name='pingInt' value='" . $hostRow["pingInterval"] . "'></td>\n";

      ?>
</tr>
</table>
<p class='alignright'>
<input type='submit' name='delete' value='Delete Host' />
<div style='clear:both;'></div>
<h3>Channels</h3>
<table>
<tr>
<th>Name</th>
<th>Type</th>
<th>Variable/Boolean</th>
<th>Active</th>
<th>Input/Output</th>
<th>Host Channel Number</th>
<th>Max</th>
<th>Min</th>
<th>Color</th>
<th>Units</th>
<th>Last Ping</th>
</tr>
<script type="text/javascript" src="/static/jscolor.js"></script>
      
      <?php

      $i = 0;

      foreach($knownChan as $chan) {
        if($chan["host"] == $hostRow["id"]) {

          //This is a channel for this host, so display it's info/open editing
          echo "<input type='hidden' name='chan$i' value=" . $chan["id"] . ">\n";
          echo "<tr>\n";
          echo "<td><input type='text' name='name$i' value='" . $chan["name"] . "'></td>\n";
          echo "<td><input type='text' name='type$i' value='" . $chan["type"] . "'></td>\n";
          echo "<td>";
          if($chan["variable"]) {
            echo "Variable";
          } else {
            echo "Boolean";
          }
          echo "</td>\n<td>";
          if($chan["active"]) {
            echo "True";
          } else {
            echo "False";
          }
          echo "</td>\n<td>";
          if($chan["input"]) {
            echo "Input";
          } else {
            echo "Output";
          }
          echo "</td>\n";
          echo "<td>" . $chan["hostChNum"] . "</td>\n";
          echo "<td><input type='number' name='max$i' value=" . $chan["max"] . " step='any'></td>\n";
          echo "<td><input type='number' name='min$i' value=" . $chan["min"] . " step='any'></td>\n";
          echo "<td><input type='text' class='color' name='color$i' value='" . $chan["color"] . "'></td>\n";
          echo "<td>" . $chan["units"] . "</td>\n"; //TODO: Make sure units are alphanumeric
          echo "<td>" . \aqctrl\time_elapsed_string($chan["UNIX_TIMESTAMP(lastPing)"], $hostRow["pingInterval"]) .
            "</td>\n";
          echo "</tr>\n";

          $i++;
        }
      }

    echo "</table>\n";
    echo "</div>\n";
    break;
    }
  }
    
  //Add some csrf/replay protection.
  echo \aqctrl\token_insert($mysqli, $debug_mode);

  //Add db reset option.
  if ($debug_mode) {  
    echo"<p> <details>";
    echo"<summary>Reset the DB</summary>";
    echo"Resetting the DB will erase ALL of your settings and saved data, COMPLETELY resetting EVERYTHING.";
    echo"<br>";
    echo"Are you sure that you REALLY want to do this?<br>";
    echo"<input type=\"checkbox\" name=\"okay\" />Yes, I'm sure I want to do this.<br>";
    echo"<input type=\"submit\" name=\"reset\" value=\"Reset\" />";
    echo"</details> </p>";
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

  // Check for a host delete choice.
  if(isset($_POST["delete"])) {
    //The user clicked delete for this function.
    $sql = "DELETE FROM host WHERE id=" . (int)$_POST["editID"];

    if(!mysqli_query($mysqli, $sql)) {
      if ($debug_mode) echo "<p>Error deleting host:" . mysqli_error($mysqli) . "</p>";
    }

    return;  //We can return because all we want to do is the deletion
  }

  // Prepare our statement to update hosts
  $stmt = $mysqli->prepare("UPDATE host SET name = ?, auth = ?, inInterval = ?, outInterval = ?,
    pingInterval = ? WHERE id = ?");

  $stmt-> bind_param("ssiiii", $nameStr, $authStr, $inInt, $outInt, $pingInt, $hostID);

  $nameStr = $_POST["hostName"];
  $authStr = $_POST["hostAuth"];
  $inInt = $_POST["inInt"];
  $outInt = $_POST["outInt"];
  $pingInt = $_POST["pingInt"];
  $hostID = $_POST["editID"];

  if(!$stmt->execute() && $debug_mode) {
      echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
  }

  //Update the chans
  $stmt = $mysqli->prepare("UPDATE channel SET name = ?, type= ?, max = ?, min = ?,
    color = ? WHERE id = ?");

  $stmt-> bind_param("ssddsi", $nameStr, $typeStr, $maxD, $minD, $colorStr, $chanID);

  for ($i=0; isset($_POST["chan$i"]); $i++) {
    // Update the values
    $nameStr = $_POST["name$i"];
    $typeStr = $_POST["type$i"];
    $maxD = $_POST["max$i"];
    $minD = $_POST["min$i"];
    $colorStr = $_POST["color$i"];
    $chanID = $_POST["chan$i"];

    if(!$stmt->execute() && $debug_mode) {
      echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
    }

  }
}


function time_elapsed_string($tm, $pInt) {
  $cur_tm = time();
  $dif = $cur_tm - $tm;

  if($dif > 4*$pInt) {
    $x = "<span style='color:red'>";
  } elseif($dif > $pInt) {
    $x = "<span style='color:yellow'>";
  } else {
    $x = "<span style='color:green'>";
  }

  $pds = array('second','minute','hour','day','week','month','year','decade');
  $lngh = array(1,60,3600,86400,604800,2630880,31570560,315705600);

  for($v = sizeof($lngh)-1; ($v >= 0)&&(($no = $dif/$lngh[$v])<=1); $v--); if($v < 0) $v = 0; $_tm = $cur_tm-($dif%$lngh[$v]);
  $no = floor($no);
  if($no <> 1)
    $pds[$v] .='s';
  $x .= sprintf("%d %s ",$no,$pds[$v]);
  $x .= " ago</span>";
  return $x;
}



