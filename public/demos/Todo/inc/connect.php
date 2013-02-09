<?php if (!defined('SAFETY_NET')) die('Where is your safety net?');

// load config db params
$config = include_once('config.php');

// connect to mysql
$db = mysql_connect(
    $config['db']['host'],
    $config['db']['user'],
    $config['db']['pass']
) or die('Unable to establish a DB connection');

mysql_set_charset('utf8');
mysql_select_db($config['db']['database'], $db);
