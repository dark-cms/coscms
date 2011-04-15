<?php

/**
 * File contains contains class for doing checks on seesions
 *
 * @package    coslib
 */

/**
 * Class contains contains methods for setting sessions
 *
 * @package    coslib
 */
class session {
    // {{{ static public function initSession()
    /**
     * method for initing a session
     * set in_session and start_time of session
     */
    static public function initSession(){

        // if system cookie is set user want to reenable his
        // login
        
        
        
        // figure out session time
        if (isvalue(register::$vars['coscms_main']['session_time'])) {
            ini_set("session.cookie_lifetime", register::$vars['coscms_main']['session_time']);
        }

        // figure out session time
        if (isvalue(register::$vars['coscms_main']['session_path'])) {
            ini_set("session.cookie_path", register::$vars['coscms_main']['session_path']);
        }

        // figure out session host
        if (isvalue(register::$vars['coscms_main']['session_host'])){
            ini_set("session.cookie_domain", register::$vars['coscms_main']['session_host']);
        }


        // use memcache if available
        if (get_main_ini('session_handler') == 'memcache'){
            $host = 'localhost'; $port = '11211';
            $session_save_path = "tcp://$host:$port?persistent=0&weight=2&timeout=2&retry_interval=10,  ,tcp://$host:$port  ";
            ini_set('session.save_handler', 'memcache');
            ini_set('session.save_path', $session_save_path);
        }

        session_start();

        self::checkSystemCookie();

        // if 'started' is set for previous request
        // we truely know we are in 'in_session'
        if (isset($_SESSION['started'])){
            $_SESSION['in_session'] = 1;
        }

        // if not started we do not know for sure if session will work
        // we destroy 'in_session'
        if (!isset($_SESSION['started'])){
            $_SESSION['started'] = 1;
            $_SESSION['in_session'] = null;
        }

        // we set a session start time
        if (!isset($_SESSION['start_time'])){
            $_SESSION['start_time'] = time();
        }
    }

    public static function checkSystemCookie(){

        if (isset($_COOKIE['system_cookie'])){

            // user is in session. Can only be this after first request. 
            if (isset($_SESSION['in_session'])){
                return;
            }

            if (isset($_SESSION['id'])){
                // user is logged in we return
                return;
            }

            

            // no session id. We check database, and see if we can
            // find any there

            $db = new db();
            $db->connect();
            $row = $db->selectOne ('system_cookie', 'cookie_id', $_COOKIE['system_cookie']);

            if (!empty($row)){
                $account = $db->selectOne('account', 'id', $row['account_id']);
                if ($account){
                    $_SESSION['id'] = $account['id'];
                    $_SESSION['admin'] = $account['admin'];
                    $_SESSION['super'] = $account['super'];
                    return;
                }
            }
        }
    }

    // }}}
    public static function setSystemCookie($account_id){

        
        $uniqid = uniqid();
        $uniqid= md5($uniqid);
        // ended cookie
        //setcookie ("system_cookie", "", time() - 3600, "/");

        // calculate days into seconds

        $cookie_time = 3600 * 24 * get_main_ini('cookie_time');
        $timestamp = time() + $cookie_time;

        setcookie('system_cookie', $uniqid, $timestamp, '/');
        
        $db = new db();

        // only keep one system cookie (e.g. if user clears his cookies)
        $db->delete('system_cookie', 'account_id', $account_id);

        $values = array ('account_id' => $account_id , 'cookie_id' => $uniqid, 'timestamp' => $timestamp);
        $db->insert('system_cookie', $values);
    }
    // }}}
    //
    public static function getSystemCookie (){
        if (isset($_COOKIE['system_cookie'])){
            return $_COOKIE['system_cookie'];
        } else {
            return false;
        }
    }

    public static function killSession (){
        setcookie ("system_cookie", "", time() - 3600, "/");
        unset($_SESSION['id'], $_SESSION['admin'], $_SESSION['super'], $_SESSION['account_type']);

    }
    // {{{ static public function isInSession() (ret: boolean)
    /**
     * method for testing if user is in session or not
     * 
     * @return  boolean true or false
     */
    static public function isInSession(){
        if (isset($_SESSION['in_session'])){
            return true;
        } else {
            return false;
        }
    }

    // }}}
    // {{{ static public function getSessionTime()

    /**
     * method for getting how long user has been in session
     *
     * @return int secs in session
     */
    static public function getSessionTime(){
        if (!isset($_SESSION['start_time'])){
            return 0;
        } else {
            return time() - $_SESSION['start_time'];
        }
    }

    // }}}
    // {{{ setActionMessage($message)
    /**
     * method for setting an action message. Used when we want to tell a
     * user what happened if she is redirected
     *
     * @param string the action message.
     */
    static public function setActionMessage($message){
            $_SESSION['system_message'] = $message;
            session_write_close();
    }

    // }}}
    // {{{ static public function getActionMessage()

    /**
     * method for reading an action message
     *
     * @return string current set actionMessage
     */
    static public function getActionMessage(){
        if (isset($_SESSION['system_message'])){
            $message = $_SESSION['system_message'];
            unset($_SESSION['system_message']);
            return $message;
        }
        return null;
    }

    // }}}
    // {{{ isSuper
    /**
     * method for testing if user is in super or not
     *
     * @return  boolean true or false
     */
    static public function isSuper(){
        if ( isset($_SESSION['super']) && ($_SESSION['super'] == 1)){
            return true;
        } else {
            return false;
        }
    }
    // }}}
    // {{{ isAdmin
    /**
     * method for testing if user is admin or not
     *
     * @return  boolean true or false
     */
    static public function isAdmin(){
        if ( isset($_SESSION['admin']) && ($_SESSION['admin'] == 1)){
            return true;
        } else {
            return false;
        }
    }
    // }}}
    // {{{ getUserLevel()
    /**
     * method for getting users level (null, user, admin, super)
     * return   mixed   null or string if null then user is not logged in
     *                  if string we get the users highest level, user, admin or super.
     */
    public static function getUserLevel(){
        if (self::isSuper()){
            return "super";
        }
        if (self::isAdmin()){
            return "admin";
        }
        if (self::isUser()){
            return "user";
        }
        return null;
    }
    // }}}
    // {{{ function isUser()
    /**
     * method for testing if user is loged in or not
     *
     * @return  boolean true or false
     */
    static public function isUser(){
        if ( isset($_SESSION['id']) ){
            return true;
        } else {
            return false;
        }
    }
    // }}}
    // {{{ getUserId() 
    /**
     * checks $_SESSION['id'] and if set it will return 
     * method for getting a users id
     *
     * @return  boolean true or false
     */
    static public function getUserId(){
        if ( !isset($_SESSION['id']) || empty($_SESSION['id']) ){
            return false;
        } else {
            return $_SESSION['id'];
        }
    }
    // }}}
    // {{{ checkAccessControl($allow)
    /**
     * checkAccessControl($allow)
     * checks user level:
     *      super has access to all,
     *      admin has access to more
     *      user has access to less
     *      null has access to least
     *
     * @param   string  user or admin or super
     * @return  boolean true if user has required accessLevel.
     *                  false if not. 
     * 
     */
    static public function checkAccessControl($allow, $setErrorModule = true){
        
        // we check to see if we have a ini setting for 
        // the type to be allowed to an action
        // allow_edit_article = super
        $allow = get_module_ini($allow);

        // is allow is empty means the access control
        // is not set and we grant access
        if (empty($allow)) {
            return true;
        }

        // check if we have a user
        if ($allow == 'user'){
            if(self::isUser()){
                return true;
            } else {
                if ($setErrorModule){
                    moduleLoader::$status[403] = 1;
                }
                return false;
            }
        }


        // check other than users. 'admin' and 'super' is set
        // in special session vars when logging in. User is
        // someone how just have a valid $_SESSION['id'] set

        if (!isset($_SESSION[$allow]) || $_SESSION[$allow] != 1){
            if ($setErrorModule){
                moduleLoader::$status[403] = 1;
            }
            return false;
        } else {
            return true;
        }
    }
    // }}}
    public static function loginThenRedirect ($message){
        unset($_SESSION['redirect_on_login']);
        if (!session::isUser()){
            include_module('account');
            $_SESSION['redirect_on_login'] = $_SERVER['REQUEST_URI'];
            session::setActionMessage($message);
            account::redirectDefault();
            die;
        }
    }
}