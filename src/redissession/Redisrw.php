<?php
namespace redissession;
// redis的扩展,公共空间
use redis;
/**
 * 采用一主多从，主从复制，主写从读的设计
 * Redis缓存驱动 
 * 要求安装phpredis扩展：https://github.com/nicolasff/phpredis
 * @category   	redissession
 * @package  	sessionCache
 * @subpackage  Driver
 * @author      huangzengbing
 */
class Redisrw {
	/**
	*类对象实例数组
	*共有静态变量
	*@param mixed $_instance存放创建的实例
	*/
	private static $_instance=array();

	/**
	*有效的实例
	*
	*/
	private static $_validInstance=array();
	/**
	*每次实例的句柄
	*保护变量
	*/
	public $handler;

	/**
	*redis的配置
	*全局 静态变量
	*静态的方法里调用静态变量和静态方法只能用self，不能出现$this
	*/
	static $option=array();

	/**
	*采用单例（单件模式）
	*架构函数，必须设置为私有，防止外部new
	*实例化redis驱动的实例，即一个socket
	*
	*/
	private function __construct($host,$port,$auth) {
		if(!isset($this->handler)) {
			$this->handler= new Redis;
		}
        $func = self::$option['persistent'] ? 'pconnect' : 'connect';
        if(self::$option['timeout'] === false) {
			$this->handler->$func($host,$port);
		}else {
			$this->handler->$func($host,$port,self::$option['timeout']);
		}

		// 认证
		if($auth){
			$this->handler->auth($auth);
		}
	}

	/**
	*实例函数，单例入口getInstance
	*实例化多个主从的redis对象
	*公有，静态函数
	*redis的选项配置参数，$options
	*$options=array(
	*	//字符串，多个host,用,隔开，eg:'192.167.1.127,192.168.1.131',默认第一个是master，其余是slave
	*	'host'=>''，	
	*	'port'=>'',  //string,分别对应host的redis的端口
	*	'auth'=>'',  //string,分别对应host的redis的密码
	*	'expire'=>int   //int,缓存时间，单位s
	*	'timeout'=>int   //int,redis连接时间
	*	'persistent'=>boolean //true代表pconnect,false代表connect
	*)
	*/

	public static function getInstance($options=array()) {
		// 判断是否存在redis扩展
		if (!extension_loaded('redis') ) {
            trigger_error("没有找到redis扩展");
            return false;
        }
        if(empty($options)) {
            trigger_error("redis的参数为空");
            return false;
        }
        // 将字符串的配置项转为数组
		$options['host'] = explode(',', $options['host']);
		$options['port'] = explode(',', $options['port']);
		$options['auth'] = explode(',', $options['auth']);
		foreach ($options['host'] as $key=>$value) {
			if (!isset($options['port'][$key])) {
				$options['port'][$key] = $options['port'][0];
			}
			if (!isset($options['auth'][$key])) {
				$options['auth'][$key] = $options['auth'][0];
			}
			//根据redis的实例个数,创建null值数组
			self::$_instance[$key] = null;
		}
        self::$option =  $options;
        self::$option['expire'] =  isset($options['expire']) ?  $options['expire']  :   30;
        self::$option['timeout'] =  isset($options['timeout']) ?  $options['timeout']  :  0;
        // 一次性创建redis的在不同host的实例
        foreach(self::$option['host'] as $i=>$server) {
        	$host=self::$option['host'][$i];
        	$port=self::$option['port'][$i];
        	$auth=self::$option['auth'][$i];
			if(!(self::$_instance[$i] instanceof self)) {
				$obj = new self($host,intval($port),$auth);
				//socket的连接实例是否存在 
				if(isset($obj->handler->socket)) {
					self::$_instance[$i] = $obj;
				}
				// 防止其中一个redis实例宕机,要跳过该实例
				continue;	
			}
		}
		// 实例去空,可能已经宕机的实例将从实例组中移除
		foreach (self::$_instance as $key =>$value){
			if($value){
				array_push(self::$_validInstance,$value);
			}
		}

		// 默认返回第一个实例，即master
		return self::$_validInstance[0];
	}

	/**
	*判断是否master/slave,调用不同的master或者slave实例
	*
	*/
	public function is_master($master=true) {
		if($master) {
			$i=0;
		}else {
			$count=count(self::$_validInstance);
			if($count==1) {
				$i=0;
			}else{
				$i=rand(1,$count - 1);
			}
		}
		//返回每一个实例的句柄
		return self::$_validInstance[$i]->handler;
	}

	/**
	*在执行session_start()函数后调用，一般在这里是连接redis，创建实例的。
	*但因为用到单例模式，事实已经在getInstance函数中创建，并返回new self对象。
	*open可以不做任何操作，必须要定义。
	*/
	public function open($savePath,$sessionName) {
		
	}
	
	/**
     * 读取缓存session，随机从slave服务器中读缓存
     * @access public
     * @param string $sessionId 缓存sessionId变量名
     * @return mixed
     */
    public function read($sessionId) {
		$redis=$this->is_master(false);
        $value = $redis->get($sessionId);
        $jsonData  = json_decode($value,true);
        //检测是否为JSON数据 true 返回JSON解析数组, false返回原数据
        return ($jsonData === NULL) ? $value : $jsonData;	

    }

    /**
     * 写入缓存session，写入master的redis服务器
     * @access public
     * @param string $sessionId 缓存sessionId变量名
     * @param mixed $value  存储数据
     * @param integer $expire  有效时间（秒）
     * @return boolean
     */
    public function write($sessionId,$value) {
		$redis=$this->is_master(true);
        $expire  =  self::$option['expire'];
        //对数组/对象数据进行缓存处理，保证数据完整性
        $value  =  (is_object($value) || is_array($value)) ? json_encode($value) : $value;
        if(is_int($expire) && $expire > 0) {
            $result = $redis->setex($sessionId, $expire, $value);
        }else{
            $result = $redis->set($sessionId, $value);
        }
    }

    /**
    *关闭redis的socket连接
    *
    */
    public function close() {

    }

    /**
    * 删除session,比如退出登录，就把该session删除
    * @access public
    * @return boolean
    */
    public function destroy($sessionId) {
		$redis=$this->is_master(true);
		if($redis->del($sessionId)){
			return true;
		}else{
			return false;
		}
    }
    /*
    *垃圾回收
    */
    public function gc() {

    }
    /**
    *禁止外部克隆对象  
    *
    */
    private function __clone() {

    }

} 