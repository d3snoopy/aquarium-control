<?php

/* Model code; make a function per model actions desired

Models objects:

Host - A device that the system talks to.

Hidden with in the host, handled at the host level, via config/arduino code: resources and devices.
  The resources/devices manage the hardware of the host.  Left up to non-database/non web code.


Channel - A single thing to drive/read from.

Source - The user basis for driven channels - the "thing" that you're trying to control/simulate

Profile - A driving shape/schedule for driven channels.

CP - a linking item between profiles and channels.

Macro - prebuilt set of sources/profiles for common cases (TODO)

Scheduler - the driver that dictates updates to a profile

Reaction - an operation taking an input and pushing an output

More details/links between these: see design docs

*/

namespace aqctrl;

include 'mysql.ini';

// access via: \aqctrl\db_create();, etc

// Db functions

function db_test()
{
    global $aqctrl_sql_hostname, $aqctrl_sql_user, $aqctrl_sql_pass, $aqctrl_sql_db;

    // Number of quickstart steps plus one
    $numQuickstart = 5; //Make sure to update this appropriately

    // Connect to our db.
    $mysqli = mysqli_connect($aqctrl_sql_hostname, $aqctrl_sql_user,
        $aqctrl_sql_pass, $aqctrl_sql_db);

    if(!$mysqli) {
        return False;
    }

    $res = mysqli_query($mysqli, "SHOW TABLES");

    $out = mysqli_num_rows($res);

    mysqli_close($mysqli);

    if(!$out) {
        return False;
    } else {
        return True;
    }
}



function db_create()
{
    // Create the necessary db tables to get our database going.
    // Pull global variables
    global $aqctrl_sql_hostname, $aqctrl_sql_user, $aqctrl_sql_pass, $aqctrl_sql_db;

    // Connect
    $mysqli = mysqli_connect($aqctrl_sql_hostname, $aqctrl_sql_user,
        $aqctrl_sql_pass);
    if (!$mysqli) {
        die("<p>Failed to connect to MySQL: (" . mysqli_connect_errno() . ") " . mysqli_connect_error(). "</p>");
    }

    // Drop the old database
    $sql = "DROP DATABASE " . $aqctrl_sql_db;

    $res = mysqli_query($mysqli, $sql);

    // Create the database
    $sql = "CREATE DATABASE " . $aqctrl_sql_db;

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating database: " . mysqli_error($mysqli) . "<br>";
    }

    // Create tables in the database
    $res = mysqli_select_db($mysqli, $aqctrl_sql_db);

    // Host
    $sql = "CREATE TABLE host (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(30) NOT NULL,
        ip VARCHAR(30) NOT NULL UNIQUE
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }

    // Channel
    $sql = "CREATE TABLE channel (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(30) NOT NULL,
        type VARCHAR(30) NOT NULL,
        variable INT(1),
        max FLOAT(4,4) NOT NULL,
        min FLOAT(4,4) NOT NULL,
        scale FLOAT(4,4) NOT NULL,
        color CHAR(6) NOT NULL,
        host INT(6) UNSIGNED,
        FOREIGN KEY(host)
        REFERENCES host(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }

    //Source
    $sql = "CREATE TABLE source (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(30) NOT NULL
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }

    //Scheduler
    $sql = "CREATE TABLE scheduler (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(30) NOT NULL,
        type INT(1) UNSIGNED
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }

    //Profile
    $sql = "CREATE TABLE profile (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(30) NOT NULL,
        start TIMESTAMP,
        stop TIMESTAMP,
        type INT(2) UNSIGNED NOT NULL,
        coeff0 FLOAT(6,4) NOT NULL,
        coeff1 FLOAT(8,4) NOT NULL,
        coeff2 FLOAT(8,4) NOT NULL,
        coeff3 FLOAT(8,4) NOT NULL,
        coeff4 FLOAT(8,4) NOT NULL,
        coeff5 FLOAT(8,4) NOT NULL,
        coeff6 FLOAT(8,4) NOT NULL,
        coeff7 FLOAT(8,4) NOT NULL,
        coeff8 FLOAT(8,4) NOT NULL,
        coeff9 FLOAT(8,4) NOT NULL
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }


    //CP
    $sql = "CREATE TABLE cp (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        scale FLOAT(4,4) NOT NULL,
        channel INT(6) UNSIGNED,
        profile INT(6) UNSIGNED,
        FOREIGN KEY (channel) REFERENCES channel(id),
        FOREIGN KEY (profile) REFERENCES profile(id)
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }


    //Reaction
    $sql = "CREATE TABLE reaction (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        action INT(2) NOT NULL,
        scale FLOAT(4,4) NOT NULL,
        channel INT(6) UNSIGNED,
        profile INT(6) UNSIGNED,
        Pcol INT(2) UNSIGNED NOT NULL,
        react INT(6) UNSIGNED,
        FOREIGN KEY (channel) REFERENCES channel(id),
        FOREIGN KEY (profile) REFERENCES profile(id),
        FOREIGN KEY (react) REFERENCES reaction(id)
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }


    //Quickstart Tracker
    $sql = "CREATE TABLE quickstart (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        step INT(1) NOT NULL
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }

    //Create a record for the quickstart and set it to 1
    $sql = "INSERT INTO quickstart (id, step)
        VALUES (1, 1)";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error initiating quickstart count " . mysqli_error($mysqli) . "<br>";
    }

    mysqli_close($mysqli);
    

}

