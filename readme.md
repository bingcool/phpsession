phpsession主要是将session和redis结合起来，建立分布式的session缓存系统,对于大规模的登录和大流量网站是可以起到很好缓存的作用。
注意：
（1）首先要安装phpredis的扩展
（2）要配置redis的多个主从实例，参考我的博客：
	http://blog.csdn.net/u012979009/article/details/50423864

主要实现以下几个功能：
（1）主从复制，主写从读，单例模式，命名空间
（2）整合自动注册加载类的功能，在非框架和tp框架中都可以快速使用。
存在问题：
因为redis2.8版本没有整合集群，所以没法实现监控和自动转移故障，但是从redis服务器slave挂掉，仍然不会有影响读session信息，但是主redis挂掉，就没法写，但仍然没有影响读。

下面是测试example:

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
	if(isset($_SESSION['address'])) {
		var_dump('session存在');
		// 删除session
		session_unset();
        session_destroy();
	}else {
		var_dump('session不存在');
		// 写入字符串
		$_SESSION['address']='广州';
		// 写入数组
		$_SESSION['info']=array(
				'name'=>'bing',
				'number'=>123456
			);	
	}

