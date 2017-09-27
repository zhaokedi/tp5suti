<?php
namespace app\index\controller;
use Home\Logic;
use think\Controller;

class Common extends Controller
{
    public $wxconfig;
    public $user;
    public $user_id;
    public $openid;
    //普通的access_token
    public $access_token;

    public function _initialize(){


        //不是微信登入就用测试环境数据
        if(!is_weixin()){
            $_SESSION['openid'] = 'oY7u7s7HAb302BmdfZ0LJ8X2pZbA';
//            $_SESSION['openid'] = 'oY7u7s7HAb302BmdfZ0LJ8X2pZbA';
            $_SESSION['lat'] = 120.13693841030545;
            $_SESSION['lng'] = 30.289194502412062;
            $this->openid='oY7u7s7HAb302BmdfZ0LJ8X2pZbA';
            $this->user=$this->get_user_info();
            $this->assign('user',$this->user);
            $this->user_id= $this->user['id'];
            $wx_config = db("wx_config")->find();
            $this->wxconfig=json_decode($wx_config["jsoncontents"],true);
            $vip_status=$this->vip_status();

            //每个月会员的积分变更操作
            $this->add_point();
            $this->assign("vip_status",$vip_status);
            $jssdk = new \app\index\Logic\Jssdk($this->wxconfig['appid'], $this->wxconfig['appsecret']);
            $signPackage = $jssdk->GetSignPackage();
            $this->access_token=$jssdk->get_access_token();
            $this->assign('signPackage', $signPackage);
            $time=time();
            $user_condition['openid'] = $_SESSION['openid'];
            $user_condition['endtime'] = array('lt',$time);
            db('user')->where($user_condition)->update(array('card_id'=>0));
            return;
        }

        if ($_SERVER['QUERY_STRING']){
            $url =  'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
        }else{
            $url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
        }
        $wx_config = db("wx_config")->find();
        $this->wxconfig=json_decode($wx_config["jsoncontents"],true);
        if(!$_SESSION['openid']){
            $this->get_notice_info($url);
        }
        //输出用户信息变量
        $this->user=$this->get_user_info();
        $this->openid=$_SESSION['openid'];
        $this->user_id= $this->user['id'];
        session('user',$this->user);
        $this->assign('user',$this->user);
        $time=time();
        //检查用户昵称头像是否改变并更新
        $this->check_update($_SESSION['nickname'],$_SESSION['headurl']);
        //验证当前登录用户是不是会员，以及会员是否过期
        $vip_status=$this->vip_status();
        $this->assign("vip_status",$vip_status);
        /* 判断过期用户有没购买没使用的会员卡 并更新  */
//        if($vip_status['status'] ==0 || $vip_status['status'] ==-2){
//            $this->vip_end_change();
//        }
        //判断用户是否需要解冻
        if($vip_status['status']==-3 && !empty($this->user['auto_thaw']) && $this->user['auto_thaw']<time() ){
          $this->auto_thaw();
        }
        //每个月会员的积分变更操作
        $this->add_point();
        //把当前用户待进行的(status = 1)  过期预约  变成已完成(status = 3)
        $condition=array(
            'openid'=>$_SESSION['openid'],
            'status'=>1,
            'yuyuetime'=> array("lt",$time),
        );
        if($_SESSION['openid'])
            db('appointment')->where($condition)->update(array('status'=>3));

//         微信Jssdk 操作类 用分享朋友圈 JS
        $jssdk = new \Home\Logic\Jssdk($this->wxconfig['appid'], $this->wxconfig['appsecret']);
        $signPackage = $jssdk->GetSignPackage();
        $this->access_token=$jssdk->get_access_token();
        $this->assign('signPackage', $signPackage);
        //会员到期后变为0
        $user_condition['openid'] = $_SESSION['openid'];
        $user_condition['endtime'] = array('lt',$time);
        db('user')->where($user_condition)->update(array('card_id'=>0));

    }
    //自动解冻
    public function auto_thaw(){
        $user=$this->user;
        $data['status']=1;
        $data['endtime']=$user['brozen_time']+time();
        $data['auto_thaw']=0;
        db('user')->update($data);
    }
    //每月增加会员用户的积分
    public function add_point(){

        $vip_status=$this->vip_status();
        $user=$this->user;
        $month=date('ym');
        if($vip_status['status']==1){
            $res=db('point_log')->where(array('uid'=>$user['id'],'month'=>$month,'type'=>1))->find();
            //不存在表示当月还未进行操作
            if(!$res){
                $data['point']=$this->cardtopoint($user['card_id']);
                $data['uid']=$user['id'];
                $data['month']=$month;
                $data['type']=1;
                $data['card_id']=$user['card_id'];
                $data['addtime']=time();
                $userdata['point']=array('exp','point+'. $data['point']);
                db('user')->where(array('id'=>$user['id']))->update($userdata);
                db('point_log')->save($data);
            }
        }elseif($vip_status['status']==-2){
            //此情况代表会员卡到期 若超过3天未续费则每月扣1000分
            $expire_time=time()-$user['endtime'];
            $res=db('point_log')->where(array('uid'=>$user['id'],'month'=>$month,'type'=>2))->find();
            //到期时间大于3天
            if($expire_time>3*3600*24 && !$res){
                $data['point']=1000;
                $data['uid']=$user['id'];
                $data['month']=$month;
                $data['type']=2;
                $data['card_id']=$user['card_id'];
                $data['addtime']=time();
                if($user['point']>1000){
                    $userdata['point']=array('exp','point-1000');
                }else{
                    $userdata['point']=0;
                }

                db('user')->where(array('id'=>$user['id']))->update($userdata);
                db('point_log')->save($data);
            }
        }
    }
    public function cardtopoint($cardid){
        $point=0;
        if( in_array($cardid,array(1,2,9))){
            $point=1300;
        }elseif(in_array($cardid,array(3,6))){
            $point=1500;
        }elseif(in_array($cardid,array(4,7))){
            $point=1700;
        }elseif(in_array($cardid,array(5,8))){
            $point=1800;
        }
        return $point;
    }
    //把用户的地理位置信息写入session中
    public function write_location(){
        $lat = $_POST['lat'];//纬度
        $lng = $_POST['lng'];//经度

        if ( $lat && $lng ){
            $_SESSION['lat'] = $lat;
            $_SESSION['lng'] = $lng;
        }else{
            $_SESSION['lat'] = -1;
            $_SESSION['lng'] = -1;
        }
    }
    //程序内部获取用户信息
    public function get_user_info(){
        $card_img=array(0=>'my_card01.jpg',1=>'card_03.png',2=>'card_04.png',3=>'card_02.png',4=>'card_01.png',5=>'card_05.png',6=>'card_06.jpg',7=>'card_06.jpg',8=>'card_06.jpg',9=>'card_03.png');
        $user = db('user')->where(array('openid' => $_SESSION['openid']))->find();

        if(!$user)
            return '';
        if(date('ymd',$user['lasttime'])!=date('ymd')){
            $user['first_login']=1;
        }else{
            $user['first_login']=0;
        }
        if($user['card_id']>0){
            $user['cardtype']=db('vipcard')->where(array('id'=>$user['card_id']))->column("type");
            if($user['card_id']==2){
                $user['ttt']='ttt';
            }else{
                $user['ttt']='';
            }
        }
        $user['card_img']=$card_img[$user['card_id']];

        $user['has_days']=0;//会员剩余天数
        if($user['endtime']>time())
            $user['has_days'] = ceil(($user['endtime'] -time())/86400);
        $color='#FFFFF';
        if($user['has_days']>=8 && $user['has_days']<=15){
            $color='#f4d420';
        }elseif($user['has_days']<8){
            $color='red';
        }
        $user['color']=$color;
        //冻结状态下 剩余时间得以延长
        if($user['status']!=1){
            $user['has_days'] = ceil($user['brozen_time']/86400);
        }

        if(in_array($user['card_id'],array(1,2,9)) && $user['shop_id']>0){
            $user['shopname']=db('shop')->where(array('id'=>$user['shop_id']))->column("title");
        }else{
            $user['shopname']='各门店通用';
        }
        //优惠券数量
        $user['levelname']= getlevelname($user['point']);
        $user['coupon_num']= db('coupon_list')->where(array('status'=>0,'openid'=>$user['openid'],'use_time'=>0,'end_time'=>array('gt',time())))->count();
        $user['card_num']=sprintf("%05d", $user['id']);
        $user['nickname']=$this->userTextDecode($user['nickname']);
        $appo=db('appointment')->where(array('openid'=>$_SESSION['openid']))->order('id desc')->find();
        //判断是否超过1周未锻炼
        $week_time=3600*24*7;
        if($appo&& time()-$appo['yuyuetime'] >$week_time){
            $user['exercise_msg']=1;
        }else{
            $user['exercise_msg']=0;
        }
        $user['msg']='';
        $mess=array(
            array('status'=>2,'msg'=>'会员卡有效期小于10天'),
            array('status'=>2,'msg'=>'超过7天未健身'),
            array('status'=>2,'msg'=>'优惠券即将到期（3天）'),
            array('status'=>2,'msg'=>'上次未评价'));
        if($user['has_days']==10){
            $mess[0]['status']=1;
        }


        //两次刷新或登入的时间差

        db('user')->where(array('openid' => $_SESSION['openid']))->update(array('lasttime'=>time()));
        return $user;


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
    /**解码上面的转义*/
    function userTextDecode($str){
        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback('/\\\\\\\\/i',function($str){
            return '\\';
        },$text); //将两条斜杠变成一条，其他不动
        return json_decode($text);
    }
    //验证用户的昵称头像是否变化并更新
    private function check_update($nickname,$headurl){
        $onelist = $this->user;
        $bol1=0;
        if ( !$onelist )
            return false;
        if ( $onelist['nickname'] != $nickname ){
            $bol1=1;
            $data1['nickname']=$this->userTextEncode($nickname);
        }
        if( $onelist['headurl'] != $headurl ){
            $bol1=1;
            $data1['headurl']=$headurl;
        }


        if($bol1==1){
            db('user')->where(array('id'=>$onelist['id']))->update($data1);  //用户表
        }
    }
    //验证当前用户是否是会员，以及会员是否过期
    //会员状态（0： 学生卡冻结，1：正常 2后台冻结）
    public function vip_status(){

        $result = db('user')->where(array('openid'=>$_SESSION['openid']))->find();
        $time=time();
        if(!$result)
            return false;


        if($result['card_id'] == 0 && empty($result['endtime']))
            return array('status'=>0,'msg'=>"您还不是我们的会员，无法预约哦！");
        if($result['endtime'] < $time )
            return array('status'=>-2,'msg'=>"您的会员到期啦，无法预约哦！");
        if($result['status'] == 0 )
            return array('status'=>-1,'msg'=>"您已经购买了学生卡,请凭学生证到店激活");
        if($result['status'] == 2 )
            return array('status'=>-3,'msg'=>"会员被冻结，无法预约");
        return array('status'=>1,'msg'=>"会员功能正常");

    }
    //验证当前用户是否到期，并查询有无购买为使用的会员卡，没有则把card_id变为0
    public function vip_end_change(){
        $user = db('user')->where(array('openid'=>$_SESSION['openid']))->find();
        if(!$user)
            return false;
        $vipres=db('vipbuy')->where(array('uid'=>$user['id'],'status'=>0))->find();
        $vipcard= db('vipcard')->find($vipres['card_id']);
        if($vipres){
            $user_data['endtime']=$user['endtime']+($vipres['hasday']*86400);
            $user_data['id'] = $user['id'];
            $user_data['shop_id'] = $vipres['shop_id'];//绑定的门店需要到时后传入
            $user_data['card_id'] = $vipres['card_id'];
            $user_data['eat_num'] = $vipcard['yingyang_num'];
            $user_data['sport_num'] = $vipcard['buji_num'];
            $user_data['ty_num'] = $vipcard['ty_num'];
            $user_data['point'] = $user['point'] + $vipcard['point_buy'];
            $user_data['vipbuy_id'] = $vipres['id'];
            $user_data['upteacher'] = $vipres['teacher_id'];
            db('user')->update($user_data);
            db('vipbuy')->update(array('id'=>$vipres['id'],'status'=>1,'end_time'=>$user['endtime']+($vipres['hasday']*86400),'change_time'=>time()));
        }else{
            $user_data['id'] = $user['id'];
            $user_data['card_id']=0;
            db('user')->update($user_data);
        }

    }
    /*** 检测当前用户是否能预约选择的时间  ***/
    public function is_can_do_appointment($appointment_time='',$msg=''){
        $appointment_time=strtotime($appointment_time);//传进来是date格式
        $user_info = $this->get_user_info();
        $vip_result = db('vipcard')->where(array('id'=>$user_info['card_id']))->find();
         $daynum =$vip_result['next_day']!=0? $vip_result['next_day']:1;//可以预约的天数

        /*
         * 条件一：预约的时间要在当前时间之后
         * 条件二：预约的时间段-》当前时间-------最大预约天数的22：00之前
         */
        $time = time();//当前时间戳
        $max_time_day = strtotime('+'.($daynum-1).' days',time());//最大预约天数的日期
        $max_time = date("Y-m-d",$max_time_day)." 22:00:00";//最大预约天数晚上10点
        $max_time_unix = strtotime($max_time);//最大预约天数晚上10点的时间戳
        //$appointment_time;预约时间的时间戳
        if ($appointment_time <= $time)
            return array('status'=>2,'msg'=>"不能预约过去的时间段噢");

        if ( $appointment_time > $max_time_unix )
            return array('status'=>2,'msg'=>"大人，该课程还未到预约时间哦");

        return array('status'=>1,'msg'=>"可以预约");


    }
    //查询订单
    public function Queryorder($out_trade_no){
        $url="https://api.mch.weixin.qq.com/pay/orderquery";

        $xmlarr['appid'] = $this->wxconfig['appid'];
        $xmlarr['mch_id'] =$this->wxconfig['mchid'];
        $xmlarr['out_trade_no'] =$out_trade_no;
        $xmlarr['nonce_str'] = create_noncestr();
        $xmlarr['sign'] = getSign($xmlarr,$this->wxconfig['key']);
        $xml=arrayToXml($xmlarr);

        $result= httpRequest($url,'post',$xml);
        $result = xmlToArray($result);
        if(array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS"
            && $result["trade_state"] == "SUCCESS")
        {
            return true;
        }

        return false;
    }
    /*
     * 获取该用户开门密码有效时间
     */
    public function get_vip_info(){
        $user_info = $this->get_user_info();
        $vip_result = db('vipcard')->where(array('id'=>$user_info['card_id']))->find();
        return $vip_result;
    }
    // 裁剪指定的图片，生成指定尺寸的图片（原图地址，存储地址，新宽度，新高度,图片标识(1:用于图文类型的列表，2：用于宝贝类型的列表)，是否删除原图（0：不删除，1：删除），）
//    public function set_two_kind_pic($src_file, $dst_file, $new_width, $new_height,$img_num='n',$is_del=0)
//    {
//        $image = new \think\Image();
//        $image->open($src_file);
//        $width = $image->width(); //返回图片的宽度
//        $height = $image->height(); //返回图片的高度
//        $type = $image->type(); //返回图片的类型
//        $old_present = $width / $height;
//        $new_present = $new_width / $new_height;
//        $filename = strtotime(date("Y-m-d H:i:s")) . '-' . uniqid('abc') . '-' . $img_num . '.' . $type;
//
//        //对应生成图片的缩略图
//        if ( $old_present > $new_present ){
//            //根据需要的高度进行裁剪
//            $temp_width = $width * $new_height / $height;
//            $image->thumb($temp_width, $new_height,\Think\Image::IMAGE_THUMB_FIXED)->crop($new_width, $new_height, ($temp_width-$new_width)/2 ,0)->update($dst_file.$filename);
//        }else if( $old_present < $new_present ){
//            //根据需要的宽度进行裁剪
//            $temp_height = $height * $new_width / $width;
//            $image->thumb($new_width, $temp_height,\Think\Image::IMAGE_THUMB_FIXED)->crop($new_width, $new_height, 0, ($temp_height-$new_height)/2 )->update($dst_file.$filename);
//        }else if( $old_present == $new_present ){
//            //根据需要的宽度进行裁剪
//            $image->thumb($new_width, $new_height,\Think\Image::IMAGE_THUMB_FIXED)->update($dst_file.$filename);
//        }
//        if($is_del == 1){
//            //删除原图
//            unlink($src_file);
//        }
//
//        return $dst_file.$filename;
//    }

    /*  获取关注用户的信息 （检查数据库中是否存在用户信息，没有就创建）*/
    public function get_notice_info($url){
        $code = $this->get_web_code($url);//获取用户授权的code
        //检查access_token是否存在于session中，是否过期
        if (empty($_SESSION['web_access_token']) || $_SESSION['web_expires_time'] < time() || empty($_SESSION['openid']) ){
           $this->get_web_access_token($url,$code);
        }
        $user_info_url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$_SESSION['web_access_token']."&openid=".$_SESSION['openid']."&lang=zh_CN";
        $user_info_result = httpRequest($user_info_url,'get');
        $user_info_result = json_decode($user_info_result,1);
        if( $user_info_result['openid'] ){
            $_SESSION['nickname'] =$user_info_result['nickname'];
            $_SESSION['headurl'] = $user_info_result['headimgurl'];
            $user = db('user');
            $user_result = $user->where(array('openid'=> $_SESSION['openid'],))->find();
            if( !$user_result ){
                $data['openid'] = $_SESSION['openid'];
                $data['nickname'] = $_SESSION['nickname'];
                $data['headurl'] = $_SESSION['headurl'];
                $data['sex'] = $user_info_result['sex'];
                $data['addtime'] = date("Y-m-d H:i:s");
                $user->save($data);
            }else{
                if ( $user_info_result['nickname'] != $user_result['nickname'] )
                    $user->update(array('id'=>$user_result['id'],'nickname'=>$user_info_result['nickname']));
                if ( $user_info_result['headimgurl'] != $user_result['headurl'] )
                    $user->update(array('id'=>$user_result['id'],'headurl'=>$user_info_result['headimgurl']));
                if ( $user_info_result['sex'] != $user_result['sex'] )
                    $user->update(array('id'=>$user_result['id'],'sex'=>$user_info_result['sex']));
            }
        }
    }

    /* 获取网页授权的code,tearget_url:跳转地址。获取code之后存放在session中 */
    public function get_web_code($target_url){
        $redirect_url = urlencode($target_url);//跳转的页面-urlencode处理
        $code_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$this->wxconfig['appid']."&redirect_uri=".$redirect_url."&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect";//获取code的链接
        //code只能使用一次
        if ( isset($_GET['code']) &&  !empty($_GET['code']) ){
            return $_GET['code'];
        }else{
            echo "<script>window.location.href='".$code_url."';</script>";
            exit;
        }
    }

    /* 获取网页授权的access_token */
    public function get_web_access_token($target_url,$code){
        $access_token_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->wxconfig['appid']."&secret=".$this->wxconfig['appsecret']."&code=".$code."&grant_type=authorization_code";
        $access_token_result = httpRequest($access_token_url,'get');
        $access_token_result = json_decode($access_token_result,true);

        if( $access_token_result['errcode'] == 40029 ){
            $this->get_web_code($target_url);//重新获取code
            exit;
        }
        if($access_token_result['access_token']){
            //将access_token 内容、获取时间、有效时间和openid存入session中
            $_SESSION['web_access_token'] = $access_token_result['access_token'];
            $_SESSION['web_expires_time'] =  $access_token_result['expires_in']+time();
            $_SESSION['openid'] = $access_token_result['openid'];
            return $access_token_result;
        }
    }


    public function send_phone_message(){

        $v_code = '';
        for ($i=0; $i<6; $i++){
            $temp_num = rand(0, 9);
            $v_code .= $temp_num;
        }
        $data['phone']= $_POST['phone'];
        $data['jsoncontent']='{"code":"'.$v_code.'","product":"肌动塑体"}';
        $data['signname']="肌动塑体";
        $data['tempcode']="SMS_34345368";
        $res=smsAlidayu($data);

        if( $res== true ){
            session('message_check_num',$v_code);
            $this->ajaxReturn(array('status'=>1,'msg'=>'验证码发送成功'));
        }else{
            $this->ajaxReturn(array('status'=>2,'msg'=>'验证码发送失败'));
        }

    }
    //短信发送密码
    public function send_appo_message($door,$exctime){

        $user_info = $this->get_user_info();
        $data['phone']=  $user_info['tel'];
        $data['jsoncontent']='{"username":"'.$user_info['nickname'].'","door":"'.$door.'","time":"'.$exctime.'"}';
        $data['signname']="肌动塑体";
        $data['tempcode']="SMS_46190019";
        $res=smsAlidayu($data);


    }
    /* 吉信通 短信读取接口--用于预约成功后发送开门密码       这个是HTTP接口(需要转为GB2312编码) */
    public function send_message($tos,$content,$doornum,$starttime,$endtime){
        $starttime = date("H:i",strtotime($starttime));
        $endtime = date("H:i",strtotime($endtime));
        $contents = $content.'预约成功。开门密码：*'.$doornum."#。健身时间：".$starttime."--".$endtime."。你知道我在等你吗，BABY";
        $url="http://service.winic.org:8009/sys_port/gateway/?";
        $data = "id=%s&pwd=%s&to=%s&content=%s&time=";
        $id = urlencode('jidongjianshen');
        $pwd = 'jdjs2015';
        $to = $tos;
        /*转为GB2312编码*/
        $contents = urlencode(iconv("UTF-8","GB2312",$contents));
        $rdata = sprintf($data, $id, $pwd, $to, $contents);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$rdata);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
        //打印一下参数 可以看到 在GB2312编码模式的浏览器下 显示字符是正常的
        $result = curl_exec($ch);
        curl_close($ch);
    }

    /* 计算当前用户预约成功后的密码生效时间  */
    public function create_doornum_time($yuyuetime){
        $result = array();
        $yuyuetime_unix = strtotime($yuyuetime);
        $user_info = $this->get_user_info();
        $vip_result = db('vipcard')->where(array('card_id'=>$user_info['card_id']))->find();

        $result['starttime'] = $yuyuetime_unix - $vip_result['prev_minute'] * 60;//生效时间 unix
        $result['endtime'] =$yuyuetime_unix + $vip_result['next_minute'] * 60;//失效时间 unix
        return $result;
    }

    /* 给用户推送消息 */
    public function send_weixin_message($title,$doornum,$starttime,$endtime){
        $starttime = date("H:i",strtotime($starttime));
        $endtime = date("H:i",strtotime($endtime));
        $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$this->access_token;
        $content = $title.' 预约成功\n开门密码：*'.$doornum.'#\n健身时间：'.$starttime.'-'.$endtime."\n你知道我在等你吗，BABY";
        $str = '{"touser":"'.$_SESSION['openid'].'","msgtype":"text","text":{"content":"'.$content.'"}}';

       $res= httpRequest($url,'post',$str);

    }

    /* 检查用户是否能预约器械预约  */
    public function check_user_appointment(){
        $ytime=explode(" ",$_POST['yuyuetime']);
        $_POST['yuyuetime']=$ytime[0].' '.$ytime[1].':'.$ytime[2];

        $userinfo = $this->get_user_info();//获取用户信息
        $shop =db('shop')->find($_POST['shopid']);
        $vipstatus= $this->vip_status();
        if($vipstatus['status']!=1)
            $this->ajaxReturn(array('status'=>2,'msg'=>$vipstatus['msg']));
        if ( $userinfo['status'] == 0 )
            $this->ajaxReturn(array('status'=>2,'msg'=>"会员卡被冻结，无法预约"));

        if ($shop['status'] == 0)
            $this->ajaxReturn(array('status'=>2,'msg'=>"门店尚未营业，敬请期待"));
        if($userinfo['shop_id']!=$_POST['shopid']){
            if($userinfo['ty_num']<=0)
                $this->ajaxReturn(array('status'=>2,'msg'=>"您的门店通用次数为0，无法预约此门店"));

        }

        //检测该用户当天的预约数量，超过2个不能预约
        $condition['openid'] = $_SESSION['openid'];
        //$condition['status'] = 1;

        $post_yuyuetime = $_POST['yuyuetime'];//预约的时间---datetime类型
        $this->check_is_manyuan($_POST['yuyuetime'],$shop['limit_people']);

        //检查当天是否被土豪包场了
//        $aaa_result = $this->check_all($_POST['yuyuetime']);
//        if ($aaa_result['status'] == 2)
//            $this->ajaxReturn(array('status'=>2,'msg'=>$aaa_result['msg']));
//

        $post_date = date("Y-m-d",strtotime($post_yuyuetime));//预约时间的年月日
        $post_date_old = date("Y-m-d",strtotime("-1 day",strtotime($post_date)));//预约时间的前一天年月日
        $post_date_new = date("Y-m-d",strtotime("+1 day",strtotime($post_date)));//预约时间的后一天年月日

        if ( strtotime($post_yuyuetime) < strtotime($post_date." 22:00") ){
            //属于当天的预约，需要比前一天的晚上22：00大，比当天的22：00小
            $condition['yuyuetime'] = array('between',array(strtotime($post_date_old." 22:00"),strtotime($post_date." 22:00")));
        }else{
            //第二天的预约,需要比当天的22：00大，比第二天的22：00小
            $condition['yuyuetime'] = array('between',array(strtotime($post_date." 22:00"),strtotime($post_date_new." 22:00")));
        }
        //学生卡在该时间段不能预约
        if($userinfo['card_id']==2){
            $vip=db('vipcard')->where(array('id'=>$userinfo['card_id']))->find();
            //设置不能预约的时间段
            $stime=strtotime($post_date." ".$vip['stime']);
            $etime=strtotime($post_date." ".$vip['etime']);
            $notime=time();
            //预约进行的时间段
            $starttime = strtotime($_POST['yuyuetime']);
            if($starttime<=$notime)
                $this->ajaxReturn(array('status'=>2,'msg'=>"不能预约过去的时间段噢"));
            if ($starttime>$stime && $starttime<$etime)
                $this->ajaxReturn(array('status'=>2,'msg'=>"学生卡不能预约该时间段的器械"));

        }
        $check_num = db('appointment')->where($condition)->count();//用户在当天的预约数量
        if ($check_num >= 2)
            $this->ajaxReturn(array('status'=>2,'msg'=>"要给肌肉恢复时间，效果才会更加猛烈"));

        //检查预约时间是否被尊贵会员预约了（限制人数是否已到）
//        $this->check_bestvip_yuyue($post_yuyuetime);

        $condition['status'] = 1;
        $check_result = db('appointment')->where($condition)->select();//用户在当天预约记录


        //当前预约的密码生效时间
        $now_appointmnet_lock_time = $this->create_doornum_time($_POST['yuyuetime']);
        //预约时间-密码开始生效时间-的时间戳
        $yuyue_time_unix_start = $now_appointmnet_lock_time['starttime'];
        //预约时间-密码结束生效时间-的时间戳
        $yuyue_time_unix_end = $now_appointmnet_lock_time['endtime'];

        $start_time_unix = 0;
        $end_time_unix = 0;
        foreach ( $check_result as $newn=>$newv ){
            //查询自助健身或者团操预约的时间
            if ( $newv['type'] == 1 ){
                //获取该条记录密码有效时间
                $temp_lock_time = $this->create_doornum_time(date("Y-m-d H:i:s",$newv['yuyuetime']));
                $start_time_unix = $temp_lock_time['starttime'];//该条预约的开始时间--时间戳
                $end_time_unix = $temp_lock_time['endtime'];//该条预约的结束时间--时间戳

                if ( $yuyue_time_unix_start < $start_time_unix ){
                    if ( $yuyue_time_unix_end >= $start_time_unix )
                        $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                }else{
                    if ( $yuyue_time_unix_start < $end_time_unix )
                        $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                }

            }else if( $newv['type'] == 2 ){
                $action_user = db('action');
                $temp_action_result = $action_user->find($newv['actionid']);
                $start_time = date("Y-m-d",strtotime($temp_action_result['teachdate']))." ".$temp_action_result['starttime'];//团操的开始时间
                $end_time = date("Y-m-d",strtotime($temp_action_result['teachdate']))." ".$temp_action_result['endtime'];//团操的结束时间

                $temp_lock_time1 = $this->create_doornum_time($start_time);
                $start_time_unix = $temp_lock_time1['starttime'];
                $end_time_unix = $temp_lock_time1['endtime'];

                if ( $yuyue_time_unix_start < $start_time_unix ){
                    if ( $yuyue_time_unix_end >= $start_time_unix )
                        $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                }else{
                    if ( $yuyue_time_unix_start < $end_time_unix )
                        $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                }
            }else if( $newv['type'] == 3 ){
                $_activ_result = db('activ')->find($newv['activ_id']);
                if ( $yuyue_time_unix_start < $_activ_result['starttime']  ){
                    //预约团操的开始时间  小于  该条预约记录的开始时间  的情况下
                    if ( $yuyue_time_unix_end >= $_activ_result['starttime'] ){
                        $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                    }
                }else{
                    //预约团操的开始时间  大于  该条预约记录的开始时间  的情况下
                    if( $yuyue_time_unix_start < $_activ_result['endtime'] ){
                        $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                    }
                }
            }
        }

//        if ( $userinfo['card_id'] == 4 ){
//            //检测尊贵用户在这个时间段是否有一键预约的记录
//            $this->check_best_appointment($post_yuyuetime, $yuyue_time_unix_start, $yuyue_time_unix_end);
//        }



        $yuyue_time_status = $this->is_can_do_appointment($_POST['yuyuetime'],'请升级会员获得更大预约权限');
        if ( $yuyue_time_status['status'] == 2 ){
            $this->ajaxReturn(array('status'=>2,'msg'=>$yuyue_time_status['msg']));
        }

        $this->ajaxReturn(array('status'=>1));
    }

    /* 检查用户能否预约团操预约 */
    public function check_user_action(){

        $temp_user_info = $this->get_user_info();
        $vipstatus= $this->vip_status();
        if($vipstatus['status']!=1)
            $this->ajaxReturn(array('status'=>2,'msg'=>$vipstatus['msg']));
        if ( $temp_user_info['status'] == 0 )
            $this->ajaxReturn(array('status'=>2,'msg'=>"会员卡被冻结，无法预约"));

        $appointment = db('appointment');
        //检查该用户有没有重复预约同一个团操
        $condition['openid'] = $_SESSION['openid'];
        $condition['action_id'] = $_POST['actionid'];
        $condition['status'] = 1;
        $res=$appointment->where($condition)->select();
        if ( $res )
            $this->ajaxReturn(array('status'=>2,'msg'=>"您已经预约过该团操"));


        $action = db('action')->find($_POST['actionid']);

        //检测该用户当前的预约数量，超过2个不能预约
        $condition1['openid'] = $_SESSION['openid'];
        //$condition1['status'] = 1;

        //传过来的预约时间
        $post_yuyuetime = $action['teachdate'];
        $post_date = date("Y-m-d",strtotime($post_yuyuetime));//预约时间的年月日
        $post_date_old = date("Y-m-d",strtotime("-1 day",strtotime($post_date)));//预约时间的前一天年月日
        $post_date_new = date("Y-m-d",strtotime("+1 day",strtotime($post_date)));//预约时间的后一天年月日

        //检查当天是否被土豪包场了
        $all_result = $this->check_all($post_yuyuetime);
        if ($all_result['status'] == 2)
            $this->ajaxReturn(array('status'=>2,'msg'=>$all_result['msg']));

        //学生卡在该时间段不能预约

        if($temp_user_info['cardtype']==1){

            $vip=db('vipcard')->where(array('id'=>$temp_user_info['card_id']))->find();
            //设置不能预约的时间段
            $stime=strtotime($post_date." ".$vip['stime']);

            $etime=strtotime($post_date." ".$vip['etime']);
            //团操进行的时间段
            $yuyuestime=strtotime($post_date." ".$action['starttime']);
            $yuyueetime=strtotime($post_date." ".$action['endtime']);
            if($yuyuestime <= time() ){
                $this->ajaxReturn(array('status'=>2,'msg'=>"不能预约过去的时间段噢"));
            }elseif($yuyuestime>=$stime && $yuyuestime<= $etime){
                $this->ajaxReturn(array('status'=>2,'msg'=>"学生卡不能预约该时间段的团操"));
            }
        }
        if ( strtotime($post_yuyuetime) < strtotime($post_date." 22:00") ){
            //属于当天的预约，需要比前一天的晚上22：00大，比当天的22：00小
            $condition1['yuyuetime'] = array('between',array(strtotime($post_date_old." 22:00"),strtotime($post_date." 22:00")));
        }else{
            //第二天的预约,需要比当天的22：00大，比第二天的22：00小
            $condition1['yuyuetime'] = array('between',array(strtotime($post_date." 22:00"),strtotime($post_date_new." 22:00")));
        }

        $check_num = $appointment->where($condition1)->count();
        if ($check_num >= 2){
            $this->ajaxReturn(array('status'=>2,'msg'=>"要给肌肉恢复时间，效果才会更加猛烈"));
        }else {
            //本次团操的开始时间
            $two_yuyue_time = $post_date . " " . $action['starttime'];

            //检查预约时间是否被尊贵会员预约了（限制人数是否已到）
            $this->check_bestvip_yuyue($two_yuyue_time);

            //当前预约的密码生效时间
            $now_appointmnet_lock_time = $this->create_doornum_time($two_yuyue_time);
            //预约时间-密码开始生效时间-的时间戳
            $yuyue_time_unix_start = $now_appointmnet_lock_time['starttime'];
            //预约时间-密码结束生效时间-的时间戳
            $yuyue_time_unix_end = $now_appointmnet_lock_time['endtime'];

            $condition1['status'] = 1;
            $check_result = $appointment->where($condition1)->select();//用户在当天的预约记录
            $start_time_unix = 0;//某条预约记录的开始时间戳
            $end_time_unix = 0;//某条预约记录的结束时间戳
            foreach ( $check_result as $newn=>$newv ){
                //查询自助健身或者团操预约的时间
                if ( $newv['type'] == 1 ){
                    $start_time_unix = strtotime($newv['yuyuetime']);
                    $end_time_unix = strtotime("+1 hour",$start_time_unix);

                    if ( $yuyue_time_unix_start < $start_time_unix  ){
                        //预约团操的开始时间  小于  该条预约记录的开始时间  的情况下
                        if ( $yuyue_time_unix_end >= $start_time_unix ){
                            $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                        }
                    }else{
                        //预约团操的开始时间  大于  该条预约记录的开始时间  的情况下
                        if( $yuyue_time_unix_start < $end_time_unix ){
                            $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                        }
                    }

                }else if( $newv['type'] == 2 ){
                    $temp_action_result = db('action')->find($newv['action_id']);
                    $start_time = date("Y-m-d",strtotime($temp_action_result['teachdate']))." ".$temp_action_result['starttime'];
                    $end_time = date("Y-m-d",strtotime($temp_action_result['teachdate']))." ".$temp_action_result['endtime'];
                    $start_time_unix = strtotime($start_time);
                    $end_time_unix = strtotime($end_time);
                    if ( $yuyue_time_unix_start < $start_time_unix  ){
                        //预约团操的开始时间  小于  该条预约记录的开始时间  的情况下
                        if ( $yuyue_time_unix_end >= $start_time_unix ){
                            $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                        }
                    }else{
                        //预约团操的开始时间  大于  该条预约记录的开始时间  的情况下
                        if( $yuyue_time_unix_start < $end_time_unix ){
                            $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                        }
                    }
                }else if( $newv['type'] == 3 ){
                    $_activ_result = db('activ')->find($newv['activ_id']);
                    if ( $yuyue_time_unix_start < $_activ_result['starttime']  ){
                        //预约团操的开始时间  小于  该条预约记录的开始时间  的情况下
                        if ( $yuyue_time_unix_end >= $_activ_result['starttime'] ){
                            $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                        }
                    }else{
                        //预约团操的开始时间  大于  该条预约记录的开始时间  的情况下
                        if( $yuyue_time_unix_start < $_activ_result['endtime'] ){
                            $this->ajaxReturn(array('status'=>2,'msg'=>"您在本时间段内有预约了"));
                        }
                    }
                }
            }

//            if ( $temp_user_info['card_id'] == 4 ){
//                //检测尊贵用户在这个时间段是否有一键预约的记录
//                $this->check_best_appointment($post_yuyuetime, $yuyue_time_unix_start, $yuyue_time_unix_end);
//            }
        }

        $yuyuetime = date( "Y-m-d",strtotime($action['teachdate']) );
        $yuyuetime = $yuyuetime ." ".$action['starttime'];

        $yuyue_time_status = $this->is_can_do_appointment($yuyuetime,"大人，该课程还未到预约时间哦");

        if ( $yuyue_time_status['status'] == 2 )
            $this->ajaxReturn(array('status'=>2,'msg'=>$yuyue_time_status['msg']));

        $this->ajaxReturn(array('status'=>1));

    }


    //尊贵用户，在这个时间段是否有一键预约，当天一键预约的数量不能超过2个
    public function check_best_appointment($yuyuetime,$start_lock_time,$end_lock_time){
        $user = db('onekey');
        $post_yuyuetime = $yuyuetime;//预约的时间---datetime类型
        $post_date = date("Y-m-d",strtotime($post_yuyuetime));//预约时间的年月日
        $post_date_old = date("Y-m-d",strtotime("-1 day",strtotime($post_date)));//预约时间的前一天年月日
        $post_date_new = date("Y-m-d",strtotime("+1 day",strtotime($post_date)));//预约时间的后一天年月日

        if ( strtotime($post_yuyuetime) < strtotime($post_date." 22:00") ){
            //属于当天的预约，需要比前一天的晚上22：00大，比当天的22：00小
            $condition['yuyuetime'] = array('between',array(($post_date_old." 22:00"),($post_date." 22:00")));
        }else{
            //第二天的预约,需要比当天的22：00大，比第二天的22：00小
            $condition['yuyuetime'] = array('between',array(($post_date." 22:00"),($post_date_new." 22:00")));
        }

        $check_result = $user->where($condition)->select();//用户在当天预约记录
        //预约时间-密码开始生效时间-的时间戳
        $yuyue_time_unix_start = $start_lock_time;//$now_appointmnet_lock_time['starttime'];
        //预约时间-密码结束生效时间-的时间戳
        $yuyue_time_unix_end = $end_lock_time;//$now_appointmnet_lock_time['endtime'];
        $start_time_unix = 0;
        $end_time_unix = 0;
        if( $check_result ){
            if( count($check_result) > 2 ){
                $backdata['status'] = 2;
                $backdata['msg'] = "要给肌肉恢复时间，效果才会更加猛烈";
                $this->ajaxReturn($backdata);
            }

            foreach ( $check_result as $n=>$v ){
                $start_time_unix = strtotime('-30 minutes',strtotime($v['yuyuetime']));//该条预约的开始时间--时间戳
                $end_time_unix = strtotime('+90 minutes',strtotime($v['yuyuetime']));//该条预约的结束时间--时间戳
                if ( $yuyue_time_unix_start < $start_time_unix ){
                    if ( $yuyue_time_unix_end < $start_time_unix ){

                    }else{
                        $this->ajaxReturn(array('status'=>2,'msg'=>'您在本时间段内有预约了'));
                    }
                }else{
                    if ( $yuyue_time_unix_start < $end_time_unix ){
                        $this->ajaxReturn(array('status'=>2,'msg'=>'您在本时间段内有预约了'));

                    }
                }
            }
        }
    }



    //检查预约时间是否被尊贵会员预约了（限制人数是否已到）
    public function check_bestvip_yuyue($yuyuetime){
        $user = db('onekey');
        $yuyue_day = date("Y-m-d",strtotime($yuyuetime));//用户预约日期
        $post_date_old = date("Y-m-d",strtotime("-1 day",strtotime($yuyue_day)));//预约时间的前一天年月日
        $post_date_new = date("Y-m-d",strtotime("+1 day",strtotime($yuyue_day)));//预约时间的后一天年月日

        if ( strtotime($yuyue_day) < strtotime($yuyue_day." 22:00") ){
            //属于当天的预约，需要比前一天的晚上22：00大，比当天的22：00小
            $condition['yuyuetime'] = array('between',array(strtotime($post_date_old." 22:00"),strtotime($yuyue_day." 22:00")));
        }else{
            //第二天的预约,需要比当天的22：00大，比第二天的22：00小
            $condition['yuyuetime'] = array('between',array(strtotime($yuyue_day." 22:00"),strtotime($post_date_new." 22:00")));
        }
        $check_result = $user->where($condition)->select();//当天的一键预约记录

        //当前预约的密码生效时间
        $now_appointmnet_lock_time = $this->create_doornum_time($yuyuetime);
        //预约时间-密码开始生效时间-的时间戳
        $yuyue_time_unix_start = $now_appointmnet_lock_time['starttime'];
        //预约时间-密码结束生效时间-的时间戳
        $yuyue_time_unix_end = $now_appointmnet_lock_time['endtime'];

        foreach ( $check_result as $n=>$v ){
            $start_time_unix = strtotime('-30 minutes',strtotime($v['yuyuetime']));//该条预约的密码锁开始时间--时间戳
            $end_time_unix = strtotime('+90 minutes',strtotime($v['yuyuetime']));//该条预约的密码锁结束时间--时间戳
            if ( $yuyue_time_unix_start < $start_time_unix ){
                if ( $yuyue_time_unix_end >= $start_time_unix ){
                    //判定这个时间段的预约人数是否已满
                    $is_full = $this->check_is_full($start_time_unix,$end_time_unix,$v['num']);
                    if ( !$is_full )
                        $this->ajaxReturn(array('status'=>2,'msg'=>'预约人数已满，请选择其他时间段预约'));
                }
            }else{
                if ( $yuyue_time_unix_start < $end_time_unix ){
                    //判定这个时间段的预约人数是否已满
                    $is_full = $this->check_is_full($start_time_unix,$end_time_unix,$v['num']);
                    if ( !$is_full ){
                        $this->ajaxReturn(array('status'=>2,'msg'=>'预约人数已满，请选择其他时间段预约'));
                    }
                }
            }
        }
    }

    //检测预约时间是否已经被尊贵用户包场
    public function check_all($posttime){
        $yuyuetime = $posttime;//$_POST['yuyuetime'];//预约的时间（datetime类型）
        $yuyuetime_day = date("Y-m-d",$yuyuetime);//预约日期
        $yuyuetime_unix = strtotime($yuyuetime_day);//预约日期的时间戳
        $before_yuyuetime = date("Y-m-d",strtotime('-1 day',$yuyuetime_unix));
        $next_yuyuetime = date("Y-m-d",strtotime('+1 day',$yuyuetime_unix));
        $baochang=db('baochang')-> where(array('status'=>1,'bao_time'=>$yuyuetime_unix))->select();
        if ($baochang)
            return array('status'=>2,'msg'=>'神秘人物出现了！！包场！亲们，要不要包场后来一睹真容 您可以预约'.$before_yuyuetime.'，或者'.$next_yuyuetime);

        return array('status'=>1,'msg'=>'没有包场');

    }

    //判断一键预约的时间段预约人数是否已满(一键预约的开始时间戳，一键预约的结束时间戳)
    public function check_is_full($yijian_start_time,$yijian_end_time,$num){
        $user = db('appointment');
        $temp_user = db('user');
        $vip_user = db('vipcard');
        $yijian_start = date("Y-m-d H:i:s",$yijian_start_time);
        $yijian_end = date("Y-m-d H:i:s",$yijian_end_time);
        //查询出预约时间在一键预约范围内的   预约记录
        $condition['yuyuetime'] = array('between',array($yijian_start, $yijian_end));
        $result = $user->where($condition)->select();
        $count = $user->where($condition)->count();
        foreach ( $result as $n=>$v ){
            $temp_user_info = $temp_user->find($v['uid']);
            $temp_condition['card_id'] = $temp_user_info['card_id'];
            $vip_info = $vip_user->where($temp_condition)->find();
            //该条预约记录的密码生效时间-----时间戳
            $temp_lock_start_time = strtotime("-".$vip_info['prev_minute']." minutes",strtotime($v['yuyuetime']));
            if( $temp_lock_start_time > $yijian_start_time ){
                if ( $temp_lock_start_time < $yijian_end_time ){
                    $count--;
                }
            }
        }
        if ( $count < $num ){
            return 1;//名额未满
        }else{
            return 0;//名额已满
        }
    }

    //检查当前时间是否已经满员----传入date类型,器械预约用
    public function check_is_manyuan($yuyuetime,$limit_people){
        $user = db('appointment');

        $post_unix = strtotime($yuyuetime);
        $start_search_unix = strtotime("-30 minutes",$post_unix);//开始查询的时间戳
        $end_search_unix = strtotime("+30 minutes",$post_unix);//结束查询的时间戳
        $start_search_time = date("Y-m-d H:i:s",$start_search_unix);//开始查询的时间
        $end_search_time = date("Y-m-d H:i:s",$end_search_unix);//结束查询的时间

        //查询在当前时间是否有团操课程
        $now_day = date("Y-m-d",$post_unix);
        $start_time = date("H:i",$start_search_unix);
        $end_time = date("H:i",$end_search_unix);

        $action_user = db('action');
        //团操开始时间在  预约的  判定时间段里面
        $action_condition1['teachdate'] = $now_day;
        $action_condition1['starttime'] = array('between',array($start_time,$end_time));
        $temp_action_result1 = $action_user->where($action_condition1)->select();

        //团操的开始时间<预约的开始判定时间、团操的结束时间>预约的开始判定时间
        $action_condition2['teachdate'] = $now_day;
        $action_condition2['starttime'] = array('lt',$start_time);
        $action_condition2['endtime'] = array('gt',$start_time);
        $temp_action_result2 = $action_user->where($action_condition2)->select();
        $num = $limit_people;
        if ( $temp_action_result1 || $temp_action_result2 ){
            //最大数量按照45人计算
            $num = 45;
            //$condition3['type'] = 1;
            $condition3['yuyuetime'] = array('between',array($start_search_time,$end_search_time));
            $count = $user->where($condition3)->count();
            if( $count >= $num ){
                //查询离预约时间之前最近的预约时间
                //$condition1['type'] = 1;
                $condition1['yuyuetime'] = array('lt',$yuyuetime);
                $before_near_onelist = $user->order('id desc')->where($condition1)->find();
                $before_near_time = $before_near_onelist['yuyuetime'];

                //查询离预约时间之后最近的预约时间
                //$condition2['type'] = 1;
                $condition2['yuyuetime'] = array('gt',$yuyuetime);
                $next_near_onelist = $user->where($condition2)->find();
                $next_near_time = $next_near_onelist['yuyuetime'];
                if ( $before_near_onelist ){
                    $before_time = date("Y-m-d H:i",strtotime('-1 hour',$before_near_time));
                }else{
                    $before_time = date("Y-m-d H:i",strtotime('-1 hour',$next_near_time));
                }
                if ( $next_near_onelist ){
                    $next_time = date("Y-m-d H:i",strtotime("+1 hour",$next_near_time));
                }else{
                    $next_time = date("Y-m-d H:i",strtotime("+1 hour",$before_near_time));
                }


                $this->ajaxReturn(array('status'=>2,'msg'=>'健身房已爆炸。您可以预约'.$next_time.'，或者'.$before_time.''));
            }

        }else{
            $condition['type'] = 1;
            $condition['yuyuetime'] = array('between',array($start_search_time,$end_search_time));
            $count = $user->where($condition)->count();
            if( $count >= $num ){
                //查询离预约时间之前最近的预约时间
                $condition1['type'] = 1;
                $condition1['yuyuetime'] = array('lt',$yuyuetime);
                $before_near_onelist = $user->order('id desc')->where($condition1)->find();
                $before_near_time = $before_near_onelist['yuyuetime'];

                //查询离预约时间之后最近的预约时间
                $condition2['type'] = 1;
                $condition2['yuyuetime'] = array('gt',$yuyuetime);
                $next_near_onelist = $user->where($condition2)->find();
                $next_near_time = $next_near_onelist['yuyuetime'];

                if ( $before_near_onelist ){
                    $before_time = date("Y-m-d H:i",strtotime('-1 hour',strtotime($before_near_time)));
                }else{
                    $before_time = date("Y-m-d H:i",strtotime('-1 hour',strtotime($next_near_time)));
                }

                if ( $next_near_onelist ){
                    $next_time = date("Y-m-d H:i",strtotime("+1 hour",strtotime($next_near_time)));
                }else{
                    $next_time = date("Y-m-d H:i",strtotime("+1 hour",strtotime($before_near_time)));
                }
                $this->ajaxReturn(array('status'=>2,'msg'=>'健身房已爆炸。您可以预约'.$next_time.'，或者'.$before_time.''));
            }
        }


    }

    //根据经纬度计算距离的函数
    public function get_distance($lat2,$lng2){
        $earthRadius = 6367000;
        //用户经纬度
        $lat1 = ($_SESSION['lat'] * pi()) / 180;
        $lng1 = ($_SESSION['lng'] * pi()) / 180;

        //门店经纬度
        $lat2 = ($lat2 * pi()) / 180;
        $lng2 = ($lng2 * pi()) / 180;

        $calcLatitude = $lat2 - $lat1;
        $calcLongitude = $lng2 - $lng1;

        $stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
        $stepTwo = 2 * asin(min(1, sqrt($stepOne)));
        $calculatedDistance = $earthRadius * $stepTwo;

        return round($calculatedDistance);
    }
    //获取可以使用的优惠券
    public  function getCouponList($type=-1){
        $openid=session('openid');
        $time=time();
        $where="l.openid ='$openid' and l.use_time=0 and l.end_time>$time";
        if($type !=-1){
            $where.=" and l.type=".$type;
        }
        $sql="select l.*,c.name,c.money from coupon_list l LEFT JOIN coupon c ON l.cid=c.id WHERE ".$where;
        $couponlist=db()->query($sql);
        foreach ($couponlist as $k=>$v){
            $couponlist[$k]['expire_days']=floor(($v['end_time']-time())/86400);
            $couponlist[$k]['endtime']=date('Y-m-d', $v['end_time']);
        }

        return $couponlist;
    }

}