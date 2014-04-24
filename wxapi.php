<?php
/*
Name: 微信公众平台PHP SDK
Version: V1.0424
Author: Luke<http://blog.molab.cn>
Description: 本SDK是基于微信官方发布的基础SDK Demo完善而成。 当前已经完成多种格式的消息回复、 快递查询、 天气查询、 聊天、 二维码生成、 翻译、 听歌、 自定义菜单动作…… 几乎包含了所有的微信 （没有认证的服务号、已经认证的订阅号、未认证的订阅号） API接口。 具体的API文档可以参照微信公众平台说明http://mp.weixin.qq.com/wiki/index.php?title=%E9%A6%96%E9%A1%B5 
Notice: 文件包含某些音乐、图片资源，你可以选择使用或者摈弃，对于使用资源的同学，这里不保证资源的有效性
URL: http://blog.molab.cn
*/

//定义通讯Token
define("TOKEN", "molab");
$wechatObj = new WeChatSDK();
$wechatObj->responseMsg();//消息反馈
$wechatObj->valid();//服务器接口配置认证

class WeChatSDK{
	public function valid(){
        $echoStr = $_GET["echostr"];//获取微信传输过来的参数
        //验证接口信息
        if($this->checkSignature()){
        	echo $echoStr;
        	exit;
        }
    }
	
	private function checkSignature(){
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
		
		$token = TOKEN;
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

    public function responseMsg(){
		//获取POST参数，使用了HTTP_RAW_POST_DATA，防止不同服务器造成的不兼容
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
		
      	//解析POST提交的数据
		if (!empty($postStr)){
			$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
			$RX_TYPE = trim($postObj->MsgType);
			
			/*
			判断接收到消息的类型，根据消息类型做出对应的响应。关于微信用户发送给公众帐号的消息类型请参照
			http://mp.weixin.qq.com/wiki/index.php?title=%E6%8E%A5%E6%94%B6%E6%99%AE%E9%80%9A%E6%B6%88%E6%81%AF 普通消息
			http://mp.weixin.qq.com/wiki/index.php?title=%E6%8E%A5%E6%94%B6%E4%BA%8B%E4%BB%B6%E6%8E%A8%E9%80%81 事件推送
			http://mp.weixin.qq.com/wiki/index.php?title=%E6%8E%A5%E6%94%B6%E8%AF%AD%E9%9F%B3%E8%AF%86%E5%88%AB%E7%BB%93%E6%9E%9C 语音识别
			
			MsgType 可以判断消息类型
			Event 可以判断用户动作类型
			EventKey 可以判断点击进来的自定义菜单或者用户动作类型（关注、二维码）
			*部分消息类型可以在当前方法细化操作，也可以在回复方法中进行操作
			
			*微信接受消息和发送消息使用的是不同的方式，，发送消息请参照replyType()
			*/
			switch($RX_TYPE){
				case 'text':
					$resultStr = $this->handleText($postObj);
					break;
				case 'image':
					$resultStr = $this->handleImage($postObj);
					break;
				case 'voice':
					//接受语音消息，也可以接受语音识别结果
					$resultStr = $this->handleVoice($postObj);
					break;
				case 'video':
					$resultStr = $this->handleVideo($postObj);
					break;
				case 'location':
					//接受普通的地理位置信息，但不能接受上报的地理位置信息
					$resultStr = $this->handleLocation($postObj);
					break;
				case 'event':
					//接受用户操作，接受自定义菜单，接受地理位置上报
					$resultStr = $this->handleEvent($postObj);
					break;
				case 'link':
					$resultStr = $this->handleLink($postObj);
					break;
				default:
					$resultStr = "未知消息类型".$RX_TYPE;
					break;
			}
        }else{
        	echo "";
        	exit;
        }
    }
	
	/*
	回发的消息模版
	$type 为定义的消息类型
	$postObj 为用户发送过来的消息，经过xml解析之后的数组
	*/
	public function replyType($type, $postObj){
		/*
		发送给用户的消息需要将接收到的消息用户位置调换
		几乎所有的消息类型都有$fromUsername $toUsername $time 这三个属性
		FuncFlag默认为0，可以省略，该标志可以用来处理连续事件
		
		这里说列的模版都是被动消息，客服消息不同于被动消息，客服消息可以在24Hr主动发送给用户。 客服消息使用的是json格式数据。

		*客服消息、高级群发目前还在完善
		*/
		$fromUsername = $postObj->FromUserName;
		$toUsername = $postObj->ToUserName;
		$time = time();
		switch($type){
			case 'text':
				$tpl = "<xml>
					<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
					<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
					<CreateTime>{$time}</CreateTime>
					<MsgType><![CDATA[{$type}]]></MsgType>
					<Content><![CDATA[%s]]></Content>
					<FuncFlag>0</FuncFlag>
					</xml>";
				break;
			case 'image'://回复图文消息
				$tpl = "<xml>
					<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
					<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
					<CreateTime>{$time}</CreateTime>
					<MsgType><![CDATA[{$type}]]></MsgType>
					<Image>
						<MediaId><![CDATA[media_id]]></MediaId>
					</Image>
					</xml>";
				break;
			case 'voice':
				$tpl = "<xml>
					<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
					<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
					<CreateTime>{$time}</CreateTime>
					<MsgType><![CDATA[{$type}]]></MsgType>
					<Voice>
						<MediaId><![CDATA[media_id]]></MediaId>
					</Voice>
					</xml>";
				break;
			case 'video':
				$tpl = "<xml>
					<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
					<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
					<CreateTime>{$time}</CreateTime>
					<MsgType><![CDATA[{$type}]]></MsgType>
					<Video>
						<MediaId><![CDATA[media_id]]></MediaId>
						<Title><![CDATA[title]]></Title>
						<Description><![CDATA[description]]></Description>
					</Video> 
					</xml>";
				break;
			case 'music':
				$tpl = "<xml>
					<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
					<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
					<CreateTime>{$time}</CreateTime>
					<MsgType><![CDATA[{$type}]]></MsgType>
					<Music>
						<Title><![CDATA[%s]]></Title>
						<Description><![CDATA[%s]]></Description>
						<MusicUrl><![CDATA[%s]]></MusicUrl>
						<HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
					</Music>
					</xml>";
				break;
			case 'news':
				$tpl = "<xml>
					<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
					<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
					<CreateTime>{$time}</CreateTime>
					<MsgType><![CDATA[{$type}]]></MsgType>
					<Content><![CDATA[%s]]></Content>
					<ArticleCount>1</ArticleCount>
					<Articles>
						<item>
							<Title><![CDATA[%s]]></Title>
							<Description><![CDATA[%s]]></Description>
							<PicUrl><![CDATA[%s]]></PicUrl>
							<Url><![CDATA[%s]]></Url>
						</item>
					</Articles>
					<FuncFlag>0</FuncFlag>
					</xml>";
				break;
			default:
				$textTpl = "<xml>
					<ToUserName><![CDATA[{$fromUsername}]]></ToUserName>
					<FromUserName><![CDATA[{$toUsername}]]></FromUserName>
					<CreateTime>{$time}</CreateTime>
					<MsgType><![CDATA[{text}]]></MsgType>
					<Content><![CDATA[%s]]></Content>
					<FuncFlag>0</FuncFlag>
					</xml>";
				break;
		}
		return $tpl;
	}
	
	/*
	发送文本消息
	$postObj 为用户传递过来的消息类型的数组数据
	*/
	public function handleText($postObj){
		$keyword = trim($postObj->Content);
		$textTpl = $this->replyType("text",$postObj);
		
		if(!empty($keyword)){
			$msgType = "text";
			//substr($keyword,4)
			$strlen = strlen($keyword);
			if($strlen>6){
				$act = mb_strcut($keyword,0,6,'utf-8');//获取执行操作
				//$act = mb_substr($keyword,0,2,'utf-8');//截取开始两个字符
				$content = trim(mb_strcut($keyword,6,$strlen,'utf-8'));//获取执行内容
				if($act=="天气"){
					$data = $this->weather($content);
					if(empty($data->weatherinfo)){
						$contentStr = "抱歉，没有查到\"".$content."\"的天气信息！";
					}else{
						$contentStr = "【".$data->weatherinfo->city."天气预报】\n".
						$data->weatherinfo->date_y." ".
						$data->weatherinfo->fchh."时发布"."\n实时天气:".
						$data->weatherinfo->weather1." ".
						$data->weatherinfo->temp1." ".
						$data->weatherinfo->wind1."\n温馨提示：".
						$data->weatherinfo->index_d."\n明天:".
						$data->weatherinfo->weather2." ".
						$data->weatherinfo->temp2." ".
						$data->weatherinfo->wind2."\n后天:".
						$data->weatherinfo->weather3." ".
						$data->weatherinfo->temp3." ".
						$data->weatherinfo->wind3;
					}
					$resultStr = sprintf($textTpl,$contentStr);
				}else if($act=="快递"){
					//http://m.kuaidi100.com/index_all.html?type=yunda&postid=1201207116091#result
					$arr = explode(" ",$keyword);
					$expname = $arr[1];//获取快递服务商
					$expno = $arr[2];//获取快递单号
					
					include("ExpressList.php");
					//in_array($expname,$exp);是否存在
					$expcode = array_search($expname,$exp);
					
					$url = "http://m.kuaidi100.com/index_all.html?type=".$expcode."&postid=".$expno."#result";
					
					$newsTpl = $this->replyType('news',$postObj);
					$content = "快递查询";
					$picurl = "http://sanshu.qiniudn.com/pic_anymouse1359347919-1.jpg?token=5FZTA1Dfl7J2SbsAiSNwWusgvd1k10IMyKNY9b1G:rxOz8tgh0a_s9c8CLOCbW8Zycrk=:eyJTIjoic2Fuc2h1LnFpbml1ZG4uY29tL3BpY19hbnltb3VzZTEzNTkzNDc5MTktMS5qcGciLCJFIjoxMzk3NTQyMDEwfQ==&imageView/2/w/203/h/203";
					$resultStr = sprintf($newsTpl, $content, $expcode, $expname, $picurl, $url, 0);
					$resultStr;
				}else if($act=="翻译"){
					$contentStr = $this->baiduDict($content);
					$resultStr = sprintf($textTpl,$contentStr);
				}else if($act=="搜歌"){
					echo $this->soMusic($postObj,urlencode($content));
				}else if($act=="线路"){
					$contentStr = "线路查询".$content;
					$resultStr = sprintf($textTpl,$contentStr);
				}else{
					echo $this->replyChat($postObj);
				}
			}else{
				//装逼模式启动
				if($keyword=="老夏" || $keyword=="夏露"){
					$newsTpl = $this->replyType('news',$postObj);
					$content = "Hey , girl! What\'s your name";
					$title = "没错，我就是老夏，如假包换的老夏！";
					$description = "你好，我是……，大家都叫我老夏，不妨你也这样称呼我吧！我是一个程序员，主要擅长LAMP。目前从事互联网行业，喜欢折腾新的东西……我有一个博客，记录我的生活、写着我的笔记，如果你有兴趣，你可以点击这里！";
					$picurl = "http://sanshu.qiniudn.com/pic_anymouse1359347919-1.jpg?token=5FZTA1Dfl7J2SbsAiSNwWusgvd1k10IMyKNY9b1G:rxOz8tgh0a_s9c8CLOCbW8Zycrk=:eyJTIjoic2Fuc2h1LnFpbml1ZG4uY29tL3BpY19hbnltb3VzZTEzNTkzNDc5MTktMS5qcGciLCJFIjoxMzk3NTQyMDEwfQ==&imageView/2/w/203/h/203";
					$url = "http://find.aliapp.com/Resume/";
					$resultStr = sprintf($newsTpl, $content, $title, $description, $picurl, $url, 0);
					$resultStr;
				}else if($keyword == "听歌"){
					echo $this->randomMusic($postObj);
				}else if($keyword == "笑话"){
					echo $this->replyHaha($postObj);
				}else{
					echo $this->replyChat($postObj);
				}
			}
			//$contentStr = "Welcome to wechat world!";
			echo $resultStr;
		}else{
			echo $this->replyChat($postObj);
		}
	}
	
	/*
	和用户聊天
	$postObj 为用户发过来的消息整体
	*/
	public function replyChat($postObj){
		$json = file_get_contents("http://api.ajaxsns.com/api.php?key=free&appid=0&msg=".urlencode(trim($postObj->Content)));
		$info = json_decode($json,true);
		$content = $info['content'];
		$textTpl = $this->replyType("text",$postObj);
		return $resultStr = sprintf($textTpl,$content);
	}
	
	public function replyHaha($postObj){
		$json = file_get_contents("http://api.ajaxsns.com/api.php?key=free&appid=0&msg=%e7%ac%91%e8%af%9d");
		$info = json_decode($json,true);
		$content = $info['content'];
		$textTpl = $this->replyType("text",$postObj);
		return $resultStr = sprintf($textTpl,$content);
	}
	
	public function randomMusic($postObj){
		$music = array(
			array(
				'title'=>'You are beautiful',
				'description'=>'You are beautiful',
				'url'=>'http://find.aliapp.com/Uploads/WeChat/Musics/James%20Blunt%20-%20You%20Are%20Beautiful.mp3',
				'hqurl'=>'http://find.aliapp.com/Uploads/WeChat/Musics/James%20Blunt%20-%20You%20Are%20Beautiful.mp3'
			),
			array(
				'title'=>'Jewel - Stand',
				'description'=>'Stand',
				'url'=>'http://find.aliapp.com/Uploads/WeChat/Musics/jewel%20-%20stand.mp3',
				'hqurl'=>'http://find.aliapp.com/Uploads/WeChat/Musics/jewel%20-%20stand.mp3'
			),
			array(
				'title'=>'TimeLess',
				'description'=>'Timeless',
				'url'=>'http://find.aliapp.com/Uploads/WeChat/Musics/Kelly%20Clarkson%20-%20Timeless.mp3',
				'hqurl'=>'http://find.aliapp.com/Uploads/WeChat/Musics/Kelly%20Clarkson%20-%20Timeless.mp3'
			),
			array(
				'title'=>'Because of you',
				'description'=>'Because of you',
				'url'=>'http://find.aliapp.com/Uploads/WeChat/Musics/kelly%20clarkson%20-%20because%20of%20you.mp3',
				'hqurl'=>'http://find.aliapp.com/Uploads/WeChat/Musics/kelly%20clarkson%20-%20because%20of%20you.mp3'
			),
			array(
				'title'=>'Every Moment of my life',
				'description'=>'Every Moment of my life',
				'url'=>'http://find.aliapp.com/Uploads/WeChat/Musics/Sarah%20Connor%20-%20Every%20Moment%20Of%20My%20Life.mp3',
				'hqurl'=>'http://find.aliapp.com/Uploads/WeChat/Musics/Sarah%20Connor%20-%20Every%20Moment%20Of%20My%20Life.mp3'
			),
			array(
				'title'=>'TimeLess',
				'description'=>'Timeless',
				'url'=>'http://find.aliapp.com/Uploads/WeChat/Musics/Kelly%20Clarkson%20-%20Timeless.mp3',
				'hqurl'=>'http://find.aliapp.com/Uploads/WeChat/Musics/Kelly%20Clarkson%20-%20Timeless.mp3'
			)
		);
		$musicTpl = $this->replyType("music",$postObj);
		$len = count($music);
		$rd = rand(0,$len-1);
		$resultStr = sprintf($musicTpl, $music[$rd]['title'],$music[$rd]['description'],$music[$rd]['url'],$music[$rd]['hqurl']);
		return $resultStr;
	}
	
	public function soMusic($postObj,$music){
		$json = file_get_contents("http://api2.sinaapp.com/search/music/?appkey=0020130430&appsecert=fa6095e1133d28ad&reqtype=music&keyword=".urlencode($music));
		$info = json_decode($json,true);
		$content = $info['music'];
		$musicTpl = $this->replyType("music",$postObj);
		$resultStr = sprintf($musicTpl, $content['title'],$content['title'],$content['musicurl'],$content['hqmusicurl']);
		return $resultStr;
	}
	
	public function handleEvent($postObj){
		$content = "";
		switch($postObj->Event){
			case 'subscribe':
				$newsTpl = $this->replyType('news',$postObj);
				$content = "Hey , girl! What\'s your name";
				$title = "Ladies and 乡亲们，欢迎关注夏露君";
				$description = "如您所知，我是夏露君！";
				$picurl = "http://xialu-public.stor.sinaapp.com/wechat/image/2014/0418/pic_anymouse1359347919-1.jpg";
				$url = "http://xialu.sinaapp.com/Resume/";
				$resultStr = sprintf($newsTpl, $content, $title, $description, $picurl, $url, 0);
				echo $resultStr;
				break;
			case 'CLICK':
				$this->handleMenu($postObj);
				break;
			case 'VIEW':
				break;
			case 'LOCATION':
				break;
			default:
				$content = "未知事件".$postObj->Event;
				$textTpl = $this->replyType("text",$postObj);
				$resultStr = sprintf($textTpl, $content, 0);
				echo $resultStr;
				break;
		}
	}
	
	public function handleMenu($postObj){
		$EventKey = $postObj->EventKey;
		$content = "";
		switch($EventKey){
			case 'tianqi':
				$title = "天气查询";
				$content = "天气查询";
				$description = "给我发送\"天气城市名\"，比如\"天气上海\"，即可查询该城市的天气";
				$picurl = "http://xialu-public.stor.sinaapp.com/wechat/image/2014/0419/tianqi_0.jpg";
				$url = "";
				$this->replySingleNews($postObj, $content, $title, $description, $picurl, $url);
				break;
			case 'kuaidi':
				$title = "快递查询";
				$content = "快递查询";
				$description = "给我发送\"快递 快递服务商 单号\"，比如\"快递 韵达 1234567890123\"，即可查询该单号的快递信息";
				$picurl = "http://xialu-public.stor.sinaapp.com/wechat/image/2014/0419/kuaidi_0.jpg";
				$url = "";
				$this->replySingleNews($postObj, $content, $title, $description, $picurl, $url);
				break;
			case 'fanyi':
				$title = "翻译";
				$content = "翻译";
				$description = "给我发送\"翻译单词\"，比如\"翻译Hello\"，即可查询到Hello的意思";
				$picurl = "http://xialu-public.stor.sinaapp.com/wechat/image/2014/0419/fanyi_0.jpg";
				$url = "";
				$this->replySingleNews($postObj, $content, $title, $description, $picurl, $url);
				break;
			case 'tingge':
				echo $this->randomMusic($postObj);
				break;
			case 'souge':
				$title = "搜歌听歌";
				$content = "搜歌";
				$description = "给我发送\"搜歌歌曲名\"，比如\"搜歌我爱你\"，即可微信听歌曲我爱你";
				$picurl = "http://xialu-public.stor.sinaapp.com/wechat/image/2014/0419/yinyue_0.jpg";
				$url = "";
				$this->replySingleNews($postObj, $content, $title, $description, $picurl, $url);
				break;
			case 'xiaohua':
				echo $this->replyHaha($postObj);
				break;
			default:
				$title = "未知事件";
				$content = "使用帮助";
				$description = "这里显示使用帮助";
				$picurl = "http://xialu-public.stor.sinaapp.com/wechat/image/2014/0419/bangzhu_0.png";
				$url = "";
				$this->replySingleNews($postObj, $content, $title, $description, $picurl, $url);
				break;
		}
	}
	
	public function replySingleNews($postObj, $content, $title, $description, $picurl, $url){
		$tpl = $this->replyType("news", $postObj);
		$resultStr = sprintf($tpl, $content, $title, $description, $picurl, $url, 0);
		echo $resultStr;
	}
	
	public function replyMutiNews($postObj,$array,$numbers){
		return $postObj;
	}
	
	public function handleLocation($postObj){
		$newsTpl = $this->replyType('news',$postObj);
		$x = $postObj->Location_X;
		$y = $postObj->Location_Y;
		$s = $postObj->Scale;
		$l = $postObj->Label;
		$title = "你发送了一条地理信息";
		$description = "纬度：".$x."；\n经度：".$y."；\n缩放比例：".$s."；\n地址：".$l;
		$content = "描述";
		$picurl = "http://sanshu.qiniudn.com/pic59b1OOOPIC46.jpg?token=5FZTA1Dfl7J2SbsAiSNwWusgvd1k10IMyKNY9b1G:3tyD0k5p5-Hs_JH_p2Ram2j8lJc=:eyJTIjoic2Fuc2h1LnFpbml1ZG4uY29tL3BpYzU5YjFPT09QSUM0Ni5qcGciLCJFIjoxMzk3NjYyODQ4fQ==&imageView/2/w/203/h/203";
		//$url = "http://blog.molab.cn";
		//$url = "http://maps.google.com/maps?q=".$x.",".$y."&iwloc=A&hl=zh-CN";
		$url = "http://maps.google.com/maps?q=".$x.",".$y;
		$resultStr = sprintf($newsTpl, $content, $title, $description, $picurl, $url, 0);
		echo $resultStr;
	}
	
	private function weather($n){
		include("ProvinceList.php");
		$c_name = $city_id[$n];
		if(!empty($c_name)){
			$json = file_get_contents("http://m.weather.com.cn/data/".$c_name.".html");
			return json_decode($json);
		}else{
			return null;
		}
	}
	
	private function baiduDict($word,$from="auto",$to="auto"){
		$word_code = urlencode($word);
		$appid = "Rp0Y2XgqZCMENfW2qybiWY8t";
		$url = "http://openapi.baidu.com/public/2.0/bmt/translate?client_id=".$appid."&q=".$word_code."&from=".$from."&to=".$to;
		$text = json_decode($this->language_text($url));
		$text = $text->trans_result;
		return $text[0]->dst;
	}
	
	private function language_text($url){
		if(!function_exists('file_get_contents')){
            $file_contents = file_get_contents($url);
        }else{
            //初始化一个cURL对象
            $ch = curl_init();
            $timeout = 5;
            //设置需要抓取的URL
            curl_setopt ($ch, CURLOPT_URL, $url);
            //设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
            //在发起连接前等待的时间，如果设置为0，则无限等待
            curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            //运行cURL，请求网页
            $file_contents = curl_exec($ch);
            //关闭URL请求
            curl_close($ch);
        }
        return $file_contents;
	}
}
?>
