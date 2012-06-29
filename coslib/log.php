<?php

/**
 * File contains helper functions. 
 * 
 *
 * @package    coslib
 */

class log {
    public static function error ($message, $write_file = true) {
        cos_error_log($message, $write_file);
    }
    
    public static function message ($message, $write_file = true) {
        cos_error_log($message, $write_file);
    }
    
    public static function debug ($message) {
        cos_debug($message);
    }
    
    public static function createLog () {
        if (!defined('_COS_PATH')) {
            die('No _COS_PATH defined');
        }
        
        $file = _COS_PATH . "/logs/error.log";
        if (!file_exists($file)) {
            $res = @file_put_contents($file, '');
            if ($res === false) {
                die("Can not create log file: $file");
            }
        }
    }
}

/**
 * puts a string in logs/error.log
 * You can log objects and arrays. They will be exported to a string
 * @param mixed $message
 */
function cos_error_log ($message, $write_file = true) {
    if (!is_string($message)) {
        $message = var_export($message, true);
    }
    
    $message = strftime('%c', time()) . ": " . $message;
    $message.="\n";
    if (isset($write_file)) {
        error_log($message, 3, $write_file);
    } else {
        error_log($message);
    }
}

/**
 * simple debug which write to error log if 'debug' is set in main config.ini
 * @param mixed $message
 * @return void 
 */
function cos_debug ($message) {
    static $debug = null;
    if ($debug) {
        cos_error_log($message);
        return;
    }
    
    if (config::getMainIni('debug')) {
        $debug = 1;
    }
}
