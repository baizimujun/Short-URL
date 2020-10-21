<?php
class CommonAction extends Action 
{
	function _initialize() 
	{
		$model = M('config');
		$list = $model->select();
		foreach($list as $v)
		{
			$GLOBALS[$v['varname']] =trim($v['value']);
		}
		$this->isLogin() ? define('USER_LOGINED',true) : define('USER_LOGINED',false) ;
		if(USER_LOGINED==true)
		{
			$model = M('member');
			$list = $model->where(array('id'=>cookie('uid')))->find();
			$GLOBALS['member'] = $list;
			if($list['status']==1 && !in_array(MODULE_NAME,array('Index','Api','User','Public'))) jump(U('User/myfile'));
		}
		if($GLOBALS['weboff']==0)
		{
			header("Content-type:text/html;charset=utf-8");
			//检测管理员登陆
			$uname = cookie('uname');
			$uid = cookie('uid');
			if(empty($uname) or empty($uid))
			{
				
				die($GLOBALS['weboffmsg']);
			}

			if(session('cmsauth')<>substr(md5(strrev(cookie('uname')).'waikucms'.cookie('uid')),0,10))
			{
				die($GLOBALS['weboffmsg']);
			}
		}
		define('URL_CALLBACK', getroot(1).'index.php?m=Index&a=callback&type=');
		$autharr = array('qq','renren','douban','baidu','tencent','t163','taobao','x360','sohu','kaixin','google','msn','diandian');
		foreach($autharr as $v)
		{
			C('THINK_SDK_'.strtoupper($v).'.APP_KEY',$GLOBALS[$v.'_appkey']);
			C('THINK_SDK_'.strtoupper($v).'.APP_SECRET',$GLOBALS[$v.'_appsecret']);
			C('THINK_SDK_'.strtoupper($v).'.CALLBACK',URL_CALLBACK . $v);
		}
			C('THINK_SDK_SINA.APP_KEY',$GLOBALS['weibo_appkey']);
			C('THINK_SDK_SINA.APP_SECRET',$GLOBALS['weibo_appsecret']);
			C('THINK_SDK_SINA.CALLBACK',URL_CALLBACK . 'sina');
	}
	
	protected function isLogin()
	{
		$uname = cookie('uname');
		$uid = cookie('uid');
		$sid = session('uid');
		if(strcmp($uid,$sid)<>0) return false;
		$wkcode = cookie('wkcode');
		if(empty($uname) || empty($uid) || empty($wkcode))
		{
			return false;
		}
		return true;
	}
}