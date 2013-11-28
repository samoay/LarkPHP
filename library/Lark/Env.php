<?php
namespace Lark;

use Lark\Exception\NoAutoloaderDefinedException;

require_once 'Constants.php';

/**
 * Global App Environment Manager
 * 
 * @author samoay
 *
 */
class Env{
	/**
	 * The name of this framework
	 * @var string
	 */
    public static $codename = 'Lark';

    /**
     * Current version of this framework
     * @var string
     */
    public static $version = '0.0.1';

	/**
	 * Framework base configuration
	 * @var array
	 */
	private static $_options;

	/**
	 * Pool of singleton instance of common object like cache handler/logger
	 * @var array
	 */
	private static $_instancesPool;

	/**
	 * Remember is session already started
	 * @var boolean
	 */
	private static $_sessionStarted = false;

	/**
	 * Initialize function, init the framework options
	 * @param array $options
	 */
	public static function init(array $options=array()){
		//some default options to keep this framework without any outer options
		$upperCodename = strtoupper(self::$codename);
		$default = array(
			'project'          => 'test',
			'projectName'      => 'Test Project',
			'projectPath'      => dirname(__FILE__).'/../../',
			'namespace'        => 'Larktest',
			'platform'         => !empty($_SERVER["{$upperCodename}_PLATFORM"]) ? $_SERVER["{$upperCodename}_PLATFORM"] : 'foo',
			'libPath'          => dirname(__FILE__).'/../',
			'logDir'           => sys_get_temp_dir() . DIRECTORY_SEPARATOR . strtolower(self::$codename) . '_logs',
			'logTypes'         => array('action', 'error', 'debug', 'exception'),
			'configDir'        => 'config',
			'timezone'         => 'Asia/Shanghai',
			'charset'          => 'UTF-8',
			'dataEngine'       => '\Lark\Mysql',
			'router'           => array('\Lark\Router', 'routeController'),
			'autoloader'       => array('\Lark\Env', 'defaultAutoloader'),
			'errorHandler'     => array('\Lark\Env', 'defaultErrorHandler'),
			'error_reporting'  => E_ALL & ~E_NOTICE,
			'shutdownFunction' => array('\Lark\Env', 'defaultShutdownFunction'),
		);

		$requestOptions = isset($_REQUEST['Lark']) ? $_REQUEST['Lark'] : array();
		foreach($options as $key=>$value){
		    $default[$key] = $value;
		    if (isset($requestOptions[$key])){
		        $default[$key] = $requestOptions[$key];
		    }
		}

		error_reporting($default['error_reporting']);

		$default['projectPath'] = realpath($default['projectPath']);
		$default['libPath'] = realpath($default['libPath']);

		self::$_options = $default;

		set_error_handler(self::$_options['errorHandler'], $default['error_reporting']);
		register_shutdown_function(self::$_options['shutdownFunction']);
		spl_autoload_register(self::$_options['autoloader']);

		if (!empty(self::$_options['timezone'])){
		    date_default_timezone_set(self::$_options['timezone']);
		}
	}

	/**
	 * Return the value of a special option
	 * @param string $optionName
	 * @return NULL
	 */
	public static function getOption($optionName){
	    if (isset(self::$_options[$optionName])){
	        return self::$_options[$optionName];
	    }else{
	        return null;
	    }
	}

	/**
	 * Start Session method for ensure session will only be started once
	 */
	public static function startSession(){
	    if (!self::$_sessionStarted){
	        session_start();
	        self::$_sessionStarted = true;
	    }
	}

	/**
	 * Default autoloader for framework
	 * @param string $className
	 * @throws \Exception
	 */
	public static function defaultAutoloader($className){
	    $classParts = explode('\\', $className);
	    if (count($classParts)<2){
	        throw new NoAutoloaderDefinedException(sprintf('try to autoload class named %s', $className));
	    }

	    $baseNamespace = array_shift($classParts);
	    $subNamespace = array_shift($classParts);
	    if ($baseNamespace===self::$codename){
	        $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $className);
	        $filePath = self::getOption('libPath') . DIRECTORY_SEPARATOR . $classPath . '.php';
	    }elseif($baseNamespace===self::getOption('namespace')){
	        $classPath = implode('/', $classParts);
	        $filePath = self::getOption('projectPath') . DIRECTORY_SEPARATOR . self::getOption('project') . DIRECTORY_SEPARATOR . strtolower($subNamespace) . DIRECTORY_SEPARATOR .$classPath . '.php';
	    }else{
	    	$classPath = str_replace('\\', DIRECTORY_SEPARATOR, $className);
	        $filePath = self::getOption('libPath') . DIRECTORY_SEPARATOR . $classPath . '.php';
	    }

	    if (file_exists($filePath)){
	        require $filePath;
	    }else{
	        throw new \Exception(sprintf('path %s not found', $filePath));
	    }
	}

	/**
	 * Default error handler for framework
	 */
	public static function defaultErrorHandler(){
	    echo "<pre>";
	    print_r(debug_backtrace());
	    echo "</pre>";
	}

	/**
	 * Default shutdown function for framework
	 */
	public static function defaultShutdownFunction(){
		$lastError = error_get_last();
		if ($lastError){
			echo "<pre>";
			print_r($lastError);
			echo "</pre>";
		}
	}
	
	/**
	 * Return the singleton instance of a common object from the instance pool
	 * @param string $className
	 * @return mixed
	 */
	public static function getInstance($className){
		$fullClassName = self::$codename . '\\' . $className;
	    if (empty(self::$_instancesPool[$fullClassName])){
	        $instance = $fullClassName::getInstance();
	        self::$_instancesPool[$fullClassName] = $instance;
	        return $instance;
	    }else{
	        return self::$_instancesPool[$fullClassName];
	    }
	}

	/**
	 * Execute the request
	 */
	public static function execute(){
	    call_user_func(self::getOption('router'));
	}
}