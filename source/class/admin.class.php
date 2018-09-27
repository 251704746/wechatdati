<?php
class admin	
{
	public function __construct()
	{	
		error_reporting(0);
		$this->smarty = $GLOBALS["smarty"];
		$this->mysql = $GLOBALS["mysql"];
		$this->smarty->setTemplateDir(dirname(__FILE__) . '/tpl/');
		$wxinfo = $this->mysql->getRow("select * from dt_wxinfo"); 
		
		include_once (dirname( __FILE__ ) .'/common.class.php');
		$this->common = new common();

	}
	
	public function index()
	{
		$this->checklogin();

		$this->smarty->display( "index.html" );
	}
	public function head()
	{
		$this->checklogin();
		$username = $_COOKIE['adminname'];
		$this->smarty->assign( "username" ,$username); 
		$this->smarty->display( "head.html" );
	}
	public function left()
	{
		$this->checklogin();

		$this->smarty->display( "left.html" );
	}
	public function main()
	{
		$this->checklogin();
		$sysinfo = $this->getSystemInfo();
		$manageinfo = $this->getManageInfo();
		$this->smarty->assign("sysinfo",$sysinfo);
		$this->smarty->assign("manageinfo",$manageinfo);
		$this->smarty->display( "main.html" );
	}
	
	public function ainfo($info,$url='')
	{
		$this->smarty->assign("info",$info);
		$this->smarty->assign("url",$url);
		$this->smarty->display( "ainfo.html" );
	}
	
	public function uminfo($info,$url='')
	{
		$this->smarty->assign("info",$info);
		$this->smarty->assign("url",$url);
		$this->smarty->display( "minfo.html" );
	}
	
	public function user()
	{
		$this->checklogin();
		$filter = array();
		$num = 10;
		
		$susername = isset($_GET['susername'])?$_GET['susername']:"";
		
		$where = "";
		
		if($susername){
			$where = " and username like '%".$susername."%'";
		}
		
		$query = $this->mysql->query("select * from dt_users where ugid=100 $where");
		$filter["total"] = $this->mysql->num_rows($query);
		$filter["tpage"] = ceil( $filter["total"] / $num );
		$filter["cpage"] = isset( $_GET["cpage"] ) ? $_GET["cpage"] : 1;
		$filter["lpage"] = ( $filter["tpage"] > 0 ) ? $filter["tpage"] : 1;
		
		$offset = ( $filter["cpage"] - 1 ) * $num;
		$filter["fpage"] = ( ( $filter["cpage"] - 1 ) > 0 ) ? ( $filter["cpage"] - 1 ) : 1;
		$filter["npage"] = ( ( $filter["cpage"] + 1 ) <= $filter["tpage"] ) ? ( $filter["cpage"] + 1 ) : (( $filter["tpage"] > 0)?$filter["tpage"]:1);

		$sql = "select * from dt_users where ugid=100 $where";
		$res = $this->mysql->selectLimit($sql,$num,$offset);
		$arr = array();
		while ($rows = $this->mysql->fetchRow($res))
		{
			$rows['uaddtime'] = date("Y-m-d H:i:s",$rows['uaddtime']);
			$arr[] = $rows;
		}

		$this->smarty->assign("susername",$susername);
		$this->smarty->assign("users",$arr);
		$this->smarty->assign("filter",$filter);

		$this->smarty->display( "user.html" );
	}
	
	public function manage()
	{
		$this->checklogin();

		$aid = isset($_GET["aid"])?intval($_GET["aid"]):0;
		
		$manage = $this->mysql->getRow("select * from dt_manage where aid=".$aid);
		$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

		$url = $http_type.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."?mod=admin&act=mcenter";
		
		$this->smarty->assign( "url" ,$url);
		$this->smarty->assign( "aid" ,$aid);
		$this->smarty->assign( "manage" ,$manage);
		$this->smarty->display( "manage.html" );
	}
	
	public function changepwd()
	{
		$this->checklogin();
		$uid = $_COOKIE['adminid'];
		$username = $_COOKIE['adminname'];
		$this->smarty->assign( "username" ,$username); 
		$this->smarty->assign( "uid" ,$uid); 
		$this->smarty->display( "changepwd.html" );
	}
	
	public function black()
	{
		$this->checklogin();
		
		$black = $this->mysql->getAll("select * from dt_black");
		$this->smarty->assign( "black" ,$black); 
		$this->smarty->display( "black.html" );
	}
	
	public function holdblack()
	{
		$this->checklogin();
		$bname = isset($_POST["bname"])?$_POST["bname"]:"";

		$exist = $this->mysql->getOne("select count(*) from dt_black where bname='$bname'");
		
		if($exist == 0){
			$this->mysql->query('insert into dt_black(bname) values("'.$bname.'")');
		}
	}
	
	public function delblack()
	{
		$bid = isset($_POST["bid"])?intval($_POST["bid"]):0;
		$this->mysql->query("delete from dt_black where bid=".$bid);
	}
	
	public function checkoldpwd()
	{
		$this->checklogin();
		$username = $_COOKIE['adminname'];
		$password = $_POST["password"];
		
		$id = $this->mysql->getOne("select uid from dt_users where username='$username' and password='".MD5($password)."'");
		
		if($id){
			echo 1;
		}else{
			echo 0;
		}
	}
	
	public function savemanager()
	{
		$this->checklogin();
		$mname = $_POST["mname"];
		$aid = $_POST["aid"];
		$mid = isset($_POST["mid"])?$_POST["mid"]:0;
		
		$uid = $this->mysql->getOne("select mid from dt_manage where mname='$mname' and aid<>".$aid);
		if($uid){
			echo 0;
		}else{
			$_POST['mpasswd'] = MD5($_POST['mpasswd']);
			if($mid){
				$this->mysql->autoExecute("dt_manage",$_POST,"UPDATE","mid=".$mid);
			}else{
				$_POST['mtime'] = time();
				$this->mysql->autoExecute("dt_manage",$_POST);
			}
			
			echo 1;
		}
	}
	
	public function action_changepwd()
	{
		$this->checklogin();
		$username = $_COOKIE['adminname'];
		$password = $_POST["password"];
		
		$id = $this->mysql->query("update dt_users set password='".MD5($password)."' where username='$username'");
		
		if($id){
			echo 1;
		}else{
			echo 0;
		}
	}
	
	public function menu()
	{
		$this->checklogin();
		
		$arr = $this->mysql->getAll("select * from dt_menu");

		$menu = array();
		
		foreach($arr as $key=>$val){
			if($val['mpid'] == 0){
				$menu[$val['mid']]['mhasmenu'] = 0;
				$menu[$val['mid']]['mid'] = $val['mid'];
				$menu[$val['mid']]['mpid'] = $val['mpid'];
				$menu[$val['mid']]['mtitle'] = $val['mtitle'];
				$menu[$val['mid']]['murl'] = $val['murl'];
			}else{
				$menu[$val['mpid']]['mhasmenu'] = 1;
				$menu[$val['mpid']]['menu'][$val['mid']] = $val;
			}
		}
		
		$maxmid = $this->mysql->getOne("select max(mid) from dt_menu");
		
		$this->smarty->assign("maxmid",$maxmid);
		$this->smarty->assign("menu",$menu);

		$this->smarty->display( "menu.html" );
	}
	
	public function delmenu()
	{
		$this->checklogin();
		$id = $_GET['mid'];

		$this->mysql->query("delete from dt_menu where mid=$id or mpid=$id");
		
		$this->ainfo("删除成功","index.php?mod=admin&act=menu");
	}
	
	public function savemenu()
	{
		$this->checklogin();
		$minfo = $_POST['minfo'];

		foreach($minfo as $val){
			if(!$val["mtitle"]){continue;}

			if(isset($val['mid']) && $val['mid']){
				$this->mysql->autoExecute("dt_menu",$val,"UPDATE","mid=".$val['mid']);	
			}else{
				$this->mysql->autoExecute("dt_menu",$val);
			}
		}
		$this->ainfo("保存成功","index.php?mod=admin&act=menu");
	}
	
	public function activity()
	{
		$this->checklogin();
		$filter = array();
		$num = 10;
		
		$satitle = isset($_GET['satitle'])?$_GET['satitle']:"";
		
		$where = "where 1";
		
		if($satitle){
			$where .= " and atitle like '%".$satitle."%'";
		}
		
		$query = $this->mysql->query("select * from dt_activity $where");
		$filter["total"] = $this->mysql->num_rows($query);
		$filter["tpage"] = ceil( $filter["total"] / $num );
		$filter["cpage"] = isset( $_GET["cpage"] ) ? $_GET["cpage"] : 1;
		$filter["lpage"] = ( $filter["tpage"] > 0 ) ? $filter["tpage"] : 1;
		
		$offset = ( $filter["cpage"] - 1 ) * $num;
		$filter["fpage"] = ( ( $filter["cpage"] - 1 ) > 0 ) ? ( $filter["cpage"] - 1 ) : 1;
		$filter["npage"] = ( ( $filter["cpage"] + 1 ) <= $filter["tpage"] ) ? ( $filter["cpage"] + 1 ) : (( $filter["tpage"] > 0)?$filter["tpage"]:1);

		$sql = "select * from dt_activity $where";
		$res = $this->mysql->selectLimit($sql,$num,$offset);
		$arr = array();
		while ($rows = $this->mysql->fetchRow($res))
		{
			$rows['atime'] = date("Y-m-d H:i:s",$rows['atime']);
			$arr[] = $rows;
		}

		$this->smarty->assign("satitle",$satitle);
		$this->smarty->assign("activity",$arr);
		$this->smarty->assign("filter",$filter);

		$this->smarty->display( "activity.html" );
	}
	
	public function addactivity()
	{
		$this->checklogin();
		
		$aid = isset($_GET['aid'])?$_GET['aid']:0;
		
		/*$prize1 = array(
			0 => array("pid"=>0,"ptype"=>1,"pname"=>'',"ppacket"=>0.00,"pthumb"=>'',"paward"=>'',"pnum"=>0,"pleft"=>0,"pprobability"=>0.00),			
			1 => array("pid"=>0,"ptype"=>1,"pname"=>'',"ppacket"=>0.00,"pthumb"=>'',"paward"=>'',"pnum"=>0,"pleft"=>0,"pprobability"=>0.00),			
			2 => array("pid"=>0,"ptype"=>1,"pname"=>'',"ppacket"=>0.00,"pthumb"=>'',"paward"=>'',"pnum"=>0,"pleft"=>0,"pprobability"=>0.00),			
			3 => array("pid"=>0,"ptype"=>1,"pname"=>'',"ppacket"=>0.00,"pthumb"=>'',"paward"=>'',"pnum"=>0,"pleft"=>0,"pprobability"=>0.00),			
			4 => array("pid"=>0,"ptype"=>1,"pname"=>'',"ppacket"=>0.00,"pthumb"=>'',"paward"=>'',"pnum"=>0,"pleft"=>0,"pprobability"=>0.00),			
			5 => array("pid"=>0,"ptype"=>1,"pname"=>'',"ppacket"=>0.00,"pthumb"=>'',"paward"=>'',"pnum"=>0,"pleft"=>0,"pprobability"=>0.00)		
		);
		*/
		$prizes = $this->mysql->getAll("select * from dt_prize where aid=".$aid);

		//$prizes = array_replace($prize1,$prize2);
		
		$activity = $this->mysql->getRow("select * from dt_activity where aid=".$aid);

		$this->smarty->assign("activity",$activity);
		
		$this->smarty->assign( "prizes" ,$prizes); 

		$this->smarty->display( "addactivity.html" );
	}
	
	public function action_activity()
	{
		$this->checklogin();

		if(!$_POST)return ;

		$aid = isset($_POST['aid']) ? $_POST['aid'] : 0;

		$infos = $_POST['infos'];
		
		if($_FILES['athumb']["name"]){
			$tmp = explode('.', $_FILES['athumb']["name"]);
			$_POST['athumb'] = "asthumb" . time() . rand(1000,9999) .'.'. end($tmp);
			move_uploaded_file($_FILES['athumb']["tmp_name"],"upload/". $_POST['athumb']);
		}else{
			unset($_POST['athumb']);
		}
		if($_FILES['abthumb']["name"]){
			$tmp = explode('.', $_FILES['abthumb']["name"]);
			$_POST['abthumb'] = "abthumb" . time() . rand(1000,9999) .'.'. end($tmp);
			move_uploaded_file($_FILES['abthumb']["tmp_name"],"upload/". $_POST['abthumb']);
		}else{
			unset($_POST['abthumb']);
		}
		
		if($_FILES['asthumb']["name"]){
			$tmp = explode('.', $_FILES['asthumb']["name"]);
			$_POST['asthumb'] = "asthumb" . time() . rand(1000,9999) .'.'. end($tmp);
			move_uploaded_file($_FILES['asthumb']["tmp_name"],"upload/". $_POST['asthumb']);
		}else{
			unset($_POST['asthumb']);
		}
		
		if($aid){
			$this->mysql->autoExecute("dt_activity",$_POST,"UPDATE","aid=".$aid);	
		}else{	
			$_POST['atime'] = time();
			$this->mysql->autoExecute("dt_activity",$_POST);
			$aid = $this->mysql->insert_id();
		}

		if($_FILES['awardimg1']["name"]){
			$tmp = explode('.', $_FILES['awardimg1']["name"]);
			$infos[1]['pthumb'] = "pth1" . time() . rand(1000,9999) .'.'. end($tmp);
			move_uploaded_file($_FILES['awardimg1']["tmp_name"],"upload/prizes/". $infos[1]['pthumb']);
		}else{
			unset($infos[1]['pthumb']);
		}
		if($_FILES['awardimg2']["name"]){
			$tmp = explode('.', $_FILES['awardimg2']["name"]);
			$infos[2]['pthumb'] = "pth2" . time() . rand(1000,9999) .'.'. end($tmp);
			move_uploaded_file($_FILES['awardimg2']["tmp_name"],"upload/prizes/". $infos[2]['pthumb']);
		}else{
			unset($infos[2]['pthumb']);
		}
		if($_FILES['awardimg3']["name"]){
			$tmp = explode('.', $_FILES['awardimg3']["name"]);
			$infos[3]['pthumb'] = "pth3" . time() . rand(1000,9999) .'.'. end($tmp);
			move_uploaded_file($_FILES['awardimg3']["tmp_name"],"upload/prizes/". $infos[3]['pthumb']);
		}else{
			unset($infos[3]['pthumb']);
		}
		if($_FILES['awardimg4']["name"]){
			$tmp = explode('.', $_FILES['awardimg4']["name"]);
			$infos[4]['pthumb'] = "pth4" . time() . rand(1000,9999) .'.'. end($tmp);
			move_uploaded_file($_FILES['awardimg4']["tmp_name"],"upload/prizes/". $infos[4]['pthumb']);
		}else{
			unset($infos[4]['pthumb']);
		}
		if($_FILES['awardimg5']["name"]){
			$tmp = explode('.', $_FILES['awardimg5']["name"]);
			$infos[5]['pthumb'] = "pth5" . time() . rand(1000,9999) .'.'. end($tmp);
			move_uploaded_file($_FILES['awardimg5']["tmp_name"],"upload/prizes/". $infos[5]['pthumb']);
		}else{
			unset($infos[5]['pthumb']);
		}
		if($_FILES['awardimg6']["name"]){
			$tmp = explode('.', $_FILES['awardimg6']["name"]);
			$infos[6]['pthumb'] = "pth6" . time() . rand(1000,9999) .'.'. end($tmp);
			move_uploaded_file($_FILES['awardimg6']["tmp_name"],"upload/prizes/". $infos[6]['pthumb']);
		}else{
			unset($infos[6]['pthumb']);
		}

		foreach($infos as $key => $val){
			if(!$val["paward"]){continue;}
			$val['aid'] = ($aid > 0)?$aid: 0;
			
			if($val['pid']){
				$prleft = $this->mysql->getOne("select prleft from dt_prize where pid=".$val['pid']);
				if( $val['pleft'] > $prleft ){
					$val['pleft'] = $prleft;
				}
				$this->mysql->autoExecute("dt_prize",$val,"UPDATE","pid=".$val['pid']);
			}else{
				$val['prleft'] = $val['pnum'];
				$val['pleft'] = $val['pnum'];
				$this->mysql->autoExecute("dt_prize",$val);
			}
		}
		
		$this->ainfo("添加成功");
	}
	
	public function mlogin()
	{
		$this->smarty->display("mlogin.html");
	}
	
	public function meditpwd()
	{
		$mname = isset($_COOKIE['mname'])?$_COOKIE['mname']:"";
		if(!$mname){
			header("Location:index.php?mod=admin&act=mlogin");
		}

		$this->smarty->assign( "mname" ,$mname); 
		$this->smarty->display("meditpwd.html");
	}
	public function mcenter()
	{
		$wxinfo = $this->mysql->getRow("select * from dt_wxinfo");
		$signPackage = $this->common->getSignPackage(); 
		
		$mname = isset($_COOKIE['mname'])?$_COOKIE['mname']:"";
		if(!$mname){
			header("Location:index.php?mod=admin&act=mlogin");
		}
		$minfo = $this->mysql->getRow("select * from dt_activity where aid=(select aid from dt_manage where mname='".$mname."' limit 1)");
		
		$this->smarty->assign("signPackage" ,$signPackage); 
		$this->smarty->assign("minfo" ,$minfo); 
		$this->smarty->display("mcenter.html");
	}
	
	public function verification()
	{
		$dcode = isset($_GET['dcode'])?$_GET['dcode']:'';
		$muid = isset($_COOKIE['muid'])?$_COOKIE['muid']:0;
		if(!$muid){
			header("Location:index.php?mod=admin&act=mlogin");
		}
		$activity = $this->mysql->getRow("select * from dt_activity where aid=(select aid from dt_manage where mid='".$muid."' limit 1)");
		$exist = $this->mysql->getRow("select * from dt_draw where aid=".$activity['aid']." and dcode='".$dcode."'");
		if(!$exist){
			$this->uminfo("核销失败，请检查输入的核销码是否正确");exit;
		}

		if($exist['datime']){
			$this->uminfo("该核销码已经核销，请勿重复操作");exit;
		}

		$dtime = strtotime($activity['adeadline']);
		
		if(time() > $dtime){
			$this->uminfo("领奖时间截止为: ".$activity['adeadline']." ,您已错过领奖时间");
			exit;
		}
		
		if($dcode){
			$r = $this->mysql->query("update dt_draw set datime='".time()."' where dcode='".$dcode."'" );
			if($r){
				$this->uminfo("核销成功");
			}else{
				$this->uminfo("核销失败，请检查输入的核销码是否正确");
			}	
		}else{
			$this->uminfo("请输入核销码");
		}
	}
	
	public function action_login()
	{
		
		$username = $_POST["username"];
		$password = $_POST["password"];

		$id1 = $this->mysql->getOne("select uid from dt_users where username='$username' and password='".MD5($password)."'");

		$id2 = $this->mysql->getOne("select uid from dt_users where username='$username'");
		if($id1){
			setcookie("adminname", $username, time()+3600*18, "/"); 
			setcookie("adminid", $id1, time()+3600*18, "/"); 
			$info['uloginip'] = $_SERVER["REMOTE_ADDR"];
			$info['ulogintime'] = time();
			$this->mysql->autoExecute("dt_users",$info,"UPDATE","username='".$username."'");
			$result = 1;
		}elseif(!$id2){
			$result = 2;
		}else{
			$result = -1;
		}
		echo $result;
	}
	

	public function action_mlogin()
	{	
		$mname = $_POST["mname"];
		$mpasswd = $_POST["mpasswd"];

		$id1 = $this->mysql->getOne("select mid from dt_manage where mname='$mname' and mpasswd='".MD5($mpasswd)."'");

		$id2 = $this->mysql->getOne("select mid from dt_manage where mname='$mname'");
		if($id1){
			setcookie("mname", $mname, time()+3600*18, "/"); 
			setcookie("muid", $id1, time()+3600*18, "/"); 
			$info['mlogip'] = $this->common->getIP();
			$info['mlogtime'] = time();
			$this->mysql->autoExecute("dt_manage",$info,"UPDATE","mname='".$mname."'");
			$result = 1;
		}elseif(!$id2){
			$result = 2;
		}else{
			$result = -1;
		}
		echo $result;
	}
	
	public function action_meditpwd()
	{
		$mname = $_POST["mname"];
		$mpasswd = $_POST["mpasswd"];
		
		$info = array("mpasswd" => MD5($mpasswd));
		
		$this->mysql->autoExecute("dt_manage",$info,"UPDATE","mname='".$mname."'");
	}

	public function wxinfo()
	{
		$this->checklogin();	
		
		$arr = $this->mysql->getRow("select * from dt_wxinfo");

		$this->smarty->assign("wxinfo",$arr);

		$this->smarty->display( "wxinfo.html" );
	}
	
	public function action_wxinfo()
	{
		$wid = isset( $_POST['wid'] ) ? $_POST['wid'] : 0;
		
		$_POST['wxlforce'] = $_POST['wxforce'];
		$_POST['wxpcheck'] = $_POST['wxcheck'];
		
		if($wid){
			$this->mysql->autoExecute("dt_wxinfo",$_POST,"UPDATE","wid=".$wid);
			$this->ainfo("修改成功");
		}else{
			$this->mysql->autoExecute("dt_wxinfo",$_POST);
			$this->ainfo("添加成功");
		}
	}
	
	public function subimport()
	{
		$this->smarty->display("subimport.html");
	}
	
	public function action_subimport()
	{
		include('phpexcel/PHPExcel.php');
		header("content-type:text/html;charset=utf-8");
		set_time_limit(0);
		
		if (is_uploaded_file($_FILES['fcattachment']['tmp_name'])) {

			$objReader = PHPExcel_IOFactory::createReader('Excel5');
			
			$filename = $_FILES['fcattachment']['tmp_name'];
			$extension = strtolower( pathinfo($_FILES['fcattachment']['name'], PATHINFO_EXTENSION) );
			
			if ($extension =='xlsx') {
				$objReader = new PHPExcel_Reader_Excel2007();
				$objExcel = $objReader ->load($filename);
			} else if ($extension =='xls') {
				$objReader = new PHPExcel_Reader_Excel5();
				$objExcel = $objReader ->load($filename);
			} else if ($extension=='csv') {
				$PHPReader = new PHPExcel_Reader_CSV();
				$PHPReader->setInputEncoding('GBK');
				$PHPReader->setDelimiter(',');
				$objExcel = $PHPReader->load($filename);
			}
			
			$objPHPExcel = $objReader->load($filename); 
			$sheet = $objPHPExcel->getSheet(0); 
			$highestRow = $sheet->getHighestRow(); 

			$info = array();
			
			for($i=2;$i<=$highestRow;$i++)  
			{
				$info[$i]['aid'] = $objPHPExcel->getActiveSheet()->getCell("A".$i)->getValue();
				$info[$i]['stype'] = $objPHPExcel->getActiveSheet()->getCell("B".$i)->getValue();
				$info[$i]['stitle'] = $objPHPExcel->getActiveSheet()->getCell("C".$i)->getValue();
				$info[$i]['soption1'] = $objPHPExcel->getActiveSheet()->getCell("D".$i)->getValue();
				$info[$i]['soption2'] = $objPHPExcel->getActiveSheet()->getCell("E".$i)->getValue();
				$info[$i]['soption3'] = $objPHPExcel->getActiveSheet()->getCell("F".$i)->getValue();
				$info[$i]['soption4'] = $objPHPExcel->getActiveSheet()->getCell("G".$i)->getValue();
				$info[$i]['soption5'] = $objPHPExcel->getActiveSheet()->getCell("H".$i)->getValue();
				$info[$i]['soption6'] = $objPHPExcel->getActiveSheet()->getCell("I".$i)->getValue();
				$info[$i]['sanswer'] = $objPHPExcel->getActiveSheet()->getCell("J".$i)->getValue();
				$info[$i]['sanswers'] = $objPHPExcel->getActiveSheet()->getCell("K".$i)->getValue();
				$info[$i]['stime'] = time();
				
				$hsid =$this->mysql->getOne("select sid from dt_subject where aid=".$info[$i]['aid']." and stitle='".$info[$i]['stitle']."'");
				if(!$hsid){
					$this->mysql->autoExecute("dt_subject",$info[$i]);
				}
			}
			$this->ainfo("数据导入成功");
		}
	}
	
	public function addsubject()
	{
		$this->checklogin();	
		
		$sid = isset( $_GET['sid'] ) ? $_GET['sid'] : 0;
		
		$arr = $this->mysql->getRow("select * from dt_subject where sid=".$sid);
		
		$activity = $this->mysql->getAll("select * from dt_activity");

		$this->smarty->assign("subject",$arr);

		$this->smarty->assign("activity",$activity);

		$this->smarty->display( "addsubject.html" );
	}
	
	public function action_subject()
	{
		$sid = isset( $_POST['sid'] ) ? $_POST['sid'] : 0;
		
		$_POST['stime'] = time();
		
		if($sid){
			$this->mysql->autoExecute("dt_subject",$_POST,"UPDATE","sid=".$sid);
			$this->ainfo("修改成功");
		}else{
			$this->mysql->autoExecute("dt_subject",$_POST);
			$this->ainfo("添加成功");
		}
	}
	
	public function subject()
	{
		$this->checklogin();
		$filter = array();
		$num = 10;
		
		$satitle = isset($_GET['satitle'])?$_GET['satitle']:"";

		$where = "where 1";
		
		if($satitle){
			$said = $this->mysql->getCol("select aid from dt_activity where atitle like '%$satitle%'");
			if($said){
				$where .= " and aid in (". implode(",",$said) .")";
			}
		}
		
		$allactivity = $this->mysql->getAll("select aid,atitle from dt_activity");
		$this->smarty->assign("allactivity",$allactivity);
		
		$query = $this->mysql->query("select * from dt_subject $where");
		$filter["total"] = $this->mysql->num_rows($query);
		$filter["tpage"] = ceil( $filter["total"] / $num );
		$filter["cpage"] = isset( $_GET["cpage"] ) ? $_GET["cpage"] : 1;
		$filter["lpage"] = ( $filter["tpage"] > 0 ) ? $filter["tpage"] : 1;
		
		$offset = ( $filter["cpage"] - 1 ) * $num;
		$filter["fpage"] = ( ( $filter["cpage"] - 1 ) > 0 ) ? ( $filter["cpage"] - 1 ) : 1;
		$filter["npage"] = ( ( $filter["cpage"] + 1 ) <= $filter["tpage"] ) ? ( $filter["cpage"] + 1 ) : (( $filter["tpage"] > 0)?$filter["tpage"]:1);

		$sql = "select * from dt_subject $where";
		$res = $this->mysql->selectLimit($sql,$num,$offset);
		$arr = array();
		while ($rows = $this->mysql->fetchRow($res))
		{
			$rows['satitle'] = $this->mysql->getOne("select atitle from dt_activity where aid=".$rows['aid']);
			$rows['stype'] = ($rows['stype'] == 1)? "单选题" : "多选题"; 
			$rows['stitle'] = strip_tags($rows['stitle']);
			$rows['stime'] = date("Y-m-d H:i:s",$rows['stime']);
			$arr[] = $rows;
		}

		$this->smarty->assign("subject",$arr);
		$this->smarty->assign("satitle",$satitle);
		$this->smarty->assign("filter",$filter);
		$this->smarty->display( "subject.html" );
	}
	
	public function cpsubject()
	{
		$this->checklogin();	
		
		$sid = isset( $_GET['sid'] ) ? $_GET['sid'] : 0;
		
		$arr = $this->mysql->getRow("select * from dt_subject where sid=".$sid);
		
		$activity = $this->mysql->getAll("select * from dt_activity where aid<>".$arr['aid']);

		$this->smarty->assign("subject",$arr);

		$this->smarty->assign("activity",$activity);

		$this->smarty->display( "cpsubject.html" );
	}
	
	public function copy_subject()
	{
		$id = isset($_GET['ids'])?$_GET['ids']:0;
		$aid = isset($_GET['aid'])?$_GET['aid']:0;

		$usuccess = 0;
		
		if(strpos($id,',')){
			$ids = explode(",",$id);
			foreach($ids as $val)
			{
				$hoid = $this->mysql->getOne("select aid from dt_subject where sid=".$val." and aid=".$aid);
				if(!$hoid){
					$this->mysql->query("insert into dt_subject(stype,stitle,soption1,soption2,soption3,soption4,soption5,soption6,sanswer,sanswers)
					select stype,stitle,soption1,soption2,soption3,soption4,soption5,soption6,sanswer,sanswers from dt_subject where sid=".$val);
					$nid = $this->mysql->insert_id();
					$this->mysql->query("update dt_subject set aid=$aid ,stime='".time()."' where sid=".$nid);
					$usuccess = 1;
				}
			}
		}else{
			$hoid = $this->mysql->getOne("select aid from dt_subject where sid=".$val." and aid=".$aid);
			if(!$hoid){
				$this->mysql->query("insert into dt_subject(stype,stitle,soption1,soption2,soption3,soption4,soption5,soption6,sanswer,sanswers)
					select stype,stitle,soption1,soption2,soption3,soption4,soption5,soption6,sanswer,sanswers from dt_subject where sid=".$id);
				$nid = $this->mysql->insert_id();
				$this->mysql->query("update dt_subject set aid=$aid , stime='".time()."' where sid=".$nid);
				$usuccess = 1;
			}
		}
		if($usuccess){
			$this->ainfo("复制成功");
		}else{
			$this->ainfo("复制失败");
		}
		
	}
	
	public function delete_subject()
	{
		$id = isset($_GET['id'])?$_GET['id']:0;

		if(strpos($id,',')){
			$this->mysql->query("delete from dt_subject where sid in(".$id.")");
		}else{
			$this->mysql->query("delete from dt_subject where sid=".$id);
		}
		
		$this->ainfo("删除成功");
	}
	
	public function addasubject()
	{
		$this->checklogin();	
		
		$sid = isset( $_GET['sid'] ) ? $_GET['sid'] : 0;
		$aid = isset( $_GET['aid'] ) ? $_GET['aid'] : 0;
		
		$arr = $this->mysql->getRow("select * from dt_subject where sid=".$sid);
		
		$activity = $this->mysql->getAll("select * from dt_activity");

		$this->smarty->assign("subject",$arr);
		$this->smarty->assign("aid",$aid);

		$this->smarty->assign("activity",$activity);

		$this->smarty->display( "addasubject.html" );
	}
	
	
	public function asubject()
	{
		$this->checklogin();
		$filter = array();
		$num = 10;
		
		$aid = isset($_GET['aid'])?$_GET['aid']:0;
		$satitle = isset($_GET['satitle'])?$_GET['satitle']:"";

		$where = "where aid=$aid";
		
		if($satitle){
			$said = $this->mysql->getCol("select aid from dt_activity where atitle like '%$satitle%'");

			$where .= " and aid in (". implode(",",$said) .")";
		}
		
		$query = $this->mysql->query("select * from dt_subject $where");
		$filter["total"] = $this->mysql->num_rows($query);
		$filter["tpage"] = ceil( $filter["total"] / $num );
		$filter["cpage"] = isset( $_GET["cpage"] ) ? $_GET["cpage"] : 1;
		$filter["lpage"] = ( $filter["tpage"] > 0 ) ? $filter["tpage"] : 1;
		
		$offset = ( $filter["cpage"] - 1 ) * $num;
		$filter["fpage"] = ( ( $filter["cpage"] - 1 ) > 0 ) ? ( $filter["cpage"] - 1 ) : 1;
		$filter["npage"] = ( ( $filter["cpage"] + 1 ) <= $filter["tpage"] ) ? ( $filter["cpage"] + 1 ) : (( $filter["tpage"] > 0)?$filter["tpage"]:1);

		$sql = "select * from dt_subject $where";
		$res = $this->mysql->selectLimit($sql,$num,$offset);
		$arr = array();
		while ($rows = $this->mysql->fetchRow($res))
		{
			$rows['satitle'] = $this->mysql->getOne("select atitle from dt_activity where aid=".$rows['aid']);
			$rows['stype'] = ($rows['stype'] == 1)? "单选题" : "多选题"; 
			$rows['stitle'] = strip_tags($rows['stitle']);
			$rows['stime'] = date("Y-m-d H:i:s",$rows['stime']);
			$arr[] = $rows;
		}

		$this->smarty->assign("aid",$aid);
		$this->smarty->assign("subject",$arr);
		$this->smarty->assign("satitle",$satitle);
		$this->smarty->assign("filter",$filter);
		$this->smarty->display( "asubject.html" );
	}
	
	

	public function test()
	{
		$this->checklogin();
		$filter = array();
		$num = 10;
		
		$aid = isset($_GET['aid'])?$_GET['aid']:0;
		
		$susername = isset($_GET['susername'])?$_GET['susername']:"";

		$where = "";
		
		if($susername){
			$suid = $this->mysql->getCol("select uid from dt_users where username like '%$susername%'");
			if($suid)$where .= " and uid in (". implode(",",$suid) .")";
			else $where .= " and uid=0";
		}

		$query = $this->mysql->query("select * from dt_test where aid=$aid $where");
		$filter["total"] = $this->mysql->num_rows($query);
		$filter["tpage"] = ceil( $filter["total"] / $num );
		$filter["cpage"] = isset( $_GET["cpage"] ) ? $_GET["cpage"] : 1;
		$filter["lpage"] = ( $filter["tpage"] > 0 ) ? $filter["tpage"] : 1;
		
		$offset = ( $filter["cpage"] - 1 ) * $num;
		$filter["fpage"] = ( ( $filter["cpage"] - 1 ) > 0 ) ? ( $filter["cpage"] - 1 ) : 1;
		$filter["npage"] = ( ( $filter["cpage"] + 1 ) <= $filter["tpage"] ) ? ( $filter["cpage"] + 1 ) : (( $filter["tpage"] > 0)?$filter["tpage"]:1);

		$sql = "select * from dt_test where aid=$aid $where";
		$res = $this->mysql->selectLimit($sql,$num,$offset);
		$arr = array();
		while ($rows = $this->mysql->fetchRow($res))
		{
			$rows['username'] = $query = $this->mysql->getOne("select username from dt_users where uid=".$rows['uid']);
			$rows['ttime'] = date("Y-m-d H:i:s",$rows['ttime']);
			$arr[] = $rows;
		}

		$this->smarty->assign("test",$arr);
		$this->smarty->assign("susername",$susername);
		$this->smarty->assign("aid",$aid);
		$this->smarty->assign("filter",$filter);

		$this->smarty->display( "test.html" );
	}
	
	function export_drawlist()
	{
		$this->checklogin();

		include('phpexcel/PHPExcel.php');
		header("content-type:text/html;charset=utf-8");

		$objPHPExcel = new PHPExcel();
		$objPHPExcel->setActiveSheetIndex(0);

		$aid = isset($_GET['aid'])?$_GET['aid']:0;

		$data = $this->mysql->getAll("select * from dt_draw where aid=$aid and dwin>0");

		foreach($data as $key => $val){
			$users = $this->mysql->getRow("select * from dt_users where uid=".$val['uid']);
			$data[$key]['username'] = ($users["username"])?$users["username"]:"--";
			$data[$key]['name'] = ($users["name"])?$users["name"]:"--";
			$data[$key]['telphone'] = ($users["telphone"])?$users["telphone"]:"--";
			$data[$key]['atitle'] = $this->mysql->getOne("select atitle from dt_activity where aid=".$val['aid']);
			$data[$key]['dtime'] = date("Y-m-d H:i:s",$val['dtime']);
			$data[$key]['datimes'] = ($val['datime'])?date("Y-m-d H:i:s",$val['datime']):"未领取";

		}

		$objPHPExcel->getActiveSheet()->SetCellValue('A1', "活动名称");
		$objPHPExcel->getActiveSheet()->SetCellValue('B1', "中奖用户");
		$objPHPExcel->getActiveSheet()->SetCellValue('C1', "真实姓名");
		$objPHPExcel->getActiveSheet()->SetCellValue('D1', "手机号码");
		$objPHPExcel->getActiveSheet()->SetCellValue('E1', "中奖奖项");
		$objPHPExcel->getActiveSheet()->SetCellValue('F1', "中奖奖品");
		$objPHPExcel->getActiveSheet()->SetCellValue('G1', "中奖时间");
		$objPHPExcel->getActiveSheet()->SetCellValue('H1', "领取时间");
		$i = 2;
		foreach($data as $key => $val){

		$objPHPExcel->getActiveSheet()->SetCellValue('A'.$i, $val['atitle']);
		$objPHPExcel->getActiveSheet()->SetCellValue('B'.$i, $val['username']);
		$objPHPExcel->getActiveSheet()->SetCellValue('C'.$i, $val['name']);
		$objPHPExcel->getActiveSheet()->SetCellValue('D'.$i, $val['telphone']);
		$objPHPExcel->getActiveSheet()->SetCellValue('E'.$i, $val['daward']);
		$objPHPExcel->getActiveSheet()->SetCellValue('F'.$i, $val['dname']);
		$objPHPExcel->getActiveSheet()->SetCellValue('G'.$i, $val['dtime']);
		$objPHPExcel->getActiveSheet()->SetCellValue('H'.$i, $val['datimes']);
                                           
		$i++;
		}
		ob_end_clean();
		$name=date("Ymd");
		$objWriter = new PHPExcel_Writer_Excel5($objPHPExcel);
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control:must-revalidate,post-check=0,pre-check=0");
		header("Content-Type:application/force-download");
		header("Content-Type:application/vnd.ms-execl");
		header("Content-Type:application/octet-stream");
		header("Content-Type:application/download");
		header("Content-Disposition:attachment;filename=".$name.".xls");
		header("Content-Transfer-Encoding:binary");
		$objWriter->save("php://output");
		
	}
	
	public function draw()
	{
		$this->checklogin();
		$filter = array();
		$num = 10;
		
		$aid = isset($_GET['aid'])?$_GET['aid']:0;
		
		$susername = isset($_GET['susername'])?$_GET['susername']:"";

		$where = "";
		
		if($susername){
			$suid = $this->mysql->getCol("select uid from dt_users where username like '%$susername%'");
			if($suid){
				$where .= " and uid in (". implode(",",$suid) .")";
			}else{
				$sxuid = $this->mysql->getCol("select uid from dt_users where name like '%$susername%'");
				if($sxuid){
					$where .= " and uid in (". implode(",",$sxuid) .")";
				}else {
					$where .= " and uid=0";
				}
			}
			
		}

		$query = $this->mysql->query("select * from dt_draw where aid=$aid and dwin<>0 $where");
		$filter["total"] = $this->mysql->num_rows($query);
		$filter["tpage"] = ceil( $filter["total"] / $num );
		$filter["cpage"] = isset( $_GET["cpage"] ) ? $_GET["cpage"] : 1;
		$filter["lpage"] = ( $filter["tpage"] > 0 ) ? $filter["tpage"] : 1;
		
		$offset = ( $filter["cpage"] - 1 ) * $num;
		$filter["fpage"] = ( ( $filter["cpage"] - 1 ) > 0 ) ? ( $filter["cpage"] - 1 ) : 1;
		$filter["npage"] = ( ( $filter["cpage"] + 1 ) <= $filter["tpage"] ) ? ( $filter["cpage"] + 1 ) : (( $filter["tpage"] > 0)?$filter["tpage"]:1);

		$sql = "select * from dt_draw where aid=$aid and dwin<>0 $where order by dtime asc";
		$res = $this->mysql->selectLimit($sql,$num,$offset);
		$arr = array();
		while ($rows = $this->mysql->fetchRow($res))
		{
			$users = $this->mysql->getRow("select * from dt_users where uid=".$rows['uid']);
			$rows['username'] = ($users["username"])?$users["username"]:"--";
			$rows['name'] = ($users["name"])?$users["name"]:"--";
			$rows['telphone'] = ($users["telphone"])?$users["telphone"]:"--";
			$rows['dtime'] = date("Y-m-d H:i:s",$rows['dtime']);
			$rows['datimes'] = ($rows['datime'])?date("Y-m-d H:i:s",$rows['datime']):"未领取";
			$arr[] = $rows;
		}

		$this->smarty->assign("draw",$arr);
		$this->smarty->assign("aid",$aid);
		$this->smarty->assign("susername",$susername);
		$this->smarty->assign("filter",$filter);
		$this->smarty->display( "draw.html" );
	}
	
	public function receive_prize()
	{
		$did = ($_POST['did'])?$_POST['did']:0;
		
		$time = time();
		$info = array("datime" => $time);
		
		$this->mysql->autoExecute("dt_draw",$info,"UPDATE","did=".$did);
		
		echo date("Y-m-d H:i:s",$time);
	}
	
	public function action_rule()
	{
		$this->checklogin();
		
		$rid = isset($_POST['rid'])?$_POST["rid"]:0;
		$rule = isset($_POST['rule'])?$_POST["rule"]:"";
		
		$info = array("rule" => $rule);
		if($rid){
			$this->mysql->autoExecute("dt_rule",$info,"UPDATE","rid=".$rid);
		}else{
			$this->mysql->autoExecute("dt_rule",$info);
		}

		header("Location:rule");
	}
	
	public function login()
	{
		$this->smarty->display( "login.html" );
	}
		
	public function createmenu()
	{
		$menudata = $this->mysql->getAll("select * from dt_menu");
		
		$menu = array();
		
		foreach($menudata as $key=>$val){
			if($val['mpid'] == 0){
				$menu[$val['mid']]['mtitle'] = $val['mtitle'];
				$menu[$val['mid']]['murl'] = $val['murl'];
			}else{
				$menu[$val['mpid']]['menu'][$val['mid']] = $val;
			}
		}
		$data='{"button":[';  
		foreach($menu as $key=>$val){
			if(isset($val['menu'])&&$val['menu']){
				$data .= '{"name":"'.$val['mtitle'].'","sub_button":[';
				foreach($val['menu'] as $k=>$v)
				{
					if (preg_match('/(http:\/\/)|(https:\/\/)/i', $v['murl'])) {
						$data .= '{"type":"view","name":"'.$v['mtitle'].'","url":"'.$v['murl'].'"}';
					}else {
						$data .= '{"type":"click","name":"'.$v['mtitle'].'","key":"'.$v['murl'].'"}';
					}
					
					if(end($val['menu']) != $v){
						$data .= ',';
					}
				}
				$data .= ']}'; 
				if(end($menu) != $val){
					$data .= ',';
				}
			}else{
				if (preg_match('/(http:\/\/)|(https:\/\/)/i', $val['murl'])) {
						$data .= '{"type":"view","name":"'.$val['mtitle'].'","url":"'.$val['murl'].'"}';
					}else {
						$data .= '{"type":"click","name":"'.$val['mtitle'].'","key":"'.$val['murl'].'"}';
					}
				if(end($menu) != $val){
					$data .= ',';
				}
			}
		}
		$data .= ']}';  
		
		$access_token = $this->common->getLaccessToken();
		$url="https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token;    
		$result = $this->common->httpGet($url,$data);  
		$result = json_decode($result,true);    

		if($result['errcode']>0){
			$this->ainfo($result['errmsg']);
		}else{
			$this->ainfo("恭喜，已成功同步于公众号！");
		}
	}
	
	public function deletemenu()
	{
		$access_token = $this->common->getLaccessToken();
		$url="https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=".$access_token;    
		$result = $this->common->httpGet($url,$data);  
		$result = json_decode($result,true);  
		var_dump($result);  
	}
	
	
	private function getManageInfo()
	{
		$info = array();
		$info['sviews'] = $this->mysql->getOne("select sum(aviews) from dt_activity");
		$info['cusers'] = $this->mysql->getOne("select count(*) from dt_users where ugid=100");
		$info['subjects'] = $this->mysql->getOne("select count(sid) from dt_subject");
		$info['cactivity'] = $this->mysql->getOne("select count(*) from dt_activity");
		return $info;
	}
	
	private function getSystemInfo() 
	{ 
		$systemInfo = array(); 
		
		$systemInfo['sys'] = php_uname('s');
		$systemInfo['phpversion'] = PHP_VERSION;
		$systemInfo['apacheversion'] = "--";//apache_get_version(); 
		$systemInfo['zendversion'] = zend_version(); 
		$systemInfo['mysqlversion'] = mysql_get_server_info();
		$systemInfo['prc'] = date_default_timezone_get();
		$systemInfo['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

		if (function_exists('gd_info')) 
		{ 
			$gdInfo = gd_info(); 
			$systemInfo['gdsupport'] = true; 
			$systemInfo['gdversion'] = $gdInfo['GD Version']; 
		} 
		else 
		{ 
			$systemInfo['gdsupport'] = false; 
			$systemInfo['gdversion'] = ''; 
		} 
		$systemInfo['port'] = $_SERVER['SERVER_PORT'];
		$systemInfo['host'] = $_SERVER["HTTP_HOST"];	 
		//$systemInfo['addr'] = $_SERVER["SERVER_ADDR"]; 
		$systemInfo['maxuploadfile'] = ini_get('upload_max_filesize'); 
		$systemInfo['memorylimit'] = get_cfg_var("memory_limit") ? get_cfg_var("memory_limit") : '--'; 
	
		$systemInfo['safemode'] = ini_get('safe_mode') ? ini_get('safe_mode') : '--'; 
		$systemInfo['time'] =  date("Y-m-d H:i:s",time());

		return $systemInfo; 
	}
	
	public function logout()
	{
		setcookie("adminname","", time()-3600*18, "/"); 
		setcookie("adminid","", time()-3600*18, "/"); 
		exit('<script>top.location.href="index.php?mod=admin&act=login"</script>'); 
	}
	
	public function mlogout()
	{
		setcookie("mname","", time()-3600*18, "/"); 
		exit('<script>top.location.href="index.php?mod=admin&act=mlogin"</script>'); 
	}
	
	private function checklogin()
	{
		if(!$_COOKIE['adminname'])
		{
			echo "<script type='text/javascript'>window.top.location.href='index.php?mod=admin&act=login';</script>";
		}
	}
	
}