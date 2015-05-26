<?php

namespace frontend\controllers;

use Yii;
use yii\helpers\Url;
use Overtrue\Wechat\Server;
use Overtrue\Wechat\Message;
use Overtrue\Wechat\Menu;
use Overtrue\Wechat\MenuItem;

class WechatController extends \yii\web\Controller
{
    public function actionMessage()
    {
        $appid = Yii::$app->params['wechat_appid'];
        $secret = Yii::$app->params['wechat_appsecret'];
        $token = Yii::$app->params['wechat_token'];
        $encodingAESKey = Yii::$app->params['wechat_aeskey'];

        $server = new Server($appId, $token, $encodingAESKey);
        $server->on('message', function($message) {
            return Message::make('text')->content('儿童节就是大牛君和大家见面的日子啦，各位大牛敬请期待哦！没事可以调戏调戏客服');
        });

        $server->on('event', 'subscribe', function($event){
          return Message::make('text')->content('大牛贷学长正紧锣密鼓开张中！感谢您的关注，正式上线后我们会第一时间通知您！');
        });

        $result = $server->serve();

        echo $result;
    }

    public function actionSetMenu()
    {
        $appid = Yii::$app->params['wechat_appid'];
        $secret = Yii::$app->params['wechat_appsecret'];

        $menu = new Menu($appId, $secret);
        $menus = array(
            new MenuItem("申请贷款", 'view', Url::to(['loan/index'])),
            new MenuItem("我", 'click', 'ME'),
        );

        try {
            $menu->set($menus);// 请求微信服务器
            echo '设置成功！';
        } catch (\Exception $e) {
            echo '设置失败：' . $e->getMessage();
        }
    }
}