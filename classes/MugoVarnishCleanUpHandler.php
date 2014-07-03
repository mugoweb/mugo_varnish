<?php 

class MugoVarnishCleanUpHandler
{
    public static function purgeList()
    {
        StaticCacheMugoVarnish::purgeList();
    }
}
