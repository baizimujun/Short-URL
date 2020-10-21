<?php
class PublicAction extends CommonAction 
{

	public function do360()
	{
		header("Content-type:text/html;charset=utf-8");
		echo '正在开始更新恶意域名数据库...<br>';
		$content = fopen_url('http://webscan.360.cn/url');
		$pattern = "#\/url\/[0-9a-z\.-]+\.html#";
		preg_match_all($pattern,$content,$matches);
		$model = M('baddomain');
		echo '当前数据库记录总数：'.$model->count().'<br>';
		echo '正在开始更新数据,请不要关闭浏览器...';
		$data = array();
		foreach($matches[0] as $v)
		{
			$data['domain'] = substr($v,5,-5);
			if(!$model->where($data)->find())
			{
				$model->add($data);
			}
			
		}
		echo '更新后数据库记录总数：'.$model->count().'<br>';exit;
	}
	
	public function feedback()
	{
		if($GLOBALS['feedbackset']==0)
		{
			$this->error('站点反馈暂时关闭!',__ROOT__);
		}
		$this->display();
	}
	
	public function reurl()
	{
		if($GLOBALS['erweimaoff']==1)
		{
			$this->assign("erweima",1);
		}
		$this->display();
	}
	
	public function dofeedback()
	{
		if(empty($_POST['verify'])) 
		{
			$this->error('请输入验证码!');
		}
		elseif(strtolower($_POST['verify']) <>$_SESSION['verify'])
		{
			$this->error('验证码不正确!');
		}
		$model = M('feedback');
		$data['title'] = trim($_POST['title']);
		$data['content'] = trim($_POST['content']);
		$data['addtime'] = time();
		if($model->add($data))
		{
			$this->success('感谢您的提议,我们会进一步以此改进!',__ROOT__);
		}
		else
		{
			$this->error('内部错误，请重试！');
		}
	}
	
    public function index()
	{
		jump(U('User/myfile'));
	}
	
	public function reg()
	{
		if($GLOBALS['cfg_mb_allowreg']==0)
		{
			$this->error('系统暂停了新用户注册，请联系管理员！');
		}
		if(USER_LOGINED) $this->error('请先登出~');
		$this->display();
	}
	
	public function doreg()
	{
		global $cfg_mb_allowreg;
		if($cfg_mb_allowreg==0) $this->error('系统暂停新用户注册!');
		$map['username'] = trim($_POST['username']);
		$map['password'] = trim($_POST['password']);
		$map['email'] = trim($_POST['email']);
		$map['sex'] = trim($_POST['sex']);
		$map['birthday'] = trim($_POST['birthday']);
		$map['province'] = trim($_POST['province']);
		$map['city'] = trim($_POST['city']);
		$map['qq'] = trim($_POST['qq']);
		$map['avtar'] = '';	
		if(!empty($cfg_mb_notallow))
		{
			$pattern = explode(',',$cfg_mb_notallow);
			if(in_array($map['username'],$pattern)) $this->error('当前用户名不允许注册,请更换!');
		}
		$cfg_mb_idmin = 3;
		$cfg_mb_pwdmin = 6;
		if(strlen($map['username']) < $cfg_mb_idmin) $this->error('用户帐号最小长度为:'.$cfg_mb_idmin);
		if(strlen($map['password']) < $cfg_mb_pwdmin) $this->error('用户密码最小长度为:'.$cfg_mb_pwdmin);
		if(strcmp($map['password'],trim($_POST['repassword'])) <> 0) $this->error('密码和确认密码不一致!');
		$map['password'] = xmd5($map['password']);
		$model = M('member');
		if($model->where(array('username'=>$map['username']))->find()) $this->error('当前用户名已经注册！');
		if($model->where(array('email'=>$map['email']))->find()) $this->error('email:'.$map['email'].'&nbsp;已经注册过了!');
		$map['status'] = 0;
		$map['regtime'] = time();
		$map['logintime'] = time();
		$map['loginip'] = get_client_ip();
		$map['money'] = 0;
		$map['rankid'] = 1;
		$map['activekey'] = '';
		$model->add($map);
		//模拟登陆
		$list = $model->where(array('username'=>$map['username']))->find();
		cookie('uid',$list['id'],time()+3600);
		session('uid',$list['id']);
		cookie('uname',urlencode($list['username']),time()+3600);
		cookie('wkcode',xmd5($list['id'].$list['username'],3));
		$this->success('注册成功!',U('User/myfile'));
	}

	public function login()
	{
		if(USER_LOGINED)  $this->error('您已经登陆过了~');
		$this->display();
	}
	
	public function dologin()
	{
		$map['username'] = trim($_POST['username']);
		$model = M('member');
		$list = $model->where($map)->find();
		if(!$list) $this->error('用户信息不存在!');
		$map['password'] = trim($_POST['password']);
		if(strcmp(xmd5($map['password']),$list['password']) <> 0) $this->error('密码不正确!');
		$model->where('id='.$list['id'])->setField(array('logintime'=>time(),'loginip'=>get_client_ip()));
		cookie('uid',$list['id'],time()+3600);
		session('uid',$list['id']);
		cookie('uname',urlencode($list['username']),time()+3600);
		cookie('wkcode',xmd5($list['id'].$list['username'],3));
		$url = !empty($_POST['fromurl']) ? $_POST['fromurl']:U('User/myfile');
		$this->success('登陆成功!',$url);
	}
	
	public function loginout()
	{
		cookie('uid',null);
		cookie('uname',null);
		cookie('wkcode',null);
		$url = !empty($_GET['fromurl']) ? $_GET['fromurl']:U('Public/login');
		$this->success('登出成功!',$url);
	}
	
	public function verify()
	{
		import("ORG.Verify");
		$verify = new Verify();
		$verify->display();
	}
	
	public function  doregbangding()
	{
		global $cfg_mb_allowreg;
		if($cfg_mb_allowreg==0) $this->error('系统暂停新用户注册!');
		$map['username'] = trim($_POST['username']);
		$model = M('member');
		$list = $model->where($map)->find();
		if($list)
		{
			$this->error('当前用户名已经注册！');
		}
		$map['activekey']  = trim($_POST['openid']);
		if(empty($map['activekey'])) $this->error('参数缺失！');
		if($model->where(array('activekey'=>$map['activekey']))->find())
		{
			$this->error('当前用户信息已注册!');
		}
		$pwd = uniqid();
		$map['password'] = xmd5($pwd);
		$map['sex'] = 1;
		$map['status'] = 0;
		$map['email'] = trim($_POST['email']);
		$map['logintime'] = time();
		$map['regtime'] = time();
		$map['loginip'] = get_client_ip();
		$map['money'] = 0;
		$map['rankid'] = 1;
		$map['province'] = '北京';
		$map['city'] = '北京';
		$map['birthday'] = '1988-01-23';
		$map['avtar'] = '';
		$model->add($map);
		$list = $model->where(array('username'=>$map['username']))->find();
		cookie('uid',$list['id'],time()+3600);
		session('uid',$list['id']);
		cookie('uname',urlencode($list['username']),time()+3600);
		cookie('wkcode',xmd5($list['id'].$list['username'],3));
		$this->assign('waitSecond',5);
		$this->success("注册成功,您的账户为：{$list['username']} 初始化密码：{$pwd};请妥善保管。",U('User/myfile'));
	}
	
	public function freeqq()
	{
		if(!USER_LOGINED)  $this->success('请先登录！',U('Public/login'));
		$model = M('member');
		$model->where('id='.cookie('uid'))->setField('activekey','');
		$this->success('解除绑定成功！',U('User/myfile'));
	}
	
	public function downfile()
	{
		$u = trim($_GET['url']);
		if(empty($u)) $this->error('参数错误！');
		$filepath = 'http://chart.apis.google.com/chart?cht=qr&&chs=300x300&chl='.$u;
		ob_end_clean();
		header('Cache-control: max-age=31536000');
		header('Expires: '.gmdate('D, d M Y H:i:s', time() + 31536000).' GMT');
		header('Content-Encoding: none');
		header('Content-Disposition: attachment; filename=download.jpg');
		header('Content-type: image/jpeg'); 
		readfile($filepath);
		exit;
	}
	
	public function regtest()
	{
		$data['username'] = $_POST['username'];
		if(empty($data['username'])) die();
		$model = M('member');
		if($model->where($data)->find()) echo 1;die();
		die();
	}

}