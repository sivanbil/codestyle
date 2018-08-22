<?php

namespace ApiService;
use ApiService\Components\Log\LogComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/***
 * Class AbilityCenter
 * @package ApiService
 */

class AbilityCenter extends ApiServiceController implements ServiceInterface
{

    use Config;
    /**
     * @param Request $request
     * @return Response
     */
    public function getAbility(Request $request)
    {
        // 检测字段
        $this->validate($request, [
            'path' => 'required',
            'data' => 'required',
            'sign' => 'required'
        ]);
        $format_data = json_decode($request->input('data'), true);
        $sign = $request->input('sign');

        $params = [
            'sign' => $sign,
            'sysname' => $format_data['sysname'],
            'username' => $format_data['username'],
            'request_data' => $format_data['request_data']
        ];
        // 校验签名
        $this->_checkSign($params);
        // 调用链路层级控制
        $path = $request->input('path');
        $store = [];
        Helper::getRouteCls($path, $store);
        $classObj = Helper::getRouteObj($store);
        if(is_bool($classObj)) {
            return NULL;
        }
        $res = $classObj->$store['method']($request);
        return $res;
    }

    /**
     * @param $params
     */
    private function _checkSign($params)
    {
        $sign = Helper::makeSign($params);
        if($params['sign'] !== $sign) {
            $res_data = [
                'status' => 0,
                'message' => 'Sign was checked failed, please check your params',
                'data' => [
                    'sysname' => $params['sysname'],
                    'username' => $params['username'],
                    'request_data' => [$params]
                ],
                'code' => Code::get('sign_err')
            ];
            $res_data['sign'] = Helper::makeSign($res_data['data']);
            die($this->res($res_data));
        }
    }
}
