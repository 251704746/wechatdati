<?php
class index	
{
	public function __construct()
	{	
		session_start();
		header("Content-type: text/html; charset=utf-8"); 
		$this->smarty = $GLOBALS["smarty"];
		$this->mysql = $GLOBALS["mysql"];
  
		$wxinfo = $this->mysql->getRow("select * from dt_wxinfo");
		$this->smarty->assign( "wxinfo" ,$wxinfo);
		include_once (dirname( __FILE__ ) .'/common.class.php');
		$this->common = new common();
		$signPackage = $this->common->getSignPackage();  
		$aid = isset($_GET['aid'])?$_GET['aid']:0;
		if($aid){
			setcookie("aid", $aid, time()+3600*18*30, "/"); 
		}else{
			$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0;
		}
		
		$activity = $this->mysql->getRow("select * from dt_activity where aid=".$aid);
	
		if(!$activity){
			echo "<script>alert('活动ID不存在,请确认链接是否正确');</script>";
			exit();
		}
		
		if(!isset($_SESSION['formhash'])){
			$this->setformhash();
		}
		$cmd = $this->common->getCmd();
		$pageinfo['title'] = $activity['atitle'];
		$pageinfo['desc'] = $activity['adescribe'];
		$pageinfo['link'] = $signPackage['url']."&aid=".$aid;

		$pageinfo['imgUrl'] = $cmd."/upload/".$activity['asthumb'];

		$this->smarty->assign( "aid" ,$aid); 
		$this->smarty->assign( "activity" ,$activity); 
		$this->smarty->assign( "signPackage" ,$signPackage); 
		$this->smarty->assign( "pageinfo" ,$pageinfo); 
		$this->smarty->assign( "formhash" ,$_SESSION['formhash']); 
	}
	
	private function ainit($uid = 0 ,$uopid = '')
	{
		$wxinfo = $this->mysql->getRow("select * from dt_wxinfo");
		if(!$uid){	
			if($wxinfo['wxlforce']){
				$this->login($uopid);
			}else{
				$this->login();
			}	
		}else{
			if($wxinfo['wxlforce'] && $uopid){
				$this->login($uopid);
			}
			if(!$wxinfo['wxlforce']){
				$usubscribe = 1; // 不管是否关注
			}else{
				$usubscribe = $this->mysql->getOne("select usubscribe from dt_users where uid=".$uid);
			}
			$this->smarty->assign("usubscribe",$usubscribe);
			$this->smarty->assign("uid",$uid);
		}

		return $usubscribe;
	}
	
	public function index()
	{		
		$uopid = isset($_GET['uopid'])?$_GET['uopid']:'';
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0;
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;

		$this->ainit($uid,$uopid);
		
		if($uid){
			$users = $this->mysql->getRow("select * from dt_users where uid=".$uid);
			$this->smarty->assign( "users" ,$users); 
		}
		
		$dtest = $this->mysql->getOne("select count(tid) from dt_test where aid=$aid and uid=$uid and did>=0 and to_days(FROM_UNIXTIME(ttime)) = to_days(now())");
		$test = $this->mysql->getOne("select count(tid) from dt_test where aid=$aid and uid=$uid and did>=0");
		
		$this->smarty->assign( "dtest" ,$dtest); 
		$this->smarty->assign( "test" ,$test); 
		$this->smarty->display( "index.html" );
	}
	
	public function save_userinfo()
	{
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;

		$_POST['name'] = $this->common->replaceSpecialChar($_POST['name']);
		$_POST['telphone'] = $this->common->replaceSpecialChar($_POST['telphone']);
		if($uid){
			$this->mysql->autoExecute("dt_users",$_POST,"UPDATE","uid=$uid");
			die(1);
		}else{
			die(0);
		}
	}
	
	public function rule()
	{
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0; 
		$rule = $this->mysql->getOne("select arule from dt_activity where aid=".$aid);
		$this->smarty->assign( "rule" ,$rule); 
		$this->smarty->display( "rule.html" );
	}
	
	public function determine()
	{
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0; 
		$uid = isset($_COOKIE['dtuid']) ? $_COOKIE['dtuid'] : 0;
		
		$infos = array("code" => 1000,"info" => "");
		$tscore = 0;

		$activity = $this->mysql->getRow("select * from dt_activity where aid=".$aid);
		
		if(date('Y-m-d H:i:s') > $activity['aendtime']){
			$infos = array("code" => -1,"info" => "本活动已结束，敬请期待下次活动！"); ;
			die(json_encode($infos));
		}elseif(date('Y-m-d H:i:s') < $activity['astarttime']){
			$infos = array("code" => -2,"info" => "本活动尚未开始，敬请期待！"); 
			die(json_encode($infos));
		}
		
		// 判断是否有答题记录
		$tscore = $this->mysql->getOne("select max(tscore) from dt_test where aid=$aid and uid=$uid");
		if(!$tscore){
			$infos = array("code" => 1,"info" => "您还未参加答题活动，请先答题，答题达标后再进行抽奖");
			die(json_encode($infos));
		}
				
		if($tscore < $activity['asnum'] * $activity['acondition']){
			$infos = array("code" => 2,"info" => "您的答题未达".$activity['anum']*$activity['anum']."分,请答题达标后再进行抽奖！");;
			die(json_encode($infos));
		}
		
		$havedraw = $this->mysql->getRow("select * from dt_test where aid=$aid and uid=$uid order by tid desc limit 1");
		if($havedraw['did'] > 0){
			$infos = array("code" => 3,"info" => "您已抽奖，请继续参与答题达标后再进行抽奖！");
			die(json_encode($infos));
		}

		$dwins = $this->mysql->getOne("select count(did) from dt_draw where aid=$aid and uid=$uid and dwin>0");
	
		if($dwins >= $activity['aspnum']){
			$infos = array("code" => 4,"info" => "本次活动限定每人最多中奖".$activity['aspnum']."次,您已达到中奖次数上限！");;
			die(json_encode($infos));
		}
		$dwin = $this->mysql->getOne("select count(did) from dt_draw where aid=$aid and uid=$uid and dwin>0 and to_days(FROM_UNIXTIME(dtime)) = to_days(now())");
		if($dwin >= $activity['arpnum']){
			$infos = array("code" => 5,"info" => "本次活动限定每人每天最多中奖 ".$activity['arpnum']."次 ,您已达到当天中奖次数上限！");
			die(json_encode($infos));
		}
		
		die(json_encode($infos));
	}
	
	public function close_test()
	{
		$tid = isset($_POST['tid'])?intval($_POST['tid']):0;
		
		$this->mysql->query("update dt_test set did=0 where tid=".$tid);
	}
	
	public function action_itest()
	{
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0; 
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;
		
		$activity = $this->mysql->getRow("select * from dt_activity where aid=".$aid);
		
		if(date('Y-m-d H:i:s') > $activity['aendtime']){
			$infos = array("code" => -1,"info" => "本活动已结束，敬请期待下次活动！"); ;
			die(json_encode($infos));exit;
		}elseif(date('Y-m-d H:i:s') < $activity['astarttime']){
			$infos = array("code" => -2,"info" => "本活动尚未开始，敬请期待！"); 
			die(json_encode($infos));exit;
		}
		
		$dtest = $this->mysql->getOne("select count(tid) from dt_test where aid=$aid and uid=$uid and did>=0 and to_days(FROM_UNIXTIME(ttime)) = to_days(now())");
		if($dtest >= $activity['artnum']){
			$infos = array("code" => -3,"info" => "本次活动限定每人每天最多答题".$activity['artnum']."次,您已达到次数上限！");;
			die(json_encode($infos));
		}
		$itest = $this->mysql->getOne("select count(tid) from dt_test where aid=$aid and uid=$uid and did>=0");
		if($itest >= $activity['astnum']){
			$infos = array("code" => -4,"info" => "本次活动限定每人最多答题".$activity['artnum']."次,您已达到次数上限！");;
			die(json_encode($infos));
		}
		
		$_POST['aid'] = $aid;
		$_POST['uid'] = $uid;
		$_POST['ttime'] = time();
		$tid = isset($_POST['tid'])?intval($_POST['tid']):0;
		
		$lttime = $this->mysql->getOne("select count(tid) from dt_test where aid=$aid and uid=$uid");
		if($lttime == 0){
			$this->mysql->query("update dt_activity set ausers=ausers+1 where aid=".$aid);
		}
		
		$this->mysql->query("update dt_test set did=0 where aid=$aid and uid=$uid and tid <> $tid and did=-1");
		
		if($tid){
			$this->mysql->autoExecute("dt_test",$_POST,"UPDATE","tid=".$tid);
		}else{
			$this->mysql->autoExecute("dt_test",$_POST);
			$tid = $this->mysql->insert_id();
			$this->mysql->query("update dt_activity set aviews=aviews+1 where aid=".$aid);
		}
		
		$infos = array("code" => 1,"info" => $tid); 
		die(json_encode($infos));
	}
	
	public function action_test()
	{	
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0; 
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;
		
		$activity = $this->mysql->getRow("select * from dt_activity where aid=".$aid);
		
		if(date('Y-m-d H:i:s') > $activity['aendtime']){
			$infos = array("code" => -1,"info" => "本活动已结束，敬请期待下次活动！"); ;
			die(json_encode($infos));
		}elseif(date('Y-m-d H:i:s') < $activity['astarttime']){
			$infos = array("code" => -2,"info" => "本活动尚未开始，敬请期待！"); 
			die(json_encode($infos));
		}
		
		$dtest = $this->mysql->getOne("select count(tid) from dt_test where aid=$aid and uid=$uid and did>=0 and to_days(FROM_UNIXTIME(ttime)) = to_days(now())");
		if($dtest >= $activity['artnum']){
			$infos = array("code" => -3,"info" => "本次活动限定每人每天最多答题".$activity['artnum']."次,您已达到次数上限！");;
			die(json_encode($infos));
		}
		
		$itest = $this->mysql->getOne("select count(tid) from dt_test where aid=$aid and uid=$uid and did>=0");
		if($itest >= $activity['astnum']){
			$infos = array("code" => -4,"info" => "本次活动限定每人最多答题".$activity['artnum']."次,您已达到次数上限！");;
			die(json_encode($infos));
		}

		$_POST['aid'] = $aid;
		$_POST['uid'] = $uid;
		$_POST['ttime'] = time();
		
		if($_POST['formhash'] != $_SESSION['formhash']){
			$this->setformhash();
			return;
		}else{
			$this->setformhash();
		}
		$tid = isset($_POST['tid'])?intval($_POST['tid']):0;

		if($tid){
			$this->mysql->autoExecute("dt_test",$_POST,"UPDATE","tid=".$tid);
			$infos = array("code" => 0,"info" => "操作成功！");
		}else{
			$infos = array("code" => -1,"info" => "操作失败！");
		}
		die(json_encode($infos));
	}
	
	public function action_draw()
	{
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0; 
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;
		$did = 0;
		
		if(!isset($_SESSION['draw_time']))
		{
			$_SESSION['draw_time'] = time();
		}else{
			if((time() - $_SESSION['draw_time']) > 10)
			{
				$_SESSION['draw_time'] = time();
			}
			else
			{
				$_SESSION['draw_time'] = time();
				$infos = array("did" => -1,"info" => "刷新太频繁，歇歇吧！"); 
				die(json_encode($infos));
			}
		}
		
		$activity = $this->mysql->getRow("select * from dt_activity where aid=".$aid);
		
		if(date('Y-m-d H:i:s') > $activity['aendtime']){
			$infos = array("did" => -2,"info" => "本活动已结束，敬请期待下次活动！"); 
			die(json_encode($infos));
		}elseif(date('Y-m-d H:i:s') < $activity['astarttime']){
			$infos = array("did" => -3,"info" => "本活动尚未开始，敬请期待！"); 
			die(json_encode($infos));
		}
		
		$dwins = $this->mysql->getOne("select count(did) from dt_draw where aid=$aid and uid=$uid and dwin>0");
	
		if($dwins >= $activity['aspnum']){
			$infos = array("did" => -4,"info" => "本次活动限定每人最多中奖".$activity['aspnum']."次,您已达到中奖次数上限！");;
			die(json_encode($infos));
		}
		$dwin = $this->mysql->getOne("select count(did) from dt_draw where aid=$aid and uid=$uid and dwin>0 and to_days(FROM_UNIXTIME(dtime)) = to_days(now())");
		if($dwin >= $activity['arpnum']){
			$infos = array("did" => -5,"info" => "本次活动限定每人每天最多中奖 ".$activity['arpnum']."次 ,您已达到当天中奖次数上限！");
			die(json_encode($infos));
		}
		
		if($_POST['formhash'] != $_SESSION['formhash']){
			$this->setformhash();
			$infos = array("did" => -6,"info" => "系统繁忙，请刷新重试！");
			die(json_encode($infos));
		}
		
		$havedraw = $this->mysql->getRow("select * from dt_test where aid=$aid and uid=$uid order by tid desc limit 1");
		if($havedraw['did'] > 0){
			$infos = array("code" => -7,"info" => "您已抽奖，请继续参与答题达标后再进行抽奖！");
			die(json_encode($infos));
		}
		
		$psid = isset($_POST['psid'])?$_POST['psid']:0;

		$pdid = 0;
		$prize = $this->mysql->getAll("select * from dt_prize where aid=".$aid);
		foreach($prize as $key => $val){
			if($psid == substr(MD5($_SESSION['pidsession'].$val['pid']),8,16))
			{
				$pdid = $val['pid'];
				$_SESSION['pidsession'] = substr(MD5("pidsessionaward" . time()),8,16);
			}
		}
		
		if($pdid != 0 ){
			$award = $this->mysql->getRow("select * from dt_prize where aid=$aid and pid=".$pdid);
			if($award['pprobability'] < 0.0001){
				$award  = array("dwin" =>0,"paward"=>"谢谢参与",'pname'=>"未中奖");
			}else{
				$award['dwin'] = $award['pid'];
				$dcode = $this->build_dcode();
				
				$draw = array("aid"=>$aid,"uid"=>$uid,"dwin"=>$award['dwin'],"daward"=>$award['paward'],"dname"=>$award['pname'],"dcode"=>$dcode,"dtime"=>time());
				$this->mysql->query("update dt_prize set pleft=pleft-1,prleft=prleft-1 where aid=$aid and pid=".$pdid);		
			}
			
		}else{
			$award  = array("dwin" =>0,"paward"=>"谢谢参与",'pname'=>"未中奖");
			$draw = array("aid"=>$aid,"uid"=>$uid,"dwin"=>$award['dwin'],"daward"=>$award['paward'],"dname"=>$award['pname'],"dtime"=>time());
		}
		
		$this->mysql->autoExecute("dt_draw",$draw);
		$did = $this->mysql->insert_id();
		
		$ntid = $this->mysql->getOne("select max(tid) from dt_test where aid=$aid and uid=$uid");
		
		$tests = array("did"=>$did);
		$this->mysql->autoExecute("dt_test",$tests,"UPDATE","tid=$ntid");
		
		$infos = array("did" => $did,"info" => "");
		die(json_encode($infos));
	}
	
	public function subject()
	{
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0;
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;
		$usubscribe = $this->ainit($uid);
		
		$activity = $this->mysql->getRow("select * from dt_activity where aid=".$aid);

		$subject1 = $this->mysql->getAll("select * from dt_subject where aid=".$aid." and stype=1 and 
		sid >= (((SELECT MAX(sid) FROM dt_subject)-(SELECT MIN(sid) FROM dt_subject)) * RAND() + (SELECT MIN(sid) FROM dt_subject))
		limit ".$activity['arnum']);
		$subject2 = $this->mysql->getAll("select * from dt_subject where aid=".$aid." and stype=2 and
		sid >= (((SELECT MAX(sid) FROM dt_subject)-(SELECT MIN(sid) FROM dt_subject)) * RAND() + (SELECT MIN(sid) FROM dt_subject))
		limit ".$activity['acnum']);
		
		$subject = array_merge($subject1,$subject2);

		$activity['asnum'] = $activity['arnum'] + $activity['acnum'];

		$this->smarty->assign( "activity" ,$activity); 
		
		$this->smarty->assign( "subject" ,$subject); 
		
		$stotal = $this->mysql->getOne("select sum(prleft) from dt_prize where aid=".$aid);
		$this->smarty->assign( "stotal" ,$stotal); 
		$this->smarty->display( "subject.html" );
	}

	public function guserinfo()
	{
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0; 
		$uinfo = $this->mysql->getRow("select * from dt_users where uid=".$uid);
		
		die(json_encode($uinfo));
	}
	
	public function mdetail()
	{
		$id = isset($_GET['id'])?intval($_GET['id']):0;
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0;
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;
		$usubscribe = $this->ainit($uid);
		
		if($uid){
			$test = $this->mysql->getRow("select * from dt_test where tid=".$id);
				
			$query = $this->mysql->query("select * from dt_test where aid=".$aid." and uid=".$uid);
			$total = $this->mysql->num_rows($query);
			$tpage = ceil( $total / 10 );	
			
			$tmp = explode('/',$test['tscale']);
			$test['total'] = $tmp[1];
			$test['num'] = $tmp[0];
			
			if($test['did'] > 0){
				$draw = $this->mysql->getRow("select * from dt_draw where did=".$test['did']);
				if($draw['dwin']>0){
					$pinfo = $this->mysql->getRow("select pthumb,ptype,pid,ppacket from dt_prize where pid=".$draw['dwin']);
					$draw['pthumb'] = $pinfo['pthumb'];
					$draw['ptype'] = $pinfo['ptype'];
					$draw['pid'] = $pinfo['pid'];
					$draw['ppacket'] = $pinfo['ppacket'];
				}
				
				$draw['dtime'] = date("Y-m-d H:i:s",$draw['dtime']);
				$draw['datimes'] = ($draw['datime'])?date("Y-m-d H:i:s",$draw['datime']):"";
			}
			$test['ttime'] = date("Y-m-d H:i:s",$test['ttime']);	
	
			$this->smarty->assign( "test" , $test); 
			$this->smarty->assign( "draw" , $draw); 
		
			$uinfo = $this->mysql->getRow("select * from dt_users where uid=".$uid);
			
			$ulog['lttimes'] = $this->mysql->getOne("select count(tid) from dt_test where aid=".$aid." and uid=".$uid);
			$ulog['ldraws'] = $this->mysql->getOne("select count(did) from dt_draw where aid=".$aid." and uid=".$uid." and dwin>0");

			$this->smarty->assign( "uinfo" , $uinfo); 
			$this->smarty->assign( "tpage" , $tpage); 
			$this->smarty->assign( "ulog" , $ulog); 
			
			$users = $this->mysql->getRow("select * from dt_users where uid=".$uid);
			$this->smarty->assign( "users" ,$users); 
		}
		$this->smarty->display( "mdetail.html" );
	}

	public function mine()
	{
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0;
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;
		$usubscribe = $this->ainit($uid);

		if($uid){
			$query = $this->mysql->query("select * from dt_test where aid=".$aid." and uid=".$uid);
			$total = $this->mysql->num_rows($query);
			$tpage = ceil( $total / 10 );
		
			$test = $this->mysql->getAll("select * from dt_test where aid=".$aid." and uid=".$uid." limit 10");

			foreach($test as $key => $val){
				if($val['did'] > 0){
					$draws = $this->mysql->getRow("select * from dt_draw where did=".$val['did']);
					$test[$key]['dwin'] = $draws['dwin'];
					$test[$key]['datime'] = $draws['datime'];
					$test[$key]['daward'] = ($draws['dwin']>0)?"<font color='yellow'>".$draws['daward']."</font>":$draws['daward'];
					$test[$key]['dtime'] = date("m/d H:i",$draws['dtime']);
				}else{
					$test[$key]['dwin'] = 0;
					$test[$key]['daward'] = "未抽奖";
					$test[$key]['dtime'] = date("m/d H:i",$val['ttime']);
				}
			}
			
			$this->smarty->assign( "test" , $test); 
		
			$uinfo = $this->mysql->getRow("select * from dt_users where uid=".$uid);
			
			$ulog['lttimes'] = $this->mysql->getOne("select count(tid) from dt_test where aid=".$aid." and uid=".$uid);
			$ulog['ldraws'] = $this->mysql->getOne("select count(did) from dt_draw where aid=".$aid." and uid=".$uid." and dwin>0");

			$this->smarty->assign( "uinfo" , $uinfo); 
			$this->smarty->assign( "tpage" , $tpage); 
			$this->smarty->assign( "ulog" , $ulog); 
		}
		$this->smarty->display( "mine.html" );
	}
	
	public function utest()
	{
		$aid = $_COOKIE['aid'];
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;

		$num = 10;
		
		$query = $this->mysql->query("select * from dt_test where aid=".$aid." and uid=".$uid);
		$cpage = isset( $_GET["cpage"] ) ? $_GET["cpage"] : 1;
		
		$offset = ( $cpage - 1 ) * $num;
		
		$sql = "select * from dt_test where aid=".$aid." and uid=".$uid;
		$res = $this->mysql->selectLimit($sql,$num,$offset);
		$arr = array();
		while ($rows = $this->mysql->fetchRow($res))
		{
			if($rows['did']>0){
				$draws = $this->mysql->getRow("select * from dt_draw where did=".$rows['did']);
				$rows['dwin'] = $draws['dwin'];
				$rows['datime'] = $draws['datime'];
				$rows['daward'] = ($draws['dwin']>0)?"<font color='yellow'>".$draws['daward']."</font>":$draws['daward'];
				$rows['dtime'] = date("m/d H:i",$draws['dtime']);
			}else{
				$rows['dwin'] = 0;
				$rows['daward'] = "未抽奖";
				$rows['dtime'] = date("m/d H:i",$rows['ttime']);
			}
			$arr[] = $rows;
		}
		
		die(json_encode($arr));

	}
	
	public function award()
	{
		if(!isset($_SESSION['pidsession']))
		{
			$_SESSION['pidsession'] = substr(MD5("pidsessionaward" . time()),8,16);
		}
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0;
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;
		$usubscribe = $this->ainit($uid);

		$prize = $this->mysql->getAll("select * from dt_prize where aid=".$aid);
		
		foreach($prize as $key => $val){
			$prize[$key]['pprobability'] = ($val['pleft'] != 0)? $val['pprobability']:0;
			$prize[$key]['psid'] = substr(MD5($_SESSION['pidsession'].$prize[$key]['pid']),8,16);
		}
		
		$prizes = array();
		if( count($prize) == 3 ){
			$prizes[0] = $prize[0];
			$prizes[1] = $prize[1];
			$prizes[2] = $prize[2];
			$prizes[0]['pprobability'] = $prize[0]['pprobability']/2;
			$prizes[1]['pprobability'] = $prize[1]['pprobability']/2;
			$prizes[2]['pprobability'] = $prize[2]['pprobability']/2;
			$prizes[3] = $prizes[0];
			$prizes[4] = $prizes[1];
			$prizes[5] = $prizes[2];
		}else if( count($prize) == 4 ){
			$prizes[0] = $prize[0];
			$prizes[1] = $prize[1];
			$prizes[2] = $prize[2];
			$prizes[3] = $prize[3];
			$prizes[2]['pprobability'] = $prize[2]['pprobability']/2;
			$prizes[3]['pprobability'] = $prize[3]['pprobability']/2;
			$prizes[4] = $prizes[2];
			$prizes[5] = $prizes[3];
		}else if( count($prize) == 5 ){
			$prizes[0] = $prize[0];
			$prizes[1] = $prize[1];
			$prizes[2] = $prize[2];
			$prizes[3] = $prize[3];
			$prizes[3]['pprobability'] = $prize[3]['pprobability']/2;
			$prizes[4] = $prize[4];
			$prizes[5] = $prizes[3];
		}else if( count($prize) == 6 ){
			$prizes = $prize;
		}

		$this->smarty->assign( "prizes" , $prizes); 
		$this->smarty->assign( "prize" , $prize); 

		$draw = $this->mysql->getAll("select * from dt_draw where aid=".$aid." and dwin>0 order by dwin asc limit 10");
		foreach($draw as $key => $val){
			$draw[$key]['username'] = $this->mysql->getOne("select username from dt_users where uid=".$val['uid']);
		}
		
		$activity = $this->mysql->getRow("select * from dt_activity where aid=".$aid);
		$this->smarty->assign( "activity" ,$activity); 
		
		$users = $this->mysql->getRow("select * from dt_users where uid=".$uid);
		$this->smarty->assign( "users" ,$users); 
		
		$this->smarty->assign( "draw" , $draw); 
		
		$this->smarty->display( "award.html" );
	}

	public function mkqrcode()
	{
		$dcode = isset($_POST['dcode'])?$_POST['dcode']:'';
		$dqrcode = $this->mysql->getOne("select dqrcode from dt_draw where dcode='".$dcode."'");
		if(!$dqrcode){
			require_once dirname( __FILE__ ) .'/../include/phpqrcode.php';
			$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

			$hurl = $http_type.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
			$url = $hurl . "?act=verification&mod=admin&dcode=".$dcode;
			
			$errorCorrectionLevel = 'L'; 
			$matrixPointSize = 6;    
			$dqrcode = 'qrcode_' . $dcode . '.png';
			//chmod(dirname( __FILE__ ) . "/../qrcode/" , 0777);
			$filename = dirname( __FILE__ ) . '/../qrcode/'.$dqrcode;  
			QRcode::png($url,$filename , $errorCorrectionLevel, $matrixPointSize, 2);   
			
			$this->mysql->query("update dt_draw set dqrcode='".$dqrcode."' where dcode='".$dcode."'" );
		}
		
		echo $dqrcode;
	}
	
	private function ucheckout($code='',$did=0,$ordersn='')
	{
		$aid = isset($_COOKIE['aid'])?$_COOKIE['aid']:0;
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;
		
		if($code == "SUCCESS"){
			$info = array("daonum" => $ordersn,"datime" => time());
			$this->mysql->autoExecute("dt_draw",$info,"UPDATE","did=$did");
		}elseif($ordersn){
			$info = array("daonum" => $ordersn);
			$this->mysql->autoExecute("dt_draw",$info,"UPDATE","did=$did");
		}
	}
	
	public function uhandout()
	{
		require_once "wxpay/WxPay.JsApiPay.php";
		require_once "wxpay/lib/WxPay.Api.php";
		require_once 'wxpay/log.php';
		
		//初始化日志  
		$logHandler= new CLogFileHandler("wxpay/logs/".date('Y-m-d').'.log');  
		$log = Log::Init($logHandler, 15); 

		$did = isset($_POST['did'])?intval($_POST['did']):0;
		$daonum = isset($_POST['daonum'])?$_POST['daonum']:'';
		
		if(!isset($_SESSION['award_time']))
		{
			$_SESSION['award_time'] = time();
		}else{
			if((time() - $_SESSION['award_time']) > 10)
			{
				$_SESSION['award_time'] = time();
			}
			else
			{
				$_SESSION['award_time'] = time();
				die(json_encode(array("code"=>-1,"info"=>"10秒内，请勿频繁操作")));
			}
		}
		
		$dwin = $this->mysql->getOne("select dwin from dt_draw where did=$did");
		$money = 0.00;
		if($dwin>0){
			$money = $this->mysql->getOne("select ppacket from dt_prize where pid=$dwin");
		}else{
			Log::DEBUG("error（DID:".$did."）:中奖金额有误");
			die(json_encode(array("code"=>-2,"info"=>"中奖金额有误")));
		}
		
		$dhas = $this->mysql->getOne("select count(did) from dt_draw where did=$did and datime is null");
	
		if(!$dhas){
			Log::DEBUG("error（DID:".$did."）:没有参加答题抽奖或者奖品已经领取");
			die(json_encode(array("code"=>-3,"info"=>"没有参加答题抽奖或者奖品已经领取")));
		}

		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0; 
		if(!$uid){
			Log::DEBUG("error（DID:".$did."）:用户信息有误");
			die(json_encode(array("code"=>-4,"info"=>"获取用户信息有误，请清空缓存后重新登录")));
		}

		$payinfo = $this->mysql->getRow("select * from dt_wxinfo");
		
		$users = $this->mysql->getRow("select * from dt_users where uid=$uid");
		if(!$users['upopenid']){
			Log::DEBUG("error（DID:".$did."）:获取用户OPENID信息有误");
			die(json_encode(array("code"=>-4,"info"=>"获取用户OPENID信息有误，请清空缓存后重新登录")));
		}
		 
		$black = $this->mysql->getOne('select count(*) from dt_black where bname="'.$users['name'].'"');
		if($black > 0)
		{
			Log::DEBUG("error（DID:".$did."）:黑名单用户！");
			die(json_encode(array("code"=>-5,"info"=>"黑名单用户！")));
		}
		
		if($daonum){
			$businessman_sn = $daonum;
		}else{
			$businessman_sn = $this->build_order_no();
		}
		
		$ucheck = ($payinfo['wxpcheck'] == 1)?"FORCE_CHECK":"NO_CHECK";
		$record = array(
			"money" => $money,
			"businessman_sn" => $businessman_sn,
			"txnickname" => $users['name'],
			"weixin_openid" => $users['upopenid'],
			"weixin_check" => $ucheck
		);
		 
		 $branch=number_format($record["money"],"2",".","");  
		 $postData=array(  
			 "mch_appid"=>$payinfo['wxpappid'],                  //绑定支付的APPID  
			 "mchid"=>$payinfo['wxpmchid'],                      //商户号  
			 "nonce_str"=>"rand".rand(100000, 999999),           //随机数  
			 "partner_trade_no"=>$record["businessman_sn"],      //商户订单号  
			 "openid"=>$record["weixin_openid"],                 //用户唯一标识  
			 "check_name"=>$record["weixin_check"],              //校验用户姓名选项，NO_CHECK：不校验真实姓名 FORCE_CHECK：强校验真实姓名  
			 "re_user_name"=>$record["txnickname"],              //用户姓名  
			 "amount"=>$branch*100,                              //金额（以分为单位，必须大于100）  
			 "desc"=>"答题抽奖活动奖金",               //描述  
			 "spbill_create_ip"=>$this->common->getIP(),        //请求ip  
		 );  

		 ksort($postData);  
		 $buff = "";  
		 foreach ($postData as $k => $v)  
		 {  
			 if($k != "sign" && $v != "" && !is_array($v)){  
				 $buff .= $k . "=" . $v . "&";  
			 }  
		 }  
		 $string = trim($buff, "&");  
		 $string = $string . "&key=".$payinfo['wxpkey'];  

		 $string = md5($string);  

		 $sign = strtoupper($string);  
		 $postData["sign"]=$sign;  

		 $xml = "<xml>";  
		 foreach ($postData as $key=>$val)  
		 {  
			 if (is_numeric($val)){  
				 $xml.="<".$key.">".$val."</".$key.">";  
			 }else{  
				 $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";  
			 }  
		 }  
		 $xml.="</xml>";  

		 $url="https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";  

		 $ch = curl_init();  

		 curl_setopt($ch, CURLOPT_TIMEOUT, 30);  
		  
		 if(WxPayConfig::CURL_PROXY_HOST != "0.0.0.0"  
			 && WxPayConfig::CURL_PROXY_PORT != 0){  
			 curl_setopt($ch,CURLOPT_PROXY, WxPayConfig::CURL_PROXY_HOST);  
			 curl_setopt($ch,CURLOPT_PROXYPORT, WxPayConfig::CURL_PROXY_PORT);  
		 }  
		 
		 curl_setopt($ch,CURLOPT_URL, $url);  
		 curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);  
		 curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
		 curl_setopt($ch,CURLOPT_FOLLOWLOCATION,TRUE);
		 curl_setopt($ch, CURLOPT_HEADER, FALSE);  
		 curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);  
		 //设置证书  
		 curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');  
		 curl_setopt($ch,CURLOPT_SSLCERT, dirname(__FILE__) . "/../../wxpay/wxcert/apiclient_cert.pem");  
		 curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
		 curl_setopt($ch,CURLOPT_SSLKEY, dirname(__FILE__) . "/../../wxpay/wxcert/apiclient_key.pem");  
		 
		 curl_setopt($ch, CURLOPT_POST, TRUE);  
		 curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);  
		 $dataRe = curl_exec($ch);  
		 $dataArr = json_decode(json_encode(simplexml_load_string($dataRe, 'SimpleXMLElement', LIBXML_NOCDATA)), true);  

		 if($dataRe){  
			 curl_close($ch);  

			 libxml_disable_entity_loader(true);  
			 $dataArr = json_decode(json_encode(simplexml_load_string($dataRe, 'SimpleXMLElement', LIBXML_NOCDATA)), true);  

			 if($dataArr["return_code"] == "SUCCESS" && $dataArr["result_code"] == "SUCCESS"){  
				 $this->ucheckout("SUCCESS",$did,$businessman_sn);
				 Log::DEBUG("error（DID:".$did."）:" . $dataArr['err_code_des']);
				 die(json_encode(array("code"=>0,"info"=>"领取成功")));
			 }elseif($dataArr["err_code"] == "SYSTEMERROR"){  	
				 $this->ucheckout("SYSTEMERROR",$did,$businessman_sn);
				 Log::DEBUG("error（DID:".$did."）:" . $dataArr['err_code_des']);		
				 die(json_encode(array("code"=>-6,"info"=>"系统繁忙，请稍后在'我的'详情页面领取。")));
			 }elseif($dataArr["err_code"] == "NOTENOUGH"){  
				 $this->ucheckout("FAILURE",$did,$businessman_sn);
				 Log::DEBUG("error（DID:".$did."）:" . $dataArr['err_code_des']);				 
				 die(json_encode(array("code"=>-7,"info"=>"后台余额不足，请联系我们充值")));
			 }elseif($dataArr["err_code"] == "NAME_MISMATCH"){  
				 $this->ucheckout("FAILURE",$did,$businessman_sn);
				 Log::DEBUG("error（DID:".$did."）:" . $dataArr['err_code_des']);				 
				 die(json_encode(array("code"=>-8,"info"=>"您填写真实姓名与微信号绑定的真实姓名不一致")));
			 }elseif($dataArr["err_code"] == "V2_ACCOUNT_SIMPLE_BAN"){  
				 $this->ucheckout("FAILURE",$did,$businessman_sn);
				 Log::DEBUG("error（DID:".$did."）:" . $dataArr['err_code_des']);				 
				 die(json_encode(array("code"=>-9,"info"=>"您的微信号未实名认证，无法给非实名用户付款")));
			 }elseif($dataArr["err_code"] == "SENDNUM_LIMIT"){  
				 $this->ucheckout("FAILURE",$did,$businessman_sn);
				 Log::DEBUG("error（DID:".$did."）:" . $dataArr['err_code_des']);				 
				 die(json_encode(array("code"=>-10,"info"=>"今日付款次数超过限制,请明天再来吧")));
			 }else{  
				 $this->ucheckout("FAILURE",$did,$businessman_sn);
				 Log::DEBUG("error（DID:".$did."）:" . $dataArr['err_code_des']);				 
				 die(json_encode(array("code"=>-11,"info"=>$dataArr['err_code_des'])));
			 }
			 
		 } else {  
			 $error = curl_errno($ch);  
			 curl_close($ch);	
			 Log::DEBUG("error（DID:".$did."）:" . $error);  
			 die(json_encode(array("code"=>-12,"info"=>$error)));
		 }  
	}
	
	private function login($uopid = '')
	{
		$wxinfo = $this->mysql->getRow("select * from dt_wxinfo");
		
		$appid = $wxinfo['wxpappid'];
		$secret = $wxinfo['wxpsecret'];
		$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

		$redirect_uri = urlencode ( $http_type.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?mod=index&act=getuserinfo&uopid=".$uopid );
		$url ="https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_userinfo&state=1#wechat_redirect";
		header("Location:".$url);
	}
	
	public function getuserinfo()
	{
		$code = $_GET['code'];
		$uopid = $_GET['uopid'];

		$wxinfo = $this->mysql->getRow("select * from dt_wxinfo");
		
		$appid = $wxinfo['wxpappid'];
		$secret = $wxinfo['wxpsecret'];
		
		$url1 = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appid&secret=$secret&code=$code&grant_type=authorization_code";

		$res1 = json_decode($this->common->httpGet($url1));
		$url2 = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=$appid&grant_type=refresh_token&refresh_token=$res1->refresh_token";
		
		
		$res2 = json_decode($this->common->httpGet($url2));

		$url3 = "https://api.weixin.qq.com/sns/userinfo?access_token=$res2->access_token&openid=$res2->openid&lang=zh_CN";
		$res3 = json_decode($this->common->httpGet($url3),true);
		
		if($res3 && $res3['openid'] != ''){
	
			$uid = $this->mysql->getOne("select uid from dt_users where upopenid='".$res3['openid']."' and ugid=100");
			$openid = ($uopid != '') ? $uopid : $res3['openid'];
			$usubscribe = ($uopid != '') ? 1 : 0;
			$uinfo = array("username"=>$res3['nickname'],"password"=>MD5($res3['nickname'].$res3['openid']),"uopenid"=>$openid,"usubscribe"=>$usubscribe,
					"upopenid"=>$res3['openid'],"uheadimg"=>$res3['headimgurl'],"ugid"=>100,"uloginip"=>$this->common->getIP(),"ulogintime"=>time());
			
			if($uid){
				$this->mysql->autoExecute("dt_users",$uinfo,"UPDATE","uid=$uid");
			}else{
				$uinfo['uaddtime'] = time();
				$this->mysql->autoExecute("dt_users",$uinfo);
				$uid = $this->mysql->insert_id();
			}

			setcookie("dtuid", $uid, time()+3600*18*30, "/"); 
			
		}else{
			echo "<script>alert('获取用户信息失败，请重试...');</script>";
		}
		
		header("Location:index.php?mod=index&act=index");
	}
	
	public function webchatinit()
	{
		$aid = ($_GET['aid'])?$_GET['aid']:0;
		$wxinfo = $this->mysql->getRow("select * from dt_wxinfo");
		if(!isset($_GET['echostr'])){
			$postStr = file_get_contents("php://input");
			$postObj = simplexml_load_string($postStr,'SimpleXMLElement',LIBXML_NOCDATA);
			
			
			$activity = $this->mysql->getRow("select * from dt_activity where aid=".$aid);
			$cmd = $this->common->getCmd();
			if(strtolower($postObj->MsgType) == 'event'){
				if(strtolower($postObj->Event) == 'subscribe'){
					$uid = $this->mysql->getOne("select uid from dt_users where uopenid='".$postObj->FromUserName."' and ugid=100");
					$uinfo = array("usubscribe"=>1);
					if($uid){
						$this->mysql->autoExecute("dt_users",$uinfo,"UPDATE","uid=$uid");
					}
					//$content="欢迎关注".$wxinfo['wxlname']."！若参加《学习党十九大精神答题抽奖》活动，请回复《答题抽奖》点击图文消息进入";
					//$this->responseText($postObj, $content);
				}else if(strtolower($postObj->Event) == 'unsubscribe'){
					
					$uid = $this->mysql->getOne("select uid from dt_users where uopenid='".$postObj->FromUserName."' and ugid=100");
					$uinfo = array("usubscribe"=>0);
					if($uid){
						$this->mysql->autoExecute("dt_users",$uinfo,"UPDATE","uid=$uid");
					}
				}
				
				if($postObj->Event == 'CLICK'){
					if($postObj->EventKey == 'DATI_CHOUJIANG'){

						$arr = array(
							array(
							'title'=>$activity['atitle'],
							'description'=>$activity['adescribe'],
							'picUrl'=>$cmd . 'upload/'.$activity['athumb'],
							'url'=>$cmd . 'index.php?mod=index&act=index&aid='.$aid."&uopid=".$postObj->FromUserName,
							)
						);

						$this->responseNews($postObj, $arr);
					}
				}
			}else if(strtolower($postObj->MsgType) == 'text'){
				switch(trim($postObj->Content)){
					case '答题抽奖':
					
						$arr = array(
							array(
							'title'=>$activity['atitle'],
							'description'=>$activity['adescribe'],
							'picUrl'=>$cmd . 'upload/'.$activity['athumb'],
							'url'=>$cmd . 'index.php?mod=index&act=index&aid='.$aid."&uopid=".$postObj->FromUserName,
							)
						);

						$this->responseNews($postObj, $arr);
						break;
					default:
						$content="我猜您是想说《答题抽奖》，参与《党十九大精神学习》答题活动，请回复【答题抽奖】";
						$this->responseText($postObj, $content);
						break;
				}
			}
		}else{
			$echoStr = $_GET['echostr'];
			$signature = $_GET['signature'];
			$timestamp = $_GET['timestamp'];
			$nonce = $_GET['nonce'];
			$token = $wxinfo['wxltoken'];
			$tmpArr = array($token,$timestamp,$nonce);
			sort($tmpArr,SORT_STRING);
			$tmpStr = implode($tmpArr);
			$tmpStr = sha1($tmpStr);
			if($tmpStr == $signature){
				echo $echoStr;
				exit();
			}
		}
     
	}

	private function responseText($postObj,$content)
	{
		$fromUserName=$postObj->ToUserName;
		$toUserName=$postObj->FromUserName;
		$time=time();
		$msgType='text';
		$template="<xml>
						<ToUserName><![CDATA[%s]]></ToUserName>
						<FromUserName><![CDATA[%s]]></FromUserName>
						<CreateTime>%s</CreateTime>
						<MsgType><![CDATA[%s]]></MsgType>
						<Content><![CDATA[%s]]></Content>
						</xml>";
		echo sprintf($template,$toUserName,$fromUserName,$time,$msgType,$content);
	}

	private function responseNews($postObj,$arr)
	{
		$fromUserName=$postObj->ToUserName;
		$toUserName=$postObj->FromUserName;
		$time=time();
		$msgType='text';
		$template="<xml>
						<ToUserName><![CDATA[%s]]></ToUserName>
						<FromUserName><![CDATA[%s]]></FromUserName>
						<CreateTime>%s</CreateTime>
						<MsgType><![CDATA[news]]></MsgType>
						<ArticleCount>".count($arr)."</ArticleCount>
						<Articles>";
		foreach($arr as $v){
			$template.="
						<item>
						<Title><![CDATA[".$v[title]."]]></Title>
						<Description><![CDATA[".$v[description]."]]></Description>
						<PicUrl><![CDATA[".$v['picUrl']."]]></PicUrl>
						<Url><![CDATA[".$v[url]."]]></Url>
						</item>";
		}
		$template.="
						</Articles>
						</xml>";
		echo sprintf($template,$toUserName,$fromUserName,time());
	}
	
	
	private function getcode()
	{
		if (!isset($_GET['code'])){

			$wxinfo = $this->mysql->getRow("select * from dt_wxinfo");
		
			$appid = $wxinfo['wxpappid'];
			$secret = $wxinfo['wxpsecret'];
			$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

			$redirect_uri = urlencode($http_type.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].$_SERVER['QUERY_STRING']);
			$url ="https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_userinfo&state=1#wechat_redirect";
			header("Location:".$url);
			exit();
		} else {

		    $code = $_GET['code'];
			$openid = $this->getopenid($code);
			return $openid;
		}
		
	}
	
	public function getopenid($code)
	{

		$wxinfo = $this->mysql->getRow("select * from dt_wxinfo");
		
		$appid = $wxinfo['wxpappid'];
		$secret = $wxinfo['wxpsecret'];
		
		$url1 = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appid&secret=$secret&code=$code&grant_type=authorization_code";

		$res1 = json_decode($this->common->httpGet($url1));
		$url2 = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=$appid&grant_type=refresh_token&refresh_token=$res1->refresh_token";

		$res2 = json_decode($this->common->httpGet($url2));

		$url3 = "https://api.weixin.qq.com/sns/userinfo?access_token=$res2->access_token&openid=$res2->openid&lang=zh_CN";
		$res3 = json_decode($this->common->httpGet($url3),true);

		return $res3['openid'];
	}
	
	private function setformhash()
	{
		$uid = isset($_COOKIE['dtuid'])?$_COOKIE['dtuid']:0;

		$formhash = substr(MD5("FORMHASH" . $uid . time()),8,16);
		
		$_SESSION['formhash'] = $formhash;
		
	}
	
	private function replaceSpecialChar($strParam){
		$regex = "/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
		return preg_replace($regex,"",$strParam);
	}
	
	private function build_order_no(){  
		return date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);  
	} 
	
	private function build_dcode()
	{
		return date('ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 6);
	}

}