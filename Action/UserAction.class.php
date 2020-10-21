<?php
class UserAction extends CommonAction 
{
	public function index()
	{
		$this->myfile();
	}
	
	public function myfile()
	{
		global $cfg_mb_open,$member;
		if($cfg_mb_open==0) $this->error('系统会员功能已禁用!');
		if(USER_LOGINED){
			$this->assign('list',$member);
		}else{
			jump(U('Public/login'));
		} 
		if($member['status']==1)
		{
			$this->success('当前用户没有被审核通过，请与管理员联系！',U('Public/loginout'));EXIT;
		}
		//用户等级查询
		$group = M('member_rank');
		$grouplist = $group->where(array('id'=>$member['rankid']))->find();
		$this->assign('grouplist',$grouplist);
		$this->assign('remail',cookie('remail'));
		$edit = $this->_get('edit',false);
		//套餐使用情况
		$site = M('info');
		$gnum = $site->where('mid='.cookie('uid'))->count();
		$this->assign('gnum',$gnum);
		if($grouplist['groupid']>1)
		{
			$this->assign('snum','无限制');
		}
		else
		{
			$snum = $grouplist['rankmoney'] - $gnum; 
			$this->assign('snum',$snum);
		}
		if($edit=='base')
		{
			$tpl = 'editmyfile';
		}
		elseif($edit=='password')
		{
			$tpl ='repassword';
		}
		else
		{
			$tpl = 'myfile';
		}
		$this->display($tpl);
	}
	
	public function doeditmyfile()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		$map['sex'] = $this->_post('sex',false);
		$map['province'] = $this->_post('province',false);
		$map['city'] = $this->_post('city',false);
		$map['qq'] = $this->_post('qq',false);
		$map['id'] = $this->_post('id',false);
		$avtar = $this->_post('avtar');
		if(!empty($avtar)) $map['avtar'] = $avtar;
		$model = M('member');
		$model->save($map);
		$this->success('操作成功!',U('User/myfile'));
	}
	public function dorepassword()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		$map['id'] = cookie('uid');
		$model = M('member');
		$list = $model->field('password')->where($map)->find();
		if(!$list)jump(U('Public/login')); 
		$repassword = $this->_post('repassword',false);
		$password = $this->_post('password',false);
		if(strcmp($password,$repassword) <> 0 ) $this->error('确认密码与密码不一致!');
		if($map['id'] == 1) $this->error('尊贵的超级管理员,请登录管理后台修改帐户密码信息!');
		if(strcmp(xmd5(trim($_POST['oldpassword'])),$list['password']) <>0) $this->error('原始密码不正确!');
		$map['password'] = xmd5($repassword);
		$model->save($map);
		cookie('uid',null);
		cookie('uname',null);
		cookie('wkcode',null);
		$this->success('操作成功,请重新登陆!',U('Public/login'));
	}
	
	public function site()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		import('@.ORG.Page');
		$model = M('info');
		$map['mid'] = cookie('uid');
		$count = $model->where($map)->order('addtime desc')->count();
		$this->assign('totalnum',$count);
		$fenye = 10;
		$p = new Page($count,$fenye); 
		$list = $model->where($map)->order('addtime desc')->limit($p->firstRow.','.$p->listRows)->select();
		$p->setConfig('prev','上一页');
		$p->setConfig('header','条记录');
		$p->setConfig('first','首 页');
		$p->setConfig('last','末 页');
		$p->setConfig('next','下一页');
		$p->setConfig('theme',"%first%%upPage%%linkPage%%downPage%%end%<li><span>共<font color='#009900'><b>%totalRow%</b></font>条记录 ".$fenye."条/每页</span></li>\n");
		$this->assign('page',$p->show());
		$this->assign('list',$list);
		$member = D('MemberView');
		$this->assign('rankimg',$member->where('member.id='.cookie('uid'))->getField('rankimg'));
		$this->display();
	}
	
	public function addurl()
	{
		$this->check();
		$this->display();
	}
	
	private function check()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		//核查是否在套餐范围
		$model = M('info');
		$snum = $model->where('mid='.cookie('uid'))->count();
		$member = M('member');
		$rankid = $member->where('id='.cookie('uid'))->getField('rankid');
		$group = M('member_rank');
		$glist = $group->where('id='.$rankid)->find();
		if($glist['groupid']==1 && $glist['rankmoney']<=$snum)
		{
			$this->error('您所在套餐短网址数量已经用完！');
		}
	}
	
	public function doaddurl()
	{
		$this->check();
		$url = rtrim(trim($_POST['longurl']),'/');
		if(strpos($url,'://')===false)
		{
			$url = 'http://'.$url; 
		}
		if(!preg_match('@^[a-z]+://((\w+(-\w+)*)(\.(\w+(-\w+)*))+)(\?\S*)?@',$url,$matches))
		{
			$this->error('输入的网址不存在,请重新输入!');
		}
		else
		{	
			$url = strtr($url,$matches[0],strtolower($matches[0]));
			
		}
		//屏蔽自身短网址
		$root = getroot(1);
		if(substr($url.'/',0,strlen($root))==$root) 
		{
			$this->error('不支持缩短本站网址!');
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
				$this->error('当前地址不符合系统规则!');
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
					$this->error('当前地址不符合系统规则!');
				}
				if(substr($v,0,7)=='domain:' && strtolower(substr($v,7))==$predomain)
				{
					$this->error('当前地址不符合系统规则!');
				}
				if(substr($v,0,4)=='url:' && strtolower(substr($v,4))== $preallurl)
				{
					$this->error('当前地址不符合系统规则!');
				}
			}
		}
		$data['longurl'] = $url;
		$model= M('info');
		$list = $model->field('tinyurl,longurl')->select();
		$tinyurl = array();
		$longurl = array();
		foreach($list as $v)
		{
			$tinyurl[] = $v['tinyurl'];
			$longurl[] = $v['longurl'];
		}
		if(in_array($data['longurl'],$longurl))
		{
			$this->assign('waitSecond',5);
			//检测是否为该用户所有
			$id = $model->where('longurl="'.$data['longurl'].'"')->getField('mid');
			if($id==cookie('uid'))
			{
				$this->success('您已经生成过该网址的短地址！',U('User/site'));
			}
			elseif($id==0)
			{
				$model->where('longurl="'.$data['longurl'].'"')->setField('mid',cookie('uid'));
				$this->success('系统检测到匿名用户生成过当前网址，系统成功转到您的账户！',U('User/site'));exit;
			}
			else
			{
				$this->error('当前原始地址已经被其他用户生成使用！');
			}
		}
		$data['tinyurl']  = trim($_POST['tinyurl']);
		if(empty($data['tinyurl']))
		{
			$data['tinyurl'] = getfreetiny($tinyurl);
		}
		else
		{
			if(in_array($data['tinyurl'],$tinyurl)) $this->error('当前短地址已经存在，请更换！');
		}
		$data['beizhu'] = trim($_POST['beizhu']);
		$data['mid'] = cookie('uid');
		$data['addtime'] = time();
		$data['tplid'] = 0;
		$data['pwd'] =trim($_POST['pwd']);
		if(!empty($data['pwd']))
		{
			$data['pwd'] = xmd5($data['pwd']);
		}
		$data['type'] = 1;
		$model->add($data);
		$this->success('操作成功！',U('User/site'));
	}
	
	public function freeurl()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		$data['id'] = (int)$_GET['id'];
		$data['mid'] = cookie('uid');
		$model = M('info');
		$list = $model->where($data)->find();
		if($list)
		{
			$model->where($data)->setField('mid',0);
			$model->where($data)->setField('pwd','');
			$this->success('操作成功！',U('User/site'));
		}
		else
		{
			$this->error('您无权操作此数据！');
		}
	}
	
	
	public function delurl()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		$data['id'] = (int)$_GET['id'];
		$data['mid'] = cookie('uid');
		$model = M('info');
		if($model->where($data)->find())
		{
			$model->where($data)->delete();
			$this->success('操作成功！',U('User/site'));
		}
		else
		{
			$this->error('数据不存在！');
		}
	}
	
	//批处理
	public function delall()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		//批量操作
		$id = $_REQUEST['id'];  //获取文章aid
		$n = count($id);
		$ids = implode(',',$id);//批量获取aid
		$id = is_array($id) ? $ids : $id;
		$map['id'] = array('in',$id);
		$map['mid'] = cookie('uid');
		if(!$id)$this->error('请勾选记录!');
		$model = M('info');
		if($_REQUEST['Del'] == '释放')
		{
			$data['mid'] = 0;
			$model->where($map)->save($data);
			$this->success('操作成功!',U('User/site'));
		}
		elseif($_REQUEST['Del'] == '删除')
		{
			$model->where($map)->delete();
			$this->success('操作成功!',U('User/site'));
		}
		if($_REQUEST['Del'] == '认领')
		{
			//检测套餐消费情况
			$snum = $model->where(array('mid'=>cookie('uid')))->count();
			$member = M('member');
			$memberinfo = $member->where(array('id'=>$map['mid']))->find();
			$rank = M('member_rank');
			$rankinfo = $rank->where('id='.$memberinfo['rankid'])->find();
			$gsnum = $rankinfo['rankmoney'] - $snum ;
			if($gsnum<$n && $rankinfo['groupid']==1) $this->error('套餐内剩余短地址解析数量不足！');
			$data['mid'] = cookie('uid');
			unset($map);
			$map['id'] = array('in',$id);
			$model->where($map)->save($data);
			$this->success('操作成功!',U('User/site'));
		}
	}

	public function vcount()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		if($GLOBALS['visitcount']==0) $this->error('系统关闭了访问统计功能！');
		$data['tinyurl'] = trim($_GET['url']);
		$data['mid'] = cookie('uid');
		$model = M('info');
		if(!$model->where($data)->find() && cookie('uid')<>1)
		{
			$this->error('您无权浏览！');
		}
		$member = D('MemberView');
		$this->assign('rankimg',$member->where('member.id='.cookie('uid'))->getField('rankimg'));
		import('@.ORG.Page');
		$model = M('visit');
		$data['url'] = trim($_GET['url']);
		if(empty($data['url'])) return;
		$map['url'] =$data['url'];
		/***统计***/
		//总pv & ip
		$count = $model->where($map)->order('visittime desc')->count();
		$ips = $model->field('id')->where($map)->group('visitip')->select();
        $this->assign('totalpv',$count+0);
		$this->assign('totalip',count($ips)+0);
		$today = mktime(0,0,0,date('m'),date('d'),date('Y'));
		$map['visittime']  = array('egt',$today);
		//今日ip & pv
		$todayips = $model->field('id')->where($map)->group('visitip')->select();
		$todaypv  = $model->where($map)->count();
		$this->assign('todaypv',$todaypv+0);
		$this->assign('todayip',count($todayips)+0);
		//昨日ip & Pv
		$yesterday = mktime(0,0,0,date('m'),date('d'),date('Y'))-60*60*24;
		$map['visittime']  = array(array('egt',$yesterday),array('elt',$today));
		$yesterdayips = $model->field('id')->where($map)->group('visitip')->select();
		$yesterdaypv  = $model->where($map)->count();
		$this->assign('yesterdaypv',$yesterdaypv+0);
		$this->assign('yesterdayip',count($yesterdayips)+0);
		//当前短地址信息
		$model2 = M('info');
		$urlinfo = $model2->where("tinyurl='".trim($_GET['url'])."'")->find();
		$this->assign("urlinfo",$urlinfo);
		$method = $_GET['method'];
		if($method=='weekcount')
		{
			$this->assign('counttitle','一周内流量统计');
			$this->assign('countlist',$this->countlist(trim($_GET['url']),0,7,'day'));
			$this->display('piccount');
		}
		elseif($method=='monthcount')
		{
			$this->assign('counttitle','30天内流量统计');
			$this->assign('countlist',$this->countlist(trim($_GET['url']),0,30,'day'));
			$this->display('piccount');
		}
		elseif($method=='daycount')
		{
			$this->assign('counttitle','24小时内流量统计');
			$this->assign('countlist',$this->countlist(trim($_GET['url']),0,24,'hour'));
			$this->display('piccount');
		}
		else
		{
			$fenye = 20;
			$p = new Page($count,$fenye); 
			$list = $model->where($data)->order('visittime desc')->limit($p->firstRow.','.$p->listRows)->select();
			$p->setConfig('prev','上一页');
			$p->setConfig('header','条记录');
			$p->setConfig('first','首 页');
			$p->setConfig('last','末 页');
			$p->setConfig('next','下一页');
			$p->setConfig('theme',"%first%%upPage%%linkPage%%downPage%%end%<li><span>共<font color='#009900'><b>%totalRow%</b></font>条记录 ".$fenye."条/每页</span></li>\n");
			$this->assign('page',$p->show());
			$this->assign('list',$list);
			$this->display();
		}
	}

	//通用流量统计计算方法
	private function countlist($url='',$uid=0,$j=7,$method='day')
	{
		$time = time();
		$ref = array();
		$dates = array();
		if($method=='day')
		{
			for($i=$j;$i>0;$i--)
			{	
				$date = array();
				$date['time'] = date('m月d日 l',$time-3600*24*($i-1));
				$time1 = $time-3600*24*($i-1);
				$time2 = $time-3600*24*$i;
				$date['method'] = array('between',array($time2,$time1));
				$dates[] = $date;
			}
		}
		elseif($method=='hour')
		{
			for($i=$j;$i>0;$i--)
			{
				$date = array();
				$date['time'] = date('m月d日 H时',$time-3600*($i-1));
				$time1 = $time-3600*($i-1);
				$time2 = $time-3600*$i;
				$date['method'] = array('between',array($time2,$time1));
				$dates[] = $date;
			}
		}
		elseif($method=='min')
		{
			for($i=$j;$i>0;$i--)
			{
				$date = array();
				$date['time'] = date('m月d日 H:i',$time-60*($i-1));
				$time1 = $time-60*($i-1);
				$time2 = $time-60*$i;
				$date['method'] = array('between',array($time2,$time1));
				$dates[] = $date;
			}
		}
		elseif($method=='second')
		{
			for($i=$j;$i>0;$i--)
			{
				$date = array();
				$date['time'] = date('m月d日 H:i:s',$time-($i-1));
				$time1 = $time-1*($i-1);
				$time2 = $time-1*$i;
				$date['method']= array('between',array($time2,$time1));
				$dates[] = $date;
			}
		}
		$model = M('visit');
		if(!empty($url)) $map['url'] = $url;
		if($uid<>0){
			$model = D('InfoVisitView');
			$map['mid'] = $uid;
		} 
		foreach($dates as $v)
		{
			$data = array();
			$map['visittime'] = $v['method'];
			$iplist = $model->field('visitip')->where($map)->group('visitip')->select();
			$data['ip'] = count($iplist);
			$data['pv'] = (int)$model->where($map)->count();
			$data['date'] = $v['time'];
			$ref[] = $data; 
		}
		return $ref;
	}
	public function update()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		$model = M('info');
		$snum = $model->where('mid='.cookie('uid'))->count();
		$this->assign('snum',$snum);
		$member = M('member');
		$rankid = $member->where('id='.cookie('uid'))->getField('rankid');
		$group = M('member_rank');
		$glist = $group->where('id='.$rankid)->find();
		$this->assign('glist',$glist);
		$this->assign('gnum',$glist['rankmoney']);
		$gsnum = $glist['rankmoney'] - $snum;
		$this->assign('gsnum',$gsnum);
		$this->assign('list',$group->select());
		$this->display();
	}
	
	public function addall()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		global $addallurloff;
		if($addallurloff==0) $this->error('系统关闭了批量生成功能！');
		if($GLOBALS['createoff']==0)
		{
			$this->error('系统关闭了网址生成功能!');
		}
		$this->display();
	}
	
	public function doaddall()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		global $addallurloff,$addallurlnum;
		if($GLOBALS['createoff']==0)
		{
			$this->error('系统关闭了网址生成功能!');
		}
		if($addallurloff==0) $this->error('系统关闭了批量生成功能！');
		$url = trim($_POST['longurl']);
		$beizhu = trim($_POST['beizhu']);
		$arr  = explode("\n",$url);
		$n= count($arr);
		if($n>$addallurlnum) $this->error('最多批量生成'.$addallurlnum.'条短网址');
		$list = array();
		foreach($arr as $v)
		{
			$a =$this->parseurl(trim($v),$beizhu);
			if($a)
			{
				$list[] = $a;
			}
		}
		$this->assign('list',$list);
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
	
	public function  fenxi()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		if($GLOBALS['visitcount']==0) $this->error('系统关闭了访问统计功能！');
		global $member;
		//用户等级查询
		$group = M('member_rank');
		$grouplist = $group->where(array('id'=>$member['rankid']))->find();
		$model = M('info');
		$gnum = (int)$model->where('mid='.cookie('uid'))->count();
		if($gnum==0)
		{
			$this->error('没有统计数据，请先添加短网址！');
		}
		$this->assign('gnum',$gnum);
		$this->assign('rankmoney',$grouplist['rankmoney']);
		if($grouplist['groupid']>1)
		{
			$this->assign('snum','无限制');
			$this->assign('rankmoney','无限制');
		}
		else
		{
			$snum = $grouplist['rankmoney'] - $gnum; 
			$this->assign('snum',$snum);
		}
		import('@.ORG.Page');
		$model = D('InfoVisitView');
		$map['mid'] = cookie('uid');
		/***统计***/
		//总pv & ip
		$count = $model->where($map)->order('visittime desc')->count();
		$ips = $model->field('id')->where($map)->group('visitip')->select();
        $this->assign('totalpv',$count+0);
		$this->assign('totalip',count($ips)+0);
		$today = mktime(0,0,0,date('m'),date('d'),date('Y'));
		$map['visittime']  = array('egt',$today);
		//今日ip & pv
		$todayips = $model->field('id')->where($map)->group('visitip')->select();
		$todaypv  = $model->where($map)->count();
		$this->assign('todaypv',$todaypv+0);
		$this->assign('todayip',count($todayips)+0);
		//昨日ip & Pv
		$yesterday = mktime(0,0,0,date('m'),date('d'),date('Y'))-60*60*24;
		$map['visittime']  = array(array('egt',$yesterday),array('elt',$today));
		$yesterdayips = $model->field('id')->where($map)->group('visitip')->select();
		$yesterdaypv  = $model->where($map)->count();
		$this->assign('yesterdaypv',$yesterdaypv+0);
		$this->assign('yesterdayip',count($yesterdayips)+0);
		$method = $_GET['method'];
		if($method=='weekcount')
		{
			$this->assign('counttitle','一周内流量统计');
			$this->assign('countlist',$this->countlist('',cookie('uid'),7,'day'));
			$this->display('piccountfenxi');
		}
		elseif($method=='monthcount')
		{
			$this->assign('counttitle','30天内流量统计');
			$this->assign('countlist',$this->countlist('',cookie('uid'),30,'day'));
			$this->display('piccountfenxi');
		}
		elseif($method=='daycount')
		{
			$this->assign('counttitle','24小时内流量统计');
			$this->assign('countlist',$this->countlist('',cookie('uid'),24,'hour'));
			$this->display('piccountfenxi');
		}
		else
		{
			$fenye = 20;
			$p = new Page($count,$fenye); 
			$data =array();
			$data['mid'] = cookie('uid');
			$list = $model->where($data)->order('visittime desc')->limit($p->firstRow.','.$p->listRows)->select();
			$p->setConfig('prev','上一页');
			$p->setConfig('header','条记录');
			$p->setConfig('first','首 页');
			$p->setConfig('last','末 页');
			$p->setConfig('next','下一页');
			$p->setConfig('theme',"%first%%upPage%%linkPage%%downPage%%end%<li><span>共<font color='#009900'><b>%totalRow%</b></font>条记录 ".$fenye."条/每页</span></li>\n");
			$this->assign('page',$p->show());
			$this->assign('list',$list);
			$this->display();
		}
		
	}
	public function  fenxiall()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		if($GLOBALS['visitcount']==0) $this->error('系统关闭了访问统计功能！');
		global $member;
		if($member['id']<>1){ $this->error('无权查看！'); }
		//用户等级查询
		$group = M('member_rank');
		$grouplist = $group->where(array('id'=>$member['rankid']))->find();
		$model = M('info');
		$gnum = (int)$model->where('mid>0')->count();
		if($gnum==0)
		{
			$this->error('没有统计数据，请先添加短网址！');
		}
		$this->assign('gnum',$gnum);
		$this->assign('rankmoney',$grouplist['rankmoney']);
		if($grouplist['groupid']>1)
		{
			$this->assign('snum','无限制');
			$this->assign('rankmoney','无限制');
		}
		else
		{
			$snum = $grouplist['rankmoney'] - $gnum; 
			$this->assign('snum',$snum);
		}
		import('@.ORG.Page');
		$model = M('visit');
		$map['mid'] = array('gt',0);
		/***统计***/
		//总pv & ip
		$count = $model->where($map)->order('visittime desc')->count();
		$ips = $model->field('id')->where($map)->group('visitip')->select();
        $this->assign('totalpv',$count+0);
		$this->assign('totalip',count($ips)+0);
		$today = mktime(0,0,0,date('m'),date('d'),date('Y'));
		$map['visittime']  = array('egt',$today);
		//今日ip & pv
		$todayips = $model->field('id')->where($map)->group('visitip')->select();
		$todaypv  = $model->where($map)->count();
		$this->assign('todaypv',$todaypv+0);
		$this->assign('todayip',count($todayips)+0);
		//昨日ip & Pv
		$yesterday = mktime(0,0,0,date('m'),date('d'),date('Y'))-60*60*24;
		$map['visittime']  = array(array('egt',$yesterday),array('elt',$today));
		$yesterdayips = $model->field('id')->where($map)->group('visitip')->select();
		$yesterdaypv  = $model->where($map)->count();
		$this->assign('yesterdaypv',$yesterdaypv+0);
		$this->assign('yesterdayip',count($yesterdayips)+0);
		$method = $_GET['method'];
		if($method=='weekcount')
		{
			$this->assign('counttitle','一周内流量统计');
			$this->assign('countlist',$this->countlist('',0,7,'day'));
			$this->display('piccountfenxiall');
		}
		elseif($method=='monthcount')
		{
			$this->assign('counttitle','30天内流量统计');
			$this->assign('countlist',$this->countlist('',0,30,'day'));
			$this->display('piccountfenxiall');
		}
		elseif($method=='daycount')
		{
			$this->assign('counttitle','24小时内流量统计');
			$this->assign('countlist',$this->countlist('',0,24,'hour'));
			$this->display('piccountfenxiall');
		}
		else
		{
			$fenye = 20;
			$p = new Page($count,$fenye); 
			$list = $model->order('visittime desc')->limit($p->firstRow.','.$p->listRows)->select();
			$p->setConfig('prev','上一页');
			$p->setConfig('header','条记录');
			$p->setConfig('first','首 页');
			$p->setConfig('last','末 页');
			$p->setConfig('next','下一页');
			$p->setConfig('theme',"%first%%upPage%%linkPage%%downPage%%end%<li><span>共<font color='#009900'><b>%totalRow%</b></font>条记录 ".$fenye."条/每页</span></li>\n");
			$this->assign('page',$p->show());
			$this->assign('list',$list);
			$this->display();
		}
		
	}
	
	public function domain()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		global $member;
		$group = M('member_rank');
		$auth = $group->where(array('id'=>$member['rankid']))->getField('rankimg');
		$this->assign('auth',$auth);
		$model = M('domain');
		$list = $model->where(array('uid'=>$member['id']))->find();
		$this->assign('list',$list);
		$this->display();
	}
	
	public function domainadd()
	{
		if(!USER_LOGINED) jump(U('Public/login'));
		global $member;
		$group = M('member_rank');
		$auth = $group->where(array('id'=>$member['rankid']))->getField('rankimg');
		if($auth<2)
		{
			$this->error('当前套餐没有绑定域名权限！请联系管理员');
		}
		$domain = $_POST['domain'];
		if(empty($domain))
		{
			$this->error('域名不能为空！');
		}
		
		$model = M('domain');
		if($model->where(array('uid'=>$member['id']))->find())
		{
			$model->where(array('uid'=>$member['id']))->save(array('domain'=>$domain,'pubdate'=>time(),'status'=>0));
		}
		else
		{
			$model->add(array('domain'=>$domain,'pubdate'=>time(),'uid'=>$member['id'],'status'=>0));
		}
		//echo $model->getLastSql();exit;
		$this->success("操作成功！",U('User/domain'));
	}
}