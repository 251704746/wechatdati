<?php

define( 'SOURCE_PATH', dirname( __FILE__ ) .'/../' );

include(SOURCE_PATH . "include/init.php");
include_once( SOURCE_PATH . "class/index.class.php");

$act = isset( $_GET['act'] ) ? $_GET['act'] : (isset( $_POST['act'] ) ? $_POST['act'] :'');

$smarty->assign( "dir" ,"templates/"); 

$index = new index();

$index->$act(); 
