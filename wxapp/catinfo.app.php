<?php

/* 定义like语句转换为in语句的条件 */
define('MAX_ID_NUM_OF_IN', 10000); // IN语句的最大ID数
define('MAX_HIT_RATE', 0.05);      // 最大命中率（满足条件的记录数除以总记录数）
define('MAX_STAT_PRICE', 10000);   // 最大统计价格
define('PRICE_INTERVAL_NUM', 5);   // 价格区间个数
define('MIN_STAT_STEP', 50);       // 价格区间最小间隔
define('NUM_PER_PAGE', 16);        // 每页显示数量
define('ENABLE_SEARCH_CACHE', true); // 启用商品搜索缓存
define('SEARCH_CACHE_TTL', 3600);  // 商品搜索缓存时间

class CatinfoApp extends MallbaseApp
{
    /* 搜索商品 */
    function index()
    {
        // 查询参数
        $param = $this->_get_query_param();
        if (empty($param))
        {
			return $this->ej_json_failed(2001);
        }
        if (isset($param['cate_id']) && $param['layer'] === false)
        {
			return $this->ej_json_failed(2001);
        }
        /* 按分类、品牌、地区、价格区间统计商品数量 */
        $stats = $this->_get_group_by_info($param, ENABLE_SEARCH_CACHE);

        $goods_mod  =& m('goods');
		$endarr = end($stats['by_category']);
		$goods_list = array();
		//循环获取分类对应商品  待优化 
		if(!empty($stats['by_category'])){
            $sql = '';
			foreach($stats['by_category'] as $v){
				$sql .= "(select g.goods_id,g.default_image,g.goods_name,g.price,g.cate_id,gs.sales from ".DB_PREFIX."goods g ".
					" LEFT JOIN ".DB_PREFIX."goods_statistics gs ON gs.goods_id = g.goods_id ". 
					" LEFT JOIN ".DB_PREFIX."store s ON g.store_id = s.store_id  where g.if_show = 1 AND g.closed = 0 AND s.state = 1 and cate_id=".$v['cate_id']." limit 5)";
				if($v['cate_id'] != $endarr['cate_id']){
					$sql .= " union all ";	
				}
				
			}
			$goods_list = $goods_mod->getAll($sql);
		}

        //将商品匹配到分类  by newrain
        $subData = $this->_ejcat_goodslist($stats['by_category'],$goods_list);

        // 分类轮播图
        $cycleImage = $goods_mod->getAll("select image_url,image_link,image_name from ecm_business_image i where i.fk_id = {$param['cate_id']} and i.type = 'category_cycle' order by sort asc ");

        $result = [
            'cycle_image' => $cycleImage,
            'sub_data' => $subData,
        ];

		return $this->ej_json_success($result);
    }
    /**
     * 取得查询参数（有值才返回）
     *
     * @return  array(
     *              'keyword'   => array('aa', 'bb'),
     *              'cate_id'   => 2,
     *              'layer'     => 2, // 分类层级
     *              'brand'     => 'ibm',
     *              'region_id' => 23,
     *              'price'     => array('min' => 10, 'max' => 100),
     *          )
     */
    function _get_query_param()
    {
        static $res = null;
        if ($res === null)
        {
            $res = array();
    
            // keyword
            $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
            if ($keyword != '')
            {
                //$keyword = preg_split("/[\s," . Lang::get('comma') . Lang::get('whitespace') . "]+/", $keyword);
                $tmp = str_replace(array(Lang::get('comma'),Lang::get('whitespace'),' '),',', $keyword);
                $keyword = explode(',',$tmp);
                sort($keyword);
                $res['keyword'] = $keyword;
            }
    
            // cate_id
            if (isset($_GET['cate_id']) && intval($_GET['cate_id']) > 0)
            {
                $res['cate_id'] = $cate_id = intval($_GET['cate_id']);
                $gcategory_mod  =& bm('gcategory');
                $res['layer']   = $gcategory_mod->get_layer($cate_id, true);
            }
    
            // brand
            if (isset($_GET['brand']))
            {
                $brand = trim($_GET['brand']);
                $res['brand'] = $brand;
            }
    
            // region_id
            if (isset($_GET['region_id']) && intval($_GET['region_id']) > 0)
            {
                $res['region_id'] = intval($_GET['region_id']);
            }
    
            // price
            if (isset($_GET['price']))
            {
                $arr = explode('-', $_GET['price']);
                $min = abs(floatval($arr[0]));
                $max = abs(floatval($arr[1]));
                if ($min * $max > 0 && $min > $max)
                {
                    list($min, $max) = array($max, $min);
                }
    
                $res['price'] = array(
                    'min' => $min,
                    'max' => $max
                );
            }
        }

        return $res;
    }
    /**
     * 根据查询条件取得分组统计信息
     *
     * @param   array   $param  查询参数（参加函数_get_query_param的返回值说明）
     * @param   bool    $cached 是否缓存
     * @return  array(
     *              'total_count' => 10,
     *              'by_category' => array(id => array('cate_id' => 1, 'cate_name' => 'haha', 'count' => 10))
     *              'by_brand'    => array(array('brand' => brand, 'count' => count))
     *              'by_region'   => array(array('region_id' => region_id, 'region_name' => region_name, 'count' => count))
     *              'by_price'    => array(array('min' => 10, 'max' => 50, 'count' => 10))
     *          )
     */
    function _get_group_by_info($param, $cached)
    {
        $data = false;

        if ($cached)
        {
            $cache_server =& cache_server();
            $key = 'group_by_info_' . var_export($param, true);
            $data = $cache_server->get($key);
        }

        if ($data === false)
        {
            $data = array(
                'total_count' => 0,
                'by_category' => array(),
                'by_brand'    => array(),
                'by_region'   => array(),
                'by_price'    => array()
            );

            $goods_mod =& m('goods');
            $store_mod =& m('store');
            $table = " {$goods_mod->table} g LEFT JOIN {$store_mod->table} s ON g.store_id = s.store_id";
            $conditions = $this->_get_goods_conditions($param);
            $sql = "SELECT COUNT(*) FROM {$table} WHERE" . $conditions;
            $total_count = $goods_mod->getOne($sql);
            if ($total_count > 0)
            {
                $data['total_count'] = $total_count;
                /* 按分类统计 */
                $cate_id = isset($param['cate_id']) ? $param['cate_id'] : 0;
                $sql = "";
                if ($cate_id > 0)
                {
                    $layer = $param['layer'];
                    if ($layer < 4)
                    {
                        $sql = "SELECT g.cate_id_" . ($layer + 1) . " AS id, COUNT(*) AS count FROM {$table} WHERE" . $conditions . " AND g.cate_id_" . ($layer + 1) . " > 0 GROUP BY g.cate_id_" . ($layer + 1); 
						//. " ORDER BY count DESC";
                    }
                }
                else
                {
                    $sql = "SELECT g.cate_id_1 AS id, COUNT(*) AS count FROM {$table} WHERE" . $conditions . " AND g.cate_id_1 > 0 GROUP BY g.cate_id_1 ";//ORDER BY count DESC";
                }

                if ($sql)
                {
                    $category_mod =& bm('gcategory');
                    $children = $category_mod->get_children($cate_id, true);
					$res = $goods_mod->db->getAll($sql);
					//添加按照分类排序
					$ifcat = array();
					if($res){
						foreach($res as $value){
							$ifcat[$value['id']] = $value['count'];
						}
					}
					if($children){
						foreach($children as $k=>$v){
							if(isset($ifcat[$v['cate_id']])){
								$data['by_category'][$v['cate_id']] = array(
									'cate_id'   => $v['cate_id'],
									'cate_name' => $v['cate_name'],
									'count'     => $ifcat[$v['cate_id']]
								);
							}
						}
					}
                  /*   $res = $goods_mod->db->query($sql);
                    while ($row = $goods_mod->db->fetchRow($res))
                    {
                        $data['by_category'][$row['id']] = array(
                            'cate_id'   => $row['id'],
                            'cate_name' => $children[$row['id']]['cate_name'],
                            'count'     => $row['count']
                        );
                    } */
                }

                /* 按品牌统计 */
                $sql = "SELECT g.brand, COUNT(*) AS count FROM {$table} WHERE" . $conditions . " AND g.brand > '' GROUP BY g.brand";// ORDER BY count DESC";
                $by_brands = $goods_mod->db->getAllWithIndex($sql, 'brand');
                
                /* 滤去未通过商城审核的品牌 */
                if ($by_brands)
                {
                    $m_brand = &m('brand');
                    $brand_conditions = db_create_in(addslashes_deep(array_keys($by_brands)), 'brand_name');
                    $brands_verified = $m_brand->getCol("SELECT brand_name FROM {$m_brand->table} WHERE " . $brand_conditions . ' AND if_show=1');
                    foreach ($by_brands as $k => $v)
                    {
                        if (!in_array($k, $brands_verified))
                        {
                            unset($by_brands[$k]);
                        }
                    }
                }
                $data['by_brand'] = $by_brands;
                
                
                /* 按地区统计 */
                $sql = "SELECT s.region_id, s.region_name, COUNT(*) AS count FROM {$table} WHERE" . $conditions . " AND s.region_id > 0 GROUP BY s.region_id";// ORDER BY count DESC";
                $data['by_region'] = $goods_mod->getAll($sql);

                /* 按价格统计 */
                if ($total_count > NUM_PER_PAGE)
                {
                    $sql = "SELECT MIN(g.price) AS min, MAX(g.price) AS max FROM {$table} WHERE" . $conditions;
                    $row = $goods_mod->getRow($sql);
                    $min = $row['min'];
                    $max = min($row['max'], MAX_STAT_PRICE);
                    $step = max(ceil(($max - $min) / PRICE_INTERVAL_NUM), MIN_STAT_STEP);
                    $sql = "SELECT FLOOR((g.price - '$min') / '$step') AS i, count(*) AS count FROM {$table} WHERE " . $conditions . " GROUP BY i ORDER BY i";
                    $res = $goods_mod->db->query($sql);
                    while ($row = $goods_mod->db->fetchRow($res))
                    {
                        $data['by_price'][] = array(
                            'count' => $row['count'],
                            'min'   => $min + $row['i'] * $step,
                            'max'   => $min + ($row['i'] + 1) * $step,
                        );
                    }
                }
            }

            if ($cached)
            {
                $cache_server->set($key, $data, SEARCH_CACHE_TTL);
            }
        }

        return $data;
    }
	/*将分类匹配对应商品数据 by newrain*/
	function _ejcat_goodslist($catearr='',$goodslist=''){
		//声明返回数组
		$result = array();
		if(!empty($catearr) && !empty($goodslist)){
			foreach($catearr as $value){//获取分类信息
				//临时数组变量temp
				$temp['cate_id'] = $value['cate_id'];
				$temp['cate_name'] = $value['cate_name'];
				$temp['count'] = $value['count'];
				$temp['goodslist'] = array();//声明临时商品列表
				foreach($goodslist as $v){ //从商品列表中选择对应分类
					if(count($temp['goodslist'])==5){
						break;
					}
					if($value['cate_id'] == $v['cate_id']){
						$tempgood['goods_id'] = $v['goods_id'];
						$tempgood['default_image'] = $v['default_image'];
						$tempgood['goods_name'] = $v['goods_name'];
						$tempgood['price'] = $v['price'];
						$tempgood['sales'] = $v['sales'];
						array_push($temp['goodslist'],$tempgood);
					}
				}
				array_push($result,$temp);
			}
		}
		return $result;
	}
		/**
         * 取得查询条件语句
         *
         * @param   array $param 查询参数（参加函数_get_query_param的返回值说明）
         *
         * @return  string  where语句
         */
        function _get_goods_conditions( $param ) {
            /* 组成查询条件 */
            $conditions = " g.if_show = 1 AND g.closed = 0 AND s.state = 1"; // 上架且没有被禁售，店铺是开启状态,
            if ( isset( $param['keyword'] ) ) {
                $conditions .= $this->_get_conditions_by_keyword($param['keyword'], ENABLE_SEARCH_CACHE);
            }
            if ( isset( $param['cate_id'] ) ) {
                $conditions .= " AND g.cate_id_{$param['layer']} = '" . $param['cate_id'] . "'";
            }
            if ( isset( $param['brand'] ) ) {
                $conditions .= " AND g.brand = '" . $param['brand'] . "'";
            }
            if ( isset( $param['region_id'] ) ) {
                $conditions .= " AND s.region_id = '" . $param['region_id'] . "'";
            }
            if ( isset( $param['price'] ) ) {
                $min = $param['price']['min'];
                $max = $param['price']['max'];
                $min > 0 && $conditions .= " AND g.price >= '$min'";
                $max > 0 && $conditions .= " AND g.price <= '$max'";
            }

            return $conditions;
        }
}
