<?php
namespace app\index\controller;
use Home\Logic;
use Think\Controller;

class Notify extends Controller {
    public function notify(){
        $xml = <<<XML
        <xml>
          <return_code><![CDATA[SUCCESS]]></return_code>
        </xml>
XML;
        $input = file_get_contents('php://input');
        $data = xmlToArray($input);

        if ($data['result_code'] == 'SUCCESS' && $data['return_code'] == 'SUCCESS') {
            $order=M('order')->where(array('order_sn'=>$data['out_trade_no']))->find();
            if( $order['pay_status']==1){
                echo $xml;
                exit;
            }else{
                $res= $this->Queryorder($data['out_trade_no']);
                if($res){
                    $this->add_card_info($data['out_trade_no']);
                    echo $xml;
                    exit;
                }

            }

        }
        echo $xml;
    }

    public function add_card_info($out_trade_no){



        $order=M('order')->where(array('order_sn'=>$out_trade_no))->find();

        $card_id=$order['card_id'];
        $now_time=time();
        $user_info = M('user')->where(array('openid'=>$order['openid']))->find();
        $vipstatus=$this->vip_status($order['openid']);

        $vipcard = M('vipcard')->find($card_id);
        if($vipstatus['status'] == 1){
            $res= M('vipcard')->find($user_info['card_id']);
            $user_info['type']=$res['type'];//会员当前的会员卡类型
        }
        $coupon= M('coupon')->find($vipcard['coupon_id']);
        $shop=M('shop')->find($order['shop_id']);
        // 有邀请人 且不是秒杀会员才有优惠券奖励 且第一次购买
        if ($card_id > 1 && $user_info['uppeople'] && $user_info['card_id']==0) {
            $up_user_info =M('user')->find($user_info['uppeople']);
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
            M('coupon_list')->save($upcoupon_data);
            M('coupon_list')->save($coupon_data);
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
            'money'=>$order['order_amount'],
            'city_id'=>$shop['city_id'],
            'shop_id'=>$order['shop_id'],
            'teacher_id'=>$order['teacher_id'],
            'type'=>$plan_type,
            'source'=>'微信支付',
            'buy_time'=>$now_time,
        );

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
        $vb_lastid=M('vipbuy')->save($vipbuy_data);
        $user_data['endtime']=$user_endtime;
        $user_data['id'] = $user_info['id'];
        $user_data['shop_id'] = $order['shop_id'];
        $user_data['card_id'] = $card_id;
        $user_data['city_id'] = $shop['city_id'];
        $user_data['eat_num'] = $user_info['eat_num'] + $vipcard['yingyang_num'];
        $user_data['sport_num'] = $user_info['sport_num'] + $vipcard['buji_num'];
        $user_data['ty_num'] = $user_info['ty_num'] + $vipcard['ty_num'];
        $user_data['point'] = $user_info['point'] + $vipcard['point_buy'];
        $user_data['vipbuy_id'] = $vb_lastid;
        $user_data['upteacher'] = $order['teacher_id'];
        $user_data['buy_days'] =$user_info['buy_days'] + $vipcard['hasday'] ;
        //学生卡默认冻结
        if($card_id==2 && $user_info['card_id']!=2)
            $user_data['status'] = 0;
        $user_data['brozen_time'] = $vipcard['hasday']*86400;
        M('user')->save($user_data);

        //修改订单的支付状态
        M('order')->save(array('pay_status'=>1,'order_id'=>$order['order_id'],'pay_time'=>time()));
        //如果是购买的秒杀会员，把信息写入秒杀会员表
        if ( $card_id == 1 ){
            $m_list_result = M('miaolist')->where(array('status'=>1))->find();

            $data_miao['uid'] = $user_info['id'];
            $data_miao['addtime'] = $now_time;
            $data_miao['oid'] = $m_list_result['id'];
            M('miaobuy')->save($data_miao);
        }
        // 把使用的抵价券变成status = 0
        if ($order['coupon_id'] > 0) {
            $coupon_data['id'] = $order['coupon_id'];
            $coupon_data['use_time'] = $now_time;
            $coupon_data['order_id'] = $order['order_id'];
            $coupon_data['status'] = 1;
            M('coupon_list')->save($coupon_data);
        }


    }
//查询订单
    public function Queryorder($out_trade_no){
        $url="https://api.mch.weixin.qq.com/pay/orderquery";
        $wx_config = M("wx_config")->find();
        $wxconfig=json_decode($wx_config["jsoncontents"],true);
        $xmlarr['appid'] = $wxconfig['appid'];
        $xmlarr['mch_id'] =$wxconfig['mchid'];
        $xmlarr['nonce_str'] = create_noncestr();
        $xmlarr['out_trade_no'] =$out_trade_no;
        $xmlarr['sign'] = getSign($xmlarr,$wxconfig['key']);
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
    //验证当前用户是否是会员，以及会员是否过期
    //会员状态（0： 学生卡冻结，1：正常 2后台冻结）
    public function vip_status($openid){
        $result = M('user')->where(array('openid'=>$openid))->find();
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
    public function notify2(){
        $xml = <<<XML
        <xml>
          <return_code><![CDATA[SUCCESS]]></return_code>
        </xml>
XML;
        $input = file_get_contents('php://input');
        $data = xmlToArray($input);

        if ($data['result_code'] == 'SUCCESS' && $data['return_code'] == 'SUCCESS') {
            $order=M('order')->where(array('order_sn'=>$data['out_trade_no']))->find();
            if( $order['pay_status']==1){
                echo $xml;
                exit;
            }else{
                $res= $this->Queryorder($data['out_trade_no']);
                if($res){
                    $this->add_activ_appo($data['out_trade_no']);
                    echo $xml;
                    exit;
                }

            }

        }
        echo $xml;
    }

    public function add_activ_appo($out_trade_no){
        $order=M('order')->where(array('order_sn'=>$out_trade_no))->find();
        $activ_id=$order['activ_id'];
        $user_info= M('user')->where(array('openid'=>$order['openid']))->find();
        $activ=M('activ')->find($activ_id);
        $appodata=array(
            'city_id'=> $activ['city_id'],
            'shop_id'=> $activ['shop_id'],
            'openid'=>$order['openid'],
            'type'=>3,
            'activ_id'=> $activ_id,
            'yuyuetime'=>$activ['starttime'],
            'addtime'=>date("Y-m-d H:i:s"),
            'tel'=>$user_info['tel'],
            'point'=> $activ['point'],
        );
        $appo_result = M('appointment')->save($appodata);
        // 把使用的抵价券变成status = 0
        if($order['coupon_id']>0){
            $coupon_data['id'] = $order['coupon_id'];
            $coupon_data['use_time'] = time();
            $coupon_data['order_id'] = $order['id'];
            $coupon_data['status'] = 1;
            M('coupon_list')->save($coupon_data);
        }

        //修改订单的支付状态
        M('order')->save(array('pay_status'=>1,'order_id'=>$order['order_id'],'pay_time'=>time()));
        edit_point($order['openid'],$activ['point']);


    }






}