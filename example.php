<?php
ini_set('xdebug.var_display_max_depth', 10);
ini_set('xdebug.var_display_max_children', 256);
ini_set('xdebug.var_display_max_data', 1024);
ini_set('max_execution_time', 0);
ignore_user_abort(true);
header('Content-Type: text/html; charset=UTF-8');
include_once('XML2Mongo.class.php');

$xmlprocess = new XML2Mongo;
$xmlprocess->set_debug(true);
//$xmlprocess->set_collect_vehicles(false);
$xmlprocess->set_collect_extras(false);
$xmlprocess->set_collect_filter('Personbil');
//$xmlprocess->set_collect_filter('Varebil');
//$xmlprocess->set_collect_filter('Stor personbil');
//$xmlprocess->set_collect_filter('Lastbil');
//$xmlprocess->set_collect_filter('Varebil');
//$xmlprocess->set_collect_filter('Motorcykel');
//$xmlprocess->set_collect_filter('Varebil');
//$xmlprocess->set_debug_show_objects(true);
$xmlprocess->set_xml_source_path(__DIR__.'/xml/ESStatistikListeModtag.xml');
//$xmlprocess->set_limit(100000);
$xmlprocess->init();


