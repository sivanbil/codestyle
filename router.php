<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Plan\PlanRouter;
use App\Http\Controllers\Course\CourseRouter;
use App\Http\Controllers\Material\MaterialRouter;
use App\Http\Controllers\Script\ScriptRouter;

class Router
{
    protected static $router_list = [];

    /**
     * register module router
     * @var array
     */
    protected static $_router_instance = [
        PlanRouter::class,
        MaterialRouter::class,
        CourseRouter::class,
        ScriptRouter::class,
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
