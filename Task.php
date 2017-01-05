<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Task extends MY_Controller {
    public function __construct(){
        parent::__construct();
    }

    public function index(){
        $gets = $this->input->get();
        $this->load->vars('gets',$gets);
        //获取平台数据
        $platform_data = $this->query_middler_platform($this->middler_com_id);
        //获取店铺
        if(isset($gets['platform_s']) && $gets['platform_s'] != ''){
            // 条件
            $shop_wh = array();
            $shop_wh['platform_id'] = intval($gets['platform_s']);
            $shop_wh['company_id'] = $this->company_id;
            $order_by = '';
            $shops =  $this->Data_model->getData($shop_wh,$order_by,0,0,'store');
            if($shops){
                $this->load->vars('shops',$shops);
            }
        }
        //获取该公司成员
        $user_wh = array();
        $user_wh['company_id'] = $this->company_id;
        $user_wh['type'] = 2;
        $users = $this->Data_model->getData($user_wh,'',0,0,'user');
        //获取该执行成员
        $middler_wh = array();
        $middler_wh['company_id'] = $this->middler_com_id;
        $middler_wh['type'] = 1;
        $middlers = $this->Data_model->getData($middler_wh,'',0,0,'user');

        // 获取分页
        $currentpage = intval($this->uri->segment(3));
        $currentpage = $currentpage?$currentpage:1;

        $row_num = 10;
        //分页
        $page = ($currentpage - 1) * $row_num;

        //获取任务数据
        $task_table = 'product a';
        $task_join = array();
        $task_join[] = array('platform b','b.id = a.platform','left');
        $task_join[] = array('store c','c.id = a.execute_shop','left');
        $task_wh = array();
        //产品ASIN条件筛选
        if(isset($gets['product_name']) && $gets['product_name'] != ''){
            $task_wh['a.ASIN like'] = '%'.$gets['product_name'].'%';
        }
        //优惠券筛选
        if(isset($gets['coupon_s']) && $gets['coupon_s'] != ''){
            $task_wh['a.coupon like'] = '%'.$gets['coupon_s'].'%';
        }
        //关键字筛选
        if(isset($gets['key_word_s']) && $gets['key_word_s'] != ''){
            $task_wh['a.key_word like'] = '%'.$gets['key_word_s'].'%';
        }
        //平台筛选
        if(isset($gets['platform_s']) && $gets['platform_s'] != ''){
            $task_wh['b.id'] = $gets['platform_s'];
        }
        //店铺筛选
        if(isset($gets['shop_s']) && $gets['shop_s'] != ''){
            $task_wh['c.id'] = $gets['shop_s'];
        }
        //关联访问筛选
        if(isset($gets['is_relevance']) && $gets['is_relevance'] != ''){
            if($gets['is_relevance'] == '1'){
                $task_wh['a.bind_ASIN <>'] = null;
            }else{
                $task_wh['a.bind_ASIN'] = null;
            }
        }
        //捆绑购买筛选
        if(isset($gets['is_bind']) && $gets['is_bind'] != ''){
            if($gets['is_bind'] == '1'){
                $task_wh['a.bind_product >'] = 2;
            }elseif($gets['is_bind'] == '2'){
                $task_wh['a.bind_product'] = 1;
            }
        }else{
            $task_wh['a.bind_product <>'] = 2;
        }
        //加入收藏
        if(isset($gets['is_collect']) && $gets['is_collect'] != ''){
            $task_wh['a.collection'] = $gets['is_collect'];
        }
        //任务类型
        if(isset($gets['task_type_s']) && $gets['task_type_s'] != ''){
            $task_wh['a.type'] = $gets['task_type_s'];
        }
        //添加人
        if(isset($gets['create_man_s']) && $gets['create_man_s'] != ''){
            $task_wh['a.adduser'] = $gets['create_man_s'];
        }
        //任务时间
        if((isset($gets['taskstart_time']) && $gets['taskstart_time'] != '') && (!isset($gets['taskend_time']) || $gets['taskend_time'] == '')){
            $task_wh['a.taskend_time >='] = strtotime($gets['taskstart_time']);
        }elseif((!isset($gets['taskstart_time']) || $gets['taskstart_time'] == '') && (isset($gets['taskend_time']) && $gets['taskend_time'] != '')){
            $task_wh['a.taskstart_time <='] = strtotime($gets['taskend_time']);
        }elseif(isset($gets['taskstart_time']) && $gets['taskstart_time'] != '' && isset($gets['taskend_time']) && $gets['taskend_time'] != ''){
            $this->db->group_start();
            $sql1 = 'a.taskstart_time between ' . strtotime($gets['taskstart_time']) . ' and ' . strtotime($gets['taskend_time']);
            $this->db->where($sql1);
            $sql2 = 'a.taskend_time between ' . strtotime($gets['taskstart_time']) . ' and ' . strtotime($gets['taskend_time']);
            $this->db->or_where($sql2);
            $this->db->or_group_start();
            $sql3 = 'a.taskstart_time <= ' . strtotime($gets['taskstart_time']) . ' and a.taskend_time >= ' . strtotime($gets['taskend_time']);
            $this->db->where($sql3);
            $this->db->group_end();
            $this->db->group_end();
        }

        $task_wh['a.company_id'] = $this->company_id;
        $task_wh['a.status <>'] = 3;
        $task_fields = 'a.id,a.ASIN,a.bind_ASIN,a.bind_product,b.id as platform_id,b.name as platform_name,b.url,c.name as shop_name,a.adduser,
                        a.type,a.number,a.write_user,a.create_time,a.taskstart_time,a.taskend_time,a.price,c.id as shop_id,
                        a.discount,a.dprice,a.coupon,a.shipping,a.commrate,a.collection,a.key_word,a.executeuser,a.total_money';
        $task_order = 'a.create_time DESC';
        $task_group = '';
        $task_first = $page;
        $task_num = $row_num;
        $task_all = $this->Data_model->getJoinData($task_table, $task_join, $task_wh, $task_fields, $task_order, $task_group, 0, 0);
        if(isset($gets['taskstart_time']) && $gets['taskstart_time'] != '' && isset($gets['taskend_time']) && $gets['taskend_time'] != ''){
            $this->db->group_start();
            $sql1 = 'a.taskstart_time between ' . strtotime($gets['taskstart_time']) . ' and ' . strtotime($gets['taskend_time']);
            $this->db->where($sql1);
            $sql2 = 'a.taskend_time between ' . strtotime($gets['taskstart_time']) . ' and ' . strtotime($gets['taskend_time']);
            $this->db->or_where($sql2);
            $this->db->or_group_start();
            $sql3 = 'a.taskstart_time <= ' . strtotime($gets['taskstart_time']) . ' and a.taskend_time >= ' . strtotime($gets['taskend_time']);
            $this->db->where($sql3);
            $this->db->group_end();
            $this->db->group_end();
        }
        $task_info = $this->Data_model->getJoinData($task_table, $task_join, $task_wh, $task_fields, $task_order, $task_group, $task_first, $task_num);
        $task_data = array();
        foreach ($task_info as $v){
            $v['c_code'] = $this->get_currency($v['platform_id']);
            $v['bind_product'] = $v['bind_product'] != '1' && $v['bind_product'] != '2' ? '是' : '否' ;
            $v['bind_ASIN'] = $v['bind_ASIN'] != '' ? '是' : '否' ;
            $v['adduser'] = $this->get_user_name($v['adduser']);
            $v['type'] = $v['type'] == '1' ? '刷单' : '刷评' ;
            $v['write_user'] = $this->get_user_name($v['write_user']);
            $v['create_time'] = $v['create_time'] == '' ? '' : date('Y-m-d H:i:s',$v['create_time']) ;
            $v['taskstart_time'] = $v['taskstart_time'] == '' ? '' : date('Y-m-d',$v['taskstart_time']) ;
            $v['taskend_time'] = $v['taskend_time'] == '' ? '' : date('Y-m-d',$v['taskend_time']) ;
            $v['collection'] = $v['collection'] == '1' ? '是' : '否' ;
            $v['executeuser'] = $this->get_user_name($v['executeuser']);
            $v['rate'] = $this->get_rate($v['platform_id']);
            $task_data[] = $v ;
        }
        //分页
        $this->load->library('pagination');
        $config['base_url'] = site_url('/task/index');
        $config['total_rows'] = count($task_all);
        $config['per_page'] = $row_num;
        $this->pagination->initialize($config);
        $pagetext = $this->pagination->create_links();

        $this->load->vars('pagetext',$pagetext);
        $this->load->vars('middlers',$middlers);
        $this->load->vars('users',$users);
        $this->load->vars('task_data',$task_data);
        $this->load->vars('platform_data',$platform_data);
        $this->load->view('task');
    }

    //获取店铺
    public function get_shop(){
        if ($this->input->method() == 'post') {
            $posts = $this->input->post();
            $id = intval($posts['id']);
            $shop_wh = array();
            $shop_wh['status'] = 1;
            $shop_wh['platform_id'] = $id;
            $shop_wh['company_id'] = $this->company_id;
            $data_order = 'id asc';
            $shop_data = $this->Data_model->getData($shop_wh,$data_order,0,0,'store');
            if(!$shop_data){
                echo json_encode(array('s'=>0,'data'=>''));
            }else{
                echo json_encode(array('s'=>1,'data'=>$shop_data));
            }
        }
    }

    //获取该任务下的频率
    public function get_frequency(){
        if ($this->input->method() == 'post') {
            $posts = $this->input->post();
            $id = intval($posts['id']);
            $frequency_wh = array();
            $frequency_wh['productid'] = $id;
            $data_order = 'tasktime asc';

            $product = $this->Data_model->getSingle(array('id'=>(int)$id ),'product');

            $frequency_data = $this->Data_model->getData($frequency_wh,$data_order,0,0,'product_num');

            if(!$frequency_data){
                echo json_encode(array('s'=>0,'msg'=>'该任务没有频率'));
            }else{
                $back_frequency = array();
                foreach ($frequency_data as $v){
                    $v['allow'] = (int)($v['tasktime']>time());
					$wdate = date('N', $v['tasktime']);
					$v['tasktime'] = $v['tasktime'] == ''? '' : date('Y-m-d',$v['tasktime']);

					$v['wdate'] = $wdate;
                    $v['number'] = (int)$v['number'];
                    $v['finish_num'] = (int)$v['finish_num'];
                    $back_frequency[] = $v;
                }

                $id2 = $product['bind_product'];
                $data2 =array();
                $back_f2 = array();
                $product2 =array();
                $product2['ASIN'] =null;
                if($id2>2){
                    $fre2 = array();
                    $fre2['productid'] = $id2;
                    $data_order = 'tasktime asc';
                    $data2 = $this->Data_model->getData($fre2,$data_order,0,0,'product_num');
                    foreach ($data2 as $v){
                        $v['allow'] = (int)($v['tasktime']>time());
						$wdate = date('N', $v['tasktime']);
                        $v['tasktime'] = $v['tasktime'] == ''? '' : date('Y-m-d',$v['tasktime']);

						$v['wdate'] = $wdate;
                        $v['number'] = (int)$v['number'];
                        $v['finish_num'] = (int)$v['finish_num'];
                        $back_f2[] = $v;
                    }

                    $product2 = $this->Data_model->getSingle(array('id'=>(int)$id2 ),'product');
                }

                echo json_encode(array('s'=>1,'data'=>$back_frequency,'data2'=>$back_f2,'asin2'=>$product2['ASIN']) );
            }
        }
    }

    //修改频率
    public function edit_frequency($id){
        if ($this->input->method() == 'post') {
            $posts = $this->input->post();

            if(!isset($posts['frequency_data'])){
                echo json_encode(array('s'=>0,'msg'=>'没有修改的内容')); return;
            }
            $frequency_data = $posts['frequency_data'];
            //执行修改
            $this->db->trans_start();
            foreach ($frequency_data as $b) {
                $data_wh = array();
                $data_wh['id'] = (int)$b['frequency_id'];
                $data = array();
                $data['number'] = (int)$b['frequency'];
                $this->Data_model->editData($data_wh,$data,'product_num');
            }
            //修改总任务下单数量
            $this->total_money($id);

            $this->db->trans_complete();
            if ($this->db->trans_status() === FALSE) {
                echo json_encode(array('s'=>0,'msg'=>'没有修改的内容'));
            }else{
                echo json_encode(array('s'=>1,'msg'=>'修改成功'));
            }
        }
    }

    //修改任务
    public function edit($id)
    {
        $time = time();
        $now_time = strtotime('1970-1-1 ' . date('H:i:s',$time));

        $product1 = $this->Data_model->getSingle(array('id' => intval($id)),'product');
        $product1['c_code'] = $this->get_currency($product1['platform']);
        $this->load->vars('product1',$product1);

        $product2 = array();
        if($product1['bind_product']>2){
            $product2 = $this->Data_model->getSingle(array('id' => $product1['bind_product']),'product');
            $product2['c_code'] = $this->get_currency($product2['platform']);
        }
        $this->load->vars('product2',$product2);

        //获取平台
        $pt_info_wh['a.platform_id'] = $product1['platform'];
        $pt_info = $this->query_middler_platform($this->company_id,0,0,$pt_info_wh);
        $pt_info = $pt_info[0];

        //判断当天是否开始任务
        if($now_time < $pt_info['task_start']){
            $is_begin = 1;//当天未开始
        }elseif($pt_info['task_start'] <= $now_time && $pt_info['task_end'] >= $now_time){
            $is_begin = 2;//当天进行中
        }elseif($now_time > $pt_info['task_end']){
            $is_begin = 3;//当天已结束
        }
        if ($this->input->method() == 'post'){
            $url = site_url('/task/index');
            $post = $this->input->post();

            if($is_begin == 2){
                echo "<script>alert('任务进行中不能修改');location.href='$url';</script>";exit;
            }
            //判断任务是否冲突
            //副产品
            if($post['is_bind'] == 1){
                if($post['ASIN'] == $post['bind_ASIN'] && $post['type'] == $post['bind_type']){
                    echo "<script>alert('产品在任务时间内已有该类型的任务');location.href='$url';</script>";exit;
                }
                if($post['bind_ASIN'] != $product2['ASIN'] || $post['bind_type'] != $product2['type']){
                    $pro_task2 = $this->is_task($post['bind_ASIN'],$post['bind_type'],date('Y-m-d',$product2['taskstart_time']),date('Y-m-d',$product2['taskend_time']));
                    if($pro_task2){
                        echo "<script>alert('绑定产品在任务时间内已有该类型的任务');location.href='$url';</script>";exit;
                    }
                }
            }
            //主产品
            if($post['ASIN'] != $product1['ASIN'] || $post['type'] != $product1['type']){
                $pro_task1 = $this->is_task($post['ASIN'],$post['type'],date('Y-m-d',$product1['taskstart_time']),date('Y-m-d',$product1['taskend_time']));
                if($pro_task1){
                    echo "<script>alert('主产品在任务时间内已有该类型的任务');location.href='$url';</script>";exit;
                }
            }
            //获取汇率
            $rate = $this->get_rate($product1['platform']);
            //开启事务
            $this->db->trans_start();
            if($post['is_bind'] == 1){
                //修改product表
                $res1_data = array();
                $res1_data['price']          = $post['bind_price'];
                $res1_data['discount']       = $post['bind_discount'];
                $res1_data['dprice']         = $post['bind_dprice'];
                $res1_data['key_word']       = $post['bind_key_word'];
                $res1_data['shipping']       = $post['bind_shipping'];
                $res1_data['coupon']         = $post['bind_coupon'];
                $res1_data['update_time']    = time();
                //获取操作费
                $handle_price1 = $this->get_handle_price($product2['type'],$product2['collection'],$post['is_relation'],$product2['fast_comment']);
                $res1_data['total_money'] = (($post['bind_dprice'] + $post['bind_shipping'] + $post['bind_dprice'] * $product2['commrate']) * $rate + $handle_price1) * $product2['number'];
                $res1_data['total_money'] = round($res1_data['total_money'] ,2);
                $is_month_budget1_wh['id <>'] = $product2['id'];
                $is_month_budget1 = $this->is_month_budget($res1_data['total_money'],$is_month_budget1_wh);
                if(!$is_month_budget1){
                    echo "<script>alert('副产品超出月度预算');location.href='$url';</script>";exit;
                }
                // 条件
                $data_wh = array();
                $data_wh['id'] = intval($product1['bind_product']);
                $this->Data_model->editData($data_wh,$res1_data,'product');
                //查询子任务
                $pn_list1_wh['productid'] = $product1['bind_product'];
                if($is_begin == 1){
                    $pn_list1_wh['tasktime >='] = strtotime(date('Y-m-d',$time));
                }elseif($is_begin == 2){
                    $pn_list1_wh['tasktime >'] = strtotime(date('Y-m-d',$time));
                }elseif($is_begin == 3){
                    $pn_list1_wh['tasktime >'] = strtotime(date('Y-m-d',$time));
                }
                $pn_list1_wh['status'] = 2;
                $pn_list1 = $this->Data_model->getData($pn_list1_wh,'',0,0,'product_num');
                for($i=0;$i<count($pn_list1);$i++){
                    $pn_wh1 = array();
                    $pn_wh1['id'] = $pn_list1[$i]['id'];
                    $pn_da1 = array();
                    $pn_da1['total_money']  = $post['bind_dprice'];
                    $pn_da1['operat_price'] = $handle_price1;
                    $this->Data_model->editData($pn_wh1,$pn_da1,'product_num');
                }
            }
            if($id>0){
                //修改主产品
                //修改product表
                $res2_data = array();
                $res2_data['price']          = $post['price'];
                $res2_data['discount']       = $post['discount'];
                $res2_data['dprice']         = $post['dprice'];
                $res2_data['key_word']       = $post['key_word'];
                $res2_data['shipping']       = $post['shipping'];
                $res2_data['coupon']         = $post['coupon'];
                $res2_data['update_time']    = time();
                //获取操作费
                $handle_price2 = $this->get_handle_price($product1['type'],$product1['collection'],$post['is_relation'],$product1['fast_comment']);
                $res2_data['total_money'] = (($post['dprice'] + $post['shipping'] +$post['dprice'] * $product1['commrate']) * $rate + $handle_price2) * $product1['number'];
                $res2_data['total_money'] = round($res2_data['total_money'] ,2);
                $is_month_budget_wh['id <>'] = $product1['id'];
                $is_month_budget = $this->is_month_budget($res2_data['total_money'],$is_month_budget_wh);
                if(!$is_month_budget){
                    echo "<script>alert('主产品超出月度预算');location.href='$url';</script>";exit;
                }
                // 条件
                $data_wh = array();
                $data_wh['id'] = intval($id);
                $this->Data_model->editData($data_wh,$res2_data,'product');
                //查询子任务
                $pn_list2_wh['productid'] = $id;
                if($is_begin == 1){
                    $pn_list2_wh['tasktime >='] = strtotime(date('Y-m-d',$time));
                }elseif($is_begin == 2){
                    $pn_list2_wh['tasktime >'] = strtotime(date('Y-m-d',$time));
                }elseif($is_begin == 3){
                    $pn_list2_wh['tasktime >'] = strtotime(date('Y-m-d',$time));
                }
                $pn_list2_wh['status'] = 2;
                $pn_list2 = $this->Data_model->getData($pn_list2_wh,'',0,0,'product_num');
                for($i=0;$i<count($pn_list2);$i++){
                    $pn_wh2 = array();
                    $pn_wh2['id'] = $pn_list2[$i]['id'];
                    $pn_da2 = array();
                    $pn_da2['total_money']  = $post['dprice'];
                    $pn_da2['operat_price'] = $handle_price2;
                    $this->Data_model->editData($pn_wh2,$pn_da2,'product_num');
                }
            }
            $this->db->trans_complete();

            header( "Location: $url" );
        }

        // 获取平台
        $pt_data = $this->query_middler_platform($this->middler_com_id);
        //店铺列表
        $store_list_wh = array();
        $store_list_wh['status'] = 1;
        $store_list_wh['company_id'] = $this->company_id;
        $store_list_fields = 'id,name';
        $store_list = $this->Data_model->getData($store_list_wh,'',0,0,'store',$store_list_fields);
        //公司人员列表
        $user_list_wh = array();
        $user_list_wh['company_id'] = $this->company_id;
        $user_list_wh['status'] = 1;
        $user_list_wh['type'] = 2;
        $user_list_fields = 'id,username';
        $user_list = $this->Data_model->getData($user_list_wh,'',0,0,'`user`',$user_list_fields);

        $today = strtotime(date('Y-m-d',time()));

        $this->load->vars('pt_data',$pt_data);
        $this->load->vars('store_list',$store_list);
        $this->load->vars('user_list',$user_list);
        $this->load->vars('today',$today);

        $this->load->view('task_edit');
    }

    //复制任务
    public function copy($id){
        $product1 = $this->Data_model->getSingle(array('id' => intval($id)),'product');
        $product1['c_code'] = $this->get_currency($product1['platform']);
        $this->load->vars('product1',$product1);
        $product2 = array();
        if($product1['bind_product']>2){
            $product2 = $this->Data_model->getSingle(array('id' => $product1['bind_product']),'product');
            $product2['c_code'] = $this->get_currency($product2['platform']);
        }
        $this->load->vars('product2',$product2);

        // 获取平台
        $pt_data = $this->query_middler_platform($this->middler_com_id);
        //店铺列表
        $store_list_wh = array();
        $store_list_wh['status'] = 1;
        $store_list_wh['company_id'] = $this->company_id;
        $store_list_fields = 'id,name';
        $store_list = $this->Data_model->getData($store_list_wh,'',0,0,'store',$store_list_fields);
        //公司人员列表
        $user_list_wh = array();
        $user_list_wh['company_id'] = $this->company_id;
        $user_list_wh['status'] = 1;
        $user_list_wh['type'] = 2;
        $user_list_fields = 'id,username';
        $user_list = $this->Data_model->getData($user_list_wh,'',0,0,'`user`',$user_list_fields);

        $today = strtotime(date('Y-m-d',time()));

        $this->load->vars('pt_data',$pt_data);
        $this->load->vars('store_list',$store_list);
        $this->load->vars('user_list',$user_list);
        $this->load->vars('today',$today);

        $this->load->view('task_copy');
    }

    //添加任务
    public function add()
    {
        if ($this->input->method() == 'post'){
            $url = site_url('/task/index');
            $post = $this->input->post();

            if($post['taskstart_time'] == '' || $post['taskend_time'] == ''){
                echo "<script>alert('任务时间不能为空');location.href='$url';</script>";exit;
            }
            // fresh frequency @modified by james
            $arr_ret_data = $this->freshFrequency($post, $url);
            $num1_arr = $arr_ret_data['master_product'];
            $num2_arr = $arr_ret_data['bind_product'];

            //判断任务是否冲突
            if($post['is_bind'] == 1) {
                if (($post['ASIN'] == $post['bind_ASIN']) && ($post['type'] == $post['bind_type'])) {
                    echo "<script>alert('产品在任务时间内已有该类型的任务');location.href='$url';</script>";exit;
                }
                //副产品
                $pro_task2 = $this->is_task($post['bind_ASIN'], $post['bind_type'], $post['taskstart_time'], $post['taskend_time']);
                if($pro_task2){
                    echo "<script>alert('绑定产品在任务时间内已有该类型的任务');location.href='$url';</script>";exit;
                }
            }
            //主产品
            $pro_task1 = $this->is_task($post['ASIN'], $post['type'], $post['taskstart_time'], $post['taskend_time']);
            if($pro_task1){
                echo "<script>alert('主产品在任务时间内已有该类型的任务');location.href='$url';</script>";exit;
            }
            //获取汇率
            $rate = $this->get_rate($post['platform']);
            //开启事务
            $this->db->trans_start();
            //如果有绑定的 先添加绑定的
            if($post['is_bind'] == 1){
                //添加product表
                $res1_data = array();

                //获取操作费
                $res1_data['write_user'] = ($post['bind_type'] == 2) ? $post['bind_write_user'] : '';
                $res1_data['fast_comment'] = ($post['bind_type'] == 2) ? $post['bind_fast_comment'] : 2;
                $handle_price1 = $this->get_handle_price($post['bind_type'],$post['bind_collection'],$post['is_relation'],$res1_data['fast_comment']);
                $res1_data['total_money']    = (($post['bind_dprice'] + $post['bind_shipping'] + $post['bind_dprice'] * $post['bind_commrate']) * $rate + $handle_price1) * $post['bind_number'];
                $res1_data['total_money'] = round($res1_data['total_money'] ,2);
                $is_month_budget1 = $this->is_month_budget($res1_data['total_money']);
                if(!$is_month_budget1){
                    echo "<script>alert('副产品超出月度预算');location.href='$url';</script>";exit;
                }


                $res1_data['ASIN']           = $post['bind_ASIN'];
                $res1_data['bind_ASIN'] = ($post['is_relation'] == 1) ? $post['relation_ASIN'] : '';

                $res1_data['bind_product']   = 2;
                $res1_data['execute_shop']   = $post['bind_execute_shop'];
                $res1_data['price']          = $post['bind_price'];
                $res1_data['discount']       = $post['bind_discount'];
                $res1_data['dprice']         = $post['bind_dprice'];
                $res1_data['key_word']       = $post['bind_key_word'];
                $res1_data['commrate']       = $post['bind_commrate'];
                $res1_data['shipping']       = $post['bind_shipping'];
                $res1_data['coupon']         = $post['bind_coupon'];
                $res1_data['number']         = $post['bind_number'];
                $res1_data['finish_number']  = $res1_data['comment_num']    = $res1_data['add_com_num']    = 0;
                $res1_data['collection']     = $post['bind_collection'];
                $res1_data['status']         = $res1_data['order_status']   = $res1_data['comment_status'] = 2;
                $res1_data['create_time']    = time();
                $res1_data['platform']       = $post['platform'];
                $res1_data['type']           = $post['bind_type'];

                $res1_data['xp_sta_time']  = empty($post['bxp_sta_time']) ? null : strtotime($post['bxp_sta_time']);
                $res1_data['xp_end_time']  = empty($post['bxp_sta_time']) ? null : strtotime($post['bxp_end_time']);
                $res1_data['taskstart_time'] = strtotime($post['taskstart_time']);
                $res1_data['taskend_time']   = strtotime($post['taskend_time']);

                $res1_data['company_id']     = $this->company_id;
                $res1_data['middler_com_id'] = $this->middler_com_id;
                $res1_data['adduser']        = $this->uid;


                $product_id = $this->Data_model->addData($res1_data,'product');
                //添加product_num表
                $this->insertProductNumTable($num2_arr, $product_id, $post, $handle_price1);
            }

            //添加主产品
            //添加product表
            $res2_data = array();
            $res2_data['ASIN']           = $post['ASIN'];
            if($post['is_relation'] == 1){
                $res2_data['bind_ASIN']  = $post['relation_ASIN'];
            }elseif($post['is_relation'] == 2){
                $res2_data['bind_ASIN']  = '';
            }
            if($post['is_bind'] == 1){
                $res2_data['bind_product'] = $product_id;
            }else{
                $res2_data['bind_product'] = 1;
            }
            $res2_data['execute_shop']   = $post['execute_shop'];
            $res2_data['price']          = $post['price'];
            $res2_data['discount']       = $post['discount'];
            $res2_data['dprice']         = $post['dprice'];
            $res2_data['key_word']       = $post['key_word'];
            $res2_data['commrate']       = $post['commrate'];
            $res2_data['shipping']       = $post['shipping'];
            $res2_data['coupon']         = $post['coupon'];
            $res2_data['number']         = $post['number'];
            $res2_data['finish_number']  = 0;
            $res2_data['comment_num']    = 0;
            $res2_data['add_com_num']    = 0;
            $res2_data['collection']     = $post['collection'];
            $res2_data['status']         = 2;
            $res2_data['order_status']   = 2;
            $res2_data['comment_status'] = 2;
            $res2_data['create_time']    = time();
            $res2_data['platform']       = $post['platform'];
            $res2_data['adduser']        = $this->uid;
            $res2_data['type']           = $post['type'];
            if($post['type'] == 2){
                $res2_data['write_user']   = $post['write_user'];
                $res2_data['fast_comment'] = $post['fast_comment'];
                $res2_data['xp_sta_time']  = strtotime($post['xp_sta_time']);
                $res2_data['xp_end_time']  = strtotime($post['xp_end_time']);
            }elseif($post['type'] == 1){
                $res2_data['write_user']   = '';
                $res2_data['fast_comment'] = 2;
            }
            //获取操作费
            $handle_price2 = $this->get_handle_price($post['type'],$post['collection'],$post['is_relation'],$res2_data['fast_comment']);
            $res2_data['taskstart_time'] = strtotime($post['taskstart_time']);
            $res2_data['taskend_time']   = strtotime($post['taskend_time']);
            $res2_data['company_id']     = $this->company_id;
            $res2_data['middler_com_id'] = $this->middler_com_id;
            $res2_data['total_money']    = (($post['dprice'] + $post['shipping'] + $post['dprice'] * $post['commrate']) * $rate + $handle_price2) * $post['number'];
            $res2_data['total_money'] = round($res2_data['total_money'] ,2);
            $is_month_budget = $this->is_month_budget($res2_data['total_money']);
            if(!$is_month_budget){
                echo "<script>alert('主产品超出月度预算');location.href='$url';</script>";exit;
            }
            $id2 = $this->Data_model->addData($res2_data,'product');
            //添加product_num表
            for($i=0;$i<count($num1_arr);$i++){
                $pn_data = array();
                $pn_data['number']         = $num1_arr[$i]['num'];
                $pn_data['productid']      = $id2;
                $pn_data['tasktime']       = $num1_arr[$i]['date'];
                $pn_data['finish_num']     = 0;
                $pn_data['status']         = 2;
                $pn_data['platform']       = $post['platform'];
                $pn_data['pay_status']     = 2;
                $pn_data['company_id']     = $this->company_id;
                $pn_data['middler_com_id'] = $this->middler_com_id;
                $pn_data['total_money']    = $post['dprice'];
                $pn_data['operat_price']   = $handle_price2;
                $this->Data_model->addData($pn_data,'product_num');
            }
            $this->db->trans_complete();

            header( "Location: $url" );
        }else{
            // 获取平台
            $pt_data = $this->query_middler_platform($this->middler_com_id);
            //店铺列表
            $store_list_wh = array();
            $store_list_wh['status'] = 1;
            $store_list_wh['company_id'] = $this->company_id;
            $store_list_fields = 'id,name';
            $store_list = $this->Data_model->getData($store_list_wh,'',0,0,'store',$store_list_fields);
            //公司人员列表
            $user_list_wh = array();
            $user_list_wh['company_id'] = $this->company_id;
            $user_list_wh['status'] = 1;
            $user_list_wh['type'] = 2;
            $user_list_fields = 'id,username';
            $user_list = $this->Data_model->getData($user_list_wh,'',0,0,'`user`',$user_list_fields);

            $this->load->vars('pt_data',$pt_data);
            $this->load->vars('store_list',$store_list);
            $this->load->vars('user_list',$user_list);
            $this->load->view('task_add');
        }
    }
    private function insertProductNumTable($num2_arr, $product_id, $post, $handle_price)
    {
        for($i = 0; $i < count($num2_arr); $i++){
            $pn_data = array();
            $pn_data['number']         = $num2_arr[$i]['num'];
            $pn_data['productid']      = $product_id;
            $pn_data['tasktime']       = $num2_arr[$i]['date'];
            $pn_data['finish_num']     = 0;
            $pn_data['status']         = 2;
            $pn_data['platform']       = $post['platform'];
            $pn_data['pay_status']     = 2;
            $pn_data['company_id']     = $this->company_id;
            $pn_data['middler_com_id'] = $this->middler_com_id;
            $pn_data['total_money']    = $post['bind_dprice'];
            $pn_data['operat_price']   = $handle_price;
            $this->Data_model->addData($pn_data,'product_num');
        }

    }

    /* @fresh frequency
     * @param $post
     * @param $url
     * @return string
     * */
    private function freshFrequency($post, $url)
    {
        $arr_date = $master_product = $bind_product = array();
        $start_time = strtotime($post['taskstart_time']);
        $end_time = strtotime($post['taskend_time']);

        if ($start_time < strtotime(date('Y-m-d'), time())) {
            echo "<script>alert('开始时间不能小于今天');location.href='$url';</script>";exit;
        }

        if ($start_time > $end_time){
            echo "<script>alert('开始时间不能大于结束时间');location.href='$url';</script>";exit;
        }

        for($i = $start_time; $i <= $end_time; $i+=86400){
            $arr_date[] = $i;
        }

        $master_total = $bind_total = 0;
        $frequency = count($post['frequency']);
        $bind_frequency = count($post['bind_frequency']);

        for ($i = 0; $i < $frequency; $i++) {
            // master product
            $num = $post['frequency'][$i];
            $num = empty($num) ? 0 : intval($num);
            $master_product_num[] = $num;
            $master_prodcut[$i] = array('num' => $num, 'date' => $arr_date[$i]);

            // bind product
            $num = $post['bind_frequency'][$i];
            $num = empty($num) ? 0 : intval($num);
            $bind_product_num[] = $num;
            $bind_prodcut[$i] = array('num' => $num, 'date' => $arr_date[$i]);
        }
        $master_total = $master_total + array_sum($master_product_num);
        $bind_total = $bind_total + array_sum($bind_product_num);


        if($master_total != $post['number']) {
            echo "<script>alert('主产品的任务数量与频率不符');location.href='$url';</script>";exit;
        }
        if ($post['is_bind'] == 1) {
            foreach ($master_product_num as $key => $value) {
                if ($value != $bind_product_num[$key]) {
                    echo "<script>alert('绑定产品下单频率不能大于主产品下单频率');location.href='$url';</script>";exit;
                }
            }
            if($bind_total != $post['bind_number']){
                echo "<script>alert('绑定产品的任务数量与频率不符');location.href='$url';</script>";exit;
            }
        }

        return array('master_product' => $master_prodcut, 'bind_product' => $bind_product);
    }

    //重新计算
    private function total_money($id){
        $data = $this->Data_model->getSingle(array('id'=>(int)$id ),'product');
        $rate = $this->get_rate($data['platform']);
        $num = $this->get_num($id);
        if($data['bind_ASIN']){
            $is_relation = 1;
        }else{
            $is_relation = 2;
        }
        $handle_price = $this->get_handle_price($data['type'],$data['collection'],$is_relation,$data['fast_comment']);
        $total_money  = (($data['dprice'] + $data['shipping'] + $data['dprice'] * $data['commrate']) * $rate + $handle_price) * $num;
        $total_money = round($total_money ,2);
        $is_month_budget1_wh['id <>'] = $data['id'];
        $is_month_budget1 = $this->is_month_budget($total_money,$is_month_budget1_wh);
        if(!$is_month_budget1){
            echo json_encode(array('s'=>0,'msg'=>'主产品超出月度预算'));exit;
        }

        $arr = array();
        $arr['total_money'] = $total_money;
        $arr['number'] = $num;
        $ret = $this->Data_model->editData(array('id'=>$id),$arr,'product');

        $id2 = $data['bind_product'];
        if($id2>2){
            //绑定数据更新
            $data = $this->Data_model->getSingle(array('id'=>$id2 ),'product');
            $num = $this->get_num($id2);
            if($data['bind_ASIN']){
                $is_relation = 1;
            }else{
                $is_relation = 2;
            }
            $handle_price = $this->get_handle_price($data['type'],$data['collection'],$is_relation,$data['fast_comment']);
            $total_money  = (($data['dprice'] + $data['shipping'] + $data['dprice'] * $data['commrate']) * $rate + $handle_price) * $num;
            $total_money = round($total_money ,2);
            $is_month_budget_wh['id <>'] = $data['id'];
            $is_month_budget = $this->is_month_budget($total_money,$is_month_budget_wh);
            if(!$is_month_budget){
                echo json_encode(array('s'=>0,'msg'=>'副产品超出月度预算'));exit;
            }

            $arr = array();
            $arr['total_money'] = $total_money;
            $arr['number'] = $num;
            $ret = $this->Data_model->editData(array('id'=>$id2),$arr,'product');
        }

        return $ret;
    }

    //product总数量
    private function get_num($id){
        $numbs = $this->Data_model->getData(array('productid'=>$id ),$order='',$pagenum="0",$exnum="0",$table='product_num',$fields='sum(`number`) as count',$groupby='');
        //$sql = $this->db->last_query();echo $sql;
        return $numbs[0]['count'];
    }

    //删除任务（修改状态）
    public function del_task(){
        if($this->input->method() == 'post'){
            $post_s = $this->input->post();

            $time = time();
            $today = strtotime(date('Y-m-d',$time));
            //开启事务
            $this->db->trans_start();
            $ids = $post_s['ids'];
            for($i=0;$i<count($ids);$i++){
                //查询任务信息
                $wh = array();
                $wh['id'] = $ids[$i];
                $task = $this->Data_model->getSingle($wh,'product');
                if($task['taskstart_time'] <= $today){
                    echo json_encode(array('s'=>0,'msg'=>'任务已经开始，不能删除'));exit;
                }
                //删除主任务
                $where = array();
                $where['id'] = $ids[$i];
                $data = array();
                $data['status'] = 3;
                $this->Data_model->editData($where,$data,'product');
                //查询子任务
                $task_son_wh = array();
                $task_son_wh['productid'] = $ids[$i];
                $task_son = $this->Data_model->getData($task_son_wh,'',0,0,'product_num');
                for($j=0;$j<count($task_son);$j++){
                    $wh1 = array();
                    $wh1['id'] = $task_son[$j]['id'];
                    $da1 = array();
                    $da1['status'] = 4;
                    $this->Data_model->editData($wh1,$da1,'product_num');
                }
                //判断是否有副任务
                if($task['bind_product'] > 2){
                    $where1 = array();
                    $where1['id'] = $task['bind_product'];
                    $data1 = array();
                    $data1['status'] = 3;
                    $this->Data_model->editData($where1,$data1,'product');
                    //查询子任务
                    $task_son_wh1 = array();
                    $task_son_wh1['productid'] = $task['bind_product'];
                    $task_son1 = $this->Data_model->getData($task_son_wh1,'',0,0,'product_num');
                    for($k=0;$k<count($task_son1);$k++){
                        $wh2 = array();
                        $wh2['id'] = $task_son1[$k]['id'];
                        $da2 = array();
                        $da2['status'] = 4;
                        $this->Data_model->editData($wh2,$da2,'product_num');
                    }
                }
            }
            $this->db->trans_complete();

            echo json_encode(array('s'=>1,'msg'=>'删除成功'));
        }else{
            echo json_encode(array('s'=>0,'msg'=>'至少删除一项'));
        }
    }

    //分配执行人
    public function assign_executeuser(){
        if($this->input->method() == 'post'){
            $post = $this->input->post();

            //开启事务
            $this->db->trans_start();
            $ids = $post['ids'];
            for($i=0;$i<count($ids);$i++){
                //查询任务信息
                $wh = array();
                $wh['id'] = $ids[$i];
                $task = $this->Data_model->getSingle($wh,'product');
                //分配主任务执行人
                $where = array();
                $where['id'] = $ids[$i];
                $data = array();
                $data['executeuser'] = $post['user_id'];
                $this->Data_model->editData($where,$data,'product');
                //判断是否有副任务
                if($task['bind_product'] > 2){
                    $where1 = array();
                    $where1['id'] = $task['bind_product'];
                    $data1 = array();
                    $data1['executeuser'] = $post['user_id'];
                    $this->Data_model->editData($where1,$data1,'product');
                }
            }
            $this->db->trans_complete();

            echo json_encode(array('s'=>1,'msg'=>'执行成功'));
        }
    }

    // 修改关键词
    public function edit_keyword(){
        if ($this->input->method() == 'post'){
            $post = $this->input->post();

            $wh = array();
            $wh['id'] = $post['id'];
            $data = array();
            $data['key_word'] = $post['key_word'];
            $res = $this->Data_model->editData($wh,$data,'product');
            if($res){
                $url = site_url('/task/index');
                header( "Location: $url" );
            }
        }else{
            $gets = $this->input->get();

            //查询任务信息
            $info = $this->Data_model->getSingle($gets,'product');
            if($info['key_word']){
                $info['key_word'] = explode(',',$info['key_word']);
                printf(json_encode($info['key_word']));
            }else{
                printf(json_encode(array()));
            }

        }
    }

    // 是否任务时间
    public function has_task(){
        if ($this->input->method() == 'post'){
            $post = $this->input->post();
            $time = time();
            $now_time = strtotime('1970-1-1 ' . date('H:i:s',$time));

            //获取任务
            $task = $this->Data_model->getSingle(array('id' => intval($post['id'])),'product');
            //获取平台
            $pt_info_wh['a.platform_id'] = $task['platform'];
            $pt_info = $this->query_middler_platform($this->company_id,0,0,$pt_info_wh);
            $pt_info = $pt_info[0];
            if($pt_info['task_start'] <= $now_time && $pt_info['task_end'] >= $now_time){
                echo json_encode(array('s'=>0,'msg'=>'任务正在执行中，不能修改'));exit;
            }else{
                echo json_encode(array('s'=>1,'msg'=>'true'));exit;
            }
        }
    }

    public function get_currency_flag(){
        if ($this->input->method() == 'post') {
            $posts = $this->input->post();
            $code = $this->get_currency($posts['id']);
            echo json_encode(array('code'=>$code));
        }
    }

    // 判断任务是否冲突接口
    public function is_task_json(){
        if ($this->input->method() == 'post'){
            $post = $this->input->post();

            //查询任务
            $table = 'product a';
            $join = array();
            $join[] = array('product_num b','b.productid = a.id','left');
            $where = array();
            $where['a.ASIN'] = $post['ASIN'];
            $where['a.type'] = $post['type'];
            $sql1 = 'b.tasktime between ' . strtotime($post['taskstart_time']) . ' and ' . strtotime($post['taskend_time']);
            $this->db->where($sql1);
            $where['a.company_id'] = $this->company_id;
            $fields = 'b.id,a.ASIN,a.type,b.tasktime';
            $order = 'b.tasktime ASC';
            $group = '';
            $first = 0;
            $num = 0;
            $list = $this->Data_model->getJoinData($table, $join, $where, $fields, $order, $group, $first, $num);

            if ($list){
                echo 1;
            }else{
                echo 2;
            }
        }
    }

/*     // 判断任务是否超过预算
    public function is_month_budget($money = 0,$task_wh = null){
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
        //查询店铺本月的任务
        $task_wh['adduser'] = $this->uid;
        $task_wh['create_time >='] = $month_start;
        $task_wh['create_time <='] = $month_end;
        $task =  $this->Data_model->getData($task_wh,'',0,0,'product');
        $total_price = 0;
        for($i=0;$i<count($task);$i++){
            $total_price = $total_price + $task[$i]['total_money'];
        }
        $new_total = $total_price + $money;
        if($new_total > $user_info['month_budget']){
            return false;
        }else{
            return true;
        }
    } */

    // 判断任务是否超过预算接口
    public function is_month_budget_json(){
        if ($this->input->method() == 'post'){
            $post = $this->input->post();

            //获取汇率
            $rate = $this->get_rate($post['platform']);
            if($post['is_bind'] == 1){
                //是否快速上评
                if($post['type'] == 1){
                    $fast_comment1 = 2;
                }else{
                    $fast_comment1 = $post['fast_comment'];
                }
                if($post['bind_type'] == 1){
                    $fast_comment2 = 2;
                }else{
                    $fast_comment2 = $post['bind_fast_comment'];
                }
                //获取操作费
                $handle_price1 = $this->get_handle_price($post['type'],$post['collection'],$post['is_relation'],$fast_comment1);
                $handle_price2 = $this->get_handle_price($post['bind_type'],$post['bind_collection'],$post['is_relation'],$fast_comment2);
                //获取总价
                $total1 = (($post['dprice'] + $post['shipping'] + $post['dprice'] * $post['commrate']) * $rate + $handle_price1) * $post['num'];
                $total2 = (($post['bind_dprice'] + $post['bind_shipping'] + $post['bind_dprice'] * $post['bind_commrate']) * $rate + $handle_price2) * $post['bind_num'];
                $total = $total1 + $total2;
                $total = round($total ,2);
                $res1 = $this->is_month_budget($total);
                if(!$res1){
                    echo 1;exit;
                }else{
                    echo 2;exit;
                }
            }else{
                //是否快速上评
                if($post['type'] == 1){
                    $fast_comment1 = 2;
                }else{
                    $fast_comment1 = $post['fast_comment'];
                }
                //获取操作费
                $handle_price1 = $this->get_handle_price($post['type'],$post['collection'],$post['is_relation'],$fast_comment1);
                //获取总价
                $total1 = (($post['dprice'] + $post['shipping'] + $post['dprice'] * $post['commrate']) * $rate + $handle_price1) * $post['num'];
                $total1 = round($total1 ,2);
                $res4 = $this->is_month_budget($total1);
                if(!$res4){
                    echo 1;exit;
                }else{
                    echo 2;exit;
                }
            }
        }
    }
}
