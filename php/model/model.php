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


/* model.php; make a function per model actions desired

Models objects:

Host - A device that the system talks to.

Hidden with in the host, handled at the host level, via config/arduino code: resources and devices.
  The resources/devices manage the hardware of the host.  Left up to non-database/non web code.


Channel - A single thing to drive/read from.

Source - The user basis for driven channels - the "thing" that you're trying to control/simulate

Profile - A driving shape/schedule for a driven source. (Note: allow global and mix type of controls)

CS - a linking item between channels and sources.

Macro - prebuilt set of sources/profiles for common cases (TODO)

Scheduler - the driver that dictates updates to a profile (TODO)

Reaction - an operation taking an input and pushing an output

More details/links between these: see design docs

*/

namespace aqctrl;

// Read the ini file
$config = parse_ini_file("/etc/aqctrl.ini");

if(!$config) {
  echo "<p>could not read /etc/aqctrl.ini</p>";
  return;
}

set_include_path("template:model");

function debug_status()
{
  global $config;
  if(isset($config["debug_mode"]) && ($config["debug_mode"])) {
    return True;
  }
return False;
}


// Db functions

function db_connect()
{
  global $config;

  // Number of quickstart steps plus one
  $numQuickstart = 5; //Make sure to update this appropriately

  // Connect to our db.
  $mysqli = mysqli_connect($config["sql_hostname"], $config["sql_user"],
  $config["sql_pass"], $config["sql_db"]);

  if(!$mysqli) {
    return False;
  }

  mysqli_set_charset($mysqli, "utf8");

  $res = mysqli_query($mysqli, "SHOW TABLES");

  $out = mysqli_num_rows($res);

  if(!$out) {
    return False;
  } else {
    return $mysqli;
  }
}

function db_close()
{
  mysqli_close($mysqli);
}

function db_create()
{
  // Create the necessary db tables to get our database going.
  global $config;

  // Connect
  $mysqli = db_connect();

  if (!$mysqli) {
    die("<p>Failed to connect to MySQL: (" . mysqli_connect_errno() . ") " . mysqli_connect_error(). "</p>");
  }

  // Determine debug mode
  $debug_mode = \aqctrl\debug_status();

  // Drop the old database
  $sql = "DROP DATABASE " . $config["sql_db"];

  $res = mysqli_query($mysqli, $sql);

  // Create the database
  $sql = "CREATE DATABASE " . $config["sql_db"];

  if(!mysqli_query($mysqli, $sql)) {
    if($debug_mode) echo "Error creating database: " . mysqli_error($mysqli) . "<br>";
  }

  // Create tables in the database
  $res = mysqli_select_db($mysqli, $config["sql_db"]);

  // Host
  $mQuery = "";
  $mQuery .= "CREATE TABLE host (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ident VARCHAR(50) NOT NULL,
    name VARCHAR(50) NOT NULL,
    auth VARCHAR(50),
    lastPing DATETIME NOT NULL,
    inInterval INT(6) UNSIGNED,
    outInterval INT(6) UNSIGNED,
    pingInterval INT(6) UNSIGNED,
    status VARCHAR(50) NOT NULL
    );";

  // Channel
  $mQuery .= "CREATE TABLE channel (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30),
    type VARCHAR(30),
    variable INT(1) UNSIGNED,
    active INT(1) UNSIGNED,
    input BOOLEAN,
    hostChNum INT(6) UNSIGNED,
    max FLOAT(10,4) UNSIGNED,
    min FLOAT(10,4) UNSIGNED,
    color CHAR(6),
    units VARCHAR(30),
    lastPing DATETIME NOT NULL,
    host INT(6) UNSIGNED,
    FOREIGN KEY(host)
    REFERENCES host(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
    );";

  //Source
  $mQuery .= "CREATE TABLE source (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30),
    scale FLOAT(10,4),
    type VARCHAR(30)
    );";

  //Function
  $mQuery .= "CREATE TABLE function (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL
    );";

  //Point
  $mQuery .= "CREATE TABLE point (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    value FLOAT(10,4) NOT NULL,
    timeAdj FLOAT(10,4) NOT NULL,
    timeType BOOLEAN NOT NULL,
    timeSE BOOLEAN NOT NULL,
    function INT(6) UNSIGNED,
    FOREIGN KEY (function) REFERENCES function(id) ON DELETE CASCADE ON UPDATE CASCADE
    );";

  //Reaction
  $mQuery .= "CREATE TABLE reaction (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action INT(2) UNSIGNED NOT NULL,
    scale FLOAT(10,4) UNSIGNED NOT NULL,
    channel INT(6) UNSIGNED,
    react INT(6) UNSIGNED,
    FOREIGN KEY (channel) REFERENCES channel(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (react) REFERENCES reaction(id) ON DELETE CASCADE ON UPDATE CASCADE
    );";

  //Profile
  $mQuery .= "CREATE TABLE profile (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL,
    start DATETIME NOT NULL,
    end DATETIME NOT NULL,
    refresh INT(10) NOT NULL,
    reaction INT(6) UNSIGNED,
    function INT(6) UNSIGNED,
    FOREIGN KEY (reaction) REFERENCES reaction(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (function) REFERENCES function(id) ON DELETE CASCADE ON UPDATE CASCADE
    );";

  //CPS - linker between source, profile, and channel
  $mQuery .= "CREATE TABLE cps (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scale FLOAT(10,4) NOT NULL,
    channel INT(6) UNSIGNED,
    profile INT(6) UNSIGNED,
    source INT(6) UNSIGNED,
    FOREIGN KEY (channel) REFERENCES channel(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (profile) REFERENCES profile(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (source) REFERENCES source(id) ON DELETE CASCADE ON UPDATE CASCADE
    );";

  //Data
  $mQuery .= "CREATE TABLE data (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date DATETIME NOT NULL,
    value FLOAT(10,4) NOT NULL,
    channel INT(6) UNSIGNED,
    FOREIGN KEY (channel) REFERENCES channel(id) ON DELETE CASCADE ON UPDATE CASCADE
    );";

  //Quickstart Tracker
  $mQuery .= "CREATE TABLE quickstart (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    step INT(1) UNSIGNED NOT NULL
    );";

  //Token Tracker
  $mQuery .= "CREATE TABLE token (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    t VARCHAR(32) NOT NULL,
    date DATETIME NOT NULL
    );";

  //Do the mQuery

  if(!mysqli_multi_query($mysqli, $mQuery)) {
    if ($debug_mode) echo "multiquery: " . $mQuery . " failed.";
  }

  //Flush the results.
  while (mysqli_next_result($mysqli)) {;};


  //Create a record for the quickstart and set it to 1
  $sql = "INSERT INTO quickstart (id, step)
    VALUES (1, 1)";

  if(!mysqli_query($mysqli, $sql)) {
    if ($debug_mode) echo "Error initiating quickstart count " . mysqli_error($mysqli) . "<br>";
  }

  include 'functionFn.php';

  \aqctrl\functionInit($mysqli, $debug_mode);

  mysqli_close($mysqli);
    

}


function token_insert($mysqli, $debug_mode)
{
  //Function to add a token to the dB.  This is used to add a little bit of protection.
  $token = bin2hex(random_bytes(16));

  //Remove any token that are more than 10 monutes old.
  $nowTime = time();
  $expTime = $nowTime-600;
  $sql = "DELETE FROM token WHERE date < FROM_UNIXTIME($expTime)";

  if(!mysqli_query($mysqli, $sql)) {
    if ($debug_mode) echo "Error dropping old tokens " . mysqli_error($mysqli) . "<br>";
  }

  $sql = "INSERT INTO token (t, date)
    VALUES ('$token', FROM_UNIXTIME($nowTime))";

  if(!mysqli_query($mysqli, $sql)) {
    if ($debug_mode) echo "Error adding new token " . mysqli_error($mysqli) . "<br>";
  }

  return "<input type='hidden' name='token' value='$token'>\n";
}


function token_check($mysqli, $debug_mode)
{
  //Function to check and see if we have a valid token.

  //Drop tokens that are more than 10 minutes old.
  $expTime = time()-600;
  $sql = "DELETE FROM token WHERE date < FROM_UNIXTIME($expTime)";

  if(!mysqli_query($mysqli, $sql)) {
    if ($debug_mode) echo "Error dropping old tokens " . mysqli_error($mysqli) . "<br>";
  }

  //Check to see if the token post key exists.
  if (!array_key_exists("token", $_POST)) return false;

  //The key exists, Prepare a query.
  $qString = mysqli_real_escape_string($mysqli, $_POST['token']);

  $sql = "SELECT id FROM token WHERE t = '$qString'";

  $res = mysqli_query($mysqli, $sql);

  if (!$res) {
    if ($debug_mode) echo "Error getting token " . mysqli_error($mysqli) . "<br>";
  }

  //Drop the token (whether it exists or not...)
  $sql = "DELETE FROM token WHERE t = '$qString'";

  if(!mysqli_query($mysqli, $sql)) {
    if ($debug_mode) echo "Error dropping token: " . mysqli_error($mysqli) . "<br>";
  }

  return (bool)mysqli_num_rows($res);
  
}


function retGen($newLoc = false, $newEdit = false, $newMode = false, $otherOpts = false, $autoFwd = false)
{
  //function to generate a forwarding header.
  //handles layering of three GET parameters: edit, ret (return), mode.
  //This will enable you to "tunnel" into multiple layers of edit buttons and return back out to where you started.
  //Note this function returns the url needed to tunnel one layer deeper
  //newEdit, Ret, Mode, otherOpts all apply to the page you're linking to.
  //Note: inhereted edit, ret, mode are all preserved; other opts are not.
  //Also note: this function doesn't do anything to sanitize inputs,
  //so make sure they're sanitized before passing them here. ***This includes urlencoding before this function***.
  //Finally note: is $newRet is supplied, the assumption is that you want a new layer;
  //if it's false the assumption is that you don't want a new layer.

  //Seed with our new location.
  if($newLoc) {
    $newUrl = $newLoc;
  } else {
    $newUrl = $_SERVER['SCRIPT_NAME'];
  }

  //Add our new options for this URL.
  $newUrl .= "?";

  if($newEdit) {
    $newUrl .= "edit=$newEdit&";
  } else {
    if (isset($_GET['edit'])) $newUrl .= "edit=" . $_GET['edit'] . "&";
  }

  if($newLoc) {
    $newUrl .= "ret=" . urlencode($_SERVER['SCRIPT_NAME']) . "&";
  } else {
    if (isset($_GET['ret'])) $newUrl .= "ret=" . urlencode($_GET['ret']) . "&";
  }

  if($newMode) {
    $newUrl .= "mode=$newMode&";
  } else {
    if (isset($_GET['mode'])) $newUrl .= "mode=" . $_GET['mode'] . "&";
  }

  if($otherOpts) $newUrl .= "$otherOpts&";

  //Parse our existing options and layer them.
  $optTypes = ["ret", "edit", "mode"];
  $i = 0;

  if ($newLoc) {
    //Do increment our layering.
    foreach($optTypes as $optType) {
      //First, create the first layer.
      if(isset($_GET[$optType])) $newUrl .= "$optType$i=" . $_GET[$optType] . "&";
    }
  }

  //Parse each existing layer.
  while (true) {
    //Keep looping until we break out.
    $layerFound = false;

    //test to see if we are incrementing or not.
    if ($newLoc) {
      $j = $i+1;
    } else {
      $j = $i;
    }

    foreach($optTypes as $optType) {
      //First, create the first layer.
      if(isset($_GET["$optType$i"])) {
        $newUrl .= "$optType$j=" . $_GET["$optType$i"] . "&";
        $layerFound = true;
      }
    }

    if($layerFound) {
      $i++;
    } else {
      break;
    }
  }

  //Provide a forwarding header if requested.
  if($autoFwd) header("Location: " . $newUrl);

  //Return our new URL.
  return($newUrl);
}


function retParse($useSelf = true)
{
  //function to handle generating a forwarding header.
  //function to back out of one layer of "tunneling" generated by retGen if we're returning to a different URL.
  //Note: this function generates the header to forward you back up the layer and returns nothing.

  if(!isset($_GET["ret"]) || $useSelf) {
    //Handle the case where no ret is provided (thus implying to return to the referring script).
    //In this case blindly preserve the whole referring URL.
    header("Location: " . $_SERVER['REQUEST_URI']);
    return;
  }

  //We have been told where we want to return
  $retUrl = urldecode($_GET["ret"]);
  $retUrl .= "?";

  //Set up our loop variables.
  $optTypes = ["ret", "edit", "mode"];
  $i = 1;
  $j = 0;

  //Handle the first layer first
  foreach($optTypes as $optType) {
    if(isset($_GET["$optType$j"])) $retUrl .= "$optType=" . $_GET["$optType$j"] . "&";
  }

  //Parse our options, decrementing each option layer.
  while (true) {
    $layerFound = false;
    $j = $i-1;

    foreach($optTypes as $optType) {
      //First, create the first layer.
      if(isset($_GET["$optType$i"])) {
        $retUrl .= "$optType$j=" . $_GET["$optType$i"] . "&";
        $layerFound = true;
      }
    }

    if($layerFound) {
      $i++;
    } else {
      break;
    }
  }

  //Set the header and return.
  header("Location: $retUrl");
  return;
}




