<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller{
    public $uid;
    public $group;
    public $username;
    public $priv;

	public function __construct()
    {
        parent::__construct();
        $this->CI =& get_instance();
        $this->CI->load->model('Data_model');
        $this->load->helper(array('url'));
        //加载验证类
        $this->load->library(array('form_validation','pagination'));

        $this->loginSession();

       // $this->checkPurview();        $this->priv =$this->getPurs('',1);
        $this->load->vars('priv',$this->priv);
        if($this->uid != 1){//超级管理员不用验证  admin 123456
            $this->checkPurview();
        }else{
            
        }
    }
    private function loginSession()
    {
        if(!isset($_SESSION['user'])) {

            if($this->input->is_ajax_request())
            {
                echo json_encode(array('s'=>0,'msg'=>'登陆已经超时，请重新登陆'));
            } else {
                redirect(site_url('/login/index'));
            }
            //exit();
        }
        $this->uid        = intval($_SESSION['user']['id']);
        $this->group_id   = intval($_SESSION['user']['user_group_id']);
        $this->username   = $_SESSION['user']['username'];
        $this->company_id = intval($_SESSION['user']['company_id']);
        $this->middler_id = intval($_SESSION['user']['middler_id']);
        $this->middler_com_id = intval($_SESSION['user']['middler_com_id']);
        $this->mobile     = $_SESSION['user']['mobile'];
    }

    // 判断是否有权限
    protected function checkPurview()
    {
        //获取url
        $class = $this->CI->uri->segment(1);
        $method = $this->CI->uri->segment(2);
        $class = $class ? strtolower($class) : 'home';
        $method = $method ? strtolower($method) : 'index';
        $route = '/' . $class . '/' . $method;
        $this->load->vars('route',$route);

        //获取权限信息
        $url_wh = array('url' => trim($route), 'status' => 1, 'type' => 2);
        $url = $this->Data_model->getSingle($url_wh,'user_rule');

        //获取用户权限
        $user_group_wh = array('id' => $this->group_id);
        $user_group = $this->Data_model->getSingle($user_group_wh,'user_group');

        if(!$user_group['rule']) {
            $msg_head = '无权操作';
            $msg = '你没有权限访问该页面';
            return show_error($msg,403,$msg_head);
            exit();
        }

        $user_rule = explode(',',$user_group['rule']);
        $this->priv =$this->getPurs($user_rule);
        $this->load->vars('priv',$this->priv);

        if(in_array($url['id'],$user_rule)){
            return true;
        } else {
            if($this->input->is_ajax_request()){
                echo json_encode(array('s'=>0,'msg'=>'无权操作'));
                exit();
            }else{
                $msg_head = '无权操作';
                $msg = '你没有权限访问该页面';
                return show_error($msg,403,$msg_head);
                exit();
            }
        }
    }

     //获得权限数组
    private function getPurs($rule,$admin=0)
    {
        $where = array('id' => $rule, 'status' => 1);
        $where = $admin ? array() : $where;
        $order_by = 'id asc';
        $rule_arr = $this->Data_model->getData($where,$order_by,0,0,'user_rule','url');

        $ret = array();
        foreach ($rule_arr as $key => $value) {
            $ret[] = $value['url'];
        }
        return $ret;
    }

    public function sendSMS($mobile, $code)
    {
        //时区设置：亚洲/上海
        date_default_timezone_set('Asia/Shanghai');
        require APPPATH.'/libraries/Alidayu/TopClient.php';
        require APPPATH.'/libraries/Alidayu/ResultSet.php';
        require APPPATH.'/libraries/Alidayu/RequestCheckUtil.php';
        require APPPATH.'/libraries/Alidayu/TopLogger.php';
        require APPPATH.'/libraries/Alidayu/AlibabaAliqinFcSmsNumSendRequest.php';
        $c = new \TopClient;
        $config = array();
        //短信内容：公司名/名牌名/产品名
        $product = '慢吞吞之家';//$config['sms_product'];
        //App Key的值 这个在开发者控制台的应用管理点击你添加过的应用就有了
        $c->appkey = '23536262';//$config['sms_appkey'];
        //App Secret的值也是在哪里一起的 你点击查看就有了
        $c->secretKey = 'faf94fe13da1f4a57233a49c91c711d1';//$config['sms_secretKey'];
        //这个是用户名记录那个用户操作
        $req = new \AlibabaAliqinFcSmsNumSendRequest;
        //代理人编号 可选
        $req->setExtend("123456");
        //短信类型 此处默认 不用修改
        $req->setSmsType("normal");
        //短信签名 必须
        $req->setSmsFreeSignName("慢吞吞之家");
        //短信模板 必须
        $req->setSmsParam("{\"code\":\"$code\",\"product\":\"$product\"}");
        //短信接收号码 支持单个或多个手机号码，传入号码为11位手机号码，不能加0或+86。群发短信需传入多个号码，以英文逗号分隔，
        $req->setRecNum("$mobile");
        //短信模板ID，传入的模板必须是在阿里大鱼“管理中心-短信模板管理”中的可用模板。
        $config['sms_templateCode'] = 'SMS_26095230';
        $req->setSmsTemplateCode($config['sms_templateCode']); // templateCode

        $c->format='json';
        //发送短信
        $resp = $c->execute($req);
        //短信发送成功返回True，失败返回false
        //if (!$resp)
        if ($resp && $resp->result)   // if($resp->result->success == true)
        {
            // 从数据库中查询是否有验证码
            $data = $this->Data_model->getSingle("code = '$code' and add_time > ".(time() - 60*60),'sms_log');
            // 没有就插入验证码,供验证用
            empty($data) && $this->Data_model->addData(array('mobile' => $mobile, 'code' => $code, 'add_time' => time(), 'session_id' =>$this->uid),'sms_log' );
            return true;
        } else {
            return false;
        }
    }

    public function sms_log($mobile,$code,$session_id)
    {
        //判断是否存在验证码
        $data = $this->Data_model->getSingle(array('mobile'=>$mobile,'session_id'=>$session_id),'sms_log');
        //获取时间配置
        $sms_time_out = 120;
        //120秒以内不可重复发送
        if($data && (time() - $data['add_time']) < $sms_time_out)
            return array('status'=>-1,'msg'=>$sms_time_out.'秒内不允许重复发送');

        $row = $this->Data_model->addData(array('mobile'=>$mobile,'code'=>$code,'add_time'=>time(),'session_id'=>$session_id),'sms_log');
        if(!$row)
            return array('status'=>-1,'msg'=>'发送失败');
        //$send = sendSMS($mobile,'您好，你的验证码是：'.$code);
        $send = $this->sendSMS($mobile,$code);
        if(!$send)
            return array('status'=>-1,'msg'=>'发送失败');
        return array('status'=>1,'msg'=>'发送成功');
    }

    /**
     * 短信验证码验证
     */
    public function sms_code_verify($mobile,$code,$session_id)
    {
        //判断是否存在验证码
        //echo $mobile,$code,$session_id;exit;
        $data = $this->Data_model->getSingle(array('mobile'=>$mobile,'session_id'=>$session_id,'code'=>$code),'sms_log');
        if(empty($data))
            return array('status'=>-1,'msg'=>'手机验证码不匹配');

        //获取时间配置
        $sms_time_out = 120;
        //验证是否过时
        if((time() - $data['add_time']) > $sms_time_out)
            return array('status'=>-1,'msg'=>'手机验证码超时'); //超时处理

        $this->Data_model->delData($data['id'],'sms_log');
        return array('status'=>1,'msg'=>'验证成功');
    }

    //导出
    public function exports($name,$title,$content)
    {
        $this->load->library('Excels');
        $this->excels->exports($content,$title,$name);
    }

    /*  @获取对某个卖家应收取的汇率
     *  @param $platform_id
     *  @return int
    */
    protected function get_rate($platform_id)
    {
        $rate_table = 'company_rate a';
        $rate_join = array(
            array('currency b','b.id = a.currency_id','left'),
            array('platform c','c.currency_id = b.id','left')
        );

        $rate_wh = array(
            'c.id' => intval($platform_id),
            'a.company_id' => $this->company_id,
            'a.status' => 1
        );
        $rate_fields = 'a.id,a.real_rate,b.name,b.rate,b.code';
        $rate_order = $rate_group = '';
        $rate_first = $rate_num = 0;
        $rate_info = $this->Data_model->getJoinData($rate_table, $rate_join, $rate_wh, $rate_fields, $rate_order, $rate_group, $rate_first, $rate_num);

        $rate = 0;
        // 当前货币汇率 + 某个公司的浮动汇率
        if($rate_info) {
            $rate = floatval($rate_info[0]['rate']) + floatval($rate_info[0]['real_rate']);
        }

        return $rate;
    }

    /* @获取对某个卖家的刷单或刷屏操作费
     * 计算公式：操作费 = order_price + comment_price + collection_price + relation_visit + fast_comment_price
     * @param $type [1 - ?, 2 - ?]
     * @param $collection [是否加入收藏]
     * @param $is_relation [是否关联访问]
     * @param $fast_comment [是否上评]
     * @return int
     */
    protected function get_handle_price($type=1,$collection=2,$is_relation=2,$fast_comment=2){
        $price = 0;
        //获取费用信息
        $price_wh['company_id'] = $this->company_id;
        $price_info = $this->Data_model->getSingle($price_wh,'company_price');
        if($type == 1){
            $price = $price + $price_info['order_price'];
        }else{
            $price = $price + $price_info['comment_price'];
        }
        if($collection == 1){
            $price = $price + $price_info['collection'];
        }
        if($is_relation == 1){
            $price = $price + $price_info['relation_visit'];
        }
        $price = $price + $price_info['logistics_code'];
        if($fast_comment == 1){
            $price = $price + $price_info['fast_comment'];
        }

        return $price;
    }

    // 判断是否任务冲突
    protected function is_task($ASIN,$type,$start_time,$end_time)
    {
        //查询任务
        $table = 'product a';
        $where = $join = array();
        $join[] = array('product_num b','b.productid = a.id','left');

        empty($ASIN) ?  '' : $where['a.ASIN'] = $ASIN;
        empty($type) ?  '' : $where['a.type'] = $type;
        $where['a.company_id'] = $this->company_id;

        $sql1 = 'b.tasktime between ' . strtotime($start_time) . ' and ' . strtotime($end_time);
        $this->db->where($sql1);

        $group = '';
        $first = $num = 0;
        $order = 'b.tasktime ASC';
        $fields = 'b.id,a.ASIN,a.type,b.tasktime';

        $list = $this->Data_model->getJoinData($table, $join, $where, $fields, $order, $group, $first, $num);

        return $list;
    }

    //发送通知给中介
    protected function send_notice($msg)
    {
        //获取接受人
        $order_by = '';
        $com_wh = array('company_id' => $this->middler_com_id, 'type' => 1);

        $receive_user = $this->Data_model->getData($com_wh,$order_by,0,0,'user');
        if($receive_user) {
            //发送的消息数据
            $_msg = array();
            $_msg['message_type_id'] = 1;
            $_msg['title'] = $msg['title'];
            $_msg['content'] = $msg['content'];
            $_msg['sender'] = $this->uid;
            $_msg['create_time'] = time();
            $_msg['company_id'] = $this->middler_com_id;
            $this->db->trans_start();
            // 插入消息数据
            $this->db->set($_msg)->insert('message');
            $msg_id = $this->db->insert_id();
            // 插入收信人表
            foreach($receive_user as $a) {
                $accp_data = array();
                $accp_data['message_id'] = intval($msg_id);
                $accp_data['accepter'] = intval($a['id']);
                $this->db->set($accp_data)->insert('message_link');
            }
            $this->db->trans_complete();

            return ($this->db->trans_status() === false) ? 2 : 1;
        }
    }

    //查询平台
    protected function query_platform($where = null)
    {
        $table = 'platform a';
        $join = array(
            array('currency b','b.id = a.currency_id','left')
        );
        $where['a.status'] = $where['b.status'] = 1;
        $fields = 'a.id,a.name,a.url,b.name as currency,b.code,b.rate,a.create_time';
        $order = 'a.id asc';
        $group = '';
        $first = $num = 0;
        $list = $this->Data_model->getJoinData($table, $join, $where, $fields, $order, $group, $first, $num);

        return $list;
    }

    //查询公司的平台
    protected function query_middler_platform($company_id = '',$first = 0,$num = 0,$where = null)
    {
        $list = array();
        if($company_id)
        {
            $table = 'com_platform a';
            $join = array(
                array('platform b','b.id = a.platform_id','left'),
                array('currency c','c.id = b.currency_id','left')
            );
            $where['a.company_id'] = $company_id;
            $where['a.status'] = $where['b.status'] = $where['c.status'] = 1;
            $fields = 'b.id,b.name,b.url,c.name as currency,c.code,c.rate,a.task_start,a.task_end,b.create_time';
            $order = 'b.id asc';
            $group = '';
            $list = $this->Data_model->getJoinData($table, $join, $where, $fields, $order, $group, $first, $num);
        }

        return $list;
    }

    //获取用户名
    protected function get_user_name($uid)
    {
        $user = $this->Data_model->getSingle(array('id' => intval($uid)),'user');
        return $user ? $user['username'] : "";
    }

    //获取该中介下所以的成员
    protected function get_company_member()
    {
        $com_wh = array();
        $com_wh['company_id'] = $this->company_id;
        $order_by = '';
        $com_people = $this->Data_model->getData($com_wh,$order_by,0,0,'user');
        return $com_people ? $com_people : "";
    }

    //获取平台名字
    protected function get_platform_name($platform_id)
    {
        $platform = $this->Data_model->getSingle(array('id'=>(int)$platform_id),'platform');
        return $platform ? $platform['name'] : "";
    }

    //获取平台网址
    protected function get_platform_url($platform_id)
    {
        $platform = $this->Data_model->getSingle(array('id'=>(int)$platform_id),'platform');
        return $platform ? $platform['url'] : "";
    }

    //获取店铺名字
    protected function get_shop_name($shop_id)
    {
        $shop = $this->Data_model->getSingle(array('id'=>(int)$shop_id),'store');
        return $shop ? $shop['name'] : "";
    }

    //获取平台货币
    protected function get_currency($platform_id = '')
    {
        $code = '￥';
        if($platform_id) {
            $pt_wh = array();
            $pt_wh['a.id'] = $platform_id;
            $pt_info = $this->query_platform($pt_wh);
            $code = $pt_info[0]['code'];
        }

        return $code;
    }
    //代替字符串空格
    protected function emptyreplace($str,$s)
    {
        // == 优先级大于 &&
        $str = str_replace('　', ' ', $str);
        $str = str_replace(' ', ' ', $str);

        for ($i=0 ; $i < strlen($str); $i++) {
            if(empty($str[$i])) {
                $str[$i] = $s;
            }
        }
        return $str;
    }

    // 判断任务是否超过预算(type : 1 刷单刷评 2 QA 3 收藏 4 游评)
    public function is_month_budget($money = 0,$id = null,$type = 1)
    {
        $month_start = date('Y-m-01', strtotime(date("Y-m-d")));
        $month_end = date('Y-m-d', strtotime("$month_start +1 month -1 day"));
        $month_end = $month_end . ' 23:59:59';
        $month_start = strtotime($month_start);
        $month_end = strtotime($month_end);

        //查询个人信息
        $user_wh['id'] = $this->uid;
        $user_info = $this->Data_model->getSingle($user_wh,'`user`');
        if($user_info['month_budget'] == 0){
            return true;
        }

        $total_price = 0;
        //刷单刷评任务
        $task_wh['adduser'] = $this->uid;
        $task_wh['create_time >='] = $month_start;
        $task_wh['create_time <='] = $month_end;
        if($id != null && $type == 1){
            $task_wh['id <>'] = $id;
        }
        $task =  $this->Data_model->getData($task_wh,'',0,0,'product');
        for($i=0;$i<count($task);$i++){
            $total_price = $total_price + $task[$i]['total_money'];
        }
        //QA任务
        $qatask_wh['create_man'] = $this->uid;
        $qatask_wh['create_time >='] = $month_start;
        $qatask_wh['create_time <='] = $month_end;
        if($id != null && $type == 2){
            $qatask_wh['id <>'] = $id;
        }
        $qatask =  $this->Data_model->getData($qatask_wh,'',0,0,'qa_task');
        for($i=0;$i<count($qatask);$i++){
            $total_price = $total_price + $qatask[$i]['total_price'];
        }
        //加入收藏任务
        $collect_task_wh['create_man'] = $this->uid;
        $collect_task_wh['create_time >='] = $month_start;
        $collect_task_wh['create_time <='] = $month_end;
        if($id != null && $type == 3){
            $collect_task_wh['id <>'] = $id;
        }
        $collect_task =  $this->Data_model->getData($collect_task_wh,'',0,0,'collect_task');
        for($i=0;$i<count($collect_task);$i++){
            $total_price = $total_price + $collect_task[$i]['total_price'];
        }
        //游客评价任务
        $uc_task_wh['create_man'] = $this->uid;
        $uc_task_wh['create_time >='] = $month_start;
        $uc_task_wh['create_time <='] = $month_end;
        if($id != null && $type == 4){
            $uc_task_wh['id  <>'] = $id;
        }
        $uc_task =  $this->Data_model->getData($uc_task_wh,'',0,0,'user_comment_task');
        for($i=0;$i<count($uc_task);$i++){
            $total_price = $total_price + $uc_task[$i]['total_price'];
        }
        $new_total = $total_price + $money;
        $new_total = $new_total * 100;
        $new_total = (int)$new_total;
        $user_info['month_budget'] = $user_info['month_budget'] * 100;
        $user_info['month_budget'] = (int)$user_info['month_budget'];
        if($new_total > $user_info['month_budget']){
            return false;
        }else{
            return true;
        }
    }
    public function outputScript($message, $url, $type="alert")
    {
        $script = "<script>";

        switch($type)
        {
            case "alert":
                $script .= "alert('{$message}')"; break;
            case "confirm":
                $script .= "confirm(1231)"; break;

            default:
        }
        $script .= ";location.href='{$url}';</script>";
        echo $script;exit;
    }
}
