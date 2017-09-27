<?php
namespace app\index\controller;
use think\Controller;
class Index extends Controller
{
    public function index()
    {
        $request =Request();
        dump($request->route());
        dump($request->dispatch());
        dump($request->method());
        dump($request->route());
       return '234';
//        $a=Config::get('database');
//        $result = 'oh';
//        if ($result) {
//            //设置成功后跳转页面的地址，默认的返回页面是$_SERVER['HTTP_REFERER']
//            $this->success('新增成功');
//        } else {
//            //错误页面的默认跳转页面是返回前一页，通常不需要设置
//            $this->error('新增失败');
//        }
    }
}
