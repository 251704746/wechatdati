<?php
class common	
{
	public function __construct()
	{	
		error_reporting(0);
		$this->smarty = $GLOBALS["smarty"];
		$this->mysql = $GLOBALS["mysql"];
		$this->wxinfo = $this->mysql->getRow("select * from dt_wxinfo"); 
	}
	
	public function httpGet($url,$data = null){         
		$ch = curl_init();  
		curl_setopt($ch, CURLOPT_URL, $url);  
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);   
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);  
		   if (!empty($data)){  
			   curl_setopt($ch, CURLOPT_POST, TRUE);  
			   curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
		   }  
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  
		$output = curl_exec($ch);  
		curl_close($ch);  
		return  $output;            
	}  
	
	public function getIP(){
		if (isset($_SERVER)) {
			if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
				$realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
			} elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
				$realip = $_SERVER["HTTP_CLIENT_IP"];
			} else {
				$realip = $_SERVER["REMOTE_ADDR"];
			}
		} else {
			if (getenv("HTTP_X_FORWARDED_FOR")) {
				$realip = getenv( "HTTP_X_FORWARDED_FOR");
			} elseif (getenv("HTTP_CLIENT_IP")) {
				$realip = getenv("HTTP_CLIENT_IP");
			} else {
				$realip = getenv("REMOTE_ADDR");
			}
		}
		return $realip;
	}
	
	public function getCmd()
	{
		$cmd = explode("/",$_SERVER['PHP_SELF']);
		$l = "";

		foreach($cmd as $key => $val){
			if($key != 0 && $key != count($cmd) -1)
			$l .= $val."/";
		}

		$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

		return $http_type.$_SERVER['HTTP_HOST']."/".$l;
	}
	
	public function getSignPackage() {  
		$jsapiTicket = $this->getJsApiTicket();  
		$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

		$url = $http_type.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];  
		$timestamp = time();  
		$nonceStr = $this->createNonceStr();  

		$string = "jsapi_ticket=$jsapiTicket&noncestr=".$nonceStr."&timestamp=$timestamp&url=$url";  

		$signature = sha1($string);  

		$signPackage = array(  
			"appId"     => $this->wxinfo['wxpappid'],  
			"nonceStr"  => $nonceStr,  
			"timestamp" => $timestamp,  
			"url"       => $url,  
			"signature" => $signature,  
			"rawString" => $string  
		);  
		return $signPackage;   
	}  

	private function createNonceStr($length = 16) 
	{  
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";  
		$str = "";  
		for ($i = 0; $i < $length; $i++) {  
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);  
		}  
		return $str;  
	}  

	private function getJsApiTicket() 
	{
		$data = json_decode($this->wxinfo['wxpjsapi_ticket'],true);
		
		if ($data['expire_time'] < time()) {  

			$accessToken = $this->getAccessToken(); 
			
			$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";  
			$res = json_decode($this->httpGet($url));  
			$ticket = $res->ticket;  
			if ($ticket) {  
				$data['expire_time'] = time() + 7000;  
				$data['jsapi_ticket'] = $ticket;  
				$info = array("wxpjsapi_ticket" => json_encode($data));
				$this->mysql->autoExecute("dt_wxinfo",$info,"UPDATE", "wid=1");
			}  
		} else {  
			$ticket = $data['jsapi_ticket'];  
		}  

		return $ticket;  
	}  

	public function getAccessToken() {  

		$data = json_decode($this->wxinfo['wxpaccesstoken'],true);
		if ($data['expire_time'] < time()) {  

			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->wxinfo['wxpappid']."&secret=".$this->wxinfo['wxpsecret'];  
			$res = json_decode($this->httpGet($url));  

			$access_token = $res->access_token;  

			if ($access_token) {  
				$data['expire_time'] = time() + 7000;  
				$data['access_token'] = $access_token; 
				$info = array("wxpaccesstoken" => json_encode($data));
				$this->mysql->autoExecute("dt_wxinfo",$info,"UPDATE", "wid=1");
			}  
		} else {  
			$access_token = $data['access_token'];  
		}  
		return $access_token;  
	}  
	
	public function getLaccessToken() {  

		$data = json_decode($this->wxinfo['wxlaccesstoken'],true);
		if ($data['expire_time'] < time()) {  
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->wxinfo['wxlappid']."&secret=".$this->wxinfo['wxlsecret'];  
			$res = json_decode($this->httpGet($url));  
			$access_token = $res->access_token;  
			if ($access_token) {  
				$data['expire_time'] = time() + 7000;  
				$data['access_token'] = $access_token; 
				$info = array("wxlaccesstoken" => json_encode($data));
				$this->mysql->autoExecute("dt_wxinfo",$info,"UPDATE", "wid=1");
			}  
		} else {  
			$access_token = $data['access_token'];  
		}  
		return $access_token;  
	}  
	
	public function replaceSpecialChar($strParam){
		$regex = "/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
		return preg_replace($regex,"",$strParam);
	}
	
}