ALTER USER 'app'@'%' IDENTIFIED WITH mysql_native_password BY 'secret';
ALTER USER 'app'@'localhost' IDENTIFIED WITH mysql_native_password BY 'secret';
FLUSH PRIVILEGES;
