--TEST--
Check for yod database
--SKIPIF--
<?php
if (!extension_loaded("yod") || defined('YOD_RUNMODE') || !class_exists('PDO', false)) {
	print "skip";
} else {
	try {
		$config = include dirname(__FILE__) . '/configs/db_dsn.config.php';
		new PDO($config['dsn'], $config['user'], $config['pass']);
	} catch (Exception $e) {

	}
}
?>
--FILE--
<?php
error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

define('YOD_RUNPATH', dirname(__FILE__));

class IndexController extends Yod_Controller
{
	public function indexAction()
	{
		$db = Yod_Database::db();
		$fields = array(
			'id' => 'int(11) NOT NULL AUTO_INCREMENT COMMENT \'ID\'',
			'title' => 'varchar(255) NOT NULL COMMENT \'标题\'',
			'content' => 'text DEFAULT NULL COMMENT \'内容\'',
			'updated' => 'int(11) NOT NULL DEFAULT \'0\' COMMENT \'更新时间\'',
			'created' => 'int(11) NOT NULL DEFAULT \'0\' COMMENT \'创建时间\'',
			'status' => 'tinyint(2) NOT NULL DEFAULT \'0\' COMMENT \'状态\'',
			'PRIMARY' => 'KEY (`id`)',
		);
		$extend = 'ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT=\'Demo\' AUTO_INCREMENT=1';
		echo $db->create($fields, 'tests', $extend);

		$tests = $this->model('Tests');

		$data = array(
			'title' => 'Tests',
			'content' => 'Yod PHP Framework',
			'created' => 12345678901,
		);
		echo $tests->save($data);
		

		$demo = $this->model('Demo');

		$data = $demo->from('tests')->where('id = :id', array(':id' => 1))->find();
		print_r($data);

		echo $db->execute('DROP TABLE yod_tests');
		
	}
}

class DemoModel extends Yod_DbModel
{

}
?>
--EXPECTF--
01Array
(
    [id] => 1
    [title] => Tests
    [content] => Yod PHP Framework
    [updated] => 0
    [created] => 2147483647
    [status] => 0
)
0
