<?php

namespace App\Admin\Controllers;

use App\Model\WeixinUser;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp;
use App\Model\WeixinType;
use Illuminate\Support\Facades\Storage;

class WeixinController extends Controller
{
    use HasResourceActions;
    protected $redis_weixin_access_token = 'str:weixin_access_token';
    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('Index')
            ->description('description')
            ->body($this->grid());
    }

    /**
     * Show interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function show($id, Content $content)
    {
        return $content
            ->header('Detail')
            ->description('description')
            ->body($this->detail($id));
    }

    /**
     * Edit interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function edit($id, Content $content)
    {
        return $content
            ->header('Edit')
            ->description('description')
            ->body($this->form()->edit($id));
    }

    /**
     * Create interface.
     *
     * @param Content $content
     * @return Content
     */
    public function create(Content $content)
    {
        $show_id=$_GET['show_id'];
//        return $content
//            ->header('Create')
//            ->description('description')
//            ->body($this->form());
        $show=WeixinUser::where(['id'=>$show_id])->first();
        return $content
            ->header('Create')
            ->description('description')
            ->body(view('weixin.wxservice',['user_info'=>$show])->render());

    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new WeixinUser);

        $grid->id('Id');
        $grid->uid('Uid');
        $grid->openid('Openid');
        $grid->add_time('Add time')->display(function($time){
            return date('Y-m-d H:i:s',$time);
        });

        $grid->nickname('Nickname');
        $grid->sex('Sex')->display(function($sex){
            if($sex==0){
                $sexs='未知';
            }elseif($sex==1){
                $sexs='男';
            }elseif($sex==2){
                $sexs='女';
            }
            return $sexs;
        });
        $grid->headimgurl('Headimgurl')->display(function($img){
            return "<img src='$img'>";
        });

        $grid->subscribe_time('Subscribe time')->display(function($time){
            return date('Y-m-d H:i:s',$time);
        });

        $grid->actions(function ($actions) {
            // append一个操作
            $key=$actions->getKey();
            $actions->prepend('<a href="/admin/weixin/create?show_id='.$key.'"><i class="fa fa-paper-plane"></i></a>');

        });

        return $grid;
    }
    /**
     * 获取微信AccessToken
     */
    public function getWXAccessToken()
    {

        //获取缓存
        $token = Redis::get($this->redis_weixin_access_token);
        if(!$token){        // 无缓存 请求微信接口
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.env('WEIXIN_APPID').'&secret='.env('WEIXIN_APPSECRET');
            $data = json_decode(file_get_contents($url),true);

            //记录缓存
            $token = $data['access_token'];
            Redis::set($this->redis_weixin_access_token,$token);
            Redis::setTimeout($this->redis_weixin_access_token,3600);
        }
        return $token;

    }


    /**
     * 获取用户信息
     * @param $openid
     */
    public function getUserInfo($openid)
    {
        //$openid = 'oLreB1jAnJFzV_8AGWUZlfuaoQto';
        $access_token = $this->getWXAccessToken();      //请求每一个接口必须有 access_token
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';

        $data = json_decode(file_get_contents($url),true);
        //echo '<pre>';print_r($data);echo '</pre>';
        return $data;
    }
    // 刷新access_token

    public function refreshToken()
    {
        Redis::del($this->redis_weixin_access_token);
        echo $this->getWXAccessToken();
    }

public function wxservice(Request $request){
   // echo '<pre>';print_r($_POST);echo '</pre>';echo '<hr>';
    $url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$this->getWXAccessToken();
    $openid=$request->input('openid');
    $weixin=$request->input('weixin');

    //print_r($url);
    //$content=$request->input('weixin');
    $client = new GuzzleHttp\Client(['base_uri' => $url]);
        $data = [
          "touser"=>$openid,
           "msgtype"=>"text",
          "text"=>[
              "content"=>$weixin
         ]
        ];
  // var_dump($data);
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);      //处理中文编码
        $r = $client->request('POST', $url, [
            'body' => $body
        ]);

        // 3 解析微信接口返回信息

        $response_arr = json_decode($r->getBody(), true);
        echo '<pre>';
        print_r($response_arr);
        echo '</pre>';

        if ($response_arr['errcode'] == 0) {
            //存入数据库
                $data=[
                    'text'=>$weixin,
                    'add_time'=>time(),
                    'openid'=>$openid,
                    'nickname'=>'未凉客服'

            ];
                $res=WeixinType::insert($data);
            $arr=[
                'code'=>0,
                'msg'=>'发送成功',
            ];
        }else{
            $arr=[
                'code'=>1,
                'msg'=>$response_arr['errmsg'],
            ];
        }
    echo json_encode($arr);
}
    public function wxservices(Request $request){
        $openid=$request->input('openid');
        $new=WeixinType::orderBy('add_time','asc')->where(['openid'=>$openid])->get();
        echo json_encode($new);
    }
    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(WeixinUser::findOrFail($id));

        $show->id('Id');
        $show->uid('Uid');
        $show->openid('Openid');
        $show->add_time('Add time');
        $show->nickname('Nickname');
        $show->sex('Sex');
        $show->headimgurl('Headimgurl');
        $show->subscribe_time('Subscribe time');

        return $show;
    }

     /* Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new WeixinUser);

        $form->number('uid', 'Uid');
        $form->text('openid', 'Openid');
        $form->number('add_time', 'Add time');
        $form->text('nickname', 'Nickname');
        $form->switch('sex', 'Sex');
        $form->text('headimgurl', 'Headimgurl');
        $form->number('subscribe_time', 'Subscribe time');

        return $form;
    }
}
