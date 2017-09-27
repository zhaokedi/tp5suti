<?php
namespace app\index\controller;
 use Think\Controller;
class Share extends Common{
    // 分享列表页
    public function index(){
        $shopname=db('shop')->column('id,title');
        $openid=session('openid');
        $time=time();
        $where="l.openid ='$openid' and l.use_time=0 and l.end_time>$time and status=0 ";
//        $where.=" and l.type in (0,2) ";
        $sql="select l.*,c.name,c.money,c.change_name,c.expire_day from coupon_list l LEFT JOIN coupon c ON l.cid=c.id WHERE ".$where;
//        logResult($sql);
        $couponlist=db()->query($sql);
//        logResult($couponlist);
        foreach ($couponlist as $k=>$v){
            $couponlist[$k]['expire_days']=floor(($v['end_time']-time())/86400);
            $couponlist[$k]['endtime']=date('Y-m-d', $v['end_time']);
            if($v['shop_id']==0){
                $couponlist[$k]['shopname']='全部门店';
            }else{
                $couponlist[$k]['shopname']=$shopname[$v['shop_id']];
            }


        }

        $this->assign('couponlist', $couponlist);
        $this->assign("kind", 5);
        if(I('ajax')==1)
            layout(false);

        $this->display();

    }
    public function use_coupon(){
        $id=I('post.id');
        $data=array(
            'id'=>$id,
            'status'=>1,
            'use_time'=>time(),
        );
        db('coupon_list')->save($data);
        $this->ajaxReturn(array('status'=>1));
    }
    // 器械详情
    public function index_share()
    {
    //关注用户在数据库中的信息
//出现非法图像错误 可能accesstoken 获取错误  accesstoken只能在本地货服务器都获取  若两处都获取使用 会导致一处一定时间内出错
        $user_info = $this->get_user_info();

        $ticket_result = $this->get_ticket($user_info['id']);
        $ticket = $ticket_result['ticket'];

        $img_url = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".urlencode($ticket);
        $img_info = $this->downloadImgFromWeixin($img_url);//获取生成的图片信息

        $filepath = './Public/pic/temp.jpg';
        $local_file = fopen($filepath,'w');
        if ( $local_file !== false ){
            $res=fwrite($local_file, $img_info['body']);
            if ($res !== false ){
                fclose($local_file);
            }
        }

        $save_path = "./Public/pic/".$_SESSION['openid']."~erweima.jpg";
        $share_img_url = __ROOT__."/Public/pic/".$_SESSION['openid']."~erweima.jpg";//有子目录用此路径 不然会出错

        $this->add_logo_img($filepath,$save_path);
        $this->assign("share_img_url",$share_img_url);
        
        //获取分享奖励信息
        $share_result = db('vipcard')->where("type != 0")->select();
        $this->assign("share_info",$share_result);
        $this->assign("kind", 5);
        if(I('ajax')==1)
            layout(false);
        $this->display();

    }

    // 获取二维码的ticket
    public function get_ticket($id)
    {

        $access_token = $this->access_token;

        $ticket_url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=" . $access_token;
        $post_json = '{"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": '.$id.'}}}';
        $ticket_result = httpRequest($ticket_url,'post', $post_json);
//        logResult($ticket_result);
        $ticket_result = json_decode($ticket_result,1);
        $ticket = $ticket_result['ticket'];
        return $ticket_result;
    }
    

    
    // 保存图片到本地
    private function downloadImgFromWeixin($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $package = curl_exec($ch);
        $httpinfo = curl_getinfo($ch);
        curl_close($ch);
        return array_merge(array('body'=>$package),array('header'=>$httpinfo));
    }
    
    //给图片打水印
    private function add_logo_img($img_url,$save_name){
//        $image = new \Think\Image();
        // 给原图添加水印并保存为water_o.gif
//        $resa=$image->open($img_url)->water('./Public/pic/water_log.jpg',\Think\Image::IMAGE_WATER_CENTER,100)->save($save_name);
    }
    
    
    
    
    
    
    
    
}