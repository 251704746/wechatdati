<?php

$mod = isset( $_GET['mod'] ) ? $_GET['mod'] : ( isset( $_POST['mod'] ) ? $_POST['mod']:"index");

if(file_exists(dirname( __FILE__ ) ."/source/module/" . $mod . '.php'))
{
	include dirname( __FILE__ ) ."/source/module/" . $mod . '.php';
}else{
	echo "模块不存在";
}

