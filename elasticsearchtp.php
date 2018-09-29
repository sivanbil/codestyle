<?php
/**
 * | 通用elk 查询
 */

namespace Com\Search\Est;

use System\Base;

class Searcher extends Base {

    private $_document_index = NULL;

    /**
     * Com.Search.Est.Searcher.lists
     * @param $params
     */
    public function lists($params) {
        if(!$this->_setDocParams($params)) {
            return $this->back($this->getErrorField(), \ComFcModule\Err::PARAMS_ERR);
        }
        $lists = $this->_document_index->select();
        $lists['count'] = empty($lists['count']) ? 0 : $lists['count'];
        $lists['data'] = empty($lists['data']) ? [] : $lists['data'];

        DG(['msg' => '上一次est搜索query', 'opdata' => $lists, 'estLastSearch' => $this->_document_index->getLastsearch()], SUB_DG_OBJECT);

        return $this->back($lists);
    }

    /**
     * Com.Search.Est.Searcher.countLists
     * @param $params
     */
    public function countLists($params) {

        if(!$this->_setDocParams($params)) {
            return $this->back($this->getErrorField(), \ComFcModule\Err::PARAMS_ERR);
        }
        $lists = $this->_document_index->select();

        $lists['count'] = empty($lists['count']) ? 0 : $lists['count'];
        DG(['msg' => '上一次est搜索query', 'opdata' => $lists, 'estLastSearch' => $this->_document_index->getLastsearch()], SUB_DG_OBJECT);

        return $this->back(['count' => $lists['count']]);
    }

    /**
     * 多文档搜索，可以支持1对1，一对2
     * test 若是扩展文档里没有数据，但要显示主文档的数据 extend_empty_main_show
     * Com.Search.Est.Searcher.multiDocumentsSearcher
     * @param $params
     */
    public function multiDocumentsSearcher($params) {
        DG(['msg' => 'com 通用多文档搜索组件查询参数', 'opdata' => $params], SUB_DG_OBJECT);
        //验证
        $this->_rule = [
            //查询主文档
            ['main', 'checkArrayInput', PARAMS_ERROR, MUST_CHECK, 'function'],
            // 查询
            ['extend', 'checkArrayInput', PARAMS_ERROR, MUST_CHECK, 'function'],
        ];

        if(!$this->checkInput($this->rule, $params)) {
            $this->back($this->getErrorField());
        }

        if(empty($params['extend']['fc_order_index']['where'])) {
            $params = $this->_search_main_first_list($params);
            if(empty($params)) {
                return $this->back($params, SAME_BACK);
            }
        } else {
            $params = $this->_search_extend_first_list($params);
            if(empty($params)) {
                return $this->back($params, SAME_BACK);
            }
        }

        // 扩展信息为空，那么不显示数据
        if(empty($params['extend_data_list']) && empty($params['extend_empty_main_show'])) {
            return $this->back([]);
        }
        $search_data_list = $this->_searchDataHandle($params);
        $response_search_list = [
            'count' => $params['main_data']['response']['count'],
            'data' => $search_data_list,
        ];
        return $this->back($response_search_list);
    }

    public function _search_extend_first_list($params) {
        if(empty($params['extend']['fc_order_index']['where']['neq'])) {
            $params['extend']['fc_order_index']['where']['neq'] = [];
        }
        $params['extend']['fc_order_index']['where']['neq'] += [
            'fc_order.fc_code' => ''
        ];
        $extend_count_data = $this->invoke('Com.Search.Est.Searcher.lists', $params['extend']['fc_order_index']);

        if(empty($extend_count_data['response']['data'])) {
            return false;
        }
        $params['extend']['fc_order_index']['limit'] = [
            'current_page' => $params['main']['limit']['current_page'],
            'page_size' => $extend_count_data['response']['count']
        ];
        $extend_data = $this->invoke('Com.Search.Est.Searcher.lists', $params['extend']['fc_order_index']);

        $params['extend_data_list']['fc_order_index'] = $extend_data['response']['data'];
        $params['extend_name'] = 'fc_order_index';
        $params['main_data'] = $this->_searchMain($params);
        $params['main_data_list'] = $params['main_data']['response']['data'];
        return $params;
    }

    private function _search_main_first_list($params) {
        $main_data = $this->invoke('Com.Search.Est.Searcher.lists', $params['main']);

        if(empty($main_data['response']['data'])) {
            return $main_data;
        }
        $params['main_data'] = $main_data;
        $params['main_data_list'] = $main_data['response']['data'];

        $params['extend_data_list'] = $this->_searchExtend($params);
        return $params;
    }
    /**
     * Com.Search.Est.Searcher.searchAll
     *
     * multi_type 多文档类型
     * 试用全部订单导出
     */
    public function searchAll($params) {
        $params['search_all_type'] = empty($params['search_all_type']) ? 'order' : $params['search_all_type'];
        switch($params['search_all_type']) {
            case 'order':
                $sum_api_path = 'Com.Search.Est.Searcher.countLists';
                $api_path = 'Com.Search.Est.Searcher.lists';
                $sum_params = $params;
                break;
            case 'order_balance':
                $sum_params = $params['main'];
                $sum_api_path = 'Com.Search.Est.Searcher.countLists';
                $api_path = 'Com.Search.Est.Searcher.multiDocumentsSearcher';
                break;
        }
        $count_res = $this->invoke($sum_api_path, $sum_params);
        $total = empty($count_res['response']['count']) ? 0 : $count_res['response']['count'];
        switch($params['search_all_type']) {
            case 'order':
                $params['limit'] = [
                    'current_page' => 1,
                    'page_size' => $total
                ];
                break;
            case 'order_balance':
                $params['main']['limit'] = [
                    'current_page' => 1,
                    'page_size' => $total
                ];
                break;
        }
        unset($params['search_all_type']);

        $search_data_lists = $this->invoke($api_path, $params);
        return $this->back($search_data_lists, SAME_BACK);
    }

    /** 检测参数
     * @param $params
     * @return null|void
     */
    private function _setDocParams($params) {
        DG(['msg' => 'com 通用单文档搜索组件查询参数', 'opdata' => $params], SUB_DG_OBJECT);
        //验证
        $this->_rule = [
            ['document', 'require', PARAMS_ERROR, MUST_CHECK],
            ['fields', 'require', PARAMS_ERROR, ISSET_CHECK],
            ['where', 'require', PARAMS_ERROR, ISSET_CHECK],
            ['group_by', 'require', PARAMS_ERROR, ISSET_CHECK],
            ['having', 'require', PARAMS_ERROR, ISSET_CHECK],
            ['limit', 'require', PARAMS_ERROR, ISSET_CHECK],
            ['order_by', 'require', PARAMS_ERROR, ISSET_CHECK]
        ];

        if(!$this->checkInput($this->_rule, $params)) {
            return false;
        }

        $this->_document_index = EL($params['document']);

        $this->_setFields($params)->_setWhere($params)->_setGroupBy($params)
            ->_setHaving($params)->_setOrderBy($params)->_setLimit($params);
        return true;
    }

    private function _searchMain($params) {
        $main_data_list = [];
        if(!empty($params['main'])) {
            $relation_field_arr = $params['main']['relation_fields'][$params['extend_name']];

            if(!empty($relation_field_arr)) {

                foreach ($relation_field_arr as $main_field => $extend_field) {
                    $main_field_values = [];
                    foreach($params['extend_data_list'] as $extend_data_list) {
                        foreach($extend_data_list as $extend_data) {
                            if (!empty($extend_data['fc_order']['fc_code'])) {
                                $main_field_values[] = $extend_data['fc_order']['fc_code'];
                            }
                        }
                    }
                    $params['main']['where'][$main_field] = ['in' => $main_field_values];
                }
                $main_data_list = $this->invoke('Com.Search.Est.Searcher.lists', $params['main']);
            }

        }
        return $main_data_list;
    }
    /**
     * 搜索辅助文档
     * @param $params
     * @return array
     */
    private function _searchExtend($params) {
        $extend_data_list = [];
        if(!empty($params['extend'])) {
            foreach($params['extend'] as $name => $extend) {
                //关联字段
                $relation_field_arr = $params['main']['relation_fields'][$name];

                if(!empty($relation_field_arr)) {

                    foreach ($relation_field_arr as $main_field => $extend_field) {

                        $extend_field_values = array_unique(array_column($params['main_data_list'], $main_field));

                        $extend['where'][$extend_field] = ['in' => $extend_field_values];
                    }

                    $extend_count_data = $this->invoke('Com.Search.Est.Searcher.lists', $extend);
                    if(empty($extend_count_data['response']['count'])) {
                        return [];
                    }
                    $extend['limit'] = [
                        'current_page' => 1,
                        'page_size' => $extend_count_data['response']['count']
                    ];
                    $extend_data = $this->invoke('Com.Search.Est.Searcher.lists', $extend);
                    if(!empty($extend_data['response']['data'])) {
                        $extend_data_list[$name]= $extend_data['response']['data'];
                    }
                }

            }
        }
        return $extend_data_list;
    }

    /**
     * 综合处理返回数据
     * @param $params
     * @return array
     */
    private function _searchDataHandle($params) {

        $search_data_list = $params['main_data_list'];

        if(!empty($params['extend_data_list'])) {
            foreach($search_data_list as $main_key => &$main_data) {
                foreach ($params['extend_data_list'] as $extend_index_name => $extend_data_list) {
                    foreach($extend_data_list as $extend_data) {
                        // 主与扩展的关联的字段
                        $relation_field_arr = $params['main']['relation_fields'][$extend_index_name];
                        $relation_field_num = count($relation_field_arr);
                        $match_field_nums = 0;
                        foreach ($relation_field_arr as $rk => $relation_field) {
                            // 检测是否为嵌套的field
                            $point_pos = strpos($relation_field, '.');

                            if (!is_bool($point_pos)) {
                                $rel_arr = explode('.', $relation_field);
                                if ($main_data[$rk] === $extend_data[$rel_arr[0]][$rel_arr[1]]) {
                                    $match_field_nums += 1;

                                }
                            } else {
                                if ($main_data[$rk] === $extend_data[$relation_field]) {
                                    $match_field_nums += 1;
                                }
                            }
                        }
                        // 若关联字段的个数等于字段匹配的数量
                        if ($relation_field_num === $match_field_nums) {
                            $main_data[$extend_index_name][] = $extend_data;
                        }
                    }
                    if(empty($main_data[$extend_index_name])) {
                        unset($search_data_list[$main_key]);
                    }
                }
            }
            unset($main_data);
        }
        return $search_data_list;
    }
    /**
     * Com.Search.Est.Searcher.lists
     * @param $params
     */
    public function info($params) {
        //验证
        $this->_rule = array(
            ['fields', 'require', PARAMS_ERROR, ISSET_CHECK],
            ['where', 'require', PARAMS_ERROR, ISSET_CHECK],
            ['document', 'require', PARAMS_ERROR, MUST_CHECK],
        );

        if(!$this->checkInput($this->rule, $params)) {
            $this->back($this->getErrorField());
        }

        $this->_document_index = EL($params['document']);

        $this->_setFields($params)->_setWhere($params);

        $info = $this->_document_index->find();

        return $this->back($info);
    }

    private function _setGroupBy($params) {

        if(!empty($params['group_by'])) {
            $this->_document_index->group($params['group_by']);
        }

        return $this;
    }

    private function _setHaving($params) {

        if(!empty($params['having'])) {
            $this->_document_index->having($params['having']);
        }

        return $this;
    }

    private function _setFields($params) {

        if(!empty($params['fields'])) {
            $this->_document_index->field($params['fields']);
        }

        return $this;
    }

    private function _setWhere($params) {

        if(!empty($params['where'])) {
            $this->_document_index->where($params['where']);
        }
        return $this;
    }

    private function _setOrderBy($params) {

        if(!empty($params['order_by'])) {
            $this->_document_index->order($params['order_by']);
        }
        return $this;
    }

    /**
     * @param $params
     * @return $this
     */
    private function _setLimit($params) {

        if(!empty($params['limit'])) {
            $current_page =  isset($params['limit']['current_page']) ? $params['limit']['current_page'] : 1;

            $page_size = isset($params['limit']['page_size']) ?  $params['limit']['page_size'] : 10;;

            $offset = ($current_page - 1) * $page_size;

            $this->_document_index->limit($offset, $page_size);
        }
        return $this;
    }
}
