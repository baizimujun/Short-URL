<?php
class ApiAction extends CommonAction 
{
	public function chrome()
	{
		$format = trim($_GET['format']);
		$url = urldecode(trim($_GET['url']));
		$api = A('Index');
		$api->create($url);
	}
	
	public function ie()
	{
		if(IS_POST)
		{
			$url = urldecode(trim($_POST['url']));
			$api = A('Index');
			$api->create($url);
		}
		else
		{
			$this->display();
		}
	}
	
	public function addall()
	{
		$this->soft();
	}
	public function thirdapi()
	{
		$method = trim($_GET['api']);
		$url = trim($_POST['url']);
		$url = empty($url) ? 'http://www.baidu.com': $url;
		if($method=='dwz')
		{
			$baseurl  = "http://dwz.cn/create.php";
			$data=array('url'=>$url);
		}
		elseif($method=='126am')
		{
			$baseurl  = "http://126.am/api!shorten.action";
			$data=array('key'=>'7b7cb30d30824b2e9e02bb6960400df4','longUrl'=>$url);
			
		}
		elseif($method=='sina')
		{
			$baseurl = "https://api.weibo.com/2/short_url/shorten.json?source=1029649220&url_long={$url}";
			$data = array();
		}
		elseif($method=='isgd')
		{
			$baseurl = "http://is.gd/create.php?format=json&url={$url}";
			$data = array();
		}
		header('Content-type:text/json');
		$ch=curl_init();
		curl_setopt($ch,CURLOPT_URL,$baseurl);
		curl_setopt($ch,CURLOPT_POST,true);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		$strRes=curl_exec($ch);
		curl_close($ch);
		echo $strRes;
		exit;
	}
	
	public function soft()
	{
		if(IS_POST && $GLOBALS['createoff']<>0 && $GLOBALS['addallurloff']<>0)
		{
			global $addallurlnum;
			$addallurlnum = (int)$addallurlnum;
			$url = trim($_POST['multiurl']);
			$suffix = empty($GLOBALS['suffix']) ? '': '.'.$GLOBALS['suffix'];
			$arr  = explode("\n",$url);
			$list = array();
			foreach($arr as $k=>$v)
			{
				$a = $this->parseurl(trim($v),'');
				if($a && $addallurlnum-$k>1)
				{
					$list[] = parseDomain().$a['tinyurl'].$suffix;
				}
			}
			$liststr = implode("\n",$list);
			$this->assign('list',$liststr);
		}
		$this->display();
	}
	
	private function parseurl($url,$beizhu)
	{
		if(strpos($url,'://')===false)
		{
			$url = 'http://'.$url; 
		}
		if(!preg_match('@^[a-z]+://((\w+(-\w+)*)(\.(\w+(-\w+)*))+)(\?\S*)?@',$url,$matches))
		{
			return false;
		}
		else
		{	
			$url = strtr($url,$matches[0],strtolower($matches[0]));
			
		}
		//屏蔽自身短网址
		$root = getroot(1);
		if(substr($url.'/',0,strlen($root))==$root) 
		{
			return false;
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
				return false;
			}
		}
		if($GLOBALS['ruleoff']=='2')
		{
			//黑名单
			$arr = explode("$",$GLOBALS['blackrule']);
			foreach($arr as $v)
			{
				$v = trim($v);
				//支持 *.匹配
				$ban = strtolower(substr($v,9));
				if(substr($v,0,9)=='domain:*.' && strpos($predomain,$ban)==true)
				{	
					return false;
				}
				if(substr($v,0,7)=='domain:' && strtolower(substr($v,7))==$predomain)
				{
					return false;
				}
				if(substr($v,0,4)=='url:' && strtolower(substr($v,4))== $preallurl)
				{
					return false;
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
					//json_error('当前网址为钓鱼网址！');
					return false;
				}
				elseif($result['phish']==2)
				{
					//json_error('当前网址有高风险，有钓鱼嫌疑！');
					return false;
				}
				elseif($result['phish']==0)
				{
					//json_error('当前网址安全！');
				}
				elseif($result['phish']=='-1')
				{
					//json_error('当前网址安全性未知！');
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
					//json_error('当前网址为危险的下载链接！');
					return false;
				}
				elseif($result['down_type']==2)
				{
					//json_error('当前网址为下载地址，并且很安全！');
				}
				elseif($result['down_type']==1)
				{
					//json_error('当前网址为下载地址，安全性未知！');
				}
				elseif($result['down_type']==6)
				{
					//json_error('当前网址不是下载地址');
				}
			}
		}
		$model = M('info');
		$tinyurl = $model->field('tinyurl')->select();
		$list = $model->where(array('longurl'=>$url))->find();
		if($list)
		{
			 return $list;
		}
		else
		{
			$data['longurl'] = $url;
			$data['mid'] = 0;
			$tinyurlarr = $model->field('tinyurl')->select();
			$data['tinyurl'] = getfreetiny($tinyurl);
			$data['addtime'] = time();
			$data['type'] = 1;
			$data['pwd'] ='';
			$data['beizhu'] = $beizhu;
			$model->add($data);
			$data['id'] = $model->order('id desc')->limit('0,1')->getField('id');
			return $data;
		}
	}
	
	public function safeurlapi($url="")
	{
		header('Content-type:text/json;charset=utf-8');
		$json = array();
		if(empty($url)){
			$url = rtrim(trim($_POST['url']),'/');
		}
		//api保护
		if(strcmp(trim($_GET['appkey']),'dwzse')<>0)
		{
				json_error('接口密钥信息错误!');
		}
		if(strcmp(trim($_GET['appsecret']),'123456')<>0)
		{
				json_error('接口密钥信息错误!');
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
		
		//<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>流行恶意域名屏蔽
		if($GLOBALS['dwzbdmoff']==1)
		{
			$dwzbd = M('baddomain');
			$urlinfo = parse_url($url);
			if($dwzbd->where(array('domain'=>$urlinfo['host']))->find())
			{
				json_error('<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>检测当前网址为高危恶意网址！');
			}
			unset($dwzbd);
		}
		if($GLOBALS['dwzbdioff']==1)
		{
			$dwzbdinfo = M('badinfo');
			if($dwzbdinfo->where(array('longurl'=>$url))->find())
			{
				json_error('<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>检测当前网址为高危恶意网址！');
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
					json_error('<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>检测当前网址为钓鱼网址！');
				}
				elseif($result['phish']==2)
				{
					json_error('<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>检测当前网址有高风险，有钓鱼嫌疑！');
				}
				elseif($result['phish']==0)
				{
					//json_error('<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>检测当前网址安全！');
				}
				elseif($result['phish']=='-1')
				{
					//json_error('<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>检测当前网址安全性未知！');
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
					json_error('<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>检测当前网址为危险的下载链接！');
				}
				elseif($result['down_type']==2)
				{
					//json_error('<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>检测当前网址为下载地址，并且很安全！');
				}
				elseif($result['down_type']==1)
				{
					//json_error('<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>检测当前网址为下载地址，安全性未知！');
				}
				elseif($result['down_type']==6)
				{
					//json_error('<a href="http://74q.net/?m=Public&a=union" target="_blank">安全短网址联盟</a>检测当前网址不是下载地址');
				}
			}
		}
		
		$json['status'] = 0;
		$json['url'] = $url;
		echo json_encode($json);exit;		
	}

}