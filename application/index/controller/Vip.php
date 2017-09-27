<?php
namespace app\index\controller;
use Home\Logic;
//use Think\Controller;
class Vip extends Common {

    public function index(){
        $this->assign("kind", 5);
        $user=$this->user;

        $this->card_info($user['card_id']);
        $vip_status = $this->vip_status();
        //到期后时间判断
        $ex_days=0;
        if($vip_status['status'] == -2){
            $ex_days=ceil((time()-$user['endtime'])/86400);

        }
        $this->assign("ex_days",$ex_days);
        $autothaw_num=db('autothaw_log')->where(array('uid'=>$this->user_id))->count();
        $autothaw=db('autothaw_log')->where(array('uid'=>$this->user_id))->order('id desc')->find();
        $userdata['autobrozen_num']=2-$autothaw_num;
        //0位停卡 1自助停卡中 2后台停卡

        $userdata['is_autobrozen']=0;
        $userdata['autobrozen_show']=0;
        if( in_array($user['card_id'],array(3,4,5))){
            $userdata['autobrozen_show']=1;
        }
        if($vip_status['status'] ==-3 && (isset($autothaw) && $autothaw['endtime']>time())  ){
            $userdata['is_autobrozen']=1;
        }elseif(($vip_status['status'] ==-3 && !isset($autothaw))){
            $userdata['is_autobrozen']=2;
        }

        $this->assign("userdata",$userdata);
        $vipmiao=$this->miaosha_info();
        $vipname=array('半年卡','单年卡','两年卡');
        $student=db('vipcard')->where(array('type'=>1,'is_show'=>1))->find();
        $titan=db('vipcard')->where(array('type'=>4,'is_show'=>1))->find();
        $vip=db('vipcard')->where(array('type'=>2,'is_show'=>1))->select();
        foreach($vip as $k=>$v){
            $vip[$k]['title']=$vipname[$k];
        }
        $vippro=db('vipcard')->where(array('type'=>3,'is_show'=>1))->select();

        $this->assign("miao",$vipmiao);
        $this->assign("student",$student);
        $this->assign("titan",$titan);
        $this->assign("vip",$vip);
        $this->assign("vippro",$vippro);

        if(input('ajax'))
            $this->view->engine->layout(false);
        //不是会员 跳到会员购买页
        if ($vip_status['status'] == 1 || $vip_status['status'] == -1 || $vip_status['status'] == -3 || $vip_status['status'] == -2) {

            return view('my');
        } else {
            return view('my_card');
        }

    }
    public function card_info($card_id){
        $card_img=array(0=>'my_card01.jpg',1=>'card_03.png',2=>'card_04.png',3=>'card_02.png',4=>'card_01.png',5=>'card_05.png',9=>'card_03.png');
        $vip_name=array(1=>'秒杀月卡',2=>'学生卡',3=>'尊享VIP·半年卡',4=>'尊享VIP·年卡',5=>'尊享VIP·两年卡',9=>'体验卡');
        $color=array(1=>'#df5627',2=>'#f4d420',3=>'#586096',4=>'#f4d420',5=>'#f4d420',9=>'#df5627');
        $vipp['vipname']=$vip_name[$card_id];
        $vipp['color']=$color[$card_id];
        $this->assign("vipcard",$vipp);
    }
    public function my_card(){

        $user=$this->user;
        $this->card_info($user['card_id']);
        $this->assign("kind", 5);
        $vipmiao=$this->miaosha_info();
        $vipname=array('半年卡','单年卡','两年卡');
        $student=db('vipcard')->where(array('type'=>1,'is_show'=>1))->find();
        $vip=db('vipcard')->where(array('type'=>2,'is_show'=>1))->select();
        foreach($vip as $k=>$v){
            $vip[$k]['title']=$vipname[$k];
        }
        $vippro=db('vipcard')->where(array('type'=>3,'is_show'=>1))->select();
        $titan=db('vipcard')->where(array('type'=>4,'is_show'=>1))->find();
        $this->assign("titan",$titan);
        $this->assign("miao",$vipmiao);
        $this->assign("student",$student);
        $this->assign("vip",$vip);
        $this->assign("vippro",$vippro);
        if(input('ajax'))
            $this->view->engine->layout(false);
        return view();
    }
    //自助停卡
    public function autobrozen(){
        $user=$this->user;
        $data['brozen_time']=$user['endtime']-time();
        if(in_array($user['card_id'],array(3,6))){
            $data['auto_thaw']=time()+10*86400;
        }elseif(in_array($user['card_id'],array(4,7))){
            $data['auto_thaw']=time()+20*86400;
        }elseif(in_array($user['card_id'],array(5,8))){
            $data['auto_thaw']=time()+30*86400;
        }
        $data['brozen_time']=$user['endtime']-time();
        $data['status']=2;
        db('user')->where(array('id'=>$user['id']))->save($data);
        db('autothaw_log')->save(array('uid'=>$user['id'],'card_id'=>$user['card_id'],'addtime'=>time(),'endtime'=> $data['auto_thaw']));
        $this->ajaxReturn(1);
    }
     //秒杀信息
    public function miaosha_info(){
        $vipmiao=db('vipcard')->where(array('type'=>0,'is_show'=>1))->find();
        $miaolist= db('miaolist')->where(array('status'=>1))->find();
        if(!$miaolist){
            $vipmiao['status']=0;//没有秒杀活动
        }else{
            $vipmiao['status']=1;//有秒杀活动
        }
        if(time()>$miaolist['starttime'] &&time()<$miaolist['endtime'] ){
            $vipmiao['is_start']=1;//有
        }elseif(time()<$miaolist['starttime'] ){
            $vipmiao['is_start']=2;//未开始
        }elseif(time()>$miaolist['endtime'] ){
            $vipmiao['is_start']=3;//结束
        }
        $vipmiao['money']=$miaolist['money'];
        $vipmiao['miaoid']=$miaolist['id'];
        return $vipmiao;
    }
    // 我的健身计划详情页
    public function my_plan(){
        $plan_result = $this->check_plan_status();
        //是否有健身计划 1 有 2没有3不是会员
        $has_plan=$plan_result['planstatus'];
//        show_bug($this->user_id);
//        exit;
        if($has_plan==1){
            $result = $this->get_plan_detail($plan_result['planid']);
            $plan_list=db('plan_list')->where(array('uid'=>$this->user_id))->select();
            foreach($plan_list as $k=>$v){
                $plan_list[$k]['addtime']=strtotime($v['addtime']);
                $plan_list[$k]['one_pre'] = round(($v['qztz'] / 120), 2) * 100;
                $plan_list[$k]['two_pre'] = round(($v['ggj'] / 100), 2) * 100;
                $plan_list[$k]['three_pre'] = round(($v['tzf'] / 100), 2) * 100;
                $plan_list[$k]['four_pre'] = round(($v['tzbfb'] / 60), 2) * 100;
                $plan_list[$k]['five_pre'] = round(($v['jcdx'] / 4000), 2) * 100;
            }
            $this->assign("onelist", $result);
            $this->assign("plan_list", $plan_list);
        }
        $this->assign("kind", 5);
        $this->assign("has_plan", $has_plan);
        if(input('ajax'))
            $this->view->engine->layout(false);
        return view();
    }
    //更换手机号
    public function tel_change(){

        if(input('ajax'))
            $this->view->engine->layout(false);
        return view();
    }
    public function my_rank(){
        if(input('ajax'))
            $this->view->engine->layout(false);
        return view();
    }
    //修改手机号码的方法
    public function change_tel(){
        if (! isset($_SESSION['message_check_num']) || ($_POST['ver_code'] != $_SESSION['message_check_num']))
            $this->ajaxReturn(array('status'=>2,'msg'=>'验证码填写错误'));

        $res=  db('user')->save(array('id'=>$this->user_id,'tel'=>$_POST['phone'])) ;
        if (!$res )
            $this->ajaxReturn(array('status'=>2,'msg'=>'手机号码修改失败'));
        $this->ajaxReturn(array('status'=>1,'msg'=>'手机号码修改成功'));

    }
    //我的预约
    public function my_appointment(){
        $n=array('器械','团操','活动');
        $this->assign('kind',5);
        $appo_list1=db('appointment')->where(array('openid'=>$_SESSION['openid'],'type'=>1))->count();
        $appo_list2=db('appointment')->where(array('openid'=>$_SESSION['openid'],'type'=>2))->count();
        $appo_list3=db('appointment')->where(array('openid'=>$_SESSION['openid'],'type'=>3))->count();
        $appo_list[0]['num']=$appo_list1;
        $appo_list[1]['num']=$appo_list2;
        $appo_list[2]['num']=$appo_list3;
        foreach($appo_list as $k=>$v){
            $appo_list[$k]['act']=$n[$k];
        }

        $totallist[0]['shoplist']=$this->get_myappo(1);
        $totallist[1]['shoplist']=$this->get_myappo(2);
        $totallist[2]['shoplist']=$this->get_myappo(3);
        $this->assign("totallist",$totallist);
        $this->assign("appo_list",$appo_list);
        if(input('ajax')==1)
            $this->view->engine->layout(false);
        return view();
    }
    //异步获取活动页数据
    public function get_myappo($appotype){
        $vipcard=db('vipcard')->find($this->user['card_id']);
        $map['openid']=$_SESSION['openid'];
        $map['type']=$appotype;
        $openid=$_SESSION['openid'];
        if($appotype==3){
            $shoplist=array(array('type'=>2,'typename'=>'线下'),array('type'=>1,'typename'=>'线上'));

            foreach($shoplist as $k=>$v) {
                $sql0="select count(*) as count from appointment a LEFT JOIN activ ac ON a.activ_id = ac.id WHERE a.type = 3 and a.openid = '$openid' and ac.activtype = $v[type] order by a.addtime desc ";
                $count=db()->query($sql0);
                $shoplist[$k]['count']=$count[0]['count'];

                $sql="select a.* from appointment a LEFT JOIN activ ac ON a.activ_id = ac.id WHERE a.type = 3 and a.openid = '$openid' and ac.activtype = $v[type] order by a.addtime desc ";

                $appolist=db()->query($sql);

                foreach ($appolist as $k1 => $v1) {
                    $appolist[$k1]['pjpoint'] = $vipcard['point_com_activ'];
                    $appolist[$k1]['name'] = db('activ')->where(array('id' => $v1['activ_id']))->column('activname');
                    $appolist[$k1]['appo_detail_url'] = url('Activ/detail', array('id' => $v1['activ_id'],'act'=>'prize'));
                    $appolist[$k1]['appo_eva_url'] = url('Activ/appointment_evaluate', array('id' => $v1['id']));//评价url
                    $appolist[$k1]['appo_evad_url'] = url('Activ/appointment_evaluated', array('id' => $v1['id']));//评价完成url
                    $appolist[$k1]['appo_cal_url'] = url('Activ/appointment_cancel', array('id' => $v1['id']));//预约取消
                }
                $shoplist[$k]['appolist']=$appolist;


            }
//            show_bug($shoplist);
        }else{
            $shoplist=db('appointment')->field('shop_id,id')->where($map)->group("shop_id")->order("status asc")->select();
            if(empty($shoplist)){
                return '';
            }
            $shop_ids=get_arr_column($shoplist,'shop_id');
            $shopnames= db('shop')->where("id in (".implode(',',$shop_ids).")")->column('id,title,lat,province,city,area,address');
            foreach($shoplist as $k=>$v){
                $shoplist[$k]['shopname']=$shopnames[$v['shop_id']]['title'];
                $shoplist[$k]['mapurl']=url('index/map',array('id'=>$v['shop_id']));
                $shoplist[$k]['lat']=$shopnames[$v['shop_id']]['lat'];
                $shoplist[$k]['addr']=$shopnames[$v['shop_id']]['province'].$shopnames[$v['shop_id']]['city'].$shopnames[$v['shop_id']]['area'].$shopnames[$v['shop_id']]['address'];
                $map['shop_id']=$v['shop_id'];
                $shoplist[$k]['count']=db('appointment')->where($map)->count();//次数
                $appolist=db('appointment')->where($map)->order('addtime desc')->select();
                foreach($appolist as $k1=>$v1){
                    if($v1['lockid']){
                        $vip_info = $this->get_vip_info();
                        $prev_unix = $v1['yuyuetime']-$vip_info['prev_minute']*60;
                        $next_unix =$v1['yuyuetime']+ $vip_info['next_minute']*60;
                        if($prev_unix<time()&& time()<$next_unix){
                            //状态4为进行中
                            $appolist[$k1]['status']=4;
                        }

                    }
                    if($appotype ==1){
                        $appolist[$k1]['pjpoint']=  $vipcard['point_com_shop'];
                        $appolist[$k1]['name']='自助健身' ;
                        $appolist[$k1]['appo_detail_url']=url('Index/appointment_detail',array('id'=>$v1['id'])) ;//预约详情
                        $appolist[$k1]['appo_eva_url']=url('Index/appointment_evaluate',array('id'=>$v1['id'])) ;//评价url
                        $appolist[$k1]['appo_evad_url']=url('Index/appointment_evaluated',array('id'=>$v1['id'])) ;//评价完成url
                        $appolist[$k1]['appo_cal_url']=url('Index/appointment_cancel',array('id'=>$v1['id'])) ;//预约取消

                    }elseif($appotype ==2){
                        $appolist[$k1]['pjpoint']=  $vipcard['point_com_action'];
                        $appolist[$k1]['name']=db('action')->where(array('id'=>$v1['action_id']))->column('actionname') ;
                        $appolist[$k1]['appo_detail_url']=url('Action/appointment_detail',array('id'=>$v1['id'])) ;
                        $appolist[$k1]['appo_eva_url']=url('Action/appointment_evaluate',array('id'=>$v1['id'])) ;//评价url
                        $appolist[$k1]['appo_evad_url']=url('Action/appointment_evaluated',array('id'=>$v1['id'])) ;//评价完成url
                        $appolist[$k1]['appo_cal_url']=url('Action/appointment_cancel',array('id'=>$v1['id'])) ;//预约取消

                    }
                }
                $shoplist[$k]['appolist']=$appolist;
            }
        }
        return $shoplist;
    }
    //异步获取预约数据
    public function ajax_appo(){
        $this->view->engine->layout(false);
        $vipcard=db('vipcard')->find($this->user['card_id']);
//        $page=input('page');

        $map=array();
        $map['openid']=$_SESSION['openid'];
        $appotype=input('appotype');
        if($appotype>0){
            $map['type']=$appotype;
        }
//        $limitrow=3;
//        $starrow=($page-1)*$limitrow;
        $shoplist=db('appointment')->field('shop_id,id')->where($map)->group("shop_id")->order("status asc")->select();

        if(empty($shoplist)){

            $this->assign('shoplist',$shoplist);
            return view();
            exit;
        }

        $shop_ids=get_arr_column($shoplist,'shop_id');
//        show_bug($shoplist);exit;
        $shopnames= db('shop')->where("id in (".implode(',',$shop_ids).")")->column('id,title,lat');

        foreach($shoplist as $k=>$v){
            $shoplist[$k]['shopname']=$shopnames[$v['shop_id']]['title'];
            $shoplist[$k]['mapurl']=url('index/map',array('id'=>$v['shop_id']));
            $map['shop_id']=$v['shop_id'];
            $shoplist[$k]['count']=db('appointment')->where($map)->count();//次数
            $appolist=db('appointment')->where($map)->order('addtime desc')->select();
           foreach($appolist as $k1=>$v1){
               if($appotype ==1){
                   $appolist[$k1]['pjpoint']=  $vipcard['point_com_shop'];
                   $appolist[$k1]['name']='自助健身' ;
                   $appolist[$k1]['appo_detail_url']=url('Index/appointment_detail',array('id'=>$v1['id'])) ;//预约详情
                   $appolist[$k1]['appo_eva_url']=url('Index/appointment_evaluate',array('id'=>$v1['id'])) ;//评价url
                   $appolist[$k1]['appo_evad_url']=url('Index/appointment_evaluated',array('id'=>$v1['id'])) ;//评价完成url
                   $appolist[$k1]['appo_cal_url']=url('Index/appointment_cancel',array('id'=>$v1['id'])) ;//预约取消
               }elseif($appotype ==2){
                   $appolist[$k1]['pjpoint']=  $vipcard['point_com_action'];
                   $appolist[$k1]['name']=db('action')->where(array('id'=>$v1['action_id']))->column('actionname') ;
                   $appolist[$k1]['appo_detail_url']=url('Action/appointment_detail',array('id'=>$v1['id'])) ;
                   $appolist[$k1]['appo_eva_url']=url('Action/appointment_evaluate',array('id'=>$v1['id'])) ;//评价url
                   $appolist[$k1]['appo_evad_url']=url('Action/appointment_evaluated',array('id'=>$v1['id'])) ;//评价完成url
                   $appolist[$k1]['appo_cal_url']=url('Action/appointment_cancel',array('id'=>$v1['id'])) ;//预约取消
               }elseif($appotype ==3){
                   $appolist[$k1]['pjpoint']=  $vipcard['point_com_activ'];
                   $appolist[$k1]['name']=db('activ')->where(array('id'=>$v1['activ_id']))->column('activname') ;
                   $appolist[$k1]['appo_detail_url']=url('Activ/appointment_detail',array('id'=>$v1['id'])) ;
                   $appolist[$k1]['appo_eva_url']=url('Activ/appointment_evaluate',array('id'=>$v1['id'])) ;//评价url
                   $appolist[$k1]['appo_evad_url']=url('Activ/appointment_evaluated',array('id'=>$v1['id'])) ;//评价完成url
                   $appolist[$k1]['appo_cal_url']=url('Activ/appointment_cancel',array('id'=>$v1['id'])) ;//预约取消
               }
           }
            $shoplist[$k]['appolist']=$appolist;
        }
        $this->assign('shoplist',$shoplist);

        return view();
    }
    //检测有无权限购买
    public function check_tiyan(){
        $res=db('vipbuy')->where(array('uid'=>$this->user_id))->find();
        if($res || $this->user['card_id']>0 || !empty($this->user['endtime'])){
            $this->ajaxReturn(array('status'=>2,'msg'=>'体验会员仅限初次购买用户！'));
        }else{
            $this->ajaxReturn(array('status'=>1));
        }
    }
    //检测有无权限购买秒杀会员卡
    public function check_miao(){
        $miaoid=input('miaoid');
        $miaolist= db('miaolist')->where(array('id'=>$miaoid))->find();
        if(time()<$miaolist['starttime']  ){
            $this->ajaxReturn(array('status'=>2,'msg'=>'秒杀会员尚未开始！'));
        }
        if( time()>$miaolist['endtime'] ){
            $this->ajaxReturn(array('status'=>2,'msg'=>'秒杀活动已经结束！'));
        }
        $count=db('miaobuy')->where(array('oid'=>$miaolist['id']))->count();
        if($count>=$miaolist['num']){
            $this->ajaxReturn(array('status'=>2,'msg'=>'秒杀会员的名额已经没有了！'));
        }else{
            $this->ajaxReturn(array('status'=>1));
        }
    }
    public function my_cardDetail(){

        $id=input('get.id');
        $card=db('vipcard')->find($id);
        $card['jieshao']=htmlspecialchars_decode($card['jieshao']);
        if($id==1){
            $miaolist= db('miaolist')->where(array('status'=>1))->find();
            $card['money']=$miaolist['money'];
        }
        $CouponList=$this->getCouponList(0);
        $coupon_count=count($CouponList);
        $this->assign('coupon_count',$coupon_count);
        $this->assign('couponlist',$CouponList);
        $this->assign("card", $card);
        if(input('ajax')==1)
            $this->view->engine->layout(false);
        return view();
    }
    //异步更新开通会员或续费需要支付的金额
    public function ajax_cost_vip(){
        $data = input();
        $coupon_price=0;
        $vipcard = db('vipcard')->find($data['card_id']);
        if($data['card_id']==1){
            $miaolist= db('miaolist')->where(array('status'=>1))->find();
            $vipcard['money']=$miaolist['money'];
            $cost=$vipcard['money'];
        }else{
            $cost=$vipcard['money'];
        }
        $vipstatus=$this->vip_status();
        //会员卡9折
//        if($vipstatus['status']==1)
//            $cost *= 0.9;
        if($data['coupon_id']>0){
            $coupon_list = db('coupon_list')->find($data['coupon_id']);
            $coupon = db('coupon')->find($coupon_list['cid']);
            $coupon_price=$coupon['money'];
            if($cost>$coupon['money']){
                $cost -= $coupon['money'];
            }else{
                $cost=0;
            }
        }
        $payinfo=array(
            'original_price'=>$vipcard['money'],//原始价格
            'pay_money'=>$cost,//优惠后价格
            'coupon_list_id'=>$data['coupon_id'],//优惠卷id 没有则为0
            'coupon_price'=>$coupon_price,//优惠卷抵扣金额 没有则为0
            'card_id'=>$data['card_id'],//购买会员卡的Id
        );
        session('payinfo',$payinfo);//支付相关信息 供支付完成后记录信息用
        $this->ajaxReturn($cost);

    }
    public function my_cardChoice(){
        $citylist=db('city_list')->select();
        foreach($citylist as $k=>$v){
            $shoplist=db('shop')->field("id,title,city,area,address")->where(array('city_id'=>$v['id']))->select();
            $citylist[$k]['shoplist']=$shoplist;
        }
        $this->assign("citylist", $citylist);
        if(input('ajax')==1)
            $this->view->engine->layout(false);
        return view();
    }
    //购买成功后显示页面
    public function ajax_teacher(){
        $shop_id=input('shopid');
        $where="(shop_id=$shop_id or shop_id =0) and status =1  ";
        $teacher=db('teacher')->where($where)->select();
        $html='';
        foreach($teacher as $k=>$v){
//            $html.='<dd><a class="removeother2" href="javascript:;" onclick="choose_teacher(this,'.$v['teacher_id'].')" data-tid="'.$v['teacher_id'].'"><h1>'.$v['name'].'</h1></a></dd>';
            $html.=' <dd><input id="tec'.$v['teacher_id'].'" type="radio" name="t_id"  data-id="'.$v['teacher_id'].'" onclick="choose_teacher(this,'.$v['teacher_id'].')"><label for="tec'.$v['teacher_id'].'"><h1>'.$v['name'].'</h1></label></dd>';

        }
        $this->ajaxReturn($html);
    }

    //购买成功后显示页面
    public function vip_success(){

        $order=db('order')->where(array('order_sn'=>$_SESSION['order_sn']))->find();

        $vip_buy=db('vipbuy')->where(array('order_id'=>$order['order_id']))->find();
        $card_name=C('CARDNAME');
        $card=$card_name[$vip_buy['card_id']];
//        show_bug($order);
        $this->assign('vip_buy',$vip_buy);
        $this->assign('order',$order);
        $this->assign('card',$card);
        $this->assign('kind',5);
        if(input('ajax')==1)
            $this->view->engine->layout(false);
        return view();
    }
    //所有支付的订单页面
    public function order_pay(){

        $table=input('table');

        if($table){
            session('table',$table);
            session('post',input('post.'));
        }else{
            $table=  session('table',$table);
        }
        if(input('ajax')==1){
            $this->view->engine->layout(false);
            $post=input('post.');
        }else{
            $post= session('post');
        }
        if($table=='Activ:pay_activ'){

            $activ=db('activ')->find(input('get.id'));

            $activ['longtime']=floor(($activ['endtime']-$activ['starttime'])/60);//活动时长
            $CouponList=$this->getCouponList(0);
            $coupon_count=count($CouponList);
            $this->assign('coupon_count',$coupon_count);
            $this->assign('couponlist',$CouponList);
            $this->assign('activ',$activ);
        }elseif($table=='my_cardChoice'){
            $citylist=db('city_list')->select();
            foreach($citylist as $k=>$v){
                $shoplist=db('shop')->field("id,title,city,area,address")->where(array('city_id'=>$v['id']))->select();
                $citylist[$k]['shoplist']=$shoplist;
            }

            $this->assign("citylist", $citylist);
        }elseif($table=='bind_tel'){
            $payinfo=session('payinfo');//需要支付的费用等信息
            if($post['shop_id'] && $post['teacher_id']){
                $payinfo['shop_id']=$post['shop_id'];
                $payinfo['teacher_id']=$post['teacher_id'];
                session('payinfo',$payinfo);
            }
        }
        return view($table);

    }
    /* 绑定了手机号码，月卡会员等直接进行微信支付,获取参数  */
    public function pay_info(){
        $payinfo=session('payinfo');//需要支付的费用等信息
        $type=input('type');
        if($type==1){
            $payinfo['shop_id']=input('shop_id');
            $payinfo['teacher_id']=input('teacher_id');
            session('payinfo',$payinfo);
        }


        $shop=db('shop')->find($payinfo['shop_id']);
        $this->assign("card_id", $payinfo['card_id']);
        $this->assign("coupon_id", $payinfo['coupon_list_id']);
        $vipinfo = db('vipcard')->where(array('id'=>$payinfo['card_id']))->find();
        $need_pay_money=$payinfo['pay_money'];
//        $need_pay_money=0.01;
        $xmlarr['appid'] = $this->wxconfig['appid'];
//        $xmlarr['mch_id'] ='1313374301';
//        $xmlarr['sub_mch_id'] ='1429300602';
        $xmlarr['mch_id'] =$this->wxconfig['mchid'];
        $wx_config = db("wx_config")->find();
        if( $wx_config['type']==2){
            $xmlarr['sub_mch_id'] =$shop['pay_id'];
        }

        $xmlarr['nonce_str'] = create_noncestr();
        $xmlarr['body'] = '肌动健身开通会员:' . $vipinfo['title'];
        $xmlarr['out_trade_no'] =create_out_trade_no();
        $_SESSION['order_sn'] = $xmlarr['out_trade_no']; // 把本次生成的订单号存入session中，保存购买记录的时候用到
        $xmlarr['total_fee'] = $need_pay_money * 100;
        $xmlarr['spbill_create_ip'] = $_SERVER["REMOTE_ADDR"];
        $xmlarr['notify_url'] = "http://" . $_SERVER['SERVER_NAME'] . "/index.php/Home/Notify/notify";
        $xmlarr['trade_type'] = "JSAPI";
        $xmlarr['openid'] = session('openid');
        $xmlarr['sign'] = getSign($xmlarr,$this->wxconfig['key']);

        $xml=arrayToXml($xmlarr);

        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $response = httpRequest($url,'post',$xml);
        $payResult = xmlToArray($response);
//        logResult($payResult);
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
        $oddata['type']=1;
        $oddata['prepay_id']=$prepay_id;
        $oddata['pay_name']='微信支付';
        $oddata['total_amount']=$payinfo['original_price'];
        $oddata['order_amount']=$payinfo['pay_money'];
        $oddata['openid']=session('openid');
        $oddata['card_id']=$payinfo['card_id'];
        $oddata['coupon_id']=$payinfo['coupon_list_id'];
        $oddata['shop_id']=$payinfo['shop_id'];
        $oddata['teacher_id']=$payinfo['teacher_id'];
        db('order')->save($oddata);

        $tims = time();
        $nonstr =create_noncestr();
        $stringA = "appId=" . $this->wxconfig['appid'] . "&nonceStr=$nonstr&package=prepay_id=$prepay_id&signType=MD5&timeStamp=$tims&key=".$this->wxconfig['key'];
        $paySign=strtoupper(md5($stringA));

        $this->ajaxReturn(array("appid"=>$this->wxconfig['appid'],"status"=>1,"timeStamp"=>$tims,"nonceStr"=>$nonstr,"package"=>"prepay_id=$prepay_id","paySign"=>$paySign));


    }


    /*会员规则
     1 学生卡需要激活  其他不用
    */
   /* 支付成功后的处理方法 */
    public function pay_success(){

        $payinfo=session('payinfo');
//        $this->ajaxReturn(array('status'=>1,'msg'=>'会员购买成功'));
        $order=db('order')->where(array('order_sn'=>session('order_sn')))->find();
        if($order['pay_status']==1){
            $this->ajaxReturn(array('status'=>1,'msg'=>'会员购买成功'));
        }
        $res= $this->Queryorder(session('order_sn'));
        if(!$res){
            $this->ajaxReturn(array('status'=>2,'msg'=>'支付失败'));
        }
        $card_id=$payinfo['card_id'];
        $now_time=time();
        $user_info = $this->user;
        $vipstatus=$this->vip_status();
        $vipcard = db('vipcard')->find($card_id);
        if($vipstatus['status'] == 1){
           $res= db('vipcard')->find($user_info['card_id']);
            $user_info['type']=$res['type'];//会员当前的会员卡类型
        }

        $coupon= db('coupon')->find($vipcard['coupon_id']);

        $shop=db('shop')->find($payinfo['shop_id']);
        // 有邀请人 且不是秒杀会员才有优惠券奖励 且第一次购买
        if ($card_id > 1 && $user_info['uppeople'] && $user_info['card_id']==0) {
            $up_user_info =db('user')->find($user_info['uppeople']);
            // 邀请人获得抵价券奖励
            $upcoupon_data=array(
                'uid'=>$up_user_info['id'],
                'openid'=>$up_user_info['openid'],
                'cid'=>$coupon['id'],
                'type'=>0,
                'send_time'=>$now_time,
                'endtime'=>$now_time+($coupon['expire_day']*86400),
                'source'=>'邀请获得',
            );
            // 被邀请人获得抵价券奖励
            $coupon_data=array(
                'uid'=>$user_info['id'],
                'openid'=>$user_info['openid'],
                'cid'=>$coupon['id'],
                'type'=>0,
                'send_time'=>$now_time,
                'endtime'=>$now_time+($coupon['expire_day']*86400),
                'source'=>'被邀请获得',
            );
            db('coupon_list')->save($upcoupon_data);
            db('coupon_list')->save($coupon_data);
        }

        // 把购买记录写入购买表中
        if(in_array($card_id,array(1,2,9))){
            $plan_type=1;
        }elseif(in_array($card_id,array(3,6))){
            $plan_type=2;
        }elseif(in_array($card_id,array(4,7))){
            $plan_type=3;
        }elseif(in_array($card_id,array(5,8))){
            $plan_type=4;
        }
        $vipbuy_data=array(
            'uid'=>$user_info['id'],
            'order_id'=>$order['order_id'],
            'hasday'=> $vipcard['hasday'],
            'card_id'=>$card_id,
            'money'=>$payinfo['pay_money'],
            'city_id'=>$shop['city_id'],
            'shop_id'=>$payinfo['shop_id'],
            'teacher_id'=>$payinfo['teacher_id'],
            'type'=>$plan_type,
            'source'=>'微信支付',
            'buy_time'=>$now_time,
        );
//        if($vipstatus['status'] == 1 && $user_info['type'] !=$vipcard['type']){//此情况表示需要到期后在更新 是会员 且会员类型相同
//            //0代表未使用 默认情况是使用
//            $vipbuy_data['status']=0;
//            $vb_lastid=db('vipbuy')->save($vipbuy_data);
//        }else{
            //此情况表示购买后就更新user表

            //成为会员后对营养餐和补给领取数量进行写入
            if($vipstatus['status']==1 && time()<$user_info['endtime']){
                $vipbuy_endtime=$user_info['endtime']+($vipcard['hasday']*86400);
                $user_endtime=$user_info['endtime']+($vipcard['hasday']*86400);
            }else{
                $vipbuy_endtime= $now_time+($vipcard['hasday']*86400);
                $user_endtime= $now_time+($vipcard['hasday']*86400);
            }
            $vipbuy_data['end_time'] =$vipbuy_endtime;
            $vipbuy_data['change_time'] = $now_time;
            $vb_lastid=db('vipbuy')->save($vipbuy_data);

            $user_data['endtime']=$user_endtime;
            $user_data['id'] = $user_info['id'];
            $user_data['shop_id'] = $payinfo['shop_id'];
            $user_data['card_id'] = $card_id;
            $user_data['city_id'] = $shop['city_id'];
            $user_data['eat_num'] = $user_info['eat_num'] + $vipcard['yingyang_num'];
            $user_data['sport_num'] = $user_info['sport_num'] + $vipcard['buji_num'];
            $user_data['ty_num'] = $user_info['ty_num'] + $vipcard['ty_num'];
            $user_data['point'] = $user_info['point'] + $vipcard['point_buy'];
            $user_data['vipbuy_id'] = $vb_lastid;
            $user_data['upteacher'] = $payinfo['teacher_id'];
            $user_data['buy_days'] =$user_info['buy_days'] + $vipcard['hasday'] ;
           //学生卡默认冻结
            if($card_id==2 && $user_info['card_id']!=2)
                $user_data['status'] = 0;
                $user_data['brozen_time'] = $vipcard['hasday']*86400;
            db('user')->save($user_data);
//        }

        //修改订单的支付状态
        db('order')->save(array('pay_status'=>1,'order_id'=>$order['order_id'],'pay_time'=>time()));
        //如果是购买的秒杀会员，把信息写入秒杀会员表
        if ( $card_id == 1 ){
            $m_list_result = db('miaolist')->where(array('status'=>1))->find();

            $data_miao['uid'] = $user_info['id'];
            $data_miao['addtime'] = $now_time;
            $data_miao['oid'] = $m_list_result['id'];
            db('miaobuy')->save($data_miao);
        }
        // 把使用的抵价券变成status = 0
        if ($payinfo['coupon_list_id'] != 0) {
            $coupon_data['id'] = $payinfo['coupon_list_id'];
            $coupon_data['use_time'] = $now_time;
            $coupon_data['order_id'] = $order['order_id'];
            $coupon_data['status'] = 1;
            db('coupon_list')->save($coupon_data);
        }
        $this->ajaxReturn(array('status'=>1,'msg'=>'会员购买成功'));

    }

    public function bind_tel(){
        //需要支付的费用等信息

        $table=input('table')?input('table'):'';
        $this->assign('table',$table);
        if(input('ajax')==1)
            $this->view->engine->layout(false);
        return view();
    }
    /* 检查手机号是否被绑定过 */
    public function check_phone_use(){
        $phone_result =  db('user')->where(array('tel'=> $_POST['phone']))->find();
        if ( $phone_result ){
            $this->ajaxReturn(array('status'=>2,'msg'=>'此手机号已绑定会员，请更换手机号'));
        }else{
            $this->ajaxReturn(array('status'=>1,'msg'=>'此手机号可以使用'));
        }
    }
    /* 验证码是否正确 */
    public function check_message_num()
    {
        if (! isset($_SESSION['message_check_num']) || ($_POST['ver_code'] != $_SESSION['message_check_num'])) {
            $this->ajaxReturn(array('status'=>2,'msg'=>'验证码填写错误'));
        } else {
            // 把手机号码写入到对应的用户数据中
            db('user')->save(array('id'=>$this->user_id,'tel'=>input('phone')));
            $this->ajaxReturn(array('status'=>1,'msg'=>'绑定成功'));
        }
    }
    // 查询用户当前的状态(包含健身计划)
    private function check_plan_status(){

        $onelist = db('user')->where(array('openid'=>$_SESSION['openid']))->find();

        $card_name=C('CARDNAME');
        $card=$card_name[$onelist['card_id']];

        if (!$onelist) {
            $onelist['planstatus'] = 3;
            $onelist['planerrormsg'] = '尚未成为会员';
            return $onelist;
        }

        $onelist['endtime'] = date("Y-m-d", strtotime($onelist['endtime']));
        $onelist['typename'] = $card_name[$onelist['card_id']];

        /* 教练是否已经上传了该用户的健身计划 */
        if ($onelist['planid'] > 0) {
            $onelist['planstatus'] = 1;
            $onelist['planerrormsg'] = '健身计划已经上传';
        } else {
            $onelist['planstatus'] = 2;
            $onelist['planerrormsg'] = '健身计划尚未上传';
        }
        return $onelist;


    }
    // 获取健身计划详情--方法
    private function get_plan_detail($id)
    {

        $onelist = db('plan')->where(array('id'=>$id))->find();
        $onelist['has_plan'] = 0;
        if ($onelist)
            $onelist['has_plan'] = 1;

        if ($onelist['contentone'])
            $onelist['contentone'] = htmlspecialchars_decode($onelist['contentone']);

        if ($onelist['contenttwo'])
            $onelist['contenttwo'] = htmlspecialchars_decode($onelist['contenttwo']);

        if ($onelist['contentthree'])
            $onelist['contentthree'] = htmlspecialchars_decode($onelist['contentthree']);

        if ($onelist['contentfour'])
            $onelist['contentfour'] = htmlspecialchars_decode($onelist['contentfour']);
        if ($onelist['food1'])
            $onelist['food1'] = htmlspecialchars_decode($onelist['food1']);
        if ($onelist['food2'])
            $onelist['food2'] = htmlspecialchars_decode($onelist['food2']);
        if ($onelist['food3'])
            $onelist['food3'] = htmlspecialchars_decode($onelist['food3']);
        if ($onelist['food4'])
            $onelist['food4'] = htmlspecialchars_decode($onelist['food4']);
        if ($onelist['food5'])
            $onelist['food5'] = htmlspecialchars_decode($onelist['food5']);
        $onelist['contentfive'] = htmlspecialchars_decode($onelist['contentfive']);
        $onelist['contentsix'] = htmlspecialchars_decode($onelist['contentsix']);
        $onelist['year'] = date("Y", strtotime($onelist['addtime']));
        $onelist['month'] = date("m", strtotime($onelist['addtime']));
        $onelist['day'] = date("d", strtotime($onelist['addtime']));

        $temp_result = db('user')->find($onelist['uid']);
        $onelist['headurl'] = $temp_result['headurl'];
//        $onelist['one_pre'] = round(($onelist['qztz'] / 120), 2) * 100;
//        $onelist['two_pre'] = round(($onelist['ggj'] / 100), 2) * 100;
//        $onelist['three_pre'] = round(($onelist['tzf'] / 100), 2) * 100;
//        $onelist['four_pre'] = round(($onelist['tzbfb'] / 60), 2) * 100;
//        $onelist['five_pre'] = round(($onelist['jcdx'] / 4000), 2) * 100;
        $onelist['nickname'] =$this->userTextDecode($onelist['nickname']);
        if ($onelist['do_num'] == 1) {
            $onelist['do_num'] = "一";
        } elseif ($onelist['do_num'] == 2) {
            $onelist['do_num'] = '二';
        } elseif ($onelist['do_num'] == 3) {
            $onelist['do_num'] = '三';
        } elseif ($onelist['do_num'] == 4) {
            $onelist['do_num'] = '四';
        }
        return $onelist;
    }
}