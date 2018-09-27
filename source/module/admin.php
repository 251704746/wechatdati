<?php

define( 'SOURCE_PATH', dirname( __FILE__ ) .'/../' );

include(SOURCE_PATH . "include/init.php");
include_once( SOURCE_PATH . "class/admin.class.php");

$act = isset( $_GET['act'] ) ? $_GET['act'] : (isset( $_POST['act'] ) ? $_POST['act'] :'');

$smarty->assign( "dir" ,"source/class/tpl/"); 

$admin = new admin();

$admin->$act(); 
