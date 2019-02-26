<?php

namespace App\Http\Controllers\Weixin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Weixin\WXBizDataCryptController;
use App\Model\OrderModel;

class PayController extends Controller
{
    //

    public $weixin_unifiedorder_url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
    public $weixin_notify_url = 'http://lsy.52self.cn/weixin/pay/notice';     //支付通知回调



public function weixinTest(){
          $total_fee =1;
          $order_id = OrderModel::getModelOrder();
          $order_info=[
            'appid'=>env('WEIXIN_APPID_0'),
            'mch_id'=>env('WEIXIN_MCH_ID'),
             'nonce_str'=>str_random(16),
              'sign_type'     => 'MD5',
              'body'          => '0214'.mt_rand(1111,9999) . str_random(6),
              'out_trade_no'  => $order_id,                       //本地订单号
              'total_fee'     => $total_fee,
              'spbill_create_ip'  => $_SERVER['REMOTE_ADDR'],     //客户端IP
              'notify_url'    => $this->weixin_notify_url,        //通知回调地址
              'trade_type'    => 'NATIVE'
          ];
          $this->values= [];
          $this->values=$order_info;
          $this->SetSign();
          $xml=$this->ToXml();
          $rs = $this->postXmlCurl($xml, $this->weixin_unifiedorder_url, $useCert = false, $second = 30);
          print_r($rs);
          $data =simplexml_load_string($rs);
          print_r($data);
         echo 'code_url:'.$data->code_url;echo'<br>';

    }
   protected function ToXml(){
    if(!is_array($this->values)|| count($this->values)<= 0){
        die("数组数据出现异常！");
       }
       $xml="<mxl>";
            foreach ($this->values as $key=>$val){
                if(is_numeric($val)){
                    $xml.="<".$key.">".$val."</".$key.">";
                }else{
                    $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
                }
            }
            $xml.="</xml>";
            return $xml;
        }
    private  function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
//		if($useCert == true){
//			//设置证书
//			//使用证书：cert 与 key 分别属于两个.pem文件
//			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
//			curl_setopt($ch,CURLOPT_SSLCERT, WxPayConfig::SSLCERT_PATH);
//			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
//			curl_setopt($ch,CURLOPT_SSLKEY, WxPayConfig::SSLKEY_PATH);
//		}
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            die("curl出错，错误码:$error");
        }
    }
public function SetSign(){
    $sign=$this->MakeSign();
    $this->values['sign']=$sign;
    return $sign;
}
private function MakeSign(){
    ksort($this->values);
    $string=$this->ToUrlParams();
    $string=$string."&key=".env('WEIXN_MCH_KEY');
    $string=md5($string);
    $result=strtoupper($string);
    return $result;
}
protected function ToUrlParams(){
    $buff ="";
    foreach ($this->values as $k=>$v){
        if($k!="sign"&& $v !="" && !is_array($v)){
            $buff .= $k."=".$v."&";
        }
    }
    $buff = trim($buff,"&");
    return $buff;
}

    /**
     * 微信支付回调
     */
    public function notice()
    {
        $data = file_get_contents("php://input");

        //记录日志
        $log_str = date('Y-m-d H:i:s') . "\n" . $data . "\n<<<<<<<";
        file_put_contents('logs/wx_pay_notice.log',$log_str,FILE_APPEND);

        $xml = simplexml_load_string($data);

        if($xml->result_code=='SUCCESS' && $xml->return_code=='SUCCESS'){      //微信支付成功回调
            //验证签名
            $sign = true;

            if($sign){       //签名验证成功
                // 逻辑处理  订单状态更新

            }else{
                // 验签失败
                echo '验签失败，IP: '.$_SERVER['REMOTE_ADDR'];
                //  记录日志
            }

        }

        $response = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        echo $response;

    }
}
