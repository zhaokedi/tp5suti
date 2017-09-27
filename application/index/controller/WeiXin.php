<?php
namespace app\index\controller;
use Think\Controller;
class WeiXin extends Controller {

    public function index(){
        define("TOKEN", $_GET["token"]);//$_GET["token"]

        if(!isset($_GET["echostr"])){//判断是否存在$_GET['echostr],不存在就表示已经接入
            $this->responseMsg();
        }else{
            $this->valid();
        }
    }
    //接入验证
    public function valid()
    {
        $echoStr = $_GET["echostr"];
        //valid signature , option
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
    }
    //回复信息
    public function responseMsg()
    {
        //get post data, May be due to the different environments
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        $xmlStr = file_get_contents("php://input");
        $postObj = $this->xmlToArray($xmlStr);
        $strfind = array('&nbsp;', '&lt;', '&gt;', '&amp;','&ldquo;','&rdquo;','&mdash;&mdash;',"<br />","<br/>","&nbsp;","<p>","</p>");
        $strreplace = array(' ', '<', '>', '&','“','”','——','','',' ','','');
//        exit;
        //extract post data
        if (!empty($postObj)){
            //$this->writeLog("content:".$postStr);

//            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);

            $fromUsername = $postObj['FromUserName'];
            $toUsername = $postObj['ToUserName'];
            $keyword = trim($postObj['Content']);//获取关键字
            $msgtype = $postObj['MsgType'];//获取消息类型
            $type = $postObj['Event'];//获取事件类型，订阅（subscribe）和取消订阅（unsubscribe）,点击事件(CLICK)
            $eventKey = trim($postObj['EventKey']);//事件的关键词
            $resultStr = '';
            switch ($type) {
                case "subscribe":
                    $contentStr = "欢迎关注肌动健身 ";
                    if (isset($eventKey) && ($eventKey != '')){
                        $uid = substr($eventKey, 8);//发起邀请的用户id
                        $user = model('user');
                        $user_info = $user->find($uid);//邀请人的信息
                        
                        //把该用户的信息写入数据库
                        $check_result = model('user')->where("openid = '$fromUsername'")->find();//关注用户在数据库中的信息(被邀请人的信息)
                        if ( $check_result ){
                            if ( $check_result['id'] == $uid ){
                                $contentStr = "不可以自己邀请自己噢";
                            }else{
                                if ( $check_result['uppeople'] ){
                                    $temp_up_info = $user->find($check_result['uppeople']);
                                    $contentStr = $temp_up_info['nickname']." 已经邀请过您啦";
                                }else{
                                    $contentStr = $user_info['nickname']." 想邀请你入伙来一场身形革命，只要6个月，你就是国民偶像。这里是肌动塑体，教练免费，网红云集,欢迎关注我们的公众号";
                                    $user->save(array('id'=> $check_result['id'],'uppeople'=>$uid));
                                }
                            }
                        }else{
                            $contentStr = $user_info['nickname']." 想邀请你入伙来一场身形革命，只要6个月，你就是国民偶像。这里是肌动塑体，教练免费，网红云集,欢迎关注我们的公众号";
                            $data1['openid'] = trim($fromUsername);
                            $data1['uppeople'] = $uid;
                            $data1['addtime'] = date("Y-m-d H:i:s");
                            $user->save($data1);
                        }
                        $resultStr = $this->transmitText($postObj, $contentStr);
                    }else{

                        /* 把关注用户的信息写入数据表 */
                        $sub_result = model('user')->where("openid = '".trim($fromUsername)."'")->find();//关注用户的信息
                        $adduser['openid']=trim($fromUsername);
                        $adduser['addtime']=date("Y-m-d H:i:s");
                        if ( !$sub_result ){
                            model('user')->save($adduser);
                        }
                        
                        //不是带有二维码参数的扫描事件，就执行关注时回复事件

                        //查询被设置了首页回复关键词的图文

                        $subinfo = db("response")->where(array('keywords'=> "###subscribe"))->find();

                        $restype = $subinfo['restype'];//当前文本回复的回复类型
                        $textContent = '';
                        $picArray = array();
                        if($restype == 1){//文本回复
                            $textContent = htmlspecialchars_decode($subinfo['content']);
                            $textContent = str_replace($strfind, $strreplace, $textContent);

                        }elseif ($restype == 2){//多图文回复
                            $picArray = unserialize($subinfo['content']);
                        }elseif ($restype == 3){//单图文回复
                            $picArray[] = $subinfo;
                        }
                        
                        $resultStrText = $this->transmitText($postObj, $textContent);
                        $resultStrPic = $this->transmitNews($postObj, $picArray,$subinfo['id']);
                        $str = '';//进行回复的时候前面不能有输出，所以用一个str存放将要输出的内容
                        //判断图文回复和文本回复是否存在，由于图文回复和文本回复不会同时触发，所以图文回复优先
                        if($picArray){
                            $resultStr = $resultStrPic;
                        }else if($textContent){
                            $resultStr = $resultStrText;
                        }

                        //$resultStr = $this->transmitText($postObj, $contentStr);
                    }
                    break;
                case "SCAN":
                    $uid = $eventKey;//发起邀请的用户id 已经关注过的在扫码 eventKey 就单单只有id 没有前缀
                    $user = model('user');
                    $user_info = $user->find($uid);//发起邀请的用户的信息（邀请人的信息）
                    //要实现统计分析，则需要扫描事件写入数据库，这里可以记录 EventKey及用户OpenID，扫描时间
                    $check_result = $user->where("openid = '".$fromUsername."'")->find();//关注用户在数据库中的信息

                    if(!$check_result){
                        $adduser['openid']=trim($fromUsername);
                        $adduser['addtime']=date("Y-m-d H:i:s");
                        $adduser['uppeople'] = $uid;
                        model('user')->save($adduser);

                    }else{
                        if ( $check_result['id'] == $uid ){
                            $contentStr = "不可以自己邀请自己噢";
                        }else {
                            if ( $check_result['uppeople'] ){
                                $temp_up_info = $user->find($check_result['uppeople']);
                                $contentStr = $temp_up_info['nickname']." 已经邀请过您啦";
                            }else{
                                $contentStr = $user_info['nickname']." 邀请的贵宾,您好";
                                $data['id'] = $check_result['id'];
                                $data['uppeople'] = $uid;
//                                logResult('邀请用户data',$data);
                                $user->save($data);
                            }
                        }
                    }
                    $resultStr = $this->transmitText($postObj, $contentStr);
                    break;
                default:
                    break;
            }
            
            echo $resultStr;
            
            //CLICK   自定义菜单的点击事件
            if($type == "CLICK" && $msgtype == "event"){
                $diruser = db('response');
                //查询该关键词对应的图文消息
//                $dircondition['token'] = TOKEN;
                $dircondition['keywords'] = $eventKey;
                $dirResult = $diruser->where($dircondition)->find();
                $textContent = '';//文本回复的内容存放地址
                $picArray = array();//图文回复的内容存放地址
                if($dirResult['restype'] == 1){
                    $textContent = $dirResult['content'];
                }elseif ($dirResult['restype'] == 2){
                    //处理多图文
//                    $moreData['token'] = TOKEN;
                    $moreData['articleid'] = $dirResult['articleid'];
                    $moreResult = $diruser->where($moreData)->select();
                    foreach ($moreResult as $n=>$v){
                        $picArray[] = $moreResult[$n];
                    }
                }elseif ($dirResult['restype'] == 3){
                    $picArray[] = $dirResult;
                }
                
                $resultStrText = $this->transmitText($postObj, $textContent);
                $resultStrPic = $this->transmitNews($postObj, $picArray.$dirResult['id']);
                $str = '';//进行回复的时候前面不能有输出，所以用一个str存放将要输出的内容
                //判断图文回复和文本回复是否存在，由于图文回复和文本回复不会同时触发，所以图文回复优先
                if($picArray){
                    $str = $resultStrPic;
                }else if($textContent){
                    $str = $resultStrText;
                }
                echo $str;
            }
    
            if(!empty( $keyword ))
            {

                if($keyword=='教练'){
                    $user=model('user')->where("openid = '$fromUsername'")->find();
                    if(!$user['upteacher']){
                        echo  $content=$this->transmitText($postObj,'您还没有属于自己的教练');
                    }else{
                        $teacher=model('teacher')->where(array('teacher_id'=>$user['upteacher']))->find();
                    }
                    if($teacher && !empty($teacher['ercode'])){
                        $wx_config = db("wx_config")->find();
                        $wxconfig=json_decode($wx_config["jsoncontents"],true);
                        $access_token=$this->get_access_token($wxconfig['appid'],$wxconfig['appsecret']);

                        if(!empty($teacher['expire_time']) && $teacher['expire_time']>time()){
                            $media_id=$teacher['media_id'];
                        }else{
                            $a=dirname(dirname(dirname(dirname(__FILE__))));
                            $ercode=$a.'/Public'.$teacher['ercode'];
                            $url = 'http://file.api.weixin.qq.com/cgi-bin/media/upload?access_token='.$access_token.'&type=image';
                            $data=array("media"=>"@".$ercode);
                            $re_json= $this->http_image($url,$data);
                            $json_media = json_decode($re_json,1);
                            $media_id=$json_media['media_id'];
                            model('teacher')->where(array('teacher_id'=>$teacher['teacher_id']))->save(array('media_id'=>$media_id,'expire_time'=>(time()+200000)));
                        }

                        $content=$this->transmitImage($postObj,$media_id);
                        echo $content;

                    }else{
                        echo  $content=$this->transmitText($postObj,'目前无法查看您的教练信息');
                    }

                }else{
                    /************* 关键词回复事件   ****************/
                    $resuser = db('response');
                    //1.先模糊查询关键词对应的回复消息
                    $condition1["keywords"] = $keyword;
//                    logResult($keyword);
//                $condition1["token"] = TOKEN;
                    $keyresult = $resuser->order("keyaddtime desc")->where($condition1)->find();
                    if($keyresult['restype'] ==1){
                        $keyresult['content']=htmlspecialchars_decode($keyresult['content']);
                        $keyresult['content'] = str_replace($strfind, $strreplace, $keyresult['content']);
                    }



                    //1.1、判断是否存在该关键词的回复
                    if($keyresult){
                        $rid=0;
                        $tarResult = array();//图文回复的存放位置
                        $tarText = '';//文本回复的存放位置
                        //2.如果该条消息要求是完全匹配，比对用户输入的关键词是否跟数据库中的关键词完全一致
                        if($keyresult['matchtype'] == 1){//1:完全匹配，
                            if($keyword == $keyresult['keywords']){
                                $rid=$keyresult['id'];
                                if($keyresult['restype'] == 1){
                                    $tarText = $keyresult['content'];
                                }elseif ($keyresult['restype'] == 2){
                                    $tarResult = unserialize($keyresult['content']);
                                }elseif ($keyresult['restype'] == 3){
                                    $tarResult[] = $keyresult;
                                }
                            }else{
                                //执行无法识别的回复事件
                                $undata['keywords'] = "###unknow";
//                            $undata['token'] = TOKEN;
                                $unresult = $resuser->where($undata)->find();
                                if($unresult){
                                    $rid=$unresult['id'];
                                    if($unresult['restype'] == 1){
                                        $tarText = $unresult['content'];
                                    }elseif ($unresult['restype'] == 2){
//                                    $dataun['token'] = TOKEN;
                                        $dataun['articleid'] = $keyresult['articleid'];
                                        $tempresult = $resuser->where($dataun)->select();
                                        foreach ($tempresult as $n=>$v){
                                            $tarResult[] = unserialize($v);
                                        }
                                    }elseif ($unresult['restype'] == 3){
                                        $tarResult[] = $unresult;
                                    }
                                }
                            }
                        }else{//2：包含匹配。查询出最近的一条包含关键词的记录
                            //执行无法识别的回复事件
                            $undata['keywords'] = array("like","%".$keyword."%");
//                        $undata['token'] = TOKEN;
                            $unresult = $resuser->order('keyaddtime desc')->where($undata)->find();
                            if($unresult['restype']==1){
                                $unresult['content']=htmlspecialchars_decode($unresult['content']);
                                $unresult['content'] = str_replace($strfind, $strreplace, $unresult['content']);
                            }


                            if($unresult){
                                $rid=$unresult['id'];
                                if($unresult['restype'] == 1){
                                    $tarText = $unresult['content'];
                                }elseif ($unresult['restype'] == 2){
//                                $dataun['token'] = TOKEN;
                                    $dataun['articleid'] = $keyresult['articleid'];
                                    $tempresult = $resuser->where($dataun)->select();
                                    foreach ($tempresult as $n=>$v){
                                        $tarResult[] = unserialize($v);
                                    }
                                }elseif ($unresult['restype'] == 3){
                                    $tarResult[] = $unresult;
                                }
                            }
                        }
                        $resultStrText = $this->transmitText($postObj, $tarText);
                        $resultStrPic = $this->transmitNews($postObj, $tarResult,$rid);
                        $str = '';//进行回复的时候前面不能有输出，所以用一个str存放将要输出的内容
                        //判断图文回复和文本回复是否存在，由于图文回复和文本回复不会同时触发，所以图文回复优先
                        if($tarResult){
                            $str = $resultStrPic;
                        }else if($tarText){
                            $str = $resultStrText;
                        }
                        echo $str;
                    }else{//如果没有相关的关键词回复，查询用户设置的无法应答回复

                        //判断用户是否设置无法应答回复
                        $condition2['keywords'] = "###unknow";
//                    $condition2['token'] = TOKEN;
                        $unknowResult = $resuser->where($condition2)->find();
                        $rid=$unknowResult['id'];
                        $unknowArr = array();
                        if($unknowResult){
                            if ($unknowResult['restype'] == 1){
                                echo $this->transmitText($postObj, $unknowResult['content']);
                            }elseif($unknowResult['restype'] == 2){
                                $unknowArr = unserialize($unknowResult['content']);
                                echo $this->transmitNews($postObj, $unknowArr,$rid);
                            }elseif($unknowResult['restype'] == 3){
                                $unknowArr[] = $unknowResult;
                                echo $this->transmitNews($postObj, $unknowArr,$rid);
                            }
                        }else {
                            echo $this->transmitText($postObj, "欢迎关注本公共平台!");
                        }
                    }
                }


                /************* end 关键词回复事件   ****************/
            }
        }else {
            echo "";
            exit;
        }
    }

    function http_image($url,$fields){
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_SAFE_UPLOAD, false);//php5.6以上版本需要加此项
        curl_setopt ( $ch, CURLOPT_URL, $url);
        curl_setopt ( $ch, CURLOPT_POST, 1);
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
        $response = curl_exec ( $ch );
        return $response;
    }


    // 获取一般的 access_token
    public function get_access_token($appid,$appSecret){
        //判断是否过了缓存期
        $access_token = S('access_token');
        if(!empty($access_token)){
            return $access_token;
        }
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$appSecret";
        $return = httpRequest($url,'GET');
        $return = json_decode($return,1);
        S('access_token',$return['access_token'],7000);
        return $return['access_token'];
    }
    //自定义菜单事件推送(正在做)
    private function dirMenuRes($obj){
        $menuTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[event]]></MsgType>
                        <Event><![CDATA[CLICK]]></Event>
                        <EventKey><![CDATA[EVENTKEY]]></EventKey>
                    </xml>";
    }



    //回复文本消息
    private function transmitText($obj,$content){
        //文本回复的格式
        $textTpl = "<xml>
					 <ToUserName><![CDATA[%s]]></ToUserName>
					 <FromUserName><![CDATA[%s]]></FromUserName>
					 <CreateTime>%s</CreateTime>
					 <MsgType><![CDATA[text]]></MsgType>
					 <Content><![CDATA[%s]]></Content>
					 <FuncFlag>0</FuncFlag>
					</xml>";
        $fromUsername = $obj['FromUserName'];
        $toUsername = $obj['ToUserName'];
        $time = time();
        $str = sprintf($textTpl, $fromUsername, $toUsername, $time, $content);
        return $str;
    }
    //回复图片消息
    private function transmitImage($obj,$media_id=''){
        //图片回复的格式
        $textTpl = "<xml>
					 <ToUserName><![CDATA[%s]]></ToUserName>
					 <FromUserName><![CDATA[%s]]></FromUserName>
					 <CreateTime>%s</CreateTime>
					 <MsgType><![CDATA[image]]></MsgType>
					 <Image><MediaId><![CDATA[%s]]></MediaId></Image>
					 <FuncFlag>0</FuncFlag>
					</xml>";
        $fromUsername = $obj['FromUserName'];
        $toUsername = $obj['ToUserName'];
        $time = time();
        $str = sprintf($textTpl, $fromUsername, $toUsername, $time, $media_id);
        return $str;
    }
    //回复图文消息
    private function transmitNews($obj,$newArray,$id=0){
        if(!is_array($newArray)){
            return;
        }
        $response= db('response')->find($id);
        $itemTpl = "<item>
                     <Title><![CDATA[%s]]></Title>
                     <Description><![CDATA[%s]]></Description>
                     <PicUrl><![CDATA[%s]]></PicUrl>
                     <Url><![CDATA[%s]]></Url>
                    </item>";
        $item_str = '';
        foreach ($newArray as $n=>$v){
            //判断图片地址是否为空，为空给一张默认图片
            if($v["fileurl"] != ''){
                if ( C('test_fix') != '' ){
                    $fileurl = "http://" . $_SERVER['HTTP_HOST'] . '/Public/' . C('test_fix') . '/' . $v["fileurl"];
                }else{
                    $fileurl = "http://" . $_SERVER['HTTP_HOST'] . '/Public/' . $v["fileurl"];
                }
            }else{
                if ( C('test_fix') != '' ){
                    $fileurl = "http://" . $_SERVER['HTTP_HOST'] . '/' . C('test_fix') . "/Public/Uploads/55ac99b00e96d.png";
                }else{
                    $fileurl = "http://" . $_SERVER['HTTP_HOST'] . "/Public/Uploads/55ac99b00e96d.png";
                }
            }

           //判断是否有图文回复的跳转链接，没有的话需要添加一个默认地址，暂时用的百度网址，需要修改
           if($v["link"] != ''){
               $link = $v["link"];
           }else{
               if($response['restype']==2){
                   $link ="http://" . $_SERVER['HTTP_HOST'].url("Home/Index/news",array('id'=>$response['id'],'restype'=>$response['restype'],'key'=>$n));
               }else{
                   $link ="http://" . $_SERVER['HTTP_HOST'].url("Home/Index/news",array('id'=>$v['id'],'restype'=>$v['restype'],'key'=>$n));
               }
           }
           $item_str .= sprintf($itemTpl,$v["title"],$v["about"],$fileurl,$link);
        }
        $xmlTpl = "<xml>
         <ToUserName><![CDATA[%s]]></ToUserName>
         <FromUserName><![CDATA[%s]]></FromUserName>
         <CreateTime>%s</CreateTime>
         <MsgType><![CDATA[news]]></MsgType>
         <ArticleCount>%s</ArticleCount>
         <Articles>
         $item_str</Articles>
         </xml>";
         $result = sprintf($xmlTpl, $obj['FromUserName'], $obj['ToUserName'], time(), count($newArray));
         
         return $result;
    }
    
    private function checkSignature()
    {
        // you must define TOKEN by yourself
        if (!defined("TOKEN")) {
            throw new Exception('TOKEN is not defined!');
        }
        
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
    
        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        // use SORT_STRING rule
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
    
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 	作用：将xml转为array
     */
    private function xmlToArray($xml) {
        //将XML转为array
        //禁止引用外部xml实体
        // libxml_disable_entity_loader(true);
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if (is_array($array_data) && !empty($array_data)) {
            foreach ($array_data as $kk => $vv) {
                if (is_array($vv)) {
                    $array_data[$kk] = !empty($vv) ? $vv : '';
                } else {
                    $array_data[$kk] = trim($vv);
                }
            }
            return $array_data;
        }
        return false;
    }
}