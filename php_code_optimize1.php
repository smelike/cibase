<?php

	// Before optimize 获取汇率
    protected function get_rate($platform_id){
        $rate_table = 'company_rate a';
        $rate_join = array();
        $rate_join[] = array('currency b','b.id = a.currency_id','left');
        $rate_join[] = array('platform c','c.currency_id = b.id','left');
        $rate_wh = array();
        $rate_wh['c.id'] = intval($platform_id);
        $rate_wh['a.company_id'] = $this->company_id;
        $rate_wh['a.status'] = 1;
        $rate_fields = 'a.id,a.real_rate,b.name,b.rate,b.code';
        $rate_order = '';
        $rate_group = '';
        $rate_first = 0;
        $rate_num = 0;
        $rate_info = $this->Data_model->getJoinData($rate_table, $rate_join, $rate_wh, $rate_fields, $rate_order, $rate_group, $rate_first, $rate_num);
        if($rate_info){
            return floatval($rate_info[0]['rate']) + floatval($rate_info[0]['real_rate']);
        }else{
            return 0;
        }
    }

	
	// After optimize 获取汇率
    protected function get_rate($platform_id) {
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