CREATE USER IF NOT EXISTS 'app'@'%' IDENTIFIED BY 'secret';
ALTER USER 'app'@'%' IDENTIFIED WITH mysql_native_password BY 'secret';
CREATE USER IF NOT EXISTS 'app'@'localhost' IDENTIFIED BY 'secret';
ALTER USER 'app'@'localhost' IDENTIFIED WITH mysql_native_password BY 'secret';
FLUSH PRIVILEGES;
