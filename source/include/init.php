<?php

define("ROOT_PATH", str_replace('source/include/init.php', '', str_replace('\\', '/', __FILE__)));

require ROOT_PATH .'smarty/libs/Smarty.class.php';

$smarty = new Smarty;

$smarty->force_compile = true;
$smarty->debugging = false;
$smarty->caching = true;
$smarty->cache_lifetime = 120;

require ROOT_PATH . 'configs/config.php';

require ROOT_PATH . 'source/include/cls_mysql.php';

$mysql = new cls_mysql($db_host, $db_user, $db_pass, $db_name);

$mysql->query("set names utf8"); 

$username = (isset($_COOKIE['username']))?$_COOKIE['username']:(isset($_COOKIE['adminname'])?$_COOKIE['adminname']:"");
$uid = (isset($_COOKIE['uid']))?$_COOKIE['uid']:(isset($_COOKIE['adminid'])?$_COOKIE['adminid']:0);

$formhash = substr(MD5("FORMHASH" . MD5($db_name) . $username . $uid . date("Ym",time())),8,16);