<?php
// +----------------------------------------------------------------------
// | yodphp [ Yod PHP Framework ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://yodphp.duapp.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Baoqiang Su <zmrnet@qq.com>
// +----------------------------------------------------------------------

// yodphp config constant
defined('YOD_RUNTIME') or define('YOD_RUNTIME', microtime(true));
defined('YOD_VERSION') or define('YOD_VERSION', '1.0.2');
defined('YOD_FORWARD') or define('YOD_FORWARD', 5);

defined('YOD_DEBUG') or define('YOD_DEBUG', false);

defined('YOD_PATH') or define('YOD_PATH', dirname(__FILE__));
defined('APP_PATH') or define('APP_PATH', dirname(__FILE__));

/**
 * __autoload
 * 
 * @param string $classname
 */
function __autoload($classname)
{
	$classfile = $classname;
	// class name with namespace in PHP 5.3
	if (strpos($classname, '\\') !== false) {
		$classfile = str_replace('\\', '_', $classname);
	}

	if (substr($classfile, 0, 4) == 'Yod_') { // yodphp extends class
		if (strncmp(substr($classfile, 0, 6), 'Yod_Db', 6) == 0) {
			$directory = '/drivers/';
		} else {
			$directory = '/extends/';
		}
		$classpath = YOD_PATH . $directory . substr($classfile, 4) . '.class.php';
	} else {
		if (strncmp(substr($classname, -10), 'Controller', 10) == 0) {
			$directory = '/controllers/';
		} elseif (strncmp(substr($classname, -5), 'Model', 5) == 0) {
			$directory = '/models/';
		} else {
			$directory = '/extends/';
			$classfile = $classfile . '.class';
		}
		$classpath = APP_PATH . $directory . $classfile . '.php';
	}

	if (is_file($classpath)) include $classpath;

	return class_exists($classname, false) || interface_exists($classname, false);
}
spl_autoload_register('__autoload');

/**
 * Yod_Application
 * 
 */
final class Yod_Application
{
	protected static $_app;

	protected $_config = array();
	protected $_request = array();
	protected $_imports = array();
	protected $_running = false;

	/**
	 * __construct
	 * @access public
	 * @param mixed $config
	 * @return void
	 */
	public function __construct($config = null)
	{
		if (is_object(self::$_app)) {
			trigger_error('Only one application can be initialized', E_USER_ERROR);
			return;
		}

		// config
		if (is_null($config)) {
			$config = APP_PATH . '/configs/config.php';
		}
		if (is_string($config)) {
			if (is_file($config)) {
				$this->_config = include($config);
			} else {
				$this->_config = array();
				$confdir = dirname($config);
				if ($handle = opendir($confdir)) {
					while (($file = readdir($handle)) != false) {
						if (substr($file, -11) == '.config.php') {
							$key = substr($file, 0, -11);
							$value = include($confdir .'/'. $file);
							if (is_array($value)) {
								if ($key == 'base') {
									$this->_config = array_merge($this->_config, $value);
								}elseif (isset($this->_config[$key])){
									$this->_config[$key] = array_merge($this->_config[$key], $value);
								} else {
									$this->_config[$key] = $value;
								}
							}
						}
					}
					closedir($handle);
				}
			}
		} elseif (is_array($config)) {
			$this->_config = $config;
		}
		// request
		$this->_request = new Yod_Request();

		self::$_app = $this;
	}

	/**
	 * run
	 * @access public
	 * @param void
	 * @return void
	 */
	public function run()
	{
		if ($this->_running) {
			trigger_error('An application instance already running', E_USER_WARNING);
			return true;
		}
		$this->_running = true;

		$this->_request->dispatch();
	}

	/**
	 * config
	 * @access public
	 * @param mixed
	 * @return array
	 */
	public function config($name = null)
	{
		if (is_null($name)) {
			return $this->_config;
		} elseif(isset($this->_config[$name])) {
			return $this->_config[$name];
		} elseif(strstr($name, '.')) {
			$name = explode('.', $name);
			$value = $this->_config;
			foreach ($name as $skey) {
				if (isset($value[$skey])) {
					$value = $value[$skey];
				} else {
					return null;
				}
			}
			return $value;
		} else {
			return null;
		}
	}

	/**
	 * import
	 * @access public
	 * @param string $alias
	 * @return boolean
	 */
	public function import($alias)
	{
		if (empty($this->_imports[$alias])) {
			$classfile = str_replace('.', '/', $alias);
			$classname = basename($classfile);
			$classpath = APP_PATH . '/extends/' . $classfile . '.class.php';
			if (is_file($classpath)) include $classpath;
		}

		return class_exists($classname, false) || interface_exists($classname, false);
	}

	/**
	 * app
	 * @access public
	 * @param void
	 * @return Yod_Application
	 */
	public static function app()
	{
		return self::$_app;
	}

	/**
	 * __destruct
	 * @access public
	 * @param void
	 */
	public function __destruct()
	{

	}

}

/**
 * Yod_Request
 * 
 */
final class Yod_Request
{
	public $controller;
	public $action;
	public $params;
	public $method;

	/**
	 * __construct
	 * @access public
	 * @param string $route
	 * @return void
	 */
	public function __construct($route = null)
	{
		// method
		if (isset($_SERVER['REQUEST_METHOD'])) {
			$this->method = $_SERVER['REQUEST_METHOD'];
		} else {
			if (!strncasecmp(PHP_SAPI, 'cli', 3)) {
				$this->method = 'Cli';
			} else {
				$this->method = 'Unknown';
			}
		}
		// route
		if (empty($route)) {
			if (strtoupper($this->method) == 'CLI') {
				$route = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
			} else {
				$route = empty($_SERVER['PATH_INFO']) ? '' : $_SERVER['PATH_INFO'];
			}
		}
		$route = explode('/', str_replace('//', '/', trim($route, '/')));
		$count = count($route);

		$this->controller = empty($route[0]) ? 'Index' : ucfirst(strtolower($route[0]));
		$this->action = empty($route[1]) ? 'index' : strtolower($route[1]);
		$this->params = array();
		for ($i=2; $i<$count; $i+=2) {
			$_GET[$route[$i]] = empty($route[$i+1]) ? '' : $route[$i+1];
			$this->params[$route[$i]] = $_GET[$route[$i]];
		}

	}

	/**
	 * dispatch
	 * @access public
	 * @param void
	 * @return void
	 */
	public function dispatch()
	{
		$controller = empty($this->controller) ? 'Index' : $this->controller;
		$classpath = APP_PATH . '/controllers/' . $controller . 'Controller.php';
		if (is_file($classpath)) {
			require $classpath;
			$classname = $controller . 'Controller';
			new $classname($this);
		} else {
			$action = empty($this->action) ? 'Index' : ucfirst($this->action);
			$classpath = APP_PATH . '/actions/' . strtolower($controller) . '/' . $action . 'Action.php';
			if (is_file($classpath)) {
				require $classpath;
				$classname = $action . 'Action';
				new $classname($this);
			} else {
				$classpath = APP_PATH . '/controllers/ErrorController.php';
				if (is_file($classpath)) {
					require $classpath;
					new ErrorController($this, 'error');
				} else {
					$this->errorAction();
				}
			}
		}
	}

	/**
	 * errorAction
	 * @access public
	 * @return void
	 */
	public function errorAction()
	{
		$controller = empty($this->controller) ? 'index' : strtolower($this->controller);
		$classpath = APP_PATH . '/actions/' . $controller . '/ErrorAction.php';
		if (is_file($classpath)) {
			require $classpath;
			new ErrorAction($this);
		} else {
			$this->controller = 'Error';
			$classpath = APP_PATH . '/actions/ErrorAction.php';
			if (is_file($classpath)) {
				require $classpath;
				new ErrorAction($this);
			} else {
				$this->error404();
			}
		}
	}

	/**
	 * error404
	 * @access public
	 * @param mixed $html
	 * @return void
	 */
	public function error404($html = null)
	{
		header('HTTP/1.0 404 Not Found');
		if (is_null($html)) {
			if (strtoupper($this->method) == 'CLI') {
				$html = 'HTTP/1.0 404 Not Found';
			} else {
				$html = array(
					'<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">',
					'<html><head>',
					'<title>404 Not Found</title>',
					'</head><body>',
					'<h1>Not Found</h1>',
					'<p>The requested URL ' . $_SERVER['PHP_SELF'] . ' was not found on this server.</p>',
					'</body></html>',
				);
				$html = implode(PHP_EOL, $html);
			}
		}
		exit($html);
	}
}

/**
 * Yod_Controller
 * 
 */
abstract class Yod_Controller
{
	protected static $_forward = 0;

	protected $_name;
	protected $_action;
	protected $_request;

	protected $_view = array(
		'tpl_data' => array(),
		'tpl_path' => '',
		'tpl_file' => '',
	);

	protected $_exited = false;

	/**
	 * __construct
	 * @access public
	 * @param Yod_Request $request
	 * @param array $config
	 * @param mixed $action
	 * @return void
	 */
	public function __construct($request, $action = null)
	{
		$this->_name = strtolower($request->controller);
		$this->_action = empty($action) ? $request->action : strtolower($action);
		$this->_request = $request;
		$this->_view['tpl_path'] = APP_PATH . '/views/' . $this->_name;

		$this->init();
		$this->run();
	}

	/**
	 * init
	 * @access protected
	 * @return void
	 */
	protected function init()
	{

	}

	/**
	 * run
	 * @access protected
	 * @return void
	 */
	protected function run()
	{
		$method = ucfirst($this->_action) . 'Action';
		if (method_exists($this, $method)) {
			call_user_func_array(array($this, $method), $this->_request->params);
		} else {
			$classpath = APP_PATH . '/actions/' . $this->_name . '/' . $method . '.php';
			if (is_file($classpath)) {
				require $classpath;
				$action = new $method($this->_request);
				$action->run($this->_request->params);
			} elseif (method_exists($this, 'errorAction')) {
				$this->errorAction();
			} else {
				$this->_request->errorAction();
			}
		}
	}

	/**
	 * config
	 * @access protected
	 * @param string $name
	 * @return array
	 */
	protected function config($name = null)
	{
		return Yod_Application::app()->config($name);
	}

	/**
	 * import
	 * @access protected
	 * @param string $alias
	 * @return boolean
	 */
	protected function import($alias)
	{
		return Yod_Application::app()->import($alias);
	}

	/**
	 * model
	 * @access protected
	 * @param string $name
	 * @return array
	 */
	protected function model($name = '', $prefix = '', $config = '')
	{
		if (empty($name)) {
			$name = ucfirst($this->_name);
		}
		return Yod_Model::getInstance($name, $prefix, $config);
	}
	
	/**
	 * assign
	 * @access protected
	 * @param mixed $name
	 * @param mixed $value
	 * @return Yod_Controller
	 */
	protected function assign($name, $value = null)
	{
		if (is_array($name)) {
			foreach ($name as $key => $value) {
				$this->_view['tpl_data'][$key] = $value;
			}
		} else {
			$this->_view['tpl_data'][$name] = $value;
		}
		return $this;
	}

	/**
	 * render
	* @access protected
	 * @param mixed $view
	 * @param array $data
	 * @return string
	 */
	protected function render($view = null, $data = array())
	{
		if ($this->_exited) return;
		// tpl_file
		$view = empty($view) ? strtolower($this->_request['action']) : $view;
		$this->_view['tpl_file'] = $this->_view['tpl_path'] . '/' . $view . '.php';
		// tpl_data
		unset($this->_view['tpl_data']['this'], $data['this']);
		extract($this->_view['tpl_data']);
		if (is_array($data)) extract($data);
		// response
		ob_start();
		include $this->_view['tpl_file'];
		$response = ob_get_contents();
		ob_end_clean();

		return $response;
	}

	/**
	 * display
	 * @access protected
	 * @param mixed $view
	 * @param array $data
	 * @return void
	 */
	protected function display($view = null, $data = array())
	{
		echo $this->render($view, $data);
	}


	/**
	 * forward
	 * @access protected
	 * @param string $route
	 * @return void
	 */
	protected function forward($route, $exited = true)
	{
		if (self::$_forward++ > YOD_FORWARD) return;

		if (strpos($route,'/') === false) {
			$this->run($route);
		} else {
			$request = new Yod_Request($route);
			$request->dispatch();
		}

		if ($exited) $this->_exited = true;
	}

	/**
	 * redirect
	 * @access protected
	 * @param string $url
	 * @param integer $code
	 * @return void
	 */
	protected function redirect($url, $code = 302)
	{
		header('Location:' . $url, true, $code);
	}

	/**
	 * error404
	 * @access protected
	 * @param mixed $html
	 * @param boolean $exit
	 * @return void
	 */
	protected function error404($html = null)
	{
		$this->_request->error404($html);
	}

	/**
	 * __destruct
	 * 
	 * @return void
	 */
	public function __destruct() {

	}
}

/**
 * Yod_Action
 * 
 */
abstract class Yod_Action extends Yod_Controller
{
	/**
	 * run
	 * @access protected
	 * @return void
	 */
	protected function run()
	{

	}
}

/**
 * Yod_Model
 * 
 */
class Yod_Model
{
	protected static $_model = array();

	protected $_db;
	protected $_dsn;

	protected $_name;
	protected $_table;
	protected $_prefix;

	protected $_query = array();
	protected $_params = array();

	/**
	 * __construct
	 * @access public
	 * @return void
	 */
	public function __construct($name = '', $prefix = '', $config = '')
	{
		$this->init();

		if (empty($this->_dsn)) {
			$this->_dsn = $config;
		}
		if (empty($this->_db)) {
			$this->_db = Yod_Database::getInstance($this->_dsn);
		}
		if (empty($name)) {
			if (empty($this->_name)) {
				if (get_class($this) == __CLASS__) {
					$this->_name = '';
				} else {
					$this->_name = substr(get_class($this), 0, -5);
				}
			}
		} else {
			$this->_name = $name;
		}
		if (empty($this->_table)) {
			$this->_table = strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $this->_name), '_'));
		}
		if (empty($this->_prefix)) {
			$this->_prefix = empty($prefix) ? $this->_db->config('prefix') : $prefix;
		}
		$this->initQuery();

		self::$_model[$this->_name] = $this;
	}

	/**
	 * getInstance
	 * @access public
	 * @param mixed $config
	 * @return Yod_Model
	 */
	public static function getInstance($name, $prefix = '', $config = '')
	{
		$classname = $name .'Model';
		if (isset(self::$_model[$name])) {
			return self::$_model[$name];
		} elseif(class_exists($classname)) {
			return new $classname($name, $prefix, $config);
		} else {
			return new Yod_Model($name, $prefix, $config);
		}
	}

	/**
	 * init
	 * @access protected
	 * @param void
	 * @return void
	 */
	protected function init()
	{

	}

	/**
	 * find
	 * @access public
	 * @return mixed
	 */
	public function find($where = null, $params = array())
	{
		if (is_array($where)) {
			$params = $where;
			$where = null;
		}
		empty($where) or $this->where($where);
		$query = $this->parseQuery();
		$params = array_merge($this->_params, $params);
		$this->_db->query($query, $params);
		$data = $this->_db->fetch(true);
		$this->initQuery();
		$this->_params = array();

		return $data;
	}

	/**
	 * query
	 * @access public
	 * @return mixed
	 */
	public function query($where = null, $params = array())
	{
		if (is_array($where)) {
			$params = $where;
			$where = null;
		}
		empty($where) or $this->where($where);
		$query = $this->parseQuery();
		$params = array_merge($this->_params, $params);
		$this->_db->query($query, $params);
		$data = $this->_db->fetchAll();
		$this->initQuery();
		$this->_params = array();

		return $data;
	}

	/**
	 * count
	 * @access public
	 * @return mixed
	 */
	public function count($where = null, $params = array())
	{
		if (is_array($where)) {
			$params = $where;
			$where = null;
		}
		$this->_query['SELECT'] = 'COUNT(*)';
		empty($where) or ($this->_query['WHERE'] = "({$this->_query['WHERE']}) AND ({$where})");
		$this->_query['LIMIT'] = 1;
		$query = $this->parseQuery();
		$params = array_merge($this->_params, $params);
		$this->_db->query($query, $params);
		$this->initQuery();
		$this->_params = array();

		if ($data = $this->_db->fetch(true)) {
			return current($data);
		}
		return 0;
	}

	/**
	 * save
	 * @access public
	 * @return boolean
	 */
	public function save($data, $where = null, $params = array())
	{
		if (empty($data)) return false;
		$table = $this->_prefix . $this->_table;
		if (empty($where)) {
			return $this->_db->insert($data, $table);
		} else {
			return $this->_db->update($data, $table, $where, $params);
		}
	}

	/**
	 * remove
	 * @access public
	 * @return boolean
	 */
	public function remove($where, $params = array())
	{
		$table = $this->_prefix . $this->_table;
		return $this->_db->delete($table, $where, $params);
	}

	/**
	 * select
	 * @access public
	 * @return Yod_Model
	 */
	public function select($select)
	{
		if (is_array($select)) {
			$select = implode(', ', $select);
		}
		$this->_query['SELECT'] = $select;
		return $this;
	}

	/**
	 * from
	 * @access public
	 * @return Yod_Model
	 */
	public function from($table)
	{
		$this->_query['FROM'] = "`{$this->_prefix}{$table}` AS `t1`";
		return $this;
	}

	/**
	 * join
	 * @access public
	 * @return Yod_Model
	 */
	public function join($table, $where, $left = 'LEFT')
	{
		$alias = 't' . (count($this->_query['JOIN']) + 2);
		$this->_query['JOIN'][] = "{$left} JOIN `{$this->_prefix}{$table}` AS `{$alias}` ON {$where}";
		return $this;
	}

	/**
	 * where
	 * @access public
	 * @return Yod_Model
	 */
	public function where($where)
	{
		if ($this->_query['WHERE']) {
			$where = "({$this->_query['WHERE']}) AND ({$where})";
		}
		$this->_query['WHERE'] = $where;
		return $this;
	}

	/**
	 * group
	 * @access public
	 * @return Yod_Model
	 */
	public function group($group)
	{
		$this->_query['GROUP BY'] = $group;
		return $this;
	}

	/**
	 * having
	 * @access public
	 * @return Yod_Model
	 */
	public function having($having)
	{
		$this->_query['HAVING'] = $having;
		return $this;
	}

	/**
	 * order
	 * @access public
	 * @return Yod_Model
	 */
	public function order($order)
	{
		$this->_query['ORDER BY'] = $order;
		return $this;
	}

	/**
	 * limit
	 * @access public
	 * @return Yod_Model
	 */
	public function limit($limit)
	{
		$this->_query['LIMIT'] = $limit;
		return $this;
	}

	/**
	 * union
	 * @access public
	 * @return Yod_Model
	 */
	public function union($union)
	{
		$this->_query['UNION'] = $union;
		return $this;
	}

	/**
	 * comment
	 * @access public
	 * @return Yod_Model
	 */
	public function comment($comment)
	{
		$this->_query['COMMENT'] = $comment;
		return $this;
	}

	/**
	 * params
	 * @access public
	 * @return Yod_Model
	 */
	public function params($params)
	{
		$this->_params = empty($params) ? array() : $params;
		return $this;
	}

	/**
	 * lastQuery
	 * @access public
	 * @return string
	 */
	public function lastQuery()
	{
		return $this->_db->lastQuery();
	}

	/**
	 * initQuery
	 * @access protected
	 * @return string
	 */
	protected function initQuery()
	{
		$this->_query = array(
			'SELECT' => '*',
			'FROM' => '',
			'JOIN' => array(),
			'WHERE' => '',
			'GROUP BY' => '',
			'HAVING' => '',
			'ORDER BY' => '',
			'LIMIT' => '',
			'UNION' => '',
			'COMMENT' => '',
		);
	}

	/**
	 * parseQuery
	 * @access protected
	 * @return string
	 */
	protected function parseQuery($query = null)
	{
		$parse = array();
		$query = empty($query) ? $this->_query : $query;
		if (empty($query['FROM'])) {
			$query['FROM'] = "`{$this->_prefix}{$this->_table}` AS `t1`";
		}
		foreach ($query as $key => $value) {
			if (empty($value)) {
				continue;
			}
			if (is_array($value)) {
				if ($key == 'UNION') {
					$value = $this->parseQuery($value);
				} elseif($key == 'JOIN') {
					$value = implode(' ', $value);
					$key = '';
				}
			}
			$parse[] = $key . ' ' . $value;
		}

		return implode(' ', $parse);
	}

	/**
	 * config
	 * @access protected
	 * @param void
	 * @return array
	 */
	protected function config($name = null)
	{
		return Yod_Application::app()->config($name);
	}
	
	/**
	 * import
	 * @access protected
	 * @param string $alias
	 * @return boolean
	 */
	protected function import($alias)
	{
		return Yod_Application::app()->import($alias);
	}

	/**
	 * model
	 * @access protected
	 * @param string $name
	 * @return array
	 */
	protected function model($name = '', $prefix = '', $config = '')
	{
		if (empty($name)) return $this;
		return self::getInstance($name, $prefix, $config);
	}
}

/**
 * Yod_Database
 * 
 */
class Yod_Database
{
	protected static $_db = array();

	protected $_config;
	protected $_driver;
	protected $_linkid;
	protected $_linkids = array();

	protected $_queryid;
	protected $_lastsql = '';
	protected $_pconnect = false;

	/**
	 * __construct
	 * @access public
	 * @return void
	 */
	public function __construct($config)
	{
		$this->_config = $config;
	}

	/**
	 * __construct
	 * @access public
	 * @return void
	 */
	public function __destruct()
	{
		$this->close();
	}

	/**
	 * __call
	 * @access public
	 * @return mixed
	 */
	public function __call($method, $args)
	{
		if (empty($this->_driver)) {
			trigger_error('the database configuration errors', E_USER_WARNING);
		} else {
			trigger_error("Call to undefined method {$this->_driver}::{$method}()", E_USER_ERROR);
		}
	}

	/**
	 * getInstance
	 * @access public
	 * @param mixed $config
	 * @return Yod_Database
	 */
	public static function getInstance($config)
	{
		if (empty($config)) {
			$config = Yod_Application::app()->config('db_dsn');
		}
		if (is_string($config)) {
			$dbtype = '';
			if (substr($config, 0, 4) =='pdo_') {
				$config = substr($config, 4);
				$dbtype = 'pdo_';
			}
			$dbinfo = parse_url($config);
			if (isset($dbinfo['scheme'])) {
				$dbinfo['fragment'] = empty($dbinfo['fragment']) ? '' : $dbinfo['fragment'];
				$fragment = explode('/', trim($dbinfo['fragment'], '/'));
				$dbinfo['type'] = $dbtype . $dbinfo['scheme'];
				$dbinfo['dbname'] = empty($dbinfo['path']) ? 'test' : trim($dbinfo['path'], '/');
				$dbinfo['prefix'] = empty($fragment[0]) ? '' : $fragment[0];
				$dbinfo['charset'] = empty($fragment[1]) ? 'utf8' : $fragment[1];
			}
		} else {
			$dbinfo = $config;
		}

		$config = array(
			'type' => empty($dbinfo['type']) ? '' : strtolower($dbinfo['type']),
			'host' => empty($dbinfo['host']) ? '' : $dbinfo['host'],
			'port' => empty($dbinfo['port']) ? '' : $dbinfo['port'],
			'user' => empty($dbinfo['user']) ? '' : $dbinfo['user'],
			'pass' => empty($dbinfo['pass']) ? '' : $dbinfo['pass'],
			'dbname' => empty($dbinfo['dbname']) ? '' : $dbinfo['dbname'],
			'prefix' => empty($dbinfo['prefix']) ? '' : $dbinfo['prefix'],
			'charset' => empty($dbinfo['charset']) ? 'utf8' : $dbinfo['charset'],
			'params' => array(),
		);
		if (empty($config['type'])) {
			$classname = __CLASS__;
		} elseif(substr($config['type'], 0, 4) == 'pdo_') {
			$classname = 'Yod_DbPdo';
		} else {
			$classname = 'Yod_Db'.ucwords($config['type']);
		}
		$dbkey = md5(serialize($config));
		if (empty(self::$_db[$dbkey])) {
			self::$_db[$dbkey] = new $classname($config);
		}
		return self::$_db[$dbkey];
	}

	/**
	 * config
	 * @access public
	 * @param void
	 * @return array
	 */
	public function config($name = null, $value = null)
	{
		$argc = func_num_args();
		switch ($argc) {
			case 0:
				return $this->_config;
				break;
			case 1:
				return isset($this->_config[$name]) ? $this->_config[$name] : null;
				break;
			case 2:
				if (is_null($value)) {
					unset($this->_config[$name]);
				} else {
					$this->_config[$name] = $value;
				}
				break;
			default :
				$argv = func_get_args();
				array_shift($argv);
				$this->_config[$name] = $argv;
		}
	}

	/**
	 * active
	 * @access public
	 * @return mixed
	 */
	public function active($linknum)
	{
		if (isset($this->_linkids[$linknum])) {
			$this->_linkid = $this->_linkids[$linknum];
		}
		return $this;
	}

	/**
	 * fetchAll
	 * @access public
	 * @return mixed
	 */
	public function fetchAll()
	{
		if (empty($this->_driver)) {
			return false;
		}
		$result = array();
		while ($fetch = $this->fetch()){
			$result[] = $fetch;
		}
		return $result;
	}

	/**
	 * create
	 * @access public
	 * @return void
	 */
	public function create($sql, $params)
	{
		return $this->execute($sql, $params);
	}

	/**
	 * select
	 * @access public
	 * @return void
	 */
	public function select($sql, $params)
	{
		return $this->query($sql, $params);
	}

	/**
	 * insert
	 * @access public
	 * @return void
	 */
	public function insert($data, $table, $replace=false) {
		if (empty($data) || empty($table)) return false;
		$values = $fields = $params = array();
		foreach ($data as $key => $value){
			if(is_scalar($value) || is_null($value)) {
				$name = ':'. md5($key);
				$fields[] =  $key;
				$values[] = $name;
				$params[$name] = $value;
			}
		}
		$sql = ($replace ? 'REPLACE' : 'INSERT') .' INTO '. $table .' ('. implode(',', $fields) .') VALUES ('. implode(',', $values) .')';
		return $this->execute($sql, $params);
	}

	/**
	 * update
	 * @access public
	 * @return void
	 */
	public function update($data, $table, $where = null, $params = array())
	{
		if (empty($data) || empty($table)) return false;
		$update = $params1 = array();
		foreach ($data as $key => $value){
			if(is_scalar($value) || is_null(($value))) {
				$name = ':'. md5($key);
				$update[] = $key.'='.$name;
				$params1[$name] = $value;
			}
		}
		$params = array_merge($params1, $params);
		$sql = 'UPDATE '. $table. ' SET '.implode(',', $update) . (empty($where) ? '' : ' WHERE '. $where);
		return $this->execute($sql, $params);
	}

	/**
	 * delete
	 * @access public
	 * @return void
	 */
	public function delete($table, $where = null, $params = array())
	{
		if (empty($table)) return false;
		$sql = 'DELETE FROM '. $table . (empty($where) ? '' : ' WHERE '. $where);
		return $this->execute($sql, $params);
	}

	/**
	 * lastsql
	 * @access public
	 * @return void
	 */
	public function lastQuery()
	{
		return $this->_lastsql;
	}
}
