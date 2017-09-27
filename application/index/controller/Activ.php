<?php
namespace app\index\controller;

//use Think\Controller;
class Activ extends Common {
//    public $ActivLogic;


//    public function _initialize(){
//        parent::_initialize();
//        $this->ActivLogic= new \Home\Logic\ActivLogic();
//    }
    //活动页
    public function index(){
        $param=input('');
        $this->view->engine->layout('layout');
        $datalist[0]=$this->get_activ(0);
        $datalist[1]=$this->get_activ(1);
        $datalist[2]=$this->get_activ(2);

        $data=[
            'kind'=>3,
            'activlist0'=>$datalist[0],
            'activlist1'=>$datalist[1],
            'activlist2'=>$datalist[2],
            ];
        if(isset($param['ajax'])){
            $this->view->engine->layout(false);
        }
        return view('index',$data);


    }
    //异步获取活动页数据
    public function get_activ($activtype){


        $map=array();
        if($activtype>0){
            $map['activtype']=$activtype;
        }
        $activlist=db('activ')->where($map)->order('addtime desc')->select();
        foreach($activlist as $k=>$v){
            $activlist[$k]['activabout']=htmlspecialchars_decode($v['activabout']);
            $count=db('appointment')->where(array('activ_id'=>$v['id'],'type'=>3))->count();
            $activlist[$k]['proccess']=round($count/$v['limit_people'] ,2)*100;//百分比进度条
            $activlist[$k]['over']=$v['endtime']<time()?1:0;//活动是否结束
        }
        return $activlist;
    }
    //异步获取活动页数据
    public function ajax_activ(){
        $this->view->engine->layout(false);
        $page=input('page');
        $map=array();
        if(input('activtype')>0)
            $map['activtype']=input('activtype');
        $limitrow=3;
        $starrow=($page-1)*$limitrow;
        $activlist=db('activ')->where($map)->limit($starrow,$limitrow)->order('addtime desc')->select();
        foreach($activlist as $k=>$v){
            $activlist[$k]['activabout']=htmlspecialchars_decode($v['activabout']);
            $count=db('appointment')->where(array('activ_id'=>$v['id'],'type'=>3))->count();
            $activlist[$k]['proccess']=round($count/$v['limit_people'] ,2)*100;//百分比进度条
            $activlist[$k]['over']=$v['endtime']<time()?1:0;//活动是否结束
        }
        $this->assign('activlist',$activlist);
        return $this->fetch();
    }
    //活动详情页
    public function detail(){
        $act=input("act");
//        $act=$_GET['act']?$_GET['act']:'';

        $this->assign('act',$act);
        $this->assign('kind',3);
        $id=input('get.id');
        $activ=db('activ')->find($id);
        $activ['notice']=htmlspecialchars_decode($activ['notice']);
        $activ['activabout']=htmlspecialchars_decode($activ['activabout']);
        $activ['detail']=htmlspecialchars_decode($activ['detail']);
        $activ['sign_up']=htmlspecialchars_decode($activ['sign_up']);
        $activ['is_end']=0;
        if($activ['status'] ==2 || $activ['endtime']<time() ){
            $activ['is_end']=1;
        }
        $activ['addr']=$activ['province'].$activ['city'].$activ['area'].$activ['address'];
//        show_bug($activ);
        if( $activ['stoptime']>time()){
            $stoptime=  $activ['stoptime']-time();
            $days=floor($stoptime/86400);//剩余天数
            $h=floor($stoptime/3600)-$days*24;//剩余小时
            $i=floor($stoptime/60)-$h*60-$days*24*60;//剩余分
            $activ['topinfo']='剩余'.$days.'天'.$h.'时'.$i.'分截止报名';
        }else{
            $activ['topinfo']='已截止报名';
        }

        //获奖代码
        if($activ['is_prize']==1){
            $prize=db('activ_award_people')->where(array('activ_id'=>$id))->select();
           foreach($prize as $k=>$v){
               $nickname=db('user')->where(array('id'=>$v['uid']))->column('nickname');
               if($v['level']==1){
                   $activ['oneprize'][]=$this->userTextDecode($nickname);
               }elseif($v['level']==2){
                   $activ['twoprize'][]=$this->userTextDecode($nickname);
               } elseif($v['level']==3){
                   $activ['threeprize'][]=$this->userTextDecode($nickname);
               }
           }
            $activ['oneprize']=implode('、', $activ['oneprize']);
            $activ['twoprize']=implode('、', $activ['twoprize']);
            $activ['threeprize']=implode('、', $activ['threeprize']);
        }



        $this->assign('activ',$activ);
        //报名状态
        $sign_status=$this->sign_status($activ['id']);
        $this->assign('sign_status',$sign_status);
        //获奖状态
        $prizestatus=$this->prize_status($activ['is_prize']);
        $this->assign('prizestatus',$prizestatus);

        //找出用户获得的奖项
        $prize_user=$this->prize_user();
        $this->assign('prize_user',$prize_user);
        if(input('ajax')==1)
            $this->view->engine->layout(false);
        return $this->fetch();


    }
    //找出用户获得的奖项
    public function prize_user($aid){
        $activ_id=input('get.id');
        if($aid){
            $activ_id=$aid;
        }
       $activ= db('activ')->find($activ_id);
       $activ_award= db('activ_award_people')->where(array('uid'=>$this->user_id,'activ_id'=>$activ_id))->find($activ);
       $appo= db('appointment')->where(array('openid'=>$_SESSION['openid'],'type'=>3,'activ_id'=>$activ_id))->find();
        if($activ_award['level']==1){
            $user_prize[0]['point']=$activ['prizeo1'];
            if($activ['prizeo2']){
                $coupon=db('coupon')->find($activ['prizeo2']);
                $user_prize[1]=$coupon;
            }
            if($activ['prizeo3']) {
                $coupon2=db('coupon')->find($activ['prizeo3']);
                $user_prize[2]=$coupon2;
            }
        }elseif($activ_award['level']==2){
            $user_prize[0]['point']=$activ['prizet1'];
            if($activ['prizet2']){
                $coupon=db('coupon')->find($activ['prizet2']);
                $user_prize[1]=$coupon;
            }
            if($activ['prizet3']) {
                $coupon2=db('coupon')->find($activ['prizet3']);
                $user_prize[2]=$coupon2;
            }
        }elseif($activ_award['level']==3){
            $user_prize[0]['point']=$activ['prizeh1'];
            if($activ['prizeh2']){
                $coupon=db('coupon')->find($activ['prizeh2']);
                $user_prize[1]=$coupon;
            }
            if($activ['prizeh3']) {
                $coupon2=db('coupon')->find($activ['prizeh3']);
                $user_prize[2]=$coupon2;
            }
        }
//        show_bug($user_prize) ;
        return $user_prize;

    }
    //报名状态 ： $is_prize是否已颁奖
    protected function sign_status($activ_id){
        //status : 0 未报名 1 已报名未签到 2已签到
//        return array('status'=>0);
        $res=db('appointment')->where(array('openid'=>$_SESSION['openid'],'activ_id'=>$activ_id))->find();
        if(!$res)
            return array('status'=>0);
        if($res['password']==0)
            return array('status'=>1);
        if($res['password']>0)
            return array('status'=>2);
    }
    //获奖状态 ：$uid 用户id $is_prize是否已颁奖 颁奖后才能领奖
    protected function prize_status($is_prize){
        //status : -1 不显示 0 未领取 1已领取
//        return array('status'=>1);
        if($is_prize==0)
            return array('status'=>-1);
        $res=db('activ_award_people')->where(array('uid'=>$this->user_id))->find();
        if(!$res)
            return array('status'=>-1);
        if($res['is_get']==0)
            return array('status'=>0,'level'=>$res['level']);
        return array('status'=>1,'level'=>$res['level']);
    }

    //签到验证
    public function sign_pw(){
        $pw=input('pw');
        $activ_id=input('activ_id');
        $activ=db('activ')->find($activ_id);
        if($activ['password']==$pw){
            $r=db('appointment')->where(array('activ_id'=>$activ_id,'type'=>3,'openid'=>$_SESSION['openid']))->update(array('password'=>$pw,'sign_time'=>time()));
            if(!$r){
                $this->ajaxReturn(array('status'=>2,'msg'=>'验证失败'));
            }
            $this->ajaxReturn(array('status'=>1));
        }else{
            $this->ajaxReturn(array('status'=>2,'msg'=>'密码错误,请重新输入'));
        }
    }

    //领取奖励
    public function award_pw(){
        $pw=input('pw');
        $activ_id=input('activ_id');
        $activ=db('activ')->find($activ_id);
        $appointment=db('appointment')->where(array('activ_id'=>$activ_id,'type'=>3))->find();

        if($appointment['password'] == 0)
            $this->ajaxReturn(array('status'=>2,'msg'=>'请先签到'));
        if($activ['password'] !=$pw)
            $this->ajaxReturn(array('status'=>2,'msg'=>'密码错误,请重新输入'));

        $prize_user=$this->prize_user($activ_id);
        //领取后修改状态
        $res=db('activ_award_people')->where(array('uid'=>$this->user_id,'activ_id'=>$activ_id))->update(array('is_get'=>1,'get_time'=>time()));
        if(!$res)
            $this->ajaxReturn(array('status'=>2,'msg'=>'领取失败'));
        //添加用户获得的积分
        edit_point($_SESSION['openid'],$prize_user[0]['point']);
        unset($prize_user[0]);
        //添加用户获得的优惠券
        foreach($prize_user as $k=>$v){
            $upcoupon_data=array(
                'uid'=>$this->user_id,
                'openid'=>$_SESSION['openid'],
                'cid'=>$v['id'],
                'type'=>$v['type'],
                'send_time'=>time(),
                'endtime'=>time()+($v['expire_day']*86400),
                'source'=>'活动获得',
                'source_id'=>$activ_id,
            );
            db('coupon_list')->save($upcoupon_data);
        }

        $this->ajaxReturn(array('status'=>1));

    }




    //活动支付页
    public function pay_activ(){
//        $this->view->engine->layout(false);
        $id=input('id');
//        show_bug($id);
        $activ=db('activ')->find($id);
        $activ['longtime']=floor(($activ['endtime']-$activ['starttime'])/60);//活动时长
        $CouponList=$this->getCouponList(0);
        $coupon_count=count($CouponList);
        $this->assign('coupon_count',$coupon_count);
        $this->assign('couponlist',$CouponList);
        $this->assign('activ',$activ);
        if(input('ajax')==1)
            $this->view->engine->layout(false);
        return $this->fetch();
    }

    //检测是否能报名
    public function check_enroll(){
        $id=input('id');
        $activ=db('activ')->find($id);
        $result=db('appointment')->where(array('openid'=>$_SESSION['openid'],'activ_id'=>$id,'type'=>3))->select();
        $count=db('appointment')->where(array('activ_id'=>$id,'type'=>3))->count();

        if($result)
            $this->ajaxReturn(array('status'=>-1,'msg'=>'您已经报名过此活动，请等待活动开始'));
        if($count>=$activ['limit_people'])
            $this->ajaxReturn(array('status'=>-1,'msg'=>'报名人数已满,下次请早'));
        if( $activ['endtime']<time())
            $this->ajaxReturn(array('status'=>-1,'msg'=>'活动已结束'));
        if( $activ['stoptime']<time())
            $this->ajaxReturn(array('status'=>-1,'msg'=>'此活动已截至报名，下次请早点哦'));
        $this->ajaxReturn(array('status'=>1,'msg'=>'可以报名'));
    }
    //异步更新需要支付的金额
    public function ajax_cost_activ(){
        $data=input();
        $coupon_price=0;
        $activ=db('activ')->find($data['id']);
        $cost=$activ['money'];
        $vipstatus=$this->vip_status();
        if($data['coupon_id']>0){
            $coupon_list= db('coupon_list')->find($data['coupon_id']);
            $coupon= db('coupon')->find($coupon_list['cid']);
            $coupon_price=$coupon['money'];
            if($cost>$coupon['money']){
                $cost -= $coupon['money'];
            }else{
                $cost=0;
            }
        }
        $payinfo=array(
            'original_price'=>$activ['money'],
            'pay_money'=>$cost,
            'coupon_list_id'=>$data['coupon_id'],
            'coupon_price'=>$coupon_price,
            'activ_id'=>$data['id'],
        );
        session('payinfo_activ',$payinfo);//支付相关信息 供支付完成后记录信息用
        $this->ajaxReturn($cost);


    }


    public function pay_info(){
        $payinfo=session('payinfo_activ');
        $result=db('appointment')->where(array('openid'=>$_SESSION['openid'],'activ_id'=>$payinfo['activ_id'],'type'=>3))->select();

        if($result){
            $this->ajaxReturn(array('status'=>-1,'msg'=>'您已经报名过此活动，请等待活动开始'));
        }

        $activ= db('activ')->find($payinfo['activ_id']);

        $need_pay_money=$payinfo['pay_money'];
        $need_pay_money=0.01;
        $xmlarr['appid'] = $this->wxconfig['appid'];
        $xmlarr['mch_id'] =$this->wxconfig['mchid'];
        $wx_config = db("wx_config")->find();
        if( $wx_config['type']==2){
            $xmlarr['sub_mch_id'] =$activ['pay_id'];
        }
//        $xmlarr['sub_mch_id'] =$shop['pay_id'];
        $xmlarr['nonce_str'] = create_noncestr();
        $xmlarr['body'] = '肌动健身活动费用:' . $activ['activname'];
        $xmlarr['out_trade_no'] = create_out_trade_no();
        $_SESSION['order_sn'] = $xmlarr['out_trade_no']; // 把本次生成的订单号存入session中，保存购买记录的时候用到
        $xmlarr['total_fee'] = $need_pay_money * 100;
        $xmlarr['spbill_create_ip'] = $_SERVER["REMOTE_ADDR"];
        $xmlarr['notify_url'] = "http://" . $_SERVER['SERVER_NAME'] . "/index.php/Home/Notify/notify2";
        $xmlarr['trade_type'] = "JSAPI";
        $xmlarr['openid'] = session('openid');

        $xmlarr['sign'] = getSign($xmlarr,$this->wxconfig['key']);

        $xml=arrayToXml($xmlarr);

        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $response = httpRequest($url,'post',$xml);
        //        $payResult = json_decode($response,1);
        $payResult = xmlToArray($response);
        $prepay_id='';
        if ($payResult["result_code"] == "SUCCESS" && $payResult["return_code"] == "SUCCESS"){
            $prepay_id = $payResult['prepay_id'];
        }else{
            $this->ajaxReturn(array('status'=>2,'msg'=>$payResult['return_msg']));
        }

        if(!$prepay_id)
            $this->ajaxReturn(array('status'=>2,'msg'=>'不存在prepay_id'));

        $oddata['order_sn']=$_SESSION['order_sn'];
        $oddata['coupon_price']=$payinfo['coupon_price'];
        $oddata['add_time']=time();
        $oddata['mobile']=$this->user['tel'];
        $oddata['type']=2;
        $oddata['prepay_id']=$prepay_id;
        $oddata['pay_name']='微信支付';
        $oddata['total_amount']=$payinfo['original_price'];
        $oddata['order_amount']=$payinfo['pay_money'];
        $oddata['openid']=session('openid');
        $oddata['activ_id']=$payinfo['activ_id'];
        $oddata['coupon_id']=$payinfo['coupon_list_id'];
        db('order')->save($oddata);

        $tims = time();
        $nonstr =create_noncestr();
        $stringA = "appId=" . $this->wxconfig['appid'] . "&nonceStr=$nonstr&package=prepay_id=$prepay_id&signType=MD5&timeStamp=$tims&key=".$this->wxconfig['key'];
        $paySign=strtoupper(md5($stringA));

        $this->ajaxReturn(array("status"=>1,"timeStamp"=>$tims,"nonceStr"=>$nonstr,"package"=>"prepay_id=$prepay_id","paySign"=>$paySign));

    }
    public function pay_success(){

        $order=db('order')->where(array('order_sn'=>session('order_sn')))->find();
        $activ_id=input('activ_id');
        $payinfo=session('payinfo_activ');

        $activ=db('activ')->find($activ_id);
        if($order['pay_status']==1){
            $this->send_weixin_message_activ($activ['activname'], $activ['starttime'], $activ['endtime']);
            $this->ajaxReturn(array('status'=>1,'msg'=>'会员购买成功'));
        }
        $res= $this->Queryorder(session('order_sn'));
        if(!$res){
            $this->ajaxReturn(array('status'=>2,'msg'=>'支付失败'));
        }
        $user_info = $this->get_user_info();

        $appodata=array(
            'city_id'=> $activ['city_id'],
            'shop_id'=> $activ['shop_id'],
            'openid'=>$_SESSION['openid'],
            'type'=>3,
            'activ_id'=> $activ_id,
            'yuyuetime'=>$activ['starttime'],
            'addtime'=>date("Y-m-d H:i:s"),
            'tel'=>$user_info['tel'],
            'point'=> $activ['point'],
        );
        $appo_result = db('appointment')->save($appodata);
        // 把使用的抵价券变成status = 0
        if($payinfo['coupon_list_id']>0){
            $coupon_data['id'] = $payinfo['coupon_list_id'];
            $coupon_data['use_time'] = time();
            $coupon_data['order_id'] = $order['id'];
            $coupon_data['status'] = 1;
            db('coupon_list')->update($coupon_data);
        }

        //修改订单的支付状态
        db('order')->update(array('pay_status'=>1,'order_id'=>$order['order_id'],'pay_time'=>time()));
        edit_point($_SESSION['openid'],$activ['point']);
        $this->send_weixin_message_activ($activ['activname'], $activ['starttime'], $activ['endtime']);
        $this->ajaxReturn(array('status'=>1,'msg'=>'活动支付成功'));
    }
    /* 给报名成功用户推送消息 */
    public function send_weixin_message_activ($title,$starttime,$endtime){
        $starttime = date("Y-m-d H:i",$starttime);
        $endtime = date("H:i",$endtime);
        $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$this->access_token;
        $content ='活动'. $title.' 报名成功\n活动开始时间：'.$starttime.'-'.$endtime."\n请准时到场，BABY";
        $str = '{"touser":"'.$_SESSION['openid'].'","msgtype":"text","text":{"content":"'.$content.'"}}';

        $res= httpRequest($url,'post',$str);

    }
    //活动支付完成页
    public function activ_success(){
        $this->assign('kind',3);
        $order=db('order')->where(array('order_sn'=>$_SESSION['order_sn']))->find();
        //没有表示金额为0
        if(!$order){

        }
        $id=input('get.id');
        $activ= db('activ')->find($id);
        $activ['detail']=htmlspecialchars_decode($activ['detail']);
        $this->assign('activ',$activ);
        $this->assign('order',$order);
        if(input('ajax'))
            $this->view->engine->layout(false);
        return $this->fetch();
    }

    //团操评价页面
    public function appointment_evaluate(){
        $apid=input('get.id');
        $appointment= $this->get_appointment_info($apid);
        $this->assign("appointment",$appointment);
        $this->assign("kind",2);
        $this->assign("apid",$apid);
        if(input('ajax')==1)
            $this->view->engine->layout(false);
        return $this->fetch();
    }
    //预约评价查看页面
    public function appointment_evaluated(){
        $apid=input('get.id');
        $appointment=$this->get_appointment_info($apid);
        $eva_info=db('evaluation')->where(array('apid'=>$apid))->find();
        $eva_info['teacher_headimg']=db('teacher')->where(array('teacher_id'=>$eva_info['teacher_id']))->column('picture');

        $this->assign("eva_info",$eva_info);
        $this->assign("appointment",$appointment);
        $this->assign("kind",1);
        if(input('ajax')==1)
            $this->view->engine->layout(false);
        return $this->fetch();
    }
    //预约评价成功页面
    public function appo_evaluate_success(){
        $apid=input('id');
        $appointment= $this->get_appointment_info($apid);
        $activ=db('activ')->find($appointment['activ_id']);
//        $vipcard=$this->get_vip_info();
        $this->assign("getpoint", $activ['point']);
        $this->assign("kind",2);
        if(input('ajax')==1)
            $this->view->engine->layout(false);
        return $this->fetch();

    }
    //活动预约成功页面
    public function appointment_success(){
        $id = input('get.id');
        $appo=$this->get_appointment_info($id);
        $this->assign("kind",2);
        $this->assign("onelist",$appo);
        if(input('ajax'))
            $this->view->engine->layout(false);
        return $this->fetch();
    }
    //活动预约详情页面
    public function appointment_detail(){
        $id = input('get.id');
        $appo=$this->get_appointment_info($id);
//        show_bug($appo);
        $this->assign("kind",2);
        $this->assign("onelist",$appo);
        if(input('ajax'))
            $this->view->engine->layout(false);
       return $this->fetch();
    }
    //保存评价信息-----方法
    public function save_evaluation(){
        if(!$_POST['apid'])
            $this->ajaxReturn(array('status'=>2,'msg'=>"不存在该预约"));
        $user_result = $this->get_user_info();
        if ($user_result['status'] == 0)
            $this->ajaxReturn(array('status'=>2,'msg'=>"会员卡被冻结，评价失败"));

        $appo= $this->get_appointment_info($_POST['apid']);
        $activ=db('activ')->find($appo['activ_id']);
        if($appo['is_pingjia']==1)
            $this->ajaxReturn(array('status'=>2,'msg'=>"该预约已评价过"));


        $vipcard=db('vipcard')->find($user_result['card_id']);

        $data['apid'] = $_POST['apid'];
        $data['uid'] = $user_result['id'];
        $data['type'] = $_POST['type'];
        $data['content'] = $_POST['content'];
        $data['addtime'] = time();
        $data['activ_id'] = $appo['activ_id'];
        $data['activ_name'] = $appo['tname'];
//        $data['teacher_id'] = $action['teacher_id'];
//        $data['teacher_name'] = $action['teachername'];
        $data['point'] = $_POST['point'];
        $ev_res=db('evaluation')->save($data);
        if(!$ev_res)
            $this->ajaxReturn(array('status'=>2,'msg'=>"评价失败"));

        //改变当前预约的评价状态（变成已评价状态   is_pingjia =>  1）
        db('appointment')->update(array('id'=>$_POST['apid'],'is_pingjia'=>1));
        // 给用户加积分
        edit_point($_SESSION['openid'],$activ['point'],1);
        $this->ajaxReturn(array('status'=>1,'msg'=>"评价成功"));

    }

    //获取预约的详情---方法
    private function get_appointment_info($id){
        $result = array();
        $onelist = db('appointment')->find($id);
        $action = db('activ')->find($onelist['activ_id']);
        $action_starttime = date("Y-m-d",strtotime($action['teachdate'])) . " " .$action['starttime'];
        /*  获取该用户所属VIP的信息  */
        $vip_info = $this->get_vip_info();
        $prev_unix = $onelist['yuyuetime']-$vip_info['prev_minute']*60;
        $next_unix =$onelist['yuyuetime']+ $vip_info['next_minute']*60;
        $result['id'] = $onelist['id'];
        $result['status'] = $onelist['status'];
        $result['address'] = str_replace(",", "", $action['address']);
        $result['yuyuetime'] = date('Y-m-d',$action['starttime']);
        $result['yuyuemonth'] = date('Y-m-d',$action['starttime']);
        $result['yuyuehours'] = date("H:i",$action['starttime']);
        $result['yuyueendhours'] =date("H:i",$action['endtime']) ;
//        $result['doornum'] = $onelist['doornum'];
//        $result['accesshours'] = date( "H:i",$prev_unix );//锁生效时间
//        $result['accessendhours'] = date( "H:i",$next_unix  );//锁过期时间
        $result['notice'] = htmlspecialchars_decode($action['notice']);
        $result['nocancel'] = date("Y-m-d H:i", strtotime("-6 hours", $action['starttime'] ) );
        $result['activname'] = $action['activname'];
        $result['is_pingjia'] = $onelist['is_pingjia'];
        $result['action_id'] = $onelist['action_id'];


        return $result;
    }






}