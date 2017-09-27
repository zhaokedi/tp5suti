<?php

namespace app\index\controller;
use Home\Logic;
//use Think\Controller;
class Test extends Common {
    public function test(){
//        layout(false);

        $this->display('Share:test');

        exit;
//        $result = mysql_query("select * from lock where status=0 and info=123456 and ".time()." >= addtime and removtime >= ".time()." ORDER BY id DESC limit 1");

//        $sql="update `user` set ty_num=999 where card_id=4 AND endtime >$time";
//        $sql="Insert into user(openid,nickname,headurl,tel,card_id,endtime,uppeople,eat_num,sport_num,addtime) select openid,nickname,headurl,tel,cardtype,UNIX_TIMESTAMP(endtime),uppeople,eat_num,sport_num,addtime from userrr";
//        $a=M()->execute($sql);

//        exit;
//        $time=time();
//        $sql="select * FROM `user` where endtime >$time ";
//        $res=M()->query($sql);
//        foreach($res as $k=>$v){
//
//            $data['month']=date("ym");
//            $data['card_id']=$v['card_id'];
//            $data['addtime']=$time;
//            $data['uid']=$v['id'];
//            $data['type']=1;
//            if( in_array($v['card_id'],array(1,2,9))){
//                $data['point'] =1300;
//            }elseif(in_array($v['card_id'],array(3,6))){
//                $data['point']=1500;
//            }elseif(in_array($v['card_id'],array(4,7))){
//                $data['point']=1700;
//            }elseif(in_array($v['card_id'],array(5,8))){
//                $data['point']=1800;
//            }
//            $userdata['point']=array('exp','point+'. $data['point']);
//            M('user')->where(array('id'=>$v['id']))->save($userdata);
//            M('point_log')->save($data);
//        }
//        show_bug(  count($res));


    }



    function htttt($url,$fields){
        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_SAFE_UPLOAD, false);

        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_POST, 1 );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $fields );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
        $response = curl_exec ( $ch );
        return $response;
    }
    function userTextEncode($str){
        if(!is_string($str))return $str;
        if(!$str || $str=='undefined')return '';

        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i",function($str){
            return addslashes($str[0]);
        },$text); //将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。
        return json_decode($text);
    }
    /**
    解码上面的转义
     */
    function userTextDecode($str){
        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback('/\\\\\\\\/i',function($str){
            return '\\';
        },$text); //将两条斜杠变成一条，其他不动
        return json_decode($text);
    }
    function bytes_to_emoji($cp)
    {
        if ($cp > 0x10000){       # 4 bytes
            return chr(0xF0 | (($cp & 0x1C0000) >> 18)).chr(0x80 | (($cp & 0x3F000) >> 12)).chr(0x80 | (($cp & 0xFC0) >> 6)).chr(0x80 | ($cp & 0x3F));
        }else if ($cp > 0x800){   # 3 bytes
            return chr(0xE0 | (($cp & 0xF000) >> 12)).chr(0x80 | (($cp & 0xFC0) >> 6)).chr(0x80 | ($cp & 0x3F));
        }else if ($cp > 0x80){    # 2 bytes
            return chr(0xC0 | (($cp & 0x7C0) >> 6)).chr(0x80 | ($cp & 0x3F));
        }else{                    # 1 byte
            return chr($cp);
        }
    }
    /* 计算当前用户预约成功后的密码生效时间  */
    public function create_doornum_time($yuyuetime){
        $result = array();
        $yuyuetime_unix = strtotime($yuyuetime);
        $user_info = $this->get_user_info();
        $vip_result = M('vipcard')->where(array('card_id'=>2))->find();

        $result['starttime'] = $yuyuetime_unix - $vip_result['prev_minute'] * 60;//生效时间 unix
        $result['endtime'] =$yuyuetime_unix + $vip_result['next_minute'] * 60;//失效时间 unix
        return $result;
    }

    public function index(){
        $v_code = '';
        for ($i=0; $i<6; $i++){
            $temp_num = rand(0,9);
            $v_code .= $temp_num;
        }
        $data['phone']="15157285063";
        $data['jsoncontent']='{"code":"'.$v_code.'","product":"肌动塑体"}';
        $data['signname']="肌动塑体";
        $data['tempcode']="SMS_34345368";
        $res=smsAlidayu($data);

        session('message_check_num',$v_code);

    }
    public function smsAlidayu($phone="15157285063",$headname="身份验证",$jsoncontent,$tempcode){
        require './alidayu/TopSdk.php';//引入加载相关的类文件

        //生成六位位随机数

        $appkey = '23578649';
        $secret = 'df49d2befaef8e889dc8cd26b3d5ac1d';
        $c = new \TopClient;
        $c->appkey = $appkey;
        $c->secretKey = $secret;
        $c->format = 'json';

        $req = new \AlibabaAliqinFcSmsNumSendRequest;
        $req->setExtend("123456");
        $req->setSmsType("normal");
        $req->setSmsFreeSignName("身份验证");
        $req->setSmsParam('{"code":"{$v_code}","product":"肌动塑体"}');
        $req->setRecNum($phone);
        $req->setSmsTemplateCode("SMS_34345372");
        $resp = $c->execute($req);
//        show_bug($resp);exit;
        $sms_ok = '2';              //默认情况下不成功
        if(isset($resp->result->err_code)){
            if($resp->result->err_code == '0'){   //发送成功
                $sms_ok = '1';
            }
        }
        if( $sms_ok == '1' ){
            $backdata['status'] = 1;
            $backdata['errormsg'] = '验证码发送成功';
        }else{
            $backdata['status'] = 2;
            $backdata['errormsg'] = '验证码发送失败';
        }
        return $backdata;
    }
}