<?php
require_once "WxPay.JsApiPay.php";
require_once "lib/WxPay.Api.php";
require_once 'log.php';
 //初始化日志  
$logHandler= new CLogFileHandler("logs/".date('Y-m-d').'.log');  
$log = Log::Init($logHandler, 15);  
 
$record = array(
	"money" => isset($_GET['money'])?$_GET['money']:1,
	"businessman_sn" => date("YmdHis",time()).rand(10000,99999),
	"txnickname" => "汤红梅",
);
 
 $did = isset($_GET['did'])?$_GET['did']:0;
  
 $tools = new JsApiPay();
 $openId = $tools->GetOpenid();
 
 /** 
  * 微信提现 
  */  
 $branch=number_format($record["money"],"2",".","");  
 $postData=array(  
     "mch_appid"=>WxPayConfig::APPID,                   //绑定支付的APPID  
     "mchid"=>WxPayConfig::MCHID,                       //商户号  
     "nonce_str"=>"rand".rand(100000, 999999),           //随机数  
     "partner_trade_no"=>$record["businessman_sn"],      //商户订单号  
     "openid"=>$openId ,//$record["weixin_openid"],                 //用户唯一标识  
     "check_name"=>"FORCE_CHECK",                           //校验用户姓名选项，NO_CHECK：不校验真实姓名 FORCE_CHECK：强校验真实姓名  
     "re_user_name"=>$record["txnickname"],              //用户姓名  
     "amount"=>$branch*100,                              //金额（以分为单位，必须大于100）  
     "desc"=>"企业付款：答题抽奖活动奖金",                                     //描述  
     "spbill_create_ip"=>$_SERVER["REMOTE_ADDR"],        //请求ip  
 );  
 /** 
  * 生成签名 
  */  
 ksort($postData);  
 $buff = "";  
 foreach ($postData as $k => $v)  
 {  
     if($k != "sign" && $v != "" && !is_array($v)){  
         $buff .= $k . "=" . $v . "&";  
     }  
 }  
 $string = trim($buff, "&");  
 $string = $string . "&key=".WxPayConfig::KEY;  
 //签名步骤三：MD5加密  
 $string = md5($string);  
 //签名步骤四：所有字符转为大写  
 $sign = strtoupper($string);  
 $postData["sign"]=$sign;  
 /** 
  * 组装xml数据 
  */  
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

 /** 
  * 发送post请求 
  */  
 $url="https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";  

 $ch = curl_init();  
 //设置超时  
 curl_setopt($ch, CURLOPT_TIMEOUT, 30);  
  
 //如果有配置代理这里就设置代理  
 if(WxPayConfig::CURL_PROXY_HOST != "0.0.0.0"  
     && WxPayConfig::CURL_PROXY_PORT != 0){  
     curl_setopt($ch,CURLOPT_PROXY, WxPayConfig::CURL_PROXY_HOST);  
     curl_setopt($ch,CURLOPT_PROXYPORT, WxPayConfig::CURL_PROXY_PORT);  
 }  
 curl_setopt($ch,CURLOPT_URL, $url);  
 curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);  
 curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);//严格校验  
 curl_setopt($ch,CURLOPT_FOLLOWLOCATION,TRUE);
 //设置header  
 curl_setopt($ch, CURLOPT_HEADER, FALSE);  
 //要求结果为字符串且输出到屏幕上  
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);  
 //设置证书  
 //使用证书：cert 与 key 分别属于两个.pem文件  
 curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');  
 //curl_setopt($ch,CURLOPT_SSLCERT, WxPayConfig::SSLCERT_PATH);  
 curl_setopt($ch,CURLOPT_SSLCERT, dirname(__FILE__) . "/cert/apiclient_cert.pem");  
 curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');  
// curl_setopt($ch,CURLOPT_SSLKEY, WxPayConfig::SSLKEY_PATH);  
 curl_setopt($ch,CURLOPT_SSLKEY, dirname(__FILE__) . "/cert/apiclient_key.pem");  
 //post提交方式  
 curl_setopt($ch, CURLOPT_POST, TRUE);  
 curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);  
 //运行curl  
 $dataRe = curl_exec($ch);  

 $dataArr = json_decode(json_encode(simplexml_load_string($dataRe, 'SimpleXMLElement', LIBXML_NOCDATA)), true);  

 
 //返回结果  
 if($dataRe){  
     curl_close($ch);  
     /** 
      * xml转数组 
      */  
     //将XML转为array  
     //禁止引用外部xml实体  
     libxml_disable_entity_loader(true);  
     $dataArr = json_decode(json_encode(simplexml_load_string($dataRe, 'SimpleXMLElement', LIBXML_NOCDATA)), true);  
  
     if($dataArr["return_code"] == "SUCCESS" && $dataArr["result_code"] == "SUCCESS"){  
         $typeStr = "微信";  
         $saveData = array(  
             'id' => $data['id'],  
             'status' => $data['status'],  
             'memo' => $data['memo'],  
             'check_time' => date('Y-m-d H:i:s')  
         );  
	    			
	    header("Location:/app.php/cmain/mine?did=$did&code=SUCCESS");
     }elseif($dataArr["err_code"] == "SYSTEMERROR"){  
         Log::DEBUG("error:" . $dataRe);  
		 header("Location:/app.php/cmain/mine?did=$did&code=SYSTEMERROR");
     }else{  
         Log::DEBUG("error:" . $dataRe);  
		 header("Location:/app.php/cmain/mine?did=$did&code=FAIL");
     }  
 } else {  
     $error = curl_errno($ch);  
     curl_close($ch);  
     Log::DEBUG("error:" . $error);  
	 header("Location:/app.php/cmain/mine?did=$did&code=FAIL");
 }  