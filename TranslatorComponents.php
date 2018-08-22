<?php

namespace ApiService\Components\Translator;


class TranslatorComponents
{

    use Config;

    public $queryText = '';
    public $result = NULL;
    public $appid;
    public $appsecret;
    public $fromLang;
    public $toLang;
    public $apiurl;
    protected static $_filter_value = [];
    private $_config;
    private $_flag;

    public function __construct($flag = 'youdaoai')
    {
        $this->_flag = $flag;
        $this->_config = $this->getConfig($flag);
        $this->appid = $this->_config['appid'];
        $this->appsecret = $this->_config['appsecret'];
        $this->fromLang = $this->_config['fromLang'];
        $this->toLang = $this->_config['toLang'];
        $this->apiurl = $this->_config['apiurl'];
    }

    public function query($filter = TRUE)
    {

        if($this->_flag == 'googletrans') {
            $query_params = [
                'apiurl' => $this->apiurl,
                'text' => $this->queryText
            ];
        } else {
            $salt = mt_rand(1, 9);
            $appkey = $this->appid;
            $from = $this->fromLang;
            $to = $this->toLang;
            $secret = $this->appsecret;
            $sign = $this->makeSign([$appkey, $this->queryText, $salt, $secret]);
            $query_params = [
                'apiurl' => $this->apiurl,
                'q' => $this->queryText,
                'from' => $from,
                'to' => $to,
                'appKey' => $appkey,
                'salt' => $salt,
                'sign' => $sign,
            ];

        }
        $jsonreturn = TransSdk::getTransObject($this->_flag)->getQuery($query_params, $filter)->callback();
        $this->result = $jsonreturn;
        return $this;
    }

    public function simpleQuery()
    {
        $jsonreturn = TransSdk::getTransObject($this->_flag)->getQuery([
            'apiurl' => $this->simple_query_url,
            'keyfrom' => 'xxx',
            'key' => 'xxxxxxxxxxxxx',
            'type' => 'data',
            'doctype' => 'json',
            'version' => '1.1',
            'q' => $this->queryText,
            'speed' => 15
        ], false)->callback();
        $this->result = $jsonreturn;
        return $this;
    }

    public function setQueryText($querytext)
    {
        $this->queryText = mb_convert_encoding($querytext, "UTF-8");
        return $this;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function makeSign(array $signparams)
    {
        foreach($signparams as &$val)
        {
            $val = mb_convert_encoding($val, "UTF-8");
        }
        unset($val);
        return md5($signparams[0] . $signparams[1] . $signparams[2] . $signparams[3]);
    }
}

class TransSdk
{
    /**
     * @param $flag
     * @return BaiduSdk|YoudaoSdk
     */
    public static function getTransObject($flag)
    {
        switch($flag)
        {
            case 'youdaoai':
                $sdk = new YoudaoSdk();
                break;
            case 'googletrans':
                $sdk = new GoogleFuncSdk();
                break;
            case 'baidutrans':
            default:
                $sdk = new BaiduSdk();
                break;
        }
        return $sdk;
    }
}

class BaiduSdk extends CommonFuncSdk
{
    protected $_permit_field = [
        'q',
        'from',
        'to',
        'appKey',
        'salt',
        'sign'
    ];

    protected $_map_fields = [
        'appKey' => 'appid'
    ];

    public function callback()
    {
        $datalist = json_decode($this->_data_list, true);
        $reset_result = [];
        if(isset($datalist['error_code'])) {
            $reset_result['errorCode'] = $datalist['error_code'];
            $reset_result['query'] = [];
            $reset_result['translation'] = [];
        } else {
            $reset_result['errorCode'] = 0;
            $reset_result['query'] = [$datalist['trans_result'][0]['src']];
            $reset_result['translation'] = [$datalist['trans_result'][0]['dst']];
        }
        return json_decode(json_encode($reset_result, JSON_UNESCAPED_UNICODE), true);
    }
}

class YoudaoSdk extends CommonFuncSdk
{
    protected $_permit_field = [
        'q',
        'from',
        'to',
        'appKey',
        'salt',
        'sign'
    ];
    protected static $_filter_value = [
        'errorCode','query','translation'
    ];

    public function callback()
    {
        $result_arr = json_decode($this->_data_list, true);
        $reset_result = [];

        foreach(self::$_filter_value as $key => $field)
        {
            if(isset($result_arr[$field])) {
                $reset_result[$field] = $result_arr[$field];
            }
        }
        return json_decode(json_encode($reset_result, JSON_UNESCAPED_UNICODE), true);
    }

}

class CommonFuncSdk
{
    protected $_data_list = [];
    protected $_permit_field = [];
    protected $_map_fields = [];


    public static function curlDirectQuery($params)
    {
        if(isset($params['speed']) && $params['speed']) {
            sleep($params['speed']);
            unset($params['speed']);
        }
        $apiurl = $params['apiurl'];
        unset($params['apiurl']);
        $ch = curl_init();
        foreach($params as &$val)
        {
            $val = mb_convert_encoding($val, "UTF-8");
        }
        unset($val);
        $data_string = http_build_query($params);
        if($data_string) {
            $apiurl .= '?' . $data_string;
        }
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        return curl_exec($ch);
    }

    public function getQuery(array $params, $filter = TRUE)
    {
        // todo
        if($filter) {
            foreach ($this->_permit_field as $key => $field) {
                if (!isset($params[$field])) {
                    die($field . ' is missed.');
                }
                if($this->_map_fields && isset($this->_map_fields[$field])) {
                    $addfiled = $this->_map_fields[$field];
                    $params[$addfiled] = $params[$field];
                    unset($params[$field]);
                }
            }
        }
        $this->_data_list = self::curlDirectQuery($params);

        return $this;
    }
}

class GoogleFuncSdk
{
    protected $_data_list = [];
    protected $_permit_field = ['text'];
    protected $_map_fields = [];

    protected static $_filter_value = [
        'text'
    ];

    public static function curlDirectQuery($params)
    {

        $apiurl = $params['apiurl'];
        unset($params['apiurl']);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode(["text"=>$params['text']]));
        return curl_exec($ch);
    }

    public function getQuery(array $params, $filter = TRUE)
    {
        if($filter) {
            foreach ($this->_permit_field as $key => $field) {
                if (!isset($params[$field])) {
                    die($field . ' is missed.');
                }
                if($this->_map_fields && isset($this->_map_fields[$field])) {
                    $addfiled = $this->_map_fields[$field];
                    $params[$addfiled] = $params[$field];
                    unset($params[$field]);
                }
            }
        }
        $this->_data_list = self::curlDirectQuery($params);

        return $this;
    }

    public function callback()
    {
        $datalist = json_decode($this->_data_list, true);
        $reset_result = [];
        if(isset($datalist['text'])) {
            $reset_result['errorCode'] = 0 ;
            $reset_result['query'] = empty($datalist['from']['text']['value']) ? []: [$datalist['from']['text']['value']];
            $reset_result['translation'] = [$datalist['text']];
        } else {
            $reset_result['errorCode'] = 0;
            $reset_result['query'] = [];
            $reset_result['translation'] = [];
        }
        return json_decode(json_encode($reset_result, JSON_UNESCAPED_UNICODE), true);
    }
}
