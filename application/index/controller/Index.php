<?php
namespace app\index\controller;
use Home\Logic;
//use Think\Controller;
class Index extends Common {
    public function news(){
        $id=input('id');
        $key=input('key');
//        $key=1;
//        $id=59;
        $response= db('response')->find($id);
        if($response['restype']==2){
            $response['content']=unserialize($response['content']);
            $response= $response['content'][$key];
        }else{
            $response['content']=htmlspecialchars_decode($response['content']);
        }


//        show_bug( $response['content']);
//        exit;
        $this->assign("response",$response);
        $this->display();
    }
    public function index(){
//        show_bug($_SERVER);
//        exit;
//        layout('layout_no');
        return redirect("index_shop");
        return view('index');



    }

    //器械列表页
    public function index_shop(){
        //获取门店信息，并根据距离从近到远排序
        $list = $this->get_shop_list(1);
        $shoplist=array();
        foreach($list as $k=>$v){
                $shoplist['id']=$v['id'];
                $shoplist['focusUrl']=url('shop_detail',array('id'=>$v['id']));
                $shoplist['focusImg']=ROOT_PATH .'/Public/'.$v['first_img'];
                $shoplist['focusName']=$v['title'];
                $shoplist['focusAddr']=$v['city'].$v['area'].$v['address'];
                $shoplist['focusaddress']=$v['address'];
                $shoplist['favour']=0;
                $shoplist['status']=$v['status'];
                $shoplist['option']=$v['lat'];
            if($k==0){
                $shoplist['mapurl']=url('map',array('id'=>$v['id'],'table'=>'shop'));
                $shop=$shoplist;
            }else{
                $more[]=$shoplist;
            }
        }

        $notice=db('system')->find();



        if(isset($notice) && time()>strtotime($notice['starttime']) && time()<strtotime($notice['endtime']) &&$this->user['first_login']==1){
            $notice['is_show']=1;
            $notice['content']=htmlspecialchars_decode($notice['content']);
        }else{
            $notice['is_show']=0;
            $notice['content']='';
        }

        if(input('ajax'))
            $this->view->engine->layout(false);


        return view('index_shop',['notice'=>$notice,'shop'=>$shop,'morelist'=>$more,'kind'=>1]);

//        $this->display();
    }
    //异步获取活动页数据
    public function ajax_shop(){
        layout(false);
        $page=input('page');
        $list = $this->get_shop_list($page);
        $this->assign('morelist',$list);
        $this->display();
    }
    //地图页面
    public function map(){
        layout('layout_no');
        $id=input('id');
        $shop=db('shop')->find($id);
        $address_local_arr = split(',', $shop['lat']);
        $address=$shop['province'].$shop['city'].$shop['area'].$shop['address'];
        $this->assign('lng',$address_local_arr[0]);
        $this->assign('lat',$address_local_arr[1]);
        $this->assign('address',$address);
        $this->display();
    }

    //器械详情
    public function shop_detail(){

        $id=input('get.id');
        $shop=$this->get_shop_info($id);
//        show_bug($shop);
        $this->assign('favour',1);
        $this->assign('shop',$shop);
        $this->assign('imgs',$shop['imgs']);
        if(input('ajax')==1)
            layout(false);
        $this->display();
    }
    //预约页面
    public function appointment(){

        $ytime=explode(" ",$_GET['time']);
        $starttime=$ytime[0].' '.$ytime[1].':'.$ytime[2];

        $endtime = date("H:i",strtotime("+1Hours",strtotime($starttime)));

        $position=$this->set_option($starttime,$_GET['shopid']);

        $this->assign("starttime",$starttime);
        $this->assign("endtime",$endtime);
        $this->assign("shop_info",$this->get_shop_info($_GET['shopid']));
        $this->assign("user_info",$this->get_user_info());
        $this->assign("shopid",$_GET['shopid']);


        $this->assign("position",$position['position']);
        $this->assign("limit",$position['limit']);


        $this->assign("kind",1);
        if(input('ajax')==1)
            layout(false);
        $this->display();

    }
    public function get_position($num){
        switch($num){
            case 4:
                $a=1;
                break;
            case 5:
                $a=2;
                break;
            case 6:
                $a=3;
                break;
            default:
                $a=$num;
        }
        return $a;

    }
    public function set_option($yuyuetime,$shopid){
        $position=1;
        $shop = $this->get_shop_info($shopid);
        $yuyuetime_unix=strtotime($yuyuetime);
        $prev_app=db('appointment')->where(array('openid'=>$_SESSION['openid'],'type'=>1,'status'=>1))->order('id desc')->find();//上一次预约
        //只需要判断小于两周内的情况即可,其他情况都在默认范围内
        if( $prev_app  &&  in_array($prev_app['position'],array(1,2))){
            $position=$this->get_position($prev_app['position']+1);

        }
        $p_count1=db('appointment')->where(array('yuyuetime'=>array('between',array(($yuyuetime_unix-1800),$yuyuetime_unix)),'status'=>1,'type'=>1,'position'=>1))->count();
        $p_count2=db('appointment')->where(array('yuyuetime'=>array('between',array(($yuyuetime_unix-1800),$yuyuetime_unix)),'status'=>1,'type'=>1,'position'=>2))->count();
        $p_count3=db('appointment')->where(array('yuyuetime'=>array('between',array(($yuyuetime_unix-1800),$yuyuetime_unix)),'status'=>1,'type'=>1,'position'=>3))->count();

        $count_arr=array(1=>$shop['limit_xiong']-$p_count1,2=>$shop['limit_bei']-$p_count2,3=>$shop['limit_tui']-$p_count3);//空位人数
        $position2= $this->get_position($position+1);

        if($count_arr[$position]<=0 && $count_arr[$position2]<=0){

        }elseif($count_arr[$position]<=0 && $count_arr[$position2]>0){
            $position=$position2;
        }

        $limit[0]= ($p_count1>=$shop['limit_xiong'])?1:0;
        $limit[1]= ($p_count2>=$shop['limit_bei'])?1:0;
        $limit[2]= ($p_count3>=$shop['limit_tui'])?1:0;

        return array("position"=>$position,'limit'=>$limit);
    }
    //预约成功页面
    public function appointment_success(){
        $id = $_GET['ap_id'];
        $this->assign("onelist",$this->get_appointment_info($id));
        $this->assign("kind",1);
        if(input('ajax')==1)
            layout(false);
        $this->display();

    }
    //器械预约的取消页
    public function appointment_cancel(){
        $this->assign("kind",1);
        $id = $_GET['id'];
        $onelist=$this->get_appointment_info($id);
        $this->assign("onelist",$onelist);
        if(input('ajax')==1)
            layout(false);
        $this->display();
    }

    //预约评价页面
    public function appointment_evaluate(){
        $apid=input('get.id');
        $appointment=$this->get_appointment_info($apid);
        $teacher_list=db('teacher')->where(array('shop_id'=>$appointment['shop_id']))->select();
        $this->assign("teacher_list",$teacher_list);
        $this->assign("appointment",$appointment);
        $this->assign("kind",1);
        $this->assign("apid",$apid);
        if(input('ajax')==1)
            layout(false);
        $this->display();

    }
//预约评价查看页面
    public function appointment_evaluated(){
        $apid=input('get.id');
        $appointment=$this->get_appointment_info($apid);
        $evalist=db('evaluation')->where(array('apid'=>$apid))->find();
        $tids=explode(',',$evalist['teacher_id']);
        $tname=explode(',',$evalist['teacher_name']);
        $teacher=array();
        foreach($tids as $k=>$v){
            $teacher[$k]['id']=$v;
            $teacher[$k]['teacher_headimg']=db('teacher')->where(array('teacher_id'=>$v))->column('picture');
            $teacher[$k]['teacher_name']=$tname[$k];
        }
        $this->assign("teacher",$teacher);
        $this->assign("evalist",$evalist);
        $this->assign("appointment",$appointment);
        $this->assign("kind",1);
        if(input('ajax')==1)
            layout(false);
        $this->display();

    }
    //预约评价成功页面
    public function appo_evaluate_success(){
        $vipcard=$this->get_vip_info();
        $this->assign("getpoint", $vipcard['point_com_action']);
        $this->assign("kind",1);
        if(input('ajax')==1)
            layout(false);
        $this->display();

    }
    //预约详情页面
    public function appointment_detail(){
        $id = input('get.id');
        $appo=$this->get_appointment_info($id);
        $this->assign("kind",2);
        $this->assign("onelist",$appo);
        if(input('ajax'))
            layout(false);
        $this->display();
    }
    //取消预约的方法
    public function cancel_appointment(){
        //判断是否可以取消预约
        $id=input('post.id');
        if(!$id)
            $this->ajaxReturn(array('status'=>2,'msg'=>"不存在该预约"));
        $appointment=db('appointment')->find($id);
        $nowtime=time();
        $vip=$this->get_vip_info();
        //预约增加积分

       if($appointment['type']==1){
           $point=$vip['point_apo_shop'];
           $ttime=7200;//提前多少秒
           $msg="请于预约时间提前至少2小时取消";
       }elseif($appointment['type']==2){
           $point=$vip['point_apo_action'];
           $ttime=3600*6;
           $msg="请于预约时间提前至少6小时取消";
       }
        if( ($appointment['yuyuetime']-$nowtime) < $ttime )
            $this->ajaxReturn(array('status'=>2,'msg'=>$msg));

        $res=db('appointment')->save(array('id'=>$id,'status'=>2));
        if ( !$res)
            $this->ajaxReturn(array('status'=>2,'msg'=> "取消预约失败"));

        //把密码表中对应的门锁信息变成status=12取消状态 0有效 1无效
        db('lock_suo')->save(array('id'=>$appointment['lockid'],'status'=>2));
        edit_point($_SESSION['openid'],$point,2);
        $this->ajaxReturn(array('status'=>1,'msg'=> "取消预约成功"));

    }


    //我的预约统计
    public function appo_count(){
        $vipstatus=$this->vip_status();
        $shop=array();
        $action=array();
        $activ=array();
        $appointment=db('appointment');
        $shop['count']=$appointment->where(array('openid'=>$this->openid,'type'=>1,'status'=>3))->count();
//        $shop['count']=0;
        $shop['length']=$appointment->where(array('openid'=>$this->openid,'type'=>1,'status'=>3))->sum('length');
        $shop['totalpoint']=$appointment->where(array('openid'=>$this->openid,'type'=>1,'status'=>3))->sum('point');
        $shop_yuyuedate=$appointment->field("DATE_FORMAT(FROM_UNIXTIME(yuyuetime),'%Y-%m-%d') yuyuedate")->where(array('openid'=>$this->openid,'type'=>1,'status'=>3))->group("yuyuedate")->select(false);
        $shop['totaldays']=count($shop_yuyuedate);

        $action['count']=$appointment->where(array('openid'=>$this->openid,'type'=>2,'status'=>3))->count();
//        $action['count']=0;
        $action['length']=$appointment->where(array('openid'=>$this->openid,'type'=>2,'status'=>3))->sum('length');
        $action['totalpoint']=$appointment->where(array('openid'=>$this->openid,'type'=>2,'status'=>3))->sum('point');
        $action_yuyuedate=$appointment->field("DATE_FORMAT(FROM_UNIXTIME(yuyuetime),'%Y-%m-%d') yuyuedate")->where(array('openid'=>$this->openid,'type'=>2,'status'=>3))->group("yuyuedate")->select();
        $action['totaldays']=count($action_yuyuedate);

        $activ['count']=$appointment->where(array('openid'=>$this->openid,'type'=>3,'status'=>3))->count();
//        $activ['count']=0;
        $activ['length']=$appointment->where(array('openid'=>$this->openid,'type'=>3,'status'=>3))->sum('length');
        $activ['totalpoint']=$appointment->where(array('openid'=>$this->openid,'type'=>3,'status'=>3))->sum('point');
        $activ_yuyuedate=$appointment->field("DATE_FORMAT(FROM_UNIXTIME(yuyuetime),'%Y-%m-%d') yuyuedate")->where(array('openid'=>$this->openid,'type'=>3,'status'=>3))->group("yuyuedate")->select();
        $activ['totaldays']=count($activ_yuyuedate);

        $this->assign("shop",$shop);
        $this->assign("action",$action);
        $this->assign("activ",$activ);
        $this->assign("kind",1);
        if(input('ajax')==1)
            layout(false);
        if($vipstatus['status']==1){
            $this->display();
        }else{
            $this->display('my_apponull');
        }

    }

//保存用户的器械预约信息--方法
    public function save_shop_appointment(){

        if(!$_POST['shopid'])
            $this->ajaxReturn(array('status'=>2,'msg'=>"不存在该器械"));
        $userinfo = $this->get_user_info();//获取用户信息
        $shop = $this->get_shop_info($_POST['shopid']);

        $yuyuetime_unix=strtotime($_POST['yuyuetime']);

        $position=$_POST['position'];
        $vip=$this->get_vip_info();
        $data=array(
            'openid'=>$_SESSION['openid'],
            'type'=>$_POST['type'],
            'shop_id'=> $_POST['shopid'],
            'yuyuetime'=>$yuyuetime_unix,
            'addtime'=>date("Y-m-d H:i:s"),
            'tel'=>$userinfo['tel'],
            'doornum'=>set_six_password(),
            'shopname'=> $shop['title'],
            'position'=> $position,
            'city_id'=> $shop['city_id'],
            'point'=> $vip['point_apo_shop'],
        );

        $lastId = db('appointment')->save($data);
        if(!$lastId)
            $this->ajaxReturn(array('status'=>2,'msg'=>"预约失败"));
        //发送开门密码到用户手机
        $time_result = $this->create_doornum_time($_POST['yuyuetime']);

        $starttime = date("Y-m-d H:i",$time_result['starttime']);
        $endtime = date("Y-m-d H:i",$time_result['endtime']);
        $exctime=$starttime."--".$endtime;
        $door="*".$data['doornum']."#";
        $this->send_appo_message($door,$exctime);
        //微信上发送推送消息给用户
        $this->send_weixin_message('器械健身', $data['doornum'], $starttime, $endtime);
        //把开门密码写入门锁表
        if ( strtotime($starttime) < (time()-300) ){
            $lockdata['addtime'] = strtotime("+4 minutes");
        }else{
            $lockdata['addtime'] = strtotime("+4 minutes",strtotime($starttime));
        }
        $lockdata['addtime'] = strtotime($starttime);
        $lockdata['port'] = $shop['port'];
        $lockdata['apo_id'] = $lastId;
        $lockdata['info'] = $data['doornum'];
        $lockdata['removetime'] = strtotime($endtime);

        $lock_id = db('lock_suo')->save($lockdata);

        //把门锁表中的记录写入对应的预约记录表中
        db('appointment')->save(array('id'=>$lastId,'lockid'=>$lock_id));
        //预约非绑定门店 通用次数减1 预约之前判断过 无需再判断
        if($userinfo['shop_id']!=$_POST['shopid']){
            db('user')->save(array('id'=>$userinfo['id'],'ty_num'=>array('exp','ty_num-1')));
        }
        //预约增加积分
        edit_point($_SESSION['openid'],$vip['point_apo_shop']);
        $this->ajaxReturn(array('status'=>1,'msg'=>"预约成功",'ap_id'=>$lastId));

    }

    //保存评价信息-----方法
    public function save_evaluation(){

        if(!$_POST['apid'])
            $this->ajaxReturn(array('status'=>2,'msg'=>"不存在该预约"));
        $user_result = $this->get_user_info();
//        if ($user_result['status'] == 0)
//            $this->ajaxReturn(array('status'=>2,'msg'=>"会员卡被冻结，评价失败"));

        $appo=db('appointment')->find($_POST['apid']);
        if($appo['is_pingjia']==1)
            $this->ajaxReturn(array('status'=>2,'msg'=>"该预约已评价过"));

        $vipcard=db('vipcard')->find($user_result['card_id']);
        $data['apid'] = $_POST['apid'];
        $data['uid'] = $user_result['id'];
        $data['type'] = $_POST['type'];
        $data['content'] = $_POST['content'];
        $data['addtime'] = time();
        $points=get_arr_column( $_POST['sorts_arr'],'point');
        $teacher_ids=get_arr_column( $_POST['sorts_arr'],'tid');
        $teacher_names=get_arr_column( $_POST['sorts_arr'],'tname');
        $data['teacher_id'] =  implode(",",$teacher_ids);
        $data['point'] =  implode(",",$points);
        $data['teacher_name'] = implode(",",$teacher_names);
        $res=db('evaluation')->save($data);
        if(!$res)
            $this->ajaxReturn(array('status'=>2,'msg'=>"评价失败"));


        foreach ( $_POST['sorts_arr'] as $k=>$v ){
                //把本次评分写入到教练的评分记录中去
                $data1['teacher_id'] = $v['tid'];
                $data1['point'] =array('exp','point+'.$v['point']);
                $data1['evatime'] =array('exp','evatime+1');
                db('teacher')->save($data1);
        }


        //改变当前预约的评价状态（变成已评价状态   is_pingjia =>  1）
        db('appointment')->save(array('id'=>$_POST['apid'],'is_pingjia'=>1));
        // 给用户加积分
        edit_point($_SESSION['openid'],$vipcard['point_com_shop'],1);
        $this->ajaxReturn(array('status'=>1,'msg'=>"评价成功"));

    }
    //获取预约的详情---方法
    private function get_appointment_info($id){
        $result = array();
        $body_po=C('BODY_POSITION');
        $onelist = db('appointment')->find($id);

        $condition['openid'] = $_SESSION['openid'];
        $shop_onelist =$this->get_shop_info($onelist['shop_id']);

        /*  获取该用户所属VIP的信息  */
        $vip_info = $this->get_vip_info();
        $prev_unix = $onelist['yuyuetime']-$vip_info['prev_minute']*60;
        $next_unix =$onelist['yuyuetime']+ $vip_info['next_minute']*60;

        $result['id'] = $onelist['id'];
        $result['shop_id'] = $onelist['shop_id'];
        $result['city_id'] = $onelist['city_id'];
        $result['status'] = $onelist['status'];
        $result['shopname'] = $shop_onelist['title'];
        $result['addr'] = $shop_onelist['addr'];
        $result['address'] = $shop_onelist['address'];
        $result['yuyuetime'] = date("Y-m-d H:i",$onelist['yuyuetime']);
        $result['yuyuemonth'] = date('Y-m-d',$onelist['yuyuetime']);
        $result['yuyuehours'] = date("H:i",$onelist['yuyuetime']);
        $result['yuyueendhours'] = date("H:i",strtotime("+1hours",$onelist['yuyuetime']));
        $result['doornum'] = $onelist['doornum'];
        $result['accesshours'] = date( "H:i",$prev_unix );//锁生效时间
        $result['accessendhours'] = date( "H:i",$next_unix  );//锁过期时间
        $result['notice'] = htmlspecialchars_decode($shop_onelist['notice']);
        $result['step'] = htmlspecialchars_decode($shop_onelist['step']);
        $result['teacherabout'] = htmlspecialchars_decode($shop_onelist['teacherabout']);
        $result['actioninfo'] = htmlspecialchars_decode($shop_onelist['actioninfo']);
        $result['nocancel'] = date("Y-m-d H:i", strtotime("-120 minutes", strtotime($onelist['yuyuetime']) ) );
        $result['position'] = $body_po[$onelist['position']];//只能提示锻炼部位

        return $result;
    }

//获取门店的详情--方法
    private function get_shop_info($id){
        $onelist = db('shop')->find($id);
        $onelist['placeinfo'] = htmlspecialchars_decode($onelist['placeinfo']);
        $onelist['notice'] = htmlspecialchars_decode($onelist['notice']);
        $onelist['step'] = htmlspecialchars_decode($onelist['step']);
//        $onelist['photos'] = unserialize($onelist['photos']);
        $onelist['addr'] = $onelist['province'].$onelist['city'].$onelist['area'].$onelist['address'];
        $onelist['imgs'] = db('imgs')->field('id,savepath')->where(array('guid'=>$onelist['guid']))->select();

        return $onelist;
    }
    //获取器械门店的列表--方法
    //腾讯地图 纬度在前  百度经度在前
    private function get_shop_list($page){
        //查询正常营业的门店
        $temp_result_ok = db('shop')->where(array('status'=>1))->select();
        //查询尚未营业的门店
        $temp_result_error = db('shop')->where(array('status'=>0))->select();
        $temp_result = array();
        if ( ($_SESSION['lat'] != -1) && ($_SESSION['lng'] != -1) && ($_SESSION['lat'] != '') && ($_SESSION['lng'] != '') ){
            //计算开始营业门店地址与客户的距离
            foreach ( $temp_result_ok as $n=>$v ){
                if ( $v['lat'] ){
                    $temp_arr = explode(",", $v['lat']);
                    $temp_lat = $temp_arr[0];
                    $temp_lng = $temp_arr[1];
                    $temp_result_ok[$n]['distance'] = $this->get_distance($temp_lat, $temp_lng);
                }else{
                    $temp_result_ok[$n]['distance'] = 999999999999999;
                }
            }
            //对距离进行冒泡排序(从近到远)
            $temp_result_ok= quickSort($temp_result_ok);
            //计算尚未营业门店地址与客户的距离
            foreach ( $temp_result_error as $n=>$v ){
                if ( $v['lat'] ){
                    $temp_arr = explode(",", $v['lat']);
                    $temp_lat = $temp_arr[0];
                    $temp_lng= $temp_arr[1];
                    $temp_result_error[$n]['distance'] = $this->get_distance($temp_lat, $temp_lng);
                }else{
                    $temp_result_error[$n]['distance'] = 999999999999999;
                }
            }
            //对距离进行冒泡排序(从近到远)
            $temp_result_error= quickSort($temp_result_error);
            $temp_result=array_merge($temp_result_ok,$temp_result_error);
        }else{
            $temp_result=array_merge($temp_result_ok,$temp_result_error);
        }
//        dump($temp_result);
//        exit();
        foreach ( $temp_result as $n1=>$v1 ){
            $temp_result[$n1]['placeinfo'] = htmlspecialchars_decode($v1['placeinfo']);
            $temp_result[$n1]['notice'] = htmlspecialchars_decode($v1['notice']);
            $temp_result[$n1]['step'] = htmlspecialchars_decode($v1['step']);
//            $temp_result[$n1]['photos'] = unserialize($v1['photos']);
            $temp_result[$n1]['address'] = str_replace(',', '', $v1['address']);
        }
        $star=8*$page-8;
        $arr = array_slice($temp_result, $star,8);

        return $arr;
    }


}