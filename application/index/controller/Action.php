<?php
namespace app\index\controller;
use Home\Logic;
//use Think\Controller;
class Action extends Common {

    public function index(){
        $week_info=$this->week_info();
        $param=input('');
        if(isset($param['ajax'])){
            $this->view->engine->layout(false);
        }

        foreach($week_info as $k=>$v){
            $action= $this->get_action_list($v['date']);
            $week_info[$k]['actionlist']=$action;
        }
        $this->assign("week_list",$week_info);
        $this->assign("kind",2);

        return view('index');

    }
    //团操详情
    public function detail(){
        $this->assign("kind",2);
        $id = $_GET['id'];
        $onelist = $this->get_action_info($id);
//        show_bug($onelist);
        $pjlist=db('evaluation')->field('id,uid,content')->where(array('action_id'=>$id))->order("id desc")->select();
        foreach($pjlist as $k=>$v){
            $pjlist[$k]['nickname']=db('user')->where(array('id'=>$v['uid']))->column('nickname');
        }
        $this->assign("pjlist",$pjlist);
        $this->assign("action",$onelist);
        $this->assign("imgs",$onelist['imgs']);
        if(input('ajax'))
           $this->view->engine->layout(false);
        return $this->fetch();
    }
    public function appointment(){
        $this->assign("kind",2);
        $action=$this->get_action_info(input('actionid'));

        $this->assign("action_info",$action);
        $this->assign("user_info",$this->get_user_info());
        $this->assign("shopid",$_GET['shopid']);

        if(input('ajax'))
           $this->view->engine->layout(false);
        return $this->fetch();
    }
    //团操预约取消的页面
    public function appointment_cancel(){

        $id = $_GET['id'];
        $onelist=$this->get_appointment_info($id);
        $this->assign("onelist",$onelist);
        if(input('ajax'))
           $this->view->engine->layout(false);
        return $this->fetch();
    }
    //团操评价页面
    public function appointment_evaluate(){
        $apid=input('get.id');

        $appointment= $this->get_appointment_info();
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

        $this->assign("apid",$apid);
        $this->assign("eva_info",$eva_info);
        $this->assign("appointment",$appointment);
        $this->assign("kind",1);
        if(input('ajax')==1)
           $this->view->engine->layout(false);
        return $this->fetch();
    }
    //预约评价成功页面
    public function appo_evaluate_success(){

        $vipcard=$this->get_vip_info();
        $this->assign("getpoint", $vipcard['point_com_action']);
        $this->assign("kind",2);
        if(input('ajax')==1)
           $this->view->engine->layout(false);
        return $this->fetch();

    }
    //团操预约成功页面
    public function appointment_success(){
        $id = input('get.id');
        $appo=$this->get_appointment_info($id);
        $this->assign("kind",2);
        $this->assign("onelist",$appo);
        if(input('ajax'))
           $this->view->engine->layout(false);
        return $this->fetch();
    }
    //团操预约详情页面
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

        if($appo['is_pingjia']==1)
            $this->ajaxReturn(array('status'=>2,'msg'=>"该预约已评价过"));

        $action=$this->get_action_info($appo['action_id']);
        $vipcard=db('vipcard')->find($user_result['card_id']);

        $data['apid'] = $_POST['apid'];
        $data['uid'] = $user_result['id'];
        $data['type'] = $_POST['type'];
        $data['content'] = $_POST['content'];
        $data['addtime'] = time();
        $data['action_id'] = $appo['action_id'];
        $data['action_name'] = $appo['tname'];
        $data['teacher_id'] = $action['teacher_id'];
        $data['teacher_name'] = $action['teachername'];
        $data['point'] = $_POST['point'];
        $ev_res=db('evaluation')->save($data);
        if(!$ev_res)
            $this->ajaxReturn(array('status'=>2,'msg'=>"评价失败"));

        //改变当前预约的评价状态（变成已评价状态   is_pingjia =>  1）
        db('appointment')->update(array('id'=>$_POST['apid'],'is_pingjia'=>1));
        // 给用户加积分
        edit_point($_SESSION['openid'],$vipcard['point_com_action'],1);
        $this->ajaxReturn(array('status'=>1,'msg'=>"评价成功"));

    }


    //保存用户的团操预约信息
    public function save_action_appointment(){
        if(!$_POST['actionid'])
            $this->ajaxReturn(array('status'=>2,'msg'=>"不存在该团操"));
        $user_info = $this->get_user_info();
        $action_id=$_POST['actionid'];
        if ($user_info['status'] == 0 )
            $this->ajaxReturn(array('status'=>2,'msg'=>"会员卡被冻结，无法预约"));
        $appointment = model('appointment');
        $condition['openid'] = $_SESSION['openid'];
        $condition['action_id'] = $_POST['actionid'];
        $condition['status'] = 1;
        $res=$appointment->where(array('openid'=> $_SESSION['openid'],'action_id'=>$_POST['actionid'],'status'=>1))->select();
        if ( $res )
            $this->ajaxReturn(array('status'=>2,'msg'=>"您已经预约过该团操"));
        $result = $this->get_action_info($action_id);
        $yuyuetime = $result['teachdate'] ." ".$result['starttime'];
        $vip=$this->get_vip_info();
        $shop=db('shop')->find($result['shop_id']);
        $data['yuyuetime'] = strtotime($yuyuetime);
        $data['openid'] = $_SESSION['openid'];
        $data['tel'] = $user_info['tel'];
        $data['type'] = $_POST['type'];
        $data['action_id'] = $action_id;
        $data['shop_id'] = $result['shop_id'];
        $data['shopname'] = $result['shopname'];
        $data['city_id'] = $result['city_id'];
        $data['teachername'] = $result['teacher'];
        $data['teacher_id'] = $result['teacher_id'];
        $data['doornum'] = set_six_password();
        $data['addtime'] = date("Y-m-d H:i:s");
        $data['point'] =  $vip['point_apo_shop'];
        $lastId = $appointment->save($data);
        if(!$lastId)
            $this->ajaxReturn(array('status'=>2,'msg'=>"预约失败"));

        // 计算当前用户预约成功后的密码生效时间  并发送开门密码到用户手机
        $time_result = $this->create_doornum_time($yuyuetime);
        $starttime = date("Y-m-d H:i",$time_result['starttime']);
        $endtime = date("Y-m-d H:i",$time_result['endtime']);

        $exctime=$starttime."--".$endtime;
        $door="*".$data['doornum']."#";
        $this->send_appo_message($door,$exctime);
        //微信给用户发送推送消息
        $this->send_weixin_message($result['actionname'], $data['doornum'], $starttime, $endtime);
        //把开门密码写入门锁表
//        if ( strtotime($starttime) < (time()-300) ){
//            $lockdata['addtime'] = strtotime("+4 minutes");
//        }else{
//            $lockdata['addtime'] = strtotime("+4 minutes",strtotime($starttime));
//        }
        $lockdata['addtime'] = strtotime($starttime);
        $lockdata['port'] = $shop['port'];
        $lockdata['apo_id'] = $lastId;
        $lockdata['info'] = $data['doornum'];
        $lockdata['removetime'] = strtotime($endtime);
        $lock_id = db('lock_suo')->save($lockdata);
        //把门锁表中的记录写入对应的预约记录表中
        $appointment->update(array('id'=>$lastId,'lockid'=>$lock_id));
        //预约增加积分
        edit_point($_SESSION['openid'],$vip['point_apo_action']);
        $this->ajaxReturn(array('status'=>1,'msg'=>"预约成功",'action_ap_id'=>$lastId));

    }

    //获取团操的详情
    private function get_action_info($id){
        $onelist = db('action')->find($id);

//        $onelist['photos'] = unserialize($onelist['photos']);
        $onelist['teachername'] =db('teacher')->where(array('teacher_id'=>$onelist['teacher_id']))->column('name');
        $onelist['teacherhead'] =db('teacher')->where(array('teacher_id'=>$onelist['teacher_id']))->column('picture');
        $onelist['shopname'] =db('shop')->where(array('id'=>$onelist['shop_id']))->column('title');

        $onelist['longtime']= floor(strtotime(date($onelist['teachdate']." ".$onelist['endtime'])) - strtotime(date($onelist['teachdate']." ".$onelist['starttime'])))/60;
        $onelist['fitpeople'] = unserialize($onelist['fitpeople']);
        $onelist['teacherabout'] = htmlspecialchars_decode($onelist['teacherabout']);
        $onelist['teacherabout'] =str_replace(array("<p>","</p>"),"", $onelist['teacherabout']);
//        $textContent=str_replace("</p>"," \n",$textContent);
        $onelist['actioninfo'] = htmlspecialchars_decode($onelist['actioninfo']);
        $onelist['notice'] = htmlspecialchars_decode($onelist['notice']);
        $onelist['step'] = htmlspecialchars_decode($onelist['step']);
        $onelist['imgs'] = db('imgs')->field('id,savepath')->where(array('guid'=>$onelist['guid']))->select();
        if(empty($onelist['address'])){
           $shop= db('shop')->where(array('id'=>$onelist['shop_id']))->find();
            $onelist['address']=$shop['province'].$shop['city'].$shop['area'].$shop['address'];
        }
        if ( in_array(1, $onelist['fitpeople']) ){
            $onelist['one_status'] = 1;
        }else{
            $onelist['one_status'] = 0;
        }

        if ( in_array(2, $onelist['fitpeople']) ){
            $onelist['two_status'] = 1;
        }else{
            $onelist['two_status'] = 0;
        }

        if ( in_array(3, $onelist['fitpeople']) ){
            $onelist['three_status'] = 1;
        }else{
            $onelist['three_status'] = 0;
        }

        if ( in_array(4, $onelist['fitpeople']) ){
            $onelist['four_status'] = 1;
        }else{
            $onelist['four_status'] = 0;
        }
        return $onelist;
    }

    //获取预约的详情---方法
    private function get_appointment_info($id){
        $result = array();
        $onelist = db('appointment')->find($id);

        $action = db('action')->find($onelist['action_id']);

        $action_starttime = date("Y-m-d",strtotime($action['teachdate'])) . " " .$action['starttime'];
        $unix_action_start = strtotime($action_starttime);//团操开始时间的时间戳

        /*  获取该用户所属VIP的信息  */
        $vip_info = $this->get_vip_info();
        $prev_unix = $onelist['yuyuetime']-$vip_info['prev_minute']*60;
        $next_unix =$onelist['yuyuetime']+ $vip_info['next_minute']*60;

        $result['id'] = $onelist['id'];
        $onelist['shopname'] =db('shop')->where(array('id'=>$onelist['shop_id']))->column('title');

        $result['actionname'] = $action['actionname'];
        $result['shopname'] = db('shop')->where(array('id'=>$onelist['shop_id']))->column('title');
        $result['status'] = $onelist['status'];
        $result['address'] = str_replace(",", "", $action['address']);
        $result['yuyuetime'] = $action_starttime;
        $result['yuyuemonth'] = date('Y-m-d',$unix_action_start);
        $result['yuyuehours'] = date("H:i",$unix_action_start);
        $result['yuyueendhours'] = $action['endtime'];
        $result['doornum'] = $onelist['doornum'];
        $result['accesshours'] = date( "H:i",$prev_unix );//锁生效时间
        $result['accessendhours'] = date( "H:i",$next_unix  );//锁过期时间
        $result['notice'] = htmlspecialchars_decode($action['notice']);
        $result['nocancel'] = date("Y-m-d H:i", strtotime("-6 hours", $unix_action_start ) );
        $result['actionname'] = $action['actionname'];
        $result['is_pingjia'] = $onelist['is_pingjia'];
        $result['action_id'] = $onelist['action_id'];


        return $result;
    }

    //获取团操的列表
    private function get_action_list($datetime){

       $shopname= db('shop')->column("id,title,lat,province,city,area,address");
       $teacherhead= db('teacher')->column("teacher_id,picture");

        $shop_list = db('action')->field('shop_id')->where(array('teachdate'=>$datetime))->group("shop_id")->select();
        foreach($shop_list as $k=>$v){
            $shop_list[$k]['shopname']=$shopname[$v['shop_id']]['title'];
            $shop_list[$k]['position']=$shopname[$v['shop_id']]['lat'];
            $shop_list[$k]['addr']=$shopname[$v['shop_id']]['province'].$shopname[$v['shop_id']]['city'].$shopname[$v['shop_id']]['area'].$shopname[$v['shop_id']]['address'];
            $action_list = db('action')->where(array('teachdate'=>$datetime,'shop_id'=>$v['shop_id']))->order('starttime asc')->select();
            foreach ( $action_list as $k1=>$v1 ){
                $yuyuenum = db('appointment')->where(array('action_id'=>$v1['id'],'status'=>1))->count();
                $is_end=0;//名额是否已满
                $yuyue_status=0;//名额是否紧张
                $yuyue_harf = floor($v1['limit_people'] / 2);
                if( $yuyuenum >= $v1['limit_people'] )
                    $is_end = 1;
                if($yuyuenum >= $yuyue_harf )
                    $temp_result[$k1]['yuyue_status'] = 1;

                $action_list[$k1]['is_end']=$is_end;
                $action_list[$k1]['yuyue_status']=$yuyue_status;
                $action_list[$k1]['teacherhead']=$teacherhead[$v1['teacher_id']];
            }
            $shop_list[$k]['actionlist']=$action_list;
        }
//        show_bug($shop_list);
        return $shop_list;
    }
    //获取当情的日期、星期，以及未来6天的日期星期
    private function week_info(){
        $result = array();
        for ($i=0; $i<7; $i++){
            $temp_arr = array();
            $shijian = strtotime( "+".$i."days",strtotime(date("Y-m-d")) );
            $temp_arr['datetime'] = $shijian;
            $temp_arr['date'] = date( "Y-m-d",  $shijian);
            $temp_arr['date1'] = date( "n.d",  $shijian);
            $temp_arr['month'] = date("m",$shijian);
            $temp_arr['day'] = date("d",$shijian);
            $temp_arr['week_num'] = date("w",$shijian);

            switch ($temp_arr['week_num']){
                case 0:
                    $temp_arr['week'] = "周日";
                    break;
                case 1:
                    $temp_arr['week'] = "周一";
                    break;
                case 2:
                    $temp_arr['week'] = "周二";
                    break;
                case 3:
                    $temp_arr['week'] = "周三";
                    break;
                case 4:
                    $temp_arr['week'] = "周四";
                    break;
                case 5:
                    $temp_arr['week'] = "周五";
                    break;
                case 6:
                    $temp_arr['week'] = "周六";
                    break;
            }

            $temp_arr['week_num_cn'] = substr( $temp_arr['week'], 3);;
            $result[] = $temp_arr;
        }
        return $result;
    }
}