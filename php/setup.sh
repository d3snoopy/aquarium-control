#!/bin/bash

#Copyright 2017 Stuart Asp: d3snoopy AT gmail

#This file is part of Aqctrl.

#Aqctrl is free software: you can redistribute it and/or modify
#it under the terms of the GNU General Public License as published by
#the Free Software Foundation, either version 3 of the License, or
#(at your option) any later version.

#Aqctrl is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.

#You should have received a copy of the GNU General Public License
#along with Aqctrl.  If not, see <http://www.gnu.org/licenses/>.

# Script to set up the sql database for the aquarium control


mysql -u root -p </srv/code/aquarium/php/aq.sql
cp /srv/code/aquarium/php/aqctrl.ini /etc/


echo "Database Set Up, password for aqctrl user set to aqctrl_pass; make sure to change this in SQL and in the /etc/aqctrl.ini file before continuing!"
