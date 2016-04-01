<?php
namespace redissession;
use redissession\Redisrw;
class SessionInit {

	/**
    *redis的处理类的对象
    */
    public $handlerObj;
    /**
    *session的初始化
    *option array 初始化参数
    */
	public function __construct($redis_option,$session_option=array()) {
        //session的配置项 
	    if(isset($option['name']))            session_name($option['name']);
        if(isset($option['path']))            session_save_path($option['path']);
        if(isset($option['domain']))          ini_set('session.cookie_domain', $option['domain']);
        if(isset($option['expire']))          ini_set('session.gc_maxlifetime', $option['expire']);
        if(isset($option['use_trans_sid']))   ini_set('session.use_trans_sid', $option['use_trans_sid']?1:0);
        if(isset($option['use_cookies']))     ini_set('session.use_cookies', $option['use_cookies']?1:0);
        if(isset($option['cache_limiter']))   session_cache_limiter($option['cache_limiter']);
        if(isset($option['cache_expire']))    session_cache_expire($option['cache_expire']);

        // 自定义session的处理函数，本质和redis联系起来
        $this->handlerObj = Redisrw::getInstance($redis_option);
        session_set_save_handler(
                array(&$this->handlerObj,"open"),
                array(&$this->handlerObj,"close"), 
                array(&$this->handlerObj,"read"), 
                array(&$this->handlerObj,"write"), 
                array(&$this->handlerObj,"destroy"), 
                array(&$this->handlerObj,"gc"));
        // 防止出现不可预期的行为
        register_shutdown_function('session_write_close');
        // session 启动
        session_start();
	}
} 
?>