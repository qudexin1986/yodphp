<?php
//set_time_limit(0);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

defined('YOD_RUNMODE') or define('YOD_RUNMODE', 1|2|4|8);
defined('YOD_PATHVAR') or define('YOD_PATHVAR', 'r');
defined('YOD_RUNPATH') or define('YOD_RUNPATH', dirname(__FILE__) . '/testapp');
defined('YOD_EXTPATH') or define('YOD_EXTPATH', dirname(__FILE__) . '/../yodphp');
defined('YOD_LOGPATH') or define('YOD_LOGPATH', dirname(__FILE__) . '/logs/' . date('Ym'));

defined('APP_WEBROOT') or define('APP_WEBROOT', '/index.php');

class_exists('Yod_Application', false) or require dirname(__FILE__) . '/../yodphp/yodphp.php';
