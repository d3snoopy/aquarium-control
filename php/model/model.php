<?php

/* model.php; make a function per model actions desired

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

// Read the ini file
$config = parse_ini_file("/etc/aqctrl.ini");

if(!$config) {
    echo "<p>could not read /etc/aqctrl.ini</p>";
    return;
}

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

    // Drop the old database
    $sql = "DROP DATABASE " . $config["sql_db"];

    $res = mysqli_query($mysqli, $sql);

    // Create the database
    $sql = "CREATE DATABASE " . $config["sql_db"];

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating database: " . mysqli_error($mysqli) . "<br>";
    }

    // Create tables in the database
    $res = mysqli_select_db($mysqli, $config["sql_db"]);

    // Host
    $sql = "CREATE TABLE host (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ident VARCHAR(50) NOT NULL,
        name VARCHAR(50) NOT NULL,
        auth VARCHAR(50),
        lastPing DATETIME NOT NULL,
        inInterval INT(6) UNSIGNED,
        outInterval INT(6) UNSIGNED,
        pingInterval INT(6) UNSIGNED
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }

    // Channel
    $sql = "CREATE TABLE channel (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(30),
        type VARCHAR(30),
        variable INT(1) UNSIGNED,
        active INT(1) UNSIGNED,
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
        coeff0 FLOAT(10,4) NOT NULL,
        coeff1 FLOAT(10,4) NOT NULL,
        coeff2 FLOAT(10,4) NOT NULL,
        coeff3 FLOAT(10,4) NOT NULL,
        coeff4 FLOAT(10,4) NOT NULL,
        coeff5 FLOAT(10,4) NOT NULL,
        coeff6 FLOAT(10,4) NOT NULL,
        coeff7 FLOAT(10,4) NOT NULL,
        coeff8 FLOAT(10,4) NOT NULL,
        coeff9 FLOAT(10,4) NOT NULL
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }


    //CP
    $sql = "CREATE TABLE cp (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        scale FLOAT(10,4) NOT NULL,
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
        action INT(2) UNSIGNED NOT NULL,
        scale FLOAT(10,4) UNSIGNED NOT NULL,
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


    //Data
    $sql = "CREATE TABLE data (
        id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        date DATETIME NOT NULL,
        value FLOAT(10,4) NOT NULL,
        channel INT(6) UNSIGNED,
        FOREIGN KEY (channel) REFERENCES channel(id)
        )";

    if(!mysqli_query($mysqli, $sql)) {
        echo "Error creating table " . mysqli_error($mysqli) . "<br>";
    }


    //Quickstart Tracker
    $sql = "CREATE TABLE quickstart (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        step INT(1) UNSIGNED NOT NULL
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

