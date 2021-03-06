<?php

namespace frontend\controllers;

use Yii;
use yii\helpers\Url;
use Overtrue\Wechat\Auth;
use Overtrue\Wechat\Notice;
use Overtrue\Wechat\Staff;
use Overtrue\Wechat\Js;
use backend\models\User; 
use backend\models\Loan; 
use backend\models\Student;
use backend\models\School;

class LoanController extends \yii\web\Controller
{
    public $enableCsrfValidation = false;

    public function actionBank()
    {
        session_start();
        $user = $_SESSION['user'];
        $s = Student::findOne($user['openid']);
        $u = User::findOne($user['openid']);
        $l = Loan::findOne(['wechat_id'=>$user['openid']]);
        $stu_id = $_POST['stu_id'];
        $school_id = $_POST['school_id'];
        $dorm = $_POST['dorm'];
        $grade = $_POST['grade'];
        $name = $_POST['name'];

        if (isset($l) AND $l->status>0) {
            return $this->redirect(['loan/success']);
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (!isset($s)) {
                $s = new Student;
                $s->wechat_id = $user['openid'];
            }
            $s->stu_id = $stu_id;
            $s->school_id = $school_id;
            $s->dorm = $dorm;
            $s->grade = $grade;
            $s->created_at = time();
            $s->save();

            $u->name = $name;
            $u->updateAttributes(['name']);
            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $appId = Yii::$app->params['wechat_appid'];
        $secret = Yii::$app->params['wechat_appsecret'];
        $js = new Js($appId, $secret); 
        return $this->renderPartial('bank', array('user'=>$u,'loan'=>$l,'js'=>$js));
    }

    public function actionLend($type='common')
    {
        session_start();
        $user = $_SESSION['user'];
        $l = Loan::findOne(['wechat_id'=>$user['openid']]);
        if (isset($l) AND $l->status>0) {
            return $this->redirect(['loan/success']);
        }
        $rate = ($type=='common')?0.0002:0.0001;
        $appId = Yii::$app->params['wechat_appid'];
        $secret = Yii::$app->params['wechat_appsecret'];
        $js = new Js($appId, $secret); 
        return $this->renderPartial('lend', ['v'=>Yii::$app->params['assets_version'], 'rate'=>$rate,'js'=>$js]);
    }

    public function actionIndex()
    {
        $appId = Yii::$app->params['wechat_appid'];
        $secret = Yii::$app->params['wechat_appsecret'];

        session_start();
        if (empty($_SESSION['user'])) {
            $auth = new Auth($appId, $secret);
            $user = $auth->authorize(Url::to(['loan/index'], TRUE), 'snsapi_base'); // 返回用户 Bag
            $_SESSION['user'] = $user;
        }
        $user = $_SESSION['user'];
        
        $open_id = $user['openid'];

        $u = User::findOne($open_id);
        if (!isset($u)) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $u = new User;
                $u->wechat_id = $open_id;
                $u->name = '';
                $u->id = '';
                $u->mobile = '';
                $u->bank = '';
                $u->bank_id = '';
                $u->created_at = time();
                $u->save();
                $transaction->commit();
            } catch(\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }
        } else {
            $l = Loan::findOne(['wechat_id'=>$open_id]);
            if (isset($l) and $l->status>0) {
                return $this->redirect(['loan/success']);
            }
        }
        $js = new Js($appId, $secret); 
        return $this->renderPartial('index',['v'=>Yii::$app->params['assets_version'], 'js'=>$js]);
    }

    public function actionSchool()
    {
        $money = $_REQUEST['money'];
        $duration = $_REQUEST['duration'];
        $rate = $_REQUEST['rate'];
        
        session_start();
        $user = $_SESSION['user'];
        $loan = Loan::findOne(['wechat_id'=>$user['openid']]);
        $transaction = Yii::$app->db->beginTransaction();
        try {
            if (isset($loan) AND $loan->status==0) {
                $loan->money = $money;
                $loan->duration = $duration;
                $loan->rate = $rate;
                $loan->start_at = time();
                $loan->end_at = time()+$duration*3600*24;
                $loan->save();
            } else if (!isset($loan)) {
                $loan = new Loan;
                $loan->wechat_id = $user['openid'];
                $loan->money = $money;
                $loan->duration = $duration;
                $loan->rate = $rate;
                $loan->status = 0;
                $loan->start_at = time();
                $loan->end_at = time()+$duration*3600*24;
                $loan->created_at = time();
                $loan->save();
            } else if ($loan->status>0) {
                return $this->redirect(['loan/success']);
            }
            $transaction->commit();
        } catch(\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $appId = Yii::$app->params['wechat_appid'];
        $secret = Yii::$app->params['wechat_appsecret'];
        $js = new Js($appId, $secret); 
        return $this->renderPartial('school', array('from'=>$rate==0.0001?'graduate':'common', 'js'=>$js));
    }

    public function actionSuccess()
    {
        session_start();
        if (!isset($_SESSION['user'])) {
            return $this->redirect(['loan/index']);
        }
        $user = $_SESSION['user'];
        $student = Student::findOne($user['openid']);
        if (isset($_POST['id'])) {
            $u = User::findOne($user['openid']);
            $l = Loan::findOne(['wechat_id'=>$user['openid']]);
            $id = $_POST['id'];
            $mobile = $_POST['mobile'];
            $bank = $_POST['bank'];
            $bank_id = $_POST['bank_id'];

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $u->id = $id;
                $u->mobile = $mobile;
                $u->bank = $bank;
                $u->bank_id = $bank_id;
                $u->save();
                $l->status = 1;
                $l->updateAttributes(['status']);
                $transaction->commit();
            } catch(\Exception $e) {
                $transaction->rollBack();
                throw $e;
            }

            $appId = Yii::$app->params['wechat_appid'];
            $secret = Yii::$app->params['wechat_appsecret'];
            $notice = new Notice($appId, $secret);
            //通知放款员面签
            $s = School::findOne($student->school_id);
            $templateId = Yii::$app->params['templateId_task'];
            $url = Url::to(['loan/me'],TRUE);
            $color = '#FF0000';
            $data = array(
                "first"    => "大牛君呐，又一位大牛来了",
                "keyword1" => "{$u->name}，借款{$l->money}元，借{$l->duration}天，手机号{$u->mobile}，专业{$s->depart}，年级{$student->grade}",
                "keyword2" => "待办",
                "remark"   => "请快速约起来~",
            );
            if (floor($student->school_id/100)==101) {
                $userId = Yii::$app->params['pku101_supporter'];
            } else if (floor($student->school_id/100)==102) {
                $userId = Yii::$app->params['pku102_supporter'];
            }
            $messageId = $notice->uses($templateId)->withUrl($url)->andData($data)->andReceiver($userId)->send();
        }
        $l = Loan::findOne(['wechat_id'=>$user['openid']]);
        $appId = Yii::$app->params['wechat_appid'];
        $secret = Yii::$app->params['wechat_appsecret'];
        $js = new Js($appId, $secret); 
        if ($l->status==1) {
            if (floor($student->school_id/100)==101) {
                return $this->renderPartial('success', ['mobile'=>'18910279503', 'js'=>$js]);
            } else if (floor($student->school_id/100)==102) {
                return $this->renderPartial('success', ['mobile'=>'18810521341', 'js'=>$js]);
            }
        } else if ($l->status>1) {
            return $this->renderPartial('success2', ['js'=>$js]);
        } else {
            return $this->redirect(['loan/index']);
        }
    }

    public function actionMe()
    {
        $appId = Yii::$app->params['wechat_appid'];
        $secret = Yii::$app->params['wechat_appsecret'];

        session_start();
        if (empty($_SESSION['user'])) {
            $auth = new Auth($appId, $secret);
            $user = $auth->authorize(Url::to(['loan/me'], TRUE), 'snsapi_base'); // 返回用户 Bag
            $_SESSION['user'] = $user;
        }
        $user = $_SESSION['user'];

        $open_id = $user['openid'];
        if ($open_id==Yii::$app->params['pku101_supporter']) {
            $r = Yii::$app->db->createCommand('SELECT u.name,u.mobile,s.depart, l.loan_id FROM user u LEFT JOIN loan l ON u.wechat_id=l.wechat_id LEFT JOIN student stu ON u.wechat_id=stu.wechat_id LEFT JOIN school s ON stu.school_id=s.school_id WHERE stu.school_id LIKE "101%" AND l.status=1')->queryAll();
            return $this->renderPartial('personal_list',['r'=>$r]);
        } else if($open_id==Yii::$app->params['pku102_supporter']) {
            $r = Yii::$app->db->createCommand('SELECT u.name,u.mobile,s.depart, l.loan_id FROM user u LEFT JOIN loan l ON u.wechat_id=l.wechat_id LEFT JOIN student stu ON u.wechat_id=stu.wechat_id LEFT JOIN school s ON stu.school_id=s.school_id WHERE stu.school_id LIKE "102%" AND l.status=1')->queryAll();
            return $this->renderPartial('personal_list',['r'=>$r]);
        } else if($open_id==Yii::$app->params['demo_supporter']) {
            $r = Yii::$app->db->createCommand('SELECT u.name,u.bank,u.bank_id, l.loan_id,l.money,l.duration,t.name AS reviewer,l.status FROM user u LEFT JOIN loan l ON u.wechat_id=l.wechat_id LEFT JOIN user t ON l.reviewer=t.wechat_id WHERE l.status=2')->queryAll();
            return $this->renderPartial('bank_list', ['verification'=>'demo','r'=>$r]);
        } else if($open_id==Yii::$app->params['admin_supporter']) {
            $r = Yii::$app->db->createCommand('SELECT u.name,u.bank,u.bank_id, l.loan_id,l.money,l.duration,t.name AS reviewer,l.status FROM user u LEFT JOIN loan l ON u.wechat_id=l.wechat_id LEFT JOIN user t ON l.reviewer=t.wechat_id WHERE l.status=2')->queryAll();
            return $this->renderPartial('bank_list', ['verification'=>'admin','r'=>$r]);
        } else {
            $l = Loan::findOne(['wechat_id'=>$open_id]);
            if (isset($l) AND $l->status>=1) {
                return $this->redirect(['loan/success']);
            } else {
                return $this->redirect(['loan/index']);
            }
        }
    }

    public function actionPerson($loan_id)
    {
        session_start();
        $user = $_SESSION['user'];
        $open_id = $user['openid'];
        if ($open_id==Yii::$app->params['pku101_supporter'] OR $open_id==Yii::$app->params['pku102_supporter']) {
            $r = Yii::$app->db->createCommand('SELECT l.loan_id,l.rate,l.duration,l.money,u.name,u.id,stu.dorm,stu.stu_id,.s.depart,u.mobile FROM loan l LEFT JOIN user u ON l.wechat_id=u.wechat_id LEFT JOIN student stu ON l.wechat_id=stu.wechat_id LEFT JOIN school s ON stu.school_id=s.school_id WHERE l.loan_id=:loan_id')->bindValue(':loan_id',$loan_id)->queryOne();
            return $this->renderPartial('personal_details', ['r'=>$r]);
        } else {
            return $this->renderPartial('404');
        }
        
    }

    public function actionOperate($loan_id, $operation=-1)
    {
        $appId = Yii::$app->params['wechat_appid'];
        $secret = Yii::$app->params['wechat_appsecret'];

        session_start();
        if (empty($_SESSION['user'])) {
            $auth = new Auth($appId, $secret);
            $user = $auth->authorize(Url::to(['loan/me'], TRUE), 'snsapi_base'); // 返回用户 Bag
            $_SESSION['user'] = $user;
        }
        $user = $_SESSION['user'];
        $open_id = $user['openid'];
        if (($operation==-1 OR $operation==2) AND ($open_id==Yii::$app->params['pku101_supporter'] OR $open_id==Yii::$app->params['pku102_supporter'])) {
            $l = Loan::findOne($loan_id);
            $u = User::findOne($l->wechat_id);
            $l->reviewer = $open_id;
            $l->status = $operation;
            $l->updateAttributes(['reviewer', 'status']);

            $notice = new Notice($appId, $secret);
            if ($operation==2) {
                $templateId = Yii::$app->params['templateId_review'];
                $url1 = Url::to(['loan/me'],TRUE);
                $color = '#FF0000';
                $data1 = array(
                    "first"    => "又一位大牛{$u->name}通过审核！",
                    "keyword1" => "{$l->money}元",
                    "keyword2" => "{$l->duration}天",
                    "keyword3" => "{$l->rate}*每个月的天数",
                    "keyword4" => "通过",
                    "remark"   => "姓名：{$u->name}，银行类别：{$u->bank}，银行卡号：{$u->bank_id}，借款额{$l->money}元，手机：{$u->mobile}",
                );
                $messageId = $notice->uses($templateId)->withUrl($url1)->andData($data1)->andReceiver(Yii::$app->params['demo_supporter'])->send();
                $messageId = $notice->uses($templateId)->withUrl($url1)->andData($data1)->andReceiver(Yii::$app->params['admin_supporter'])->send();
                $url2 = Url::to(['loan/success'],TRUE);
                $data2 = array(
                    "first"    => "大牛您好！您的借款申请已通过审核",
                    "keyword1" => "{$l->money}元",
                    "keyword2" => "{$l->duration}天",
                    "keyword3" => "{$l->rate}*每个月的天数",
                    "keyword4" => "通过",
                    "remark"   => "我们会在 24 小时内给您汇款，请耐心等待。",
                );
                $messageId = $notice->uses($templateId)->withUrl($url2)->andData($data2)->andReceiver($l->wechat_id)->send();
            }
        } else if ($operation==3 AND $open_id==Yii::$app->params['admin_supporter']) {
            $l = Loan::findOne($loan_id);
            $u = User::findOne($l->wechat_id);
            $l->status = $operation;
            $l->updateAttributes(['status']);

            $notice = new Notice($appId, $secret);
            $templateId = Yii::$app->params['templateId_remit'];
            $bank_id = substr($u->bank_id, -4);
            $url = Url::to(['loan/success'],TRUE);
            $data = array(
                "first"    => "大牛您好，您申请的借款已汇入您尾号为{$bank_id}的银行卡中",
                "keyword1" => "已汇款",
                "keyword2" => "大牛贷",
                "keyword3" => date("Ymd"),
                "remark"   => "请及时查看",
            );
            $messageId = $notice->uses($templateId)->withUrl($url)->andData($data)->andReceiver($l->wechat_id)->send();
        }

        return $this->redirect(['loan/me']);
    }
}
