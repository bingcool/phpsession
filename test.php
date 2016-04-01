<?php
	// 导入自动注册文件类
	require_once './src/redissession/Autoload.php';
	// 执行自动注册加载类
	\redissession\Autoload::register();
	// 设置redis连接参数，默认第一个是master，其余是slave
	$redis_option=array(
			'host'=>'192.168.1.127,192.168.1.127,192.168.1.127',
			'port'=>'6380,6379,6381',
			'expire'=>120,
			'timeout'=>0,
			'persistent'=>true,
			'auth'=>''
	);
	// 创建实例
	$obj = new \redissession\SessionInit($redis_option);

	//测试
	if(isset($_SESSION['klmm'])) {
		var_dump('session存在');
		// 删除session
		session_unset();
        session_destroy();
	}else {
		var_dump('session不存在');
		// 写入字符串
		$_SESSION['klmm']='kkkkkk快快快';
		// 写入数组
		$_SESSION['hhhhh']=array(
				'name'=>'bing',
				'number'=>123456
			);	
	}
	