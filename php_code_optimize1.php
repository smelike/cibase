<?php

// 获取汇率
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

	
	