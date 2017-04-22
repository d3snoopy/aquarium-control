CREATE DATABASE IF NOT EXISTS aqctrl;
CREATE user IF NOT EXISTS 'aqctrl'@'localhost' IDENTIFIED BY 'aqctrl_pass';
GRANT ALL ON aqctrl.* TO aqctrl@localhost IDENTIFIED BY 'aqctrl_pass';
FLUSH PRIVILEGES;
GRANT CREATE TEMPORARY TABLES ON aqctrl.* TO aqctrl@localhost IDENTIFIED BY 'aqctrl_pass';
FLUSH PRIVILEGES;
ALTER DATABASE aqctrl DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
