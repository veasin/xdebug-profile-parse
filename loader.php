<?php
/**
 * Created by PhpStorm.
 * User: Vea
 * Date: 2017/07/20 020
 * Time: 17:22
 */
/**
 *  config
 */
$path="/server/profiler/";
$filter="#^cachegrind\.out#";
/**
 * load
 */
require 'profiler.php';
function fileList($path="/server/profiler/", $filter="#^cachegrind\.out#"){
	$files=[];
	$handle=opendir($path);
	while(false !== ($file=readdir($handle))){
		if($file == '.' || $file == '..') continue;
		if(is_file($path.$file) && preg_match($filter, $file)){
			$t=filemtime($path.$file).rand(0, 1);
			$files[$t]=$file;
		}
	}
	krsort($files);
	return array_values($files);
}

/**
 * action
 */
$act=$_GET['act'] ?? '';
switch($act){
	case 'list':
		echo json_encode(fileList($path, $filter), JSON_UNESCAPED_UNICODE);
		break;
	case 'load':
		$file=$_GET['file'] ?? 'last';
		if('last' === $file){
			$files=fileList($path, $filter);
			$file=$files[0];
		}
		if(is_readable($path.$file)){
			echo (new parser(new fileRead($path.$file)))->exec();
		}
		else echo [];
}

