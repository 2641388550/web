<?php
class WeChat{
	private $_appid;
	private $_appsecret;
	//验证微信平台时用到的
	private $_token;
	

	public function valid()
	{
		$echoStr = $_GET["echostr"];
	
		//valid signature , option
		if($this->checkSignature()){
			echo $echoStr;
			exit;
		}
	}
	
	public function responseMsg()
	{
		//get post data, May be due to the different environments
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
			
			$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
			switch($postObj->MsgType){
				case'text':
					$this->doText($postObj);
					break;
				case'image':
					$this->doImage($postObj);
					break;
				case'voice':
					$this->doVoice($postObj);
					exit;
				default:
					;
				
			}
	}
	private function doText($postObj){	
			$fromUsername = $postObj->FromUserName;
			$toUsername = $postObj->ToUserName;
			
			$time = time();		
			$textTpl = "<xml>
										<ToUserName><![CDATA[%s]]></ToUserName>
										<FromUserName><![CDATA[%s]]></FromUserName>
										<CreateTime>%s</CreateTime>
										<MsgType><![CDATA[%s]]></MsgType>
										<Content><![CDATA[%s]]></Content>
										<FuncFlag>0</FuncFlag>
								</xml>";
			$msgType = "Text";
			switch($postObj->Content)
			{
				case "你好":
						$contentStr="我是何周强";
						break;
				default:
					
					
				$contentStr = "Welcome to wechat world!";
				//break;
			}
				$resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
				echo $resultStr;
			
	}
	
	private function checkSignature()
	{
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];
		$token = $this->_token;
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
	
		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}
	}
	public function __construct($_appid,$_appsecret,$_token)
	{
		$this->_appid = $_appid;
		$this->_appsecret = $_appsecret;
		$this->_token = $_token;
	}
	//php向网页发起https请求 用到curl函数
	public function _request($curl,$https = true,$method = 'GET',$data = null){
		//初始化curl  得到资源
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $curl);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
		if( $https ){

			//协议的主机和证书的验证规则verify
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
		}
		if($method == 'POST'){
			//POST方式时需要设置成post 为true 及设置传输数据postfields
			curl_setopt($ch,CURLOPT_POST,true);
			curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		}


		$content = curl_exec($ch);
		curl_close($ch);
		return $content;
	}
	public function getAccessToken(){
		$file = "./accesstoken";
		if(file_exists($file)){
			$content=file_get_contents($file);
			$content=json_decode($content);
			if(time()-filemtime($file)<$content->expires_in)
				return $content->access_token;
		}
		$curl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->_appid."&secret=".$this->_appsecret;
		$content = $this->_request($curl);
		//将字符串写入文件中无文件就自动创建
		file_put_contents($file, $content);
		
		$content = json_decode($content);//将字符串转化为json对象
		return $content->access_token;//微信凭证

	}

	public function getTicket($scene_id,$type = 'temp',$expire_seconds=604800){
		if($type=='temp'){
			$data = '{"expire_seconds": %s, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": %s}}}';
			$data = sprintf($scene_id,$data,$expire_seconds);
		} else{
			$data = '{"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": %s}}}';
			$data = sprintf($scene_id,$data);
		}
		$curl = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$this->getAccessToken();
		$content = $this->_request($curl,true,'POST',$data);
		$content = json_decode($content);
		return $content->ticket;

	}
	public function getQRcode($scene_id,$type = 'temp',$expire_seconds = 604800){
		$ticket = $this->getTicket($scene_id,$type,$expire_seconds);
		$ticket = urlencode($ticket);
		$curl = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$ticket;
		$content = $this->_request($curl);
		return $content;

	}
	
	
}
