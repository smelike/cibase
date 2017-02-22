<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Task extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $gets = $this->input->get();
        $this->load->vars('gets', $gets);

        $platform_data = $this->query_middler_platform($this->middler_com_id);
        if (isset($gets['platform_s']) && $gets['platform_s'] != '') {
            $shop_wh = array();
            $shop_wh['platform_id'] = intval($gets['platform_s']);
            $shop_wh['company_id'] = $this->company_id;
            $order_by = '';
            $shops = $this->Data_model->getData($shop_wh, $order_by, 0, 0, 'store');
            if ($shops) {
                $this->load->vars('shops', $shops);
            }
        }
        //获取该公司成员
        $users = array();
        if ($_SESSION['user']['user_group_id'] == 2) {
            $user_wh = array();
            $user_wh['company_id'] = $this->company_id;
            $user_wh['type'] = 2;
            $users = $this->Data_model->getData($user_wh, '', 0, 0, 'user');
        }

        //获取该执行成员
        $middler_wh = array();
        $middler_wh['company_id'] = $this->middler_com_id;
        $middler_wh['type'] = 1;
        $middlers = $this->Data_model->getData($middler_wh, '', 0, 0, 'user');

        //获取任务数据
        $task_table = 'product a';
        $task_join = array();
        $task_join[] = array('platform b', 'b.id = a.platform', 'left');
        $task_join[] = array('store c', 'c.id = a.execute_shop', 'left');
        $task_wh = array();

        $this->filterTask($gets, $task_wh);

        $task_fields = 'a.id, a.ASIN, a.bind_ASIN, a.bind_product, b.id as platform_id,b.name as platform_name,b.url,c.name as shop_name,a.adduser,
                        a.type, a.number, a.finish_number,a.comment_num, a.add_com_num, a.write_user,a.create_time,a.taskstart_time,a.taskend_time,a.price,
                        c.id as shop_id,a.discount,a.dprice,a.coupon,a.shipping,a.commrate,a.collection,a.key_word,a.executeuser,a.total_money,
                        a.status, a.pay_status';
        $task_order = 'a.create_time DESC';
        $task_group = '';

        // 获取分页
        $currentpage = intval($this->uri->segment(3));
        $currentpage = $currentpage ? $currentpage : 1;

        $row_num = 10;
        $page = ($currentpage - 1) * $row_num;
        $task_first = $page;
        $task_num = $row_num;
        $task_all = $this->Data_model->getJoinData($task_table, $task_join, $task_wh, $task_fields, $task_order, $task_group, 0, 0);

        if (isset($gets['taskstart_time']) && $gets['taskstart_time'] != '' && isset($gets['taskend_time']) && $gets['taskend_time'] != '') {
            $this->db->group_start();
            $sql1 = 'a.taskstart_time between ' . strtotime($gets['taskstart_time']) . ' and ' . strtotime($gets['taskend_time']);
            $this->db->where($sql1);
            $sql2 = 'a.taskend_time between ' . strtotime($gets['taskstart_time']) . ' and ' . strtotime($gets['taskend_time']);
            $this->db->or_where($sql2);
            $this->db->or_group_start();
            $sql3 = 'a.taskstart_time <= ' . strtotime($gets['taskstart_time']) . ' and a.taskend_time >= ' . strtotime($gets['taskend_time']);
            $this->db->where($sql3);
            $this->db->group_end();
        }

        $task_info = $this->Data_model->getJoinData($task_table, $task_join, $task_wh, $task_fields, $task_order, $task_group, $task_first, $task_num);

        $task_data = array();
        foreach ($task_info as $v) {
            $this->taskStatus($v);
            if (isset($gets['search']) and $gets['status']) {
                if ($v['status_cn'] != $gets['status']) {
                    continue;
                }
            }

            $v['c_code'] = $this->get_currency($v['platform_id']);
            $v['bind_product'] = $v['bind_product'] != '1' && $v['bind_product'] != '2' ? '是' : '否';
            $v['bind_ASIN'] = $v['bind_ASIN'] != '' ? '是' : '否';
            $v['adduser'] = $this->get_user_name($v['adduser']);
            $v['type'] = $v['type'] == '1' ? '刷单' : '刷评';
            $v['write_user'] = $this->get_user_name($v['write_user']);
            $v['create_time'] = $v['create_time'] == '' ? '' : date('Y-m-d H:i:s', $v['create_time']);
            $v['taskstart_time'] = $v['taskstart_time'] == '' ? '' : date('Y-m-d', $v['taskstart_time']);
            $v['taskend_time'] = $v['taskend_time'] == '' ? '' : date('Y-m-d', $v['taskend_time']);
            $v['collection'] = $v['collection'] == '1' ? '是' : '否';
            $v['executeuser'] = $this->get_user_name($v['executeuser']);
            $v['rate'] = $this->get_rate($v['platform_id']);

            $task_data[] = $v;
        }

        $this->pagination($task_all, $gets, $row_num);

        $arr_status = array('未进行', '进行中', '已完成', '已删除', '已过期');
        $this->load->vars('arr_status', $arr_status);

        $this->load->vars('middlers', $middlers);
        $this->load->vars('users', $users);
        $this->load->vars('task_data', $task_data);
        $this->load->vars('platform_data', $platform_data);
        $this->load->view('task');
    }

    private function pagination($task_all, $gets, $row_num)
    {
        $this->load->library('pagination');
        $config['base_url'] = site_url('/task/index');
        if (isset($gets['search']) and $gets['status']) {
            $search_result = [];
            foreach ($task_all as &$v) {
                $this->taskStatus($v);
                if ($v['status_cn'] == $gets['status']) {
                    $search_result[] = $v;
                }
            }
            $config['total_rows'] = count($search_result);
        } else {
            $config['total_rows'] = count($task_all);
        }

        $config['per_page'] = $row_num;
        $this->pagination->initialize($config);
        $pagetext = $this->pagination->create_links();
        $this->load->vars('pagetext', $pagetext);
    }

    private function filterTask($gets, &$task_wh)
    {
        //产品ASIN条件筛选
        if (isset($gets['product_name']) && $gets['product_name'] != '') {
            $task_wh['a.ASIN like'] = '%' . $gets['product_name'] . '%';
        }
        //优惠券筛选
        if (isset($gets['coupon_s']) && $gets['coupon_s'] != '') {
            $task_wh['a.coupon like'] = '%' . $gets['coupon_s'] . '%';
        }
        //关键字筛选
        if (isset($gets['key_word_s']) && $gets['key_word_s'] != '') {
            $task_wh['a.key_word like'] = '%' . $gets['key_word_s'] . '%';
        }
        //平台筛选
        if (isset($gets['platform_s']) && $gets['platform_s'] != '') {
            $task_wh['b.id'] = $gets['platform_s'];
        }
        //店铺筛选
        if (isset($gets['shop_s']) && $gets['shop_s'] != '') {
            $task_wh['c.id'] = $gets['shop_s'];
        }
        //关联访问筛选
        if (isset($gets['is_relevance']) && $gets['is_relevance'] != '') {
            if ($gets['is_relevance'] == '1') {
                $task_wh['a.bind_ASIN <>'] = null;
            } else {
                $task_wh['a.bind_ASIN'] = null;
            }
        }
        //捆绑购买筛选
        if (isset($gets['is_bind']) && $gets['is_bind'] != '') {
            if ($gets['is_bind'] == '1') {
                $task_wh['a.bind_product >'] = 2;
            } elseif ($gets['is_bind'] == '2') {
                $task_wh['a.bind_product'] = 1;
            }
        } else {
            $task_wh['a.bind_product <>'] = 2;
        }
        //加入收藏
        if (isset($gets['is_collect']) && $gets['is_collect'] != '') {
            $task_wh['a.collection'] = $gets['is_collect'];
        }
        //任务类型
        if (isset($gets['task_type_s']) && $gets['task_type_s'] != '') {
            $task_wh['a.type'] = $gets['task_type_s'];
        }
        if ($_SESSION['user']['user_group_id'] != 2) {
            $task_wh['a.adduser'] = $this->uid;
        }
        //添加人
        if (isset($gets['create_man_s']) && $gets['create_man_s'] != '') {
            $task_wh['a.adduser'] = $gets['create_man_s'];
        }
        //任务时间
        if ((isset($gets['taskstart_time']) && $gets['taskstart_time'] != '') && (!isset($gets['taskend_time']) || $gets['taskend_time'] == '')) {
            $task_wh['a.taskend_time >='] = strtotime($gets['taskstart_time']);
        } elseif ((!isset($gets['taskstart_time']) || $gets['taskstart_time'] == '') && (isset($gets['taskend_time']) && $gets['taskend_time'] != '')) {
            $task_wh['a.taskstart_time <='] = strtotime($gets['taskend_time']);
        } elseif (isset($gets['taskstart_time']) && $gets['taskstart_time'] != '' && isset($gets['taskend_time']) && $gets['taskend_time'] != '') {
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

        if (empty($gets['search'])) {
            $task_wh['a.status <>'] = 3;
        }
    }

    /*  @任务的状态
     * @param $task_info
     * @param $task_info
     * @return void
     * */
    private function taskStatus(&$task_info)
    {
        if (empty($task_info['executeuser'])) {
            $current_date = date('Ymd', time());
            $taskend_date = date('Ymd', $task_info['taskend_time']);
            $result = $current_date - $taskend_date;

            $task_info['status_cn'] = '未进行';
            if ($task_info['status'] == 3) {
                $task_info['status_cn'] = '已删除';
            } else if ($result > 1) {
                $task_info['status_cn'] = '已过期';
            }
        } else {
            $task_info['status_cn'] = '进行中';
            if (($task_info['type'] == 1) and ($task_info['number'] == $task_info['finish_number'])) {
                $task_info['status_cn'] = '已完成';
            } else {
                if (($task_info['number'] == $task_info['finish_number']) and ($task_info['comment_num'] == $task_info['add_com_num'])) {
                    $task_info['status_cn'] = '已完成';
                }
            }
        }
    }

    /**
     *  @获取店铺
     * @param void
     * @return string
     */
    public function get_shop()
    {
        if ($this->input->method() == 'post') {
            $posts = $this->input->post();
            $id = intval($posts['id']);
            $shop_wh = array();
            $shop_wh['status'] = 1;
            $shop_wh['platform_id'] = $id;
            $shop_wh['company_id'] = $this->company_id;
            $data_order = 'id asc';
            $shop_data = $this->Data_model->getData($shop_wh, $data_order, 0, 0, 'store');

            $return = array(
                's' => $shop_data ? 1 : 0,
                'data' => $shop_data ? $shop_data : ''
            );
            echo json_encode($return);
        }
    }

    //获取该任务下的频率
    public function get_frequency()
    {
        if ($this->input->method() == 'post') {
            $posts = $this->input->post();
            $id = intval($posts['id']);
            $frequency_wh = array();
            $frequency_wh['productid'] = $id;
            $data_order = 'tasktime asc';

            $product = $this->Data_model->getSingle(array('id' => (int)$id), 'product');
            $frequency_data = $this->Data_model->getData($frequency_wh, $data_order, 0, 0, 'product_num');

            $editable = date('Ymd', $product['taskend_time']) < date('Ymd', time()) ? 0 : 1;
            if ($frequency_data) {
                $format_data1 = $this->formatWeekAndOutdate($frequency_data);
                $id2 = $product['bind_product'];
                $product2 = array('ASIN' => null);
                $format_data2 = array();
                if ($id2 > 2) {
                    $fre2 = array();
                    $fre2['productid'] = $id2;
                    $data_order = 'tasktime asc';
                    $data2 = $this->Data_model->getData($fre2, $data_order, 0, 0, 'product_num');
                    $format_data2 = $this->formatWeekAndOutdate($data2);
                    $product2 = $this->Data_model->getSingle(array('id' => (int)$id2), 'product');
                }
                echo json_encode(array('s' => 1, 'data' => $format_data1, 'data2' => $format_data2, 'asin2' => $product2['ASIN'], 'editable' => $editable));
            } else {
                echo json_encode(array('s' => 0, 'msg' => '该任务没有频率'));
            }
        }
    }

    /**
     * @格式话刷单频率的星期与是否失效
     * @param $data
     * @return array()
     */
    private function formatWeekAndOutdate($data)
    {
        $return = [];
        $timestamp = time();
        foreach ($data as $v) {
            $v['allow'] = (int)($v['tasktime'] > time());
            $wdate = date('N', $v['tasktime']);
            $v['outdate'] = ($timestamp > $v['tasktime']) ? 1 : '';
            $v['tasktime'] = $v['tasktime'] == '' ? '' : date('Y-m-d', $v['tasktime']);
            $v['wdate'] = $wdate;
            $v['number'] = (int)$v['number'];
            $v['finish_num'] = (int)$v['finish_num'];
            $return[] = $v;
        }

        return $return;
    }

    //修改频率
    public function edit_frequency($id)
    {
        if ($this->input->method() == 'post') {
            $posts = $this->input->post();

            if (!isset($posts['frequency_data'])) {
                echo json_encode(array('s' => 0, 'msg' => '没有修改的内容'));
                return;
            }

            $frequency_data = $posts['frequency_data'];
            $this->productIfOutdate($id);

            $this->db->trans_start();
            foreach ($frequency_data as $b) {
                $data_wh = array();
                $data_wh['id'] = (int)$b['frequency_id'];
                $data = array();
                $data['number'] = (int)$b['frequency'];
                $this->Data_model->editData($data_wh, $data, 'product_num');
            }
            //修改总任务下单数量与任务金额
            $this->total_money($id);
            $this->db->trans_complete();
            if ($this->db->trans_status() === FALSE) {
                echo json_encode(array('s' => 0, 'msg' => '没有修改的内容'));
            } else {
                echo json_encode(array('s' => 1, 'msg' => '修改成功'));
            }
        }
    }

    /**
     * @任务是否已失效
     * @param $product_id
     */
    private function productIfOutdate($product_id)
    {
        $product = $this->Data_model->getSingle(array('id' => (int)$product_id), 'product');
        $end = date('Ymd', $product['taskend_time']);
        $current_date = date('Ymd', time());
        if ($end < $current_date) {
            echo json_encode(array('s' => 0, 'msg' => '任务已经过期，不允许继续修改'));
            return;
        }
    }

    //修改任务
    public function edit($id)
    {
        $product1 = $this->Data_model->getSingle(array('id' => intval($id)), 'product');
        $product1['c_code'] = $this->get_currency($product1['platform']);
        $this->formatData($product1);
        $this->load->vars('product1', $product1);

        $product2 = array();
        if ($product1['bind_product'] > 2) {
            $product2 = $this->Data_model->getSingle(array('id' => $product1['bind_product']), 'product');
            $product2['c_code'] = $this->get_currency($product2['platform']);
            $this->formatData($product2);
        }
        $this->load->vars('product2', $product2);

        $pt_data = $this->query_middler_platform($this->middler_com_id);
        $this->load->vars('pt_data', $pt_data);

        $this->load->vars('user_list', $this->companyMemberList());
        $this->load->vars('store_list', $this->storeList());
        $this->load->vars('task_is_executing', $product1['executeuser']);
        $this->load->view('task_edit');
    }

    /**
     * @formatData 格式化产品的尺寸、颜色、写评人姓名数据
     * @param $product
     * @return null
     * */
    private function formatData(&$product)
    {
        $sizeColor = explode(";", $product['remarks']);
        $temp = explode(":", $sizeColor[0]);
        $product['size'] = array_pop($temp);
        $temp = explode(":", $sizeColor[1]);
        $product['color'] = array_pop($temp);
    }

    //复制任务
    public function copy($id)
    {
        $product1 = $this->Data_model->getSingle(array('id' => intval($id)), 'product');
        $product1['c_code'] = $this->get_currency($product1['platform']);
        $this->load->vars('product1', $product1);
        $product2 = array();
        if ($product1['bind_product'] > 2) {
            $product2 = $this->Data_model->getSingle(array('id' => $product1['bind_product']), 'product');
            $product2['c_code'] = $this->get_currency($product2['platform']);
        }
        $this->load->vars('product2', $product2);

        $pt_data = $this->query_middler_platform($this->middler_com_id);
        $store_list = $this->storeList();

        $today = strtotime(date('Y-m-d', time()));
        $this->load->vars('pt_data', $pt_data);
        $this->load->vars('store_list', $store_list);
        $this->load->vars('user_list', $this->companyMemberList());
        $this->load->vars('today', $today);

        $this->load->view('task_copy');
    }

    private function companyMemberList()
    {
        $user_list_wh = array();
        $user_list_wh['company_id'] = $this->company_id;
        $user_list_wh['status'] = 1;
        $user_list_wh['type'] = 2;
        $user_list_fields = 'id, username';
        return $this->Data_model->getData($user_list_wh, '', 0, 0, '`user`', $user_list_fields);
    }

    private function storeList()
    {
        $store_list_wh = array();
        $store_list_wh['status'] = 1;
        $store_list_wh['company_id'] = $this->company_id;
        $store_list_fields = 'id,name';
        return $this->Data_model->getData($store_list_wh, '', 0, 0, 'store', $store_list_fields);
    }

    public function add()
    {

        if ($this->input->method() == 'post') {
            $url = site_url('/task/index');
            $post = $this->input->post();

            $this->accessExceptions($post, $url);
            // fresh frequency @modified by james
            $arr_ret_data = $this->freshFrequency($post, $url);
            $num1_arr = $arr_ret_data['master_product'];
            $num2_arr = $arr_ret_data['bind_product'];

            $row_data = array();
            $this->db->trans_start();

            //如果有绑定的 先添加绑定的
            $bind_product_id = false;
            if ($post['is_bind'] == 1) {
                $handle_price = $this->accessMonthBudget($post, $row_data, $url);
                $bind_product_id = $this->insertProduct($row_data, $post);
                $this->insertProductNum($num2_arr, $bind_product_id, $post['platform'], $post['bind_dprice'], $handle_price);
            }

            $row_data = array();
            $handle_price = $this->accessMonthBudget($post, $row_data, $url);
            $master_product_id = $this->insertProduct($row_data, $post, $bind_product_id);
            $this->insertProductNum($num1_arr, $master_product_id, $post['platform'], $post['price'], $handle_price);
            $this->db->trans_complete();

            $msg = $this->db->trans_status() ? '任务添加成功' : '添加任务失败，请稍后再试。';
            $s = $this->db->trans_status() ?  0 : 1;
            echo json_encode(array('s' => $s, 'msg' => $msg));
        } else {
            $this->show_add_form();
        }
    }

    private function accessMonthBudget($post, &$row_data)
    {
        $rate = $this->get_rate($post['platform']);
        $row_data['fast_comment'] = 2;
        if (($post['is_bind'] == 1) AND ($post['bind_type'] == 2)) {
            $row_data['fast_comment'] = $post['bind_fast_comment'];
            $handle_price = $this->get_handle_price($post['bind_type'], $post['bind_collection'], $post['is_relation'], $row_data['fast_comment']);
            $bind_dprice = $post['bind_price'] * $post['bind_discount'];
            $bind_dprice = round($bind_dprice, 2);
            $row_data['total_money'] = (($bind_dprice + $post['bind_shipping'] + $bind_dprice * $post['bind_commrate']) * $rate
                    + $handle_price) * $post['bind_number'];
            $is_month_budget = $this->is_month_budget($row_data['total_money']);

            if (!$is_month_budget) {
                echo json_encode(array('s' => 1, 'msg' => '副产品超出月度预算'));exit;
            }
        } else {
            $row_data['fast_comment'] = $post['fast_comment'];
            $handle_price = $this->get_handle_price($post['type'], $post['collection'], $post['is_relation'], $row_data['fast_comment']);
            $dprice = $post['price'] * $post['discount'];
            $dprice = round($dprice, 2);
            $row_data['total_money'] = (($dprice + $post['shipping'] + $dprice * $post['commrate']) * $rate + $handle_price) * $post['number'];
            $row_data['total_money'] = round($row_data['total_money'], 2);
            $is_month_budget = $this->is_month_budget($row_data['total_money']);
            if (!$is_month_budget) {
                echo json_encode(array('s' => 1, 'msg' => '主产品超出月度预算'));exit;
            }
        }

        return $handle_price;
    }

    private function accessExceptions($post)
    {
        if ($post['taskstart_time'] == '' || $post['taskend_time'] == '') {
            echo json_encode(array('s' => 1, 'msg' => '任务时间不能为空'));exit;
        }

        if ($post['is_bind'] == 1) {
            if ($post['ASIN'] == $post['bind_ASIN']) {
                echo json_encode(array('s' => 1, 'msg' => '产品 ASIN 不能相同'));exit;
            }
        }
        $start_time = date('Ymd', strtotime($post['taskstart_time']));
        if ($start_time == date('Ymd', time())) {
            echo json_encode(array('s' => 1, 'msg' => '任务开始时间不能为当天'));exit;
        }

        if ($post['is_bind'] == 1) {
            if (($post['ASIN'] == $post['bind_ASIN']) && ($post['type'] == $post['bind_type'])) {
                echo json_encode(array('s' => 1, 'msg' => '产品在任务时间内已有该类型的任务'));exit;
            }
            //副产品
            $pro_task2 = $this->is_task($post['bind_ASIN'], $post['bind_type'], $post['taskstart_time'], $post['taskend_time']);
            if ($pro_task2) {
                echo json_encode(array('s' => 1, 'msg' => '绑定产品在任务时间内已有该类型的任务'));exit;
            }
        }
        //主产品
        $pro_task1 = $this->is_task($post['ASIN'], $post['type'], $post['taskstart_time'], $post['taskend_time']);
        if ($pro_task1) {
            echo json_encode(array('s' => 1, 'msg' => '主产品在任务时间内已有该类型的任务'));exit;
        }
    }

    private function show_add_form()
    {
        // 获取平台
        $pt_data = $this->query_middler_platform($this->middler_com_id);
        //店铺列表
        $store_list_wh = array();
        $store_list_wh['status'] = 1;
        $store_list_wh['company_id'] = $this->company_id;
        $store_list_fields = 'id,name';
        $store_list = $this->Data_model->getData($store_list_wh, '', 0, 0, 'store', $store_list_fields);
        //公司人员列表
        $user_list_wh = array();
        $user_list_wh['company_id'] = $this->company_id;
        $user_list_wh['status'] = 1;
        $user_list_wh['type'] = 2;
        $user_list_fields = 'id,username';
        $user_list = $this->Data_model->getData($user_list_wh, '', 0, 0, '`user`', $user_list_fields);

        $this->load->vars('pt_data', $pt_data);
        $this->load->vars('store_list', $store_list);
        $this->load->vars('user_list', $user_list);
        $this->load->view('task_add');
    }

    /* @绑定产品
     * @param $post
     * @param $row_data
     * */
    private function bindProduct($post, &$row_data)
    {
        $row_data['ASIN'] = $post['bind_ASIN'];
        $row_data['bind_product'] = 2;
        $row_data['execute_shop'] = $post['bind_execute_shop'];
        $row_data['price'] = $post['bind_price'];
        $row_data['discount'] = $post['bind_discount'];
        //$row_data['dprice']         = $post['bind_dprice'];
        $row_data['dprice'] = $post['bind_price'] * $post['bind_discount'];
        $row_data['dprice'] = round($row_data['dprice'], 2);
        $row_data['key_word'] = $post['bind_key_word'];
        $row_data['commrate'] = $post['bind_commrate'];
        $row_data['shipping'] = $post['bind_shipping'];
        $row_data['coupon'] = $post['bind_coupon'];
        $row_data['number'] = $post['bind_number'];
        $row_data['collection'] = $post['bind_collection'];
        $row_data['type'] = $post['bind_type'];
        $row_data['write_user'] = ($post['bind_type'] == 2) ? $post['bind_write_user'] : '';

        $remarks = '';
        if (isset($post['bind_size'])) {
            $remarks = "size: {$post['bind_size']};";
        }
        if (isset($post['bind_color'])) {
            $remarks .= "color: {$post['bind_color']}";
        }
        $row_data['remarks'] = $remarks;
    }

    /* @主产品
     * @param $post
     * @param $row_data
     * @param $product_id
     * */
    private function masterProduct($post, &$row_data, $product_id = false)
    {

        $row_data['ASIN'] = $post['ASIN'];
        $row_data['bind_product'] = $product_id ? $product_id : 1;
        $row_data['execute_shop'] = $post['execute_shop'];
        $row_data['price'] = $post['price'];
        $row_data['discount'] = $post['discount'];
        $row_data['dprice'] = $post['price'] * $post['discount'];
        $row_data['dprice'] = round($row_data['dprice'], 2);
        $row_data['key_word'] = $post['key_word'];
        $row_data['commrate'] = $post['commrate'];
        $row_data['shipping'] = $post['shipping'];
        $row_data['coupon'] = $post['coupon'];
        $row_data['number'] = $post['number'];
        $row_data['collection'] = $post['collection'];
        $row_data['type'] = $post['type'];
        $row_data['write_user'] = ($post['type'] == 2) ? $post['write_user'] : '';

        $remarks = '';
        if (isset($post['size'])) {
            $remarks = "size: {$post['size']};";
        }
        if (isset($post['color'])) {
            $remarks .= "color: {$post['color']};";
        }
        $row_data['remarks'] = $remarks;
    }

    /* @插入产品
     * @param $row_data
     * @param $post
     * @param $bind_product_id
     * */
    private function insertProduct($row_data, $post, $bind_product_id = false)
    {

        if ($bind_product_id || ($post['is_bind'] == 2)) {
            $this->ifTaskDuplicate($post);
            $this->masterProduct($post, $row_data, $bind_product_id);
            $row_data['xp_sta_time'] = strtotime($post['xp_sta_time']);
            $row_data['xp_end_time'] = strtotime($post['xp_end_time']);
        } else if ($post['bind_ASIN']) {
            $this->ifTaskDuplicate($post);
            $this->bindProduct($post, $row_data);
            $row_data['xp_sta_time'] = strtotime($post['bxp_sta_time']);
            $row_data['xp_end_time'] = strtotime($post['bxp_end_time']);
        }

        $row_data['platform'] = $post['platform'];
        $row_data['finish_number'] = $row_data['comment_num'] = $row_data['add_com_num'] = 0;
        $row_data['status'] = $row_data['order_status'] = $row_data['comment_status'] = 2;
        $row_data['bind_ASIN'] = ($post['is_relation'] == 1) ? $post['relation_ASIN'] : '';

        $row_data['create_time'] = time();
        $row_data['taskstart_time'] = strtotime($post['taskstart_time']);
        $row_data['taskend_time'] = strtotime($post['taskend_time']);

        $row_data['adduser'] = $this->uid;
        $row_data['company_id'] = $this->company_id;
        $row_data['middler_com_id'] = $this->middler_com_id;

        return $this->Data_model->addData($row_data, 'product');
    }
    /**
     * @ifTaskDuplicate 防止添加重复的任务
     * @param $post
     * @return json
     */
    private function ifTaskDuplicate($post)
    {
        $product = $this->Data_model->getSingle(array('asin' => $post['ASIN']), 'product');
        if ($product) {
            $start = $post['taskstart_time'];
            $end = $post['taskend_time'];
            //$subTask = $this->ifExistProductNum($product['id'], $post['taskstart_time'], $post['taskend_time']);
            $sql = "SELECT * FROM product_num WHERE productid={$product['id']} AND tasktime BETWEEN " . strtotime($start) . ' AND ' . strtotime($end);
            $subTask = $this->db->query($sql);
            if ($subTask) {
                $msg = "不可在相同任务时间，添加同一 ASIN 任务。";
                echo json_encode(array('s' => 1, 'msg' => $msg));exit;
            }
        }
    }

    /* @插入 product_num 表
     * @param $num2_arr
     * @param $product_id
     * @param $platform
     * @param $price
     * @param $handle_price
     * */
    private function insertProductNum($num2_arr, $product_id, $platform, $price, $handle_price)
    {
        for ($i = 0; $i < count($num2_arr); $i++) {
            $pn_data = array();
            $pn_data['number'] = $num2_arr[$i]['num'];
            $pn_data['productid'] = $product_id;
            $pn_data['tasktime'] = $num2_arr[$i]['date'];
            $pn_data['finish_num'] = 0;
            $pn_data['status'] = 2;
            $pn_data['platform'] = $platform;
            $pn_data['pay_status'] = 2;
            $pn_data['company_id'] = $this->company_id;
            $pn_data['middler_com_id'] = $this->middler_com_id;
            $pn_data['total_money'] = $price;
            $pn_data['operat_price'] = $handle_price;
            $this->Data_model->addData($pn_data, 'product_num');
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
            echo "<script>alert('开始时间不能小于今天');location.href='$url';</script>";
            exit;
        }

        if ($start_time > $end_time) {
            echo "<script>alert('开始时间不能大于结束时间');location.href='$url';</script>";
            exit;
        }

        for ($i = $start_time; $i <= $end_time; $i += 86400) {
            $arr_date[] = $i;
        }

        $master_total = $bind_total = 0;
        $frequency = count($post['frequency']);
        $bind_frequency = count($post['bind_frequency']);

        $master_product_num = $bind_product_num = [];
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
            $bind_product[$i] = array('num' => $num, 'date' => $arr_date[$i]);
        }
        $master_total = $master_total + array_sum($master_product_num);
        $bind_total = $bind_total + array_sum($bind_product_num);

        if ($master_total != $post['number']) {
            echo "<script>alert('主产品的任务数量与频率不符');location.href='$url';</script>";
            exit;
        }
        if ($post['is_bind'] == 1) {
            foreach ($master_product_num as $key => $value) {
                if ($value != $bind_product_num[$key]) {
                    echo "<script>alert('绑定产品下单频率不能大于主产品下单频率');location.href='$url';</script>";
                    exit;
                }
            }
            if ($bind_total != $post['bind_number']) {
                echo "<script>alert('绑定产品的任务数量与频率不符');location.href='$url';</script>";
                exit;
            }
        }

        return array('master_product' => $master_prodcut, 'bind_product' => $bind_product);
    }

    /**
     * @total_money 计算每个任务的花费金额
     * @param $id
     * @return null
     */
    private function total_money($id)
    {

        $data = $this->Data_model->getSingle(array('id' => $id), 'product');
        $bind_product_id = $data['bind_product'];
        $bind_product_id = ($bind_product_id > 2) ? $bind_product_id : 0;
        $arr_product_id = array($id, $bind_product_id);

        $product_datas = $this->Data_model->getMultis(array('id' => $arr_product_id), 'product');
        foreach ($product_datas as $data)
        {
            $id = ($data['bind_product'] > 2) ? $data['bind_product'] : $data['id'];
            $total_money = $this->countTaskCost($id, $data);

            $is_month_budget = $this->is_month_budget($total_money, $data['id']);
            if (!$is_month_budget) {
                if ($data['bind_product'] > 2) {
                    echo json_encode(array('s' => 0, 'msg' => '副产品超出月度预算'));
                    exit;
                }
                echo json_encode(array('s' => 0, 'msg' => '主产品超出月度预算'));
                exit;
            }

            $arr = array('total_money' => $total_money, 'number' => $this->get_num($id));
            return $this->Data_model->editData(array('id' => $id), $arr, 'product');
        }
    }
    /**
     * @countTaskCost 计算任务花费金额
     * @param $id
     * @param $data
     * @return float
     */
    private function countTaskCost($id, $data)
    {
        $num = $this->get_num($id);
        $is_relation = $data['bind_ASIN'] ? 1 : 2;
        $handle_price = $this->get_handle_price($data['type'], $data['collection'], $is_relation, $data['fast_comment']);
        $rate = $this->get_rate($data['platform']);
        $total_money = (($data['dprice'] + $data['shipping'] + $data['dprice'] * $data['commrate']) * $rate + $handle_price) * $num;
        return round($total_money, 2);
    }

    /**
     * @get_num
     * @param $id
     * @return int
    */
    private function get_num($id)
    {
        $table = 'product_num';
        $exnum = $pagenum = "0";
        $groupby = $order = '';
        $fields = 'sum(`number`) as count';

        $numbs = $this->Data_model->getData(array('productid' => $id), $order, $pagenum, $exnum, $table, $fields, $groupby);
        return $numbs[0]['count'];
    }

    //删除任务（修改状态）
    public function del_task()
    {
        if ($this->input->method() == 'post') {
            $post_s = $this->input->post();

            $time = time();
            $today = strtotime(date('Y-m-d', $time));
            //开启事务
            $this->db->trans_start();
            $ids = $post_s['ids'];
            for ($i = 0; $i < count($ids); $i++) {
                //查询任务信息
                $wh = array();
                $wh['id'] = $ids[$i];
                $task = $this->Data_model->getSingle($wh, 'product');
                if ($task['taskstart_time'] <= $today) {
                    echo json_encode(array('s' => 0, 'msg' => '任务已经开始，不能删除'));
                    exit;
                }
                //删除主任务
                $where = array();
                $where['id'] = $ids[$i];
                $data = array();
                $data['status'] = 3;
                $this->Data_model->editData($where, $data, 'product');
                //查询子任务
                $task_son_wh = array();
                $task_son_wh['productid'] = $ids[$i];
                $task_son = $this->Data_model->getData($task_son_wh, '', 0, 0, 'product_num');
                for ($j = 0; $j < count($task_son); $j++) {
                    $wh1 = array();
                    $wh1['id'] = $task_son[$j]['id'];
                    $da1 = array();
                    $da1['status'] = 4;
                    $this->Data_model->editData($wh1, $da1, 'product_num');
                }
                //判断是否有副任务
                if ($task['bind_product'] > 2) {
                    $where1 = array();
                    $where1['id'] = $task['bind_product'];
                    $data1 = array();
                    $data1['status'] = 3;
                    $this->Data_model->editData($where1, $data1, 'product');
                    //查询子任务
                    $task_son_wh1 = array();
                    $task_son_wh1['productid'] = $task['bind_product'];
                    $task_son1 = $this->Data_model->getData($task_son_wh1, '', 0, 0, 'product_num');
                    for ($k = 0; $k < count($task_son1); $k++) {
                        $wh2 = array();
                        $wh2['id'] = $task_son1[$k]['id'];
                        $da2 = array();
                        $da2['status'] = 4;
                        $this->Data_model->editData($wh2, $da2, 'product_num');
                    }
                }
            }
            $this->db->trans_complete();
            echo json_encode(array('s' => 1, 'msg' => '删除成功'));
        } else {
            echo json_encode(array('s' => 0, 'msg' => '至少删除一项'));
        }
    }

    //分配执行人
    public function assign_executeuser()
    {
        if ($this->input->method() == 'post') {
            $post = $this->input->post();

            //开启事务
            $this->db->trans_start();
            $ids = $post['ids'];
            for ($i = 0; $i < count($ids); $i++) {
                //查询任务信息
                $wh = array();
                $wh['id'] = $ids[$i];
                $task = $this->Data_model->getSingle($wh, 'product');
                //分配主任务执行人
                $where = array();
                $where['id'] = $ids[$i];
                $data = array();
                $data['executeuser'] = $post['user_id'];
                $this->Data_model->editData($where, $data, 'product');
                //判断是否有副任务
                if ($task['bind_product'] > 2) {
                    $where1 = array();
                    $where1['id'] = $task['bind_product'];
                    $data1 = array();
                    $data1['executeuser'] = $post['user_id'];
                    $this->Data_model->editData($where1, $data1, 'product');
                }
            }
            $this->db->trans_complete();

            echo json_encode(array('s' => 1, 'msg' => '执行成功'));
        }
    }

    // 修改关键词
    public function edit_keyword()
    {
        if ($this->input->method() == 'post') {

            $post = $this->input->post();
            $wh = array('id'=> $post['id']);
            $data = array('key_word' => $post['key_word']);
            $res = $this->Data_model->editData($wh, $data, 'product');
            if ($res) {
                $url = site_url('/task/index');
                header("Location: $url");
            }
        } else {
            $gets = $this->input->get();
            $info = $this->Data_model->getSingle($gets, 'product');
            if ($info['key_word']) {
                $info['key_word'] = explode(',', $info['key_word']);
                printf(json_encode($info['key_word']));
            } else {
                printf(json_encode(array()));
            }
        }
    }

    public function has_task($id = '')
    {
        if ($this->input->method() != 'post') {
            echo json_encode(array('s' => 1, '不合法的数据请求'));exit;
        }
        $post = $this->input->post();
        $task = $this->Data_model->getSingle(array('id' => intval($post['id'])), 'product');

        if (empty($id)) {
            $this->accessModifiedTask($task);
            echo json_encode(array('s' => 0, 'msg' => ''));exit;
        } else {
            $this->db->trans_start();
            if (isset($post['ASIN'])) {
                $masterProduct = [];
                $bindProductid = empty($post['bind_id']) ? '' : $post['bind_id'];
                $this->masterProduct($post, $masterProduct, $bindProductid);
                $where = array('id' => $post['id']);
                $this->Data_model->editData($where, $masterProduct, 'product');
            }

            if (isset($post['bind_ASIN'])) {
                $bindProduct = [];
                $this->bindProduct($post, $bindProduct);
                $where = array('id' => $post['bind_id']);
                $this->Data_model->editData($where, $bindProduct, 'product');
            }
            $this->total_money($post['id']);
            $this->db->trans_complete();
        }
        $msg = $this->db->trans_status() ? '任务修改成功' : '任务修改失败，请勿频繁点击【保存】。';
        $s = $this->db->trans_status() ? 0 : 1;
        $return = array('s' => $s, 'msg' => $msg);
        echo json_encode($return);
    }
    private function accessModifiedTask($task)
    {
        $now_time = strtotime(date('Y-m-d'));

        if ($task and $task['executeuser']) {
            echo json_encode(array('s' => 1, 'msg' => '任务正在执行中，不能修改'));
            exit;
        }

        if ($task['taskend_time'] < $now_time) {
            echo json_encode(array('s' => 1, 'msg' => '任务已过期，无法再修改'));exit;
        }
    }

    private function onGoingTask($task)
    {
        return $this->Data_model->getSingle(array('productid' => $task['id'], 'status' => 5), 'product_num');
    }

    private function if_locked($product_id)
    {
        $ongoingTask = $this->db->get_where('product_num', array('productid' => $product_id, 'status' => array(5, 1)));
        return $ongoingTask;
    }

    public function get_currency_flag()
    {
        if ($this->input->method() == 'post') {
            $posts = $this->input->post();
            $code = $this->get_currency($posts['id']);
            echo json_encode(array('code' => $code));
        }
    }

    // 判断任务是否冲突接口
    public function is_task_json()
    {
        if ($this->input->method() == 'post') {
            $post = $this->input->post();
            $start = date('Ymd', strtotime($post['taskstart_time']));
            $end = date('Ymd', strtotime($post['taskend_time']));

            if ($start == $end) {
                echo 3;
                exit;
            }
            $group = '';
            $first = $num = 0;
            $table = 'product a';
            $fields = 'b.id,a.ASIN,a.type,b.tasktime';
            $order = 'b.tasktime ASC';

            $join = array(array('product_num b', 'b.productid = a.id', 'left'));
            $where = array('a.ASIN' => $post['ASIN'], 'a.type' => $post['type'], 'a.platform' => $post['platform'], 'a.company_id' => $this->company_id, 'a.status' => 2);

            $sql1 = 'b.tasktime between ' . strtotime($post['taskstart_time']) . ' and ' . strtotime($post['taskend_time']);
            $this->db->where($sql1);

            $list = $this->Data_model->getJoinData($table, $join, $where, $fields, $order, $group, $first, $num);
            echo $list ? 1 : 2;
        }
    }

    // 判断任务是否超过预算接口
    public function is_month_budget_json()
    {

        if ($this->input->method() != 'post') {
            echo json_encode(array('s' => 1, 'msg' => '非法提交数据'));
            exit;
        }

        $post = $this->input->post();
        //获取汇率
        $rate = $this->get_rate($post['platform']);
        if ($post['is_bind'] == 1) {
            //是否快速上评
            $fast_comment1 = ($post['type'] == 1) ? 2 : $post['fast_comment'];
            $fast_comment2 = ($post['bind_type'] == 1) ? 2 : $post['bind_fast_comment'];
            //获取操作费
            $handle_price1 = $this->get_handle_price($post['type'], $post['collection'], $post['is_relation'], $fast_comment1);
            $handle_price2 = $this->get_handle_price($post['bind_type'], $post['bind_collection'], $post['is_relation'], $fast_comment2);
            //获取总价
            $total1 = (($post['dprice'] + $post['shipping'] + $post['dprice'] * $post['commrate']) * $rate + $handle_price1) * $post['num'];
            $total2 = (($post['bind_dprice'] + $post['bind_shipping'] + $post['bind_dprice'] * $post['bind_commrate']) * $rate + $handle_price2) * $post['bind_num'];
            $total = $total1 + $total2;
            $total = round($total, 2);
            $res = $this->is_month_budget($total);

        } else {
            $fast_comment1 = ($post['type'] == 1) ? 2 : $post['fast_comment'];
            //获取操作费
            $handle_price1 = $this->get_handle_price($post['type'], $post['collection'], $post['is_relation'], $fast_comment1);
            //获取总价
            $total1 = (($post['dprice'] + $post['shipping'] + $post['dprice'] * $post['commrate']) * $rate + $handle_price1) * $post['num'];
            $total1 = round($total1, 2);
            $res = $this->is_month_budget($total1);
        }
        echo $res ? 2 : 1;
    }
}
