<?php

namespace studioespresso\navigate\base;

use Craft;
use studioespresso\navigate\Navigate;

trait PluginTrait
{
    // Static Properties
    // =========================================================================
    public static $plugin;

    // Static Methods
    // =========================================================================
    public static function error($message, $params = [], $options = [])
    {
        Navigate::$plugin->getLogs()->log(__METHOD__, $message, $params, $options);
    }
    public static function info($message, $params = [], $options = [])
    {
        Navigate::$plugin->getLogs()->log(__METHOD__, $message, $params, $options);
    }

}