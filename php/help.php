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

// help.php

namespace aqctrl;

set_include_path("template:model");

$title = "Help";
include 'header.php';
include 'model.php';

if(strpos($_SERVER['REQUEST_URI'], 'help.php') !== false) {
  echo "<h1>Help</h1>\n";
  \aqctrl\helpIntro();
  include 'footer.php';

}


function helpIntro()
{

?>
<h2>Introduction</h2>
<p>
Welcome to Stu's aquarium controller web server!
</p>
<p>
Here's how it works:
</p>

<p>

<img src='../static/help1.jpg' class='alignright'/>
1. Set up some hosts:
<br>
Hosts are devices that interface to your physical aquarium components and talk to the server.
<br>
A typical host would be an Arduino device or a single board computer.
<br>
The host can have one or more aquarium components connected to it, which are considered channels.  These channels are things like temperature sensors, lights, heaters, etc.
<br>
<br>
Hosts should talk to the server according to the 'design' document in the documentation.
<br>
Also, see the example arduino code in the 'arduino' folder.
<br>
You will probably have to do some coding to get your hosts to talk to the server correctly, but the examples should get you started!
</p>

<div style='clear:both;'></div>

<p class='alignleft'>
Next Block of text
</p>
<p class='alignright'>
Next Picture
</p>
<div style='clear: both;'></div>


<?php


}

function helpRet()
{
  //This exists to make quickstart happy
  return;
}


