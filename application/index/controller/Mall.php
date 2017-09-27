<?php
namespace app\index\controller;
use Think\Controller;
use Home\Logic\UserLogic;

class Mall extends Common{
    public $cartLogic; // 购物车逻辑操作类

    public function  __construct() {
        parent::__construct();
//        $this->cartLogic = new \app\index\Logic\CartLogic();
    }
    public function index(){

        $this->view->engine->layout('layout_mall');
        $category=db('goods_category')->select();
        $this->assign('category',$category);

        if(input('ajax')==1){
            $this->view->engine->layout(false);
        }
        return view('index');

    }
    public function ajax_food(){
        $this->view->engine->layout(false);
//        layout('layout_mall');
        $cat_id=input('cat_id');
        $map['cat_id']=$cat_id;
        $goodslist=db('goods')->where($map)->select();

        $this->assign('goodslist',$goodslist);
        return view('ajax_food');
    }
    public function detail(){
        layout('layout_mall');
        $id=input('get.id');
        $goods=$this->get_goods_info($id);

        $this->assign('goods',$goods);
        if(input('ajax')==1)
            layout(false);
        $this->display();
    }
    private function get_goods_info($id){
        $goods=db('goods')->find($id);
        $goods['goods_desc']=htmlspecialchars_decode($goods['goods_desc']);
        return $goods;
    }
    public function cart(){

        layout('layout_mall');

        if(input('ajax')==1)
            layout(false);
        $this->display();
    }
    /**
     * ajax 将商品加入购物车
     */
    function ajaxAddCart()
    {
        $goods_id = input("goods_id"); // 商品id
        $goods_num = input("goods_num");// 商品数量
        $result = $this->cartLogic->addCart($goods_id, $goods_num,$this->user_id); // 将商品加入购物车
        exit(json_encode($result));
    }
    /*
    * 请求获取购物车列表
    */
//    public function cartList()
//    {
//        $cart_form_data = $_POST["cart_form_data"]; // goods_num 购物车商品数量
//        $cart_form_data = json_decode($cart_form_data,true); //app 端 json 形式传输过来
//
//        $unique_id = input("unique_id"); // 唯一id  类似于 pc 端的session id
//        $user_id = input("user_id"); // 用户id
//        $where = " session_id = '$unique_id' "; // 默认按照 $unique_id 查询
//        $user_id && $where = " user_id = ".$user_id; // 如果这个用户已经等了则按照用户id查询
//        $cartList = db('Cart')->where($where)->column("id,goods_num,selected");
//
//        if($cart_form_data)
//        {
//            // 修改购物车数量 和勾选状态
//            foreach($cart_form_data as $key => $val)
//            {
//                $data['goods_num'] = $val['goodsNum'];
//                $data['selected'] = $val['selected'];
//                $cartID = $val['cartID'];
//                if(($cartList[$cartID]['goods_num'] != $data['goods_num']) || ($cartList[$cartID]['selected'] != $data['selected']))
//                    db('Cart')->where("id = $cartID")->save($data);
//            }
//            //$this->assign('select_all', $_POST['select_all']); // 全选框
//        }
//
//        $result = $this->cartLogic->cartList($this->user, $unique_id,0);
//        exit(json_encode($result));
//    }

    /*
       * ajax 请求获取购物车列表
       */
    public function ajaxCartList()
    {
        layout(false);
        $post_goods_num = input("goods_num"); // goods_num 购物车商品数量
        $post_cart_select = input("cart_select"); // 购物车选中状态
        $where = " user_id = ".$this->user_id; // 如果这个用户已经等了则按照用户id查询

        $cartList = db('Cart')->where($where)->column("id,goods_id,goods_num,selected");

        if($post_goods_num)
        {
            // 修改购物车数量 和勾选状态
            foreach($post_goods_num as $key => $val)
            {
                $data['goods_num'] = $val < 1 ? 1 : $val;
                $data['selected'] = $post_cart_select[$key] ? 1 : 0 ;
                if(($cartList[$key]['goods_num'] != $data['goods_num']) || ($cartList[$key]['selected'] != $data['selected']))
                    db('Cart')->where("id = $key")->save($data);
            }
            $this->assign('select_all', $_POST['select_all']); // 全选框
        }

        $result = $this->cartLogic->cartList($this->user,1,1);
        if(empty($result['total_price']))
            $result['total_price'] = Array( 'total_fee' =>0, 'cut_fee' =>0, 'num' => 0, 'atotal_fee' =>0, 'acut_fee' =>0, 'anum' => 0);

        $this->assign('cartList', $result['cartList']); // 购物车的商品
        $this->assign('total_price', $result['total_price']); // 总计

        $this->display('ajax_cart_list');
    }




    public function my(){

        layout('layout_mall');

        if(input('ajax')==1)
            layout(false);
        $this->display();
    }
    public function order(){

        layout('layout_mall');

        if(input('ajax')==1)
            layout(false);
        $this->display();
    }

     public function ajax_order_list()
     {
         layout(false);

         $where = ' uid='.$this->user_id;
         //条件搜索
         if(in_array(strtoupper(input('type')), array('WAITCCOMMENT','COMMENTED')))
         {
             $where .= " AND order_status in(1,4) "; //代评价 和 已评价
         }elseif(input('type')=='ALL') {

         } elseif(input('type')) {
             $where .= C(strtoupper(input('type')));
         }
//         $count = db('morder')->where($where)->count();
//         $Page = new Page($count,10);
//
//         $show = $Page->show();

//         $order_list = db('morder')->order($order_str)->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();
         $order_list = db('morder')->order("order_id DESC")->where($where)->select();


         //获取订单商品
         $model = new UserLogic();
         foreach($order_list as $k=>$v)
         {
             $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
             //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount']; //订单总额
             $data = $model->get_order_goods($v['order_id']);
             $order_list[$k]['goods_list'] = $data['result'];
//            show_bug($v);
         }

//         show_bug($order_list);
//         exit;
         $this->assign('order_status',C('ORDER_STATUS'));
         $this->assign('shipping_status',C('SHIPPING_STATUS'));
         $this->assign('pay_status',C('PAY_STATUS'));
//         $this->assign('page',$show);
         $this->assign('lists',$order_list);
         $this->assign('active','order_list');
         $this->assign('active_status',input('get.type'));

         $this->display();
     }
    
    
    
    
    
}