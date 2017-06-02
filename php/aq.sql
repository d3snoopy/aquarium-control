--Copyright 2017 Stuart Asp: d3snoopy AT gmail

--This file is part of Aqctrl.

--Aqctrl is free software: you can redistribute it and/or modify
--it under the terms of the GNU General Public License as published by
--the Free Software Foundation, either version 3 of the License, or
--(at your option) any later version.

--Aqctrl is distributed in the hope that it will be useful,
--but WITHOUT ANY WARRANTY; without even the implied warranty of
--MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
--GNU General Public License for more details.

--You should have received a copy of the GNU General Public License
--along with Aqctrl.  If not, see <http://www.gnu.org/licenses/>.

--Highly recommended: change 'aqctrl_pass' to something else.


CREATE DATABASE IF NOT EXISTS aqctrl;
CREATE user IF NOT EXISTS 'aqctrl'@'localhost' IDENTIFIED BY 'aqctrl_pass';
GRANT ALL ON aqctrl.* TO aqctrl@localhost IDENTIFIED BY 'aqctrl_pass';
FLUSH PRIVILEGES;
GRANT CREATE TEMPORARY TABLES ON aqctrl.* TO aqctrl@localhost IDENTIFIED BY 'aqctrl_pass';
FLUSH PRIVILEGES;
ALTER DATABASE aqctrl DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
