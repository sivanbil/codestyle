<?php

namespace App\Http\Controllers;

use App\Http\Controllers\x\xRouter;
use App\Http\Controllers\xx\xxxRouter;
use App\Http\Controllers\xxx\xxxRouter;
use App\Http\Controllers\xxxx\xxxxRouter;

class Router
{
    protected static $router_list = [];

    /**
     * register module router
     * @var array
     */
    protected static $_router_instance = [
        xRouter::class,
        xxxRouter::class,
        xxxRouter::class,
        xxxxRouter::class,
    ];

    protected static function registerRouter()
    {
        foreach(self::$_router_instance as $instance)
        {
            self::$router_list = array_merge(self::$router_list, $instance::$_route_alias);
        }

    }

    /**
     * @return array
     */
    public static function getRouter()
    {
        self::registerRouter();
        return self::$router_list;
    }
}
