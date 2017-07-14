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

// setup.php

set_include_path("template:model");

include 'model.php';
include 'setupFn.php';

// Connect to the db.
$mysqli = \aqctrl\db_connect();

$title = "Setup";

// Do my content here
if(!$mysqli) {
    //Complain about not being able to connect and give up.
    include 'header.php';
    echo "<p>Could not connect to dB, check /etc/aqctrl.ini</p>";
    echo "<p>Or, try going to the <a href=quickstart.php>quickstart page.</a></p>";
    include 'footer.php';
    return;
}

echo "<h1>Setup</h1>";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    //Do the form fcn
    \aqctrl\setupRtn($mysqli, $_POST);

    header("Location: setup.php");

} else {
    include 'header.php';
    //Show the form
    \aqctrl\setupForm($mysqli, "setup.php");
    include 'footer.php';
}

