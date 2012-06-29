<?php

/**
 * File contains class for loading modules
 *
 * @package    coslib
 */

/**
 * Class for loading modules
 *
 * @package    coslib
 */
class moduleLoader {

    /**
     *
     * @var array $modules all enabled modules
     */
    public static $modules = array();

    /**
     *
     * @var array $lvels holding different run levels
     */
    public $levels = array();

    /**
     *
     * @var array $info 
     *                  
     * holding info about files to load when loaidng module.
     */
    public $info = array();
    
    /**
     * @var     array   $status 
     *                  
     *                  static variable which can be set in case we don't
     *                  want to load called module. Used for enablingloading
     *                  of error module when an error code has been set.
     *                  self::$status[403] or self::$status[404]
     */
    public static $status = array();

    /**
     *
     * @var array   $iniSettings 
     *              
     *              holding module ini settings.
     */
    public static $iniSettings = array();
    
    
    /**
     * constructer recieves module list and places them in $this->levels where
     * we can see at which run level modules should be run.
     * 
     * ModuleLoader will call self::getAllModules which in turn will
     * connect first time to database. 
     */
    public function __construct(){
        self::$modules = self::getAllModules(); 
        $this->setLevels();

        if (!isset(config::$vars['coscms_main']['module'])){
            config::$vars['coscms_main']['module'] = array();
        }
    }
    
    public static function setStatus ($code) {
        moduleLoader::$status[$code] = 1;
    }

    /**
     * method for getting all modules from db. This is the first time we 
     * connect to database. 
     * 
     * @return array $ary array with all rows from modules table
     */
    public static function getAllModules (){

        if (!empty(self::$modules)) {
            return self::$modules;
        }
        
        static $modules = null;
        if ($modules) return $modules;
        
        // we connect here because this should be 
        // the first time we use the database
        // in the system
        
        $db = new db();
        $db->connect();

        return $modules = $db->selectAll('modules');
    }
    
    /**
     * get all installed module name only
     * @return array $ary array of module names 
     */
    public static function getInstalledModuleNames () {
        $mods = self::getAllModules();
        $ins = array ();
        foreach ($mods as $val) {
            $ins[] = $val['module_name'];
        }
        return $ins;
    } 

    /**
     * moduleExists alias of isInstalledModule
     * @param string $module_name
     * @return boolean $res true if module exists else false.  
     */
    public static function moduleExists ($module_name) {
        return self::isInstalledModule($module_name);
    }
    
    /**
     *
     * get child modules to a parent module
     * @param string $parent name of parent module
     * @return array $ary containing child modules.
     */
    public static function getChildModules ($parent){
        static $children = array();
        if (isset($children[$parent])) return $children[$parent];

        foreach (self::$modules as $val){
            if ($val['parent'] == $parent){
                $children[$parent][] = $val['module_name'];
            }
        }

        if (empty($children[$parent])){
            return array();
        }

        return $children[$parent];
    }

    /**
     * method for getting a modules parent name.
     * 
     * @param string    $module the module to examine for a parent
     * @return mixed    $res if a parent module is found we return the parent module name
     *                       else we return null
     */
    public static function getParentModule ($module){        
        static $parent = null;
        if (isset($parent)) return $parent;
        foreach (self::$modules as $val){
            if ($val['module_name'] != $module) { 
                continue;
            } else {
                if (isset($val['parent'])){
                    $parent = $val['parent'];
                    return $parent;
                }
            }
        }
        return null;
    }

    /**
     * 
     * method for placeing all modules in $this->levels 
     * according the modules run_levels
     */
    public function setLevels(){
        foreach (self::$modules as $key => $val){
            $module_levels = explode(',', $val['run_level']);
            foreach ($module_levels as $k => $v){
                $this->levels[$v][] = $val['module_name'];
            }
        }
    }

    /**
     * check if a module is installed / exists
     * @param string $module the module we examine
     * @return boolean $res boolean
     */
    public static function isInstalledModule($module){
        foreach (self::$modules as $val){
            if ($val['module_name'] == $module){
                return true;
            }
        }
        return false;
    }

    /**
     * method for running a module at a exact runlevel.
     * This is used in coslib/head.php (bootstrap file)
     *
     * @param int $level the runlevel to run [1- 7]
     */
    public function runLevel($level){

        if (!isset($this->levels[$level])) return;
        foreach($this->levels[$level] as $val){
            moduleLoader::setModuleIniSettings($val);
            $class_path = _COS_PATH . "/modules/$val/model.$val.inc";
            include_once $class_path;
            $class = new $val;
            $class->runLevel($level);
        }
    }

    /**
     * method for setting info for home module info
     * home module is set in config/config.ini with frontpage_module
     * home module deals with requests to /
     */
    public function setHomeModuleInfo(){
        
        $frontpage_module = config::$vars['coscms_main']['frontpage_module'];
        $this->info['module_base_name'] = $frontpage_module;
        $this->info['base'] = $base = _COS_PATH . "/modules";
        $this->info['language_file'] = $base . "/$frontpage_module" . '/lang/' . config::$vars['coscms_main']['language'] . '/language.inc';
        $this->info['ini_file'] =  $base . "/$frontpage_module"  . "/$frontpage_module" . '.ini';
        $this->info['model_file'] = $base . "/$frontpage_module"  . "/model." . $frontpage_module  . ".inc";
        $this->info['view_file'] = $base . "/$frontpage_module"  . "/view." . $frontpage_module . ".inc";

        $controller_dir = $base . "/$frontpage_module/";
        $first = uri::fragment(0);
        
        if (!empty($first)){
            $controller_file = $controller_dir . $first . ".php";
        } else {
            $controller_file = $controller_dir . "index.php";
        }
        $this->info['controller_file'] = $controller_file;

    }
    
    /**
     * method for setting info for error module. E.g. we recieve
     * a 404 or 403 from a module, and we want to let the error module
     * take care
     * 
     * Notice: At the moment you can not have your own error module. 
     * But it wil lbe easy to implement at some point. 
     * 
     */
    public function setErrorModuleInfo(){     
        $error_module = 'error';
        $this->info['base'] = $base = _COS_PATH . "/modules";
        $this->info['language_file'] = $base . "/$error_module" . '/lang/' . config::$vars['coscms_main']['language'] . '/language.inc';
        $this->info['ini_file'] =  $base . "/$error_module"  . "/$error_module" . '.ini';
        $this->info['model_file'] = $base . "/$error_module"  . "/model." . $error_module  . ".inc";
        $this->info['view_file'] = $base . "/$error_module"  . "/view." . $error_module . ".inc";

        if (isset(self::$status[404])){
            $controller_file = $base . "/$error_module". '/404.php';
        }
        if (isset(self::$status[403])){           
            $controller_file = $base . "/$error_module". '/403.php';
        }

        $this->info['controller_file'] = $controller_file;
        $this->info['controller'] = "403.php";
    }

    /**
     * method for setting the requested module info
     * According to the layout of the coscms project there it has not
     * been possible to create routes and route a specific url to 
     * a specific controller. 
     */
    public function setModuleInfo (){
        
        //$routes = config::getMainIni('routes');
        //urldispatch::includeFile($routes);

        $uri = uri::getInstance();
        $info = uri::getInfo();
       
        // if no module_base is set in the URI::info we can will use
        // the home module
        if (empty($info['module_base'])){
            $this->setHomeModuleInfo();
            return;
        }

        // if we only have one fragment 
        // means we are in frontpage module
        $frontpage_module = config::$vars['coscms_main']['frontpage_module'];

        if ($uri->numFragments() == 1){
            $this->info['module_base_name'] = $frontpage_module;
            $this->info['base'] = $base = _COS_PATH . "/modules/$frontpage_module";
        } else {
            
            $this->info['module_base_name'] = $info['module_base_name'];
            $this->info['base'] = $base = _COS_PATH . "/modules";
        }
       
        $this->info['language_file'] = $base . $info['module_base'] . '/lang/' . config::$vars['coscms_main']['language'] . '/language.inc';
        $this->info['ini_file'] =  $base . $info['module_base'] . $info['module_base'] . '.ini';
        $this->info['ini_file_php'] =  $base . $info['module_base'] . $info['module_base'] . '.php.ini';
        $this->info['model_file'] = $base . $info['controller_path_str'] . "/model." . $info['module_frag'] . ".inc";
        $this->info['view_file'] = $base . $info['controller_path_str'] . "/view." . $info['module_frag'] . ".inc";        
        $controller_file = $base . $info['controller_path_str'] . '/' . $info['controller'] . '.php';
        
        
        $this->info['controller_file'] = $controller_file;
        $this->info['controller'] = $info['controller'];

        // all we need is a controller. anything else is optional
        if (!file_exists($this->info['controller_file'])){
            $mes = "Controller file does not exists: ";
            $mes.= $this->info['controller_file'];
            error_log($mes);
            self::$status[404] = 1;
            $this->setErrorModuleInfo();    
        }

        if (!$this->isInstalledModule($info['module_base_name'])){
            self::$status[404] = 1;
            $mes = "module not installed: ";
            $mes.= $info['module_base_name'];
            error_log($mes);
            $this->setErrorModuleInfo(); 
        }
    }

    /**
     * method for initing a module
     * Loads ini file if exists. If ini file exists. 
     * Check for PHP ini PHP config exists. 
     * Sets module template if specified. 
     * Sets controller specific template if specified. 
     * 
     * Then loads the model file model.module_name.inc if exists
     * Then loads the view file view.module_name.inc if exists
     * Then load language if exists
     * 
     * If any module then have to be called. We init any modules
     * from information found in the module table. These modules
     * has the flag load_on set 
     */
    public function initModule(){

        // set ini file
        if (file_exists($this->info['ini_file'])){
            $module = $this->info['module_base_name'];
            self::setModuleIniSettings($module);
            
            // load php ini if exists
            if (isset(config::$vars['coscms_main']['module']['load_php_ini'])){
                include $this->info['ini_file_php'];
                config::$vars['coscms_main']['module'] = 
                        array_merge(config::$vars['coscms_main']['module'], 
                        $_MODULE_SETTINGS);
            }

            // set module template if specified
            if (isset(config::$vars['coscms_main']['module']['template'])){
                config::$vars['coscms_main']['template'] = config::$vars['coscms_main']['module']['template'];
            }

            // load controller specific template if specified
            if (isset(config::$vars['coscms_main']['module']['page_template'])){
                $page_template = explode (':', config::$vars['coscms_main']['module']['page_template']);
                if ($this->info['controller'] == $page_template[0]){
                    config::$vars['coscms_main']['template'] = $page_template[1];
                }
            }
        }
        
        // include model if exists
        if (file_exists($this->info['model_file'])){
            include_once $this->info['model_file'];
        }

        // include view file if exists
        if (file_exists($this->info['view_file'])){
            include_once $this->info['view_file'];
        }

        // include language file if exists.
        if (file_exists($this->info['language_file'])){
            include $this->info['language_file'];
            if (isset($_COS_LANG_MODULE)){
                lang::$dict = array_merge(lang::$dict, $_COS_LANG_MODULE);
            }
        }

        // load any modules connected to this module
        // we can see this is 'load_on' is set in module table
        $module_name = uri::$info['module_name'];
        foreach (self::$modules as $val){
            if (!isset($val['load_on'])) continue;
            if ($val['load_on'] === $module_name){
                moduleLoader::includeModule($val['module_name']);
                $class_name = self::modulePathToClassName($val['module_name']);
                $class_object = new $class_name(); 
                $class_object->init();
            }    
        }
    }
    
    /**
     * var holding a reference name
     * @var mixed $reference 
     */
    public static $reference = null;
    
    /**
     * var holding a referenceId
     * @var int $referenceId 
     */
    public static $referenceId = 0;
    
    /**
     * 
     * @var int 
     */
    public static $id;
    public static $referenceLink = null;
    public static $referenceRedirect = null;
    public static $referenceOptions = null;
    
    public static function includeRefrenceModule (
            $frag_reference_id = 2, 
            
            // reserved. Will be set by the module in reference
            // e.g. will be set in files when used in content.
            
            $frag_id = 3,
            $frag_reference_name = 4) {    
        
        $reference = uri::$fragments[$frag_reference_name];  
        $id = uri::$fragments[$frag_id]; 
        $extra =  uri::getInstance()->fragment($frag_reference_name +1); 
        
        if (isset($extra) && !empty($extra)) {
            $reference.= "/$extra";
        }
        
        // normal this will not be set. 
        // because imagine this situation
        // $id = uri::$fragments[$frag_id];
        $reference_id = uri::$fragments[$frag_reference_id];

        if (!isset($reference)){
            return false;
        }
        
        $res = moduleLoader::includeModule($reference);
        //$options = array ('reference_type' => self::$referenceType);
        
        
        if ($res) {
            $class = moduleLoader::modulePathToClassName($reference);
            self::$reference = $reference;
            self::$id = $id;
            self::$referenceId = $reference_id;
            self::$referenceLink = $class::getLinkFromId(
                    moduleLoader::$referenceId, moduleLoader::$referenceOptions);
            self::$referenceRedirect = $class::getRedirect(
                    moduleLoader::$referenceId, moduleLoader::$referenceOptions);
            self::$referenceRedirect = html::getUrl(self::$referenceRedirect);
            
            return true;
        }
        return false;
    }
    
    /** 
     * return all set reference info as an array 
     * @return array $ary array 
     *                      (parent_id, inline_parent_id, reference, link, redirect)
     */
    public static function getReferenceInfo () {
        $ary = array ();
        $ary['parent_id'] = self::$referenceId;
        $ary['inline_parent_id'] = self::$id;
        $ary['reference'] = self::$reference;
        $ary['link'] = self::$referenceLink;
        $ary['redirect'] = self::$referenceRedirect;
        return $ary;
    }

    /**
     * return modules classname from a modules path.
     * e.g. account_profile will return accountProfile
     * e.g. content/article will return contentArticle
     * 
     * @param  string   $path (e.g. account/profile)
     * @return string   $classname (e.g. accountProfile)
     */
    public static function modulePathToClassName ($path){

        $ary = explode('/', $path);
        if (count($ary) == 1){
            $class = $path;
        }
        if (count($ary) == 2){
            $class = $ary[0] . ucfirst($ary[1]);
        }

        $ary = explode('_', $class);
        if (count($ary) == 1){
            return $ary[0];
        }
        if (count($ary) == 2){
            $str = $ary[0] . ucfirst($ary[1]);
            return $str;
        }
    }

    /**
     * returns a modules class path from modules path
     * @param   string $path (e.g. content/article)
     * @return  string $class_path (e.g. content/article/model.article.inc)
     */
    public static function modulePathToModelPath ($path){
        $ary = explode('/', $path);
        if (count($ary) == 1){
            return "$path/model.$path.inc";
        }
        if (count($ary) == 2){
            return "$path/model.$ary[1].inc";
        }
    }
    
    /**
     * method for loading a parsing module
     *
     * @return string the parsed modules html
     */
    public function loadModule(){
        include_once $this->info['controller_file'];        
        if (isset(self::$status[403])){
            $this->setErrorModuleInfo();
            $this->initModule();
            include_once $this->info['controller_file'];  
        }

        if (isset(self::$status[404])){
            $this->setErrorModuleInfo();
            $this->initModule();
            include_once $this->info['controller_file'];
        }

        $str = ob_get_contents();
        ob_clean();
        return $str;
    }
    
    /**
     * method for getting a modules ini settings.
     *
     * @return  array   array with ini settings of module.
     */
    public static function getModuleIniSettings($module, $single = null){

        // only read ini file settings once.
        if (!isset(self::$iniSettings[$module])){
            self::setModuleIniSettings($module);
            if (isset($single)){
                if (isset(self::$iniSettings[$module][$single])){
                    return self::$iniSettings[$module][$single];
                }
            }
        } 
    }


    /**
     * method for getting a modules ini settings.
     *
     * @return  array   array with ini settings of module.
     */
    public static function setModuleIniSettings($module, $type = 'module'){

        static $set = array();
        if (!isset(self::$iniSettings['module'])){
            self::$iniSettings['module'] = array();
        }
        
        if (!isset(config::$vars['coscms_main']['module'])){
            config::$vars['coscms_main']['module'] = array ();
        }

        if (isset($set[$module])) {
            return;
        }

        $set[$module] = $module;
        if ($type == 'module') {
            $ini_file = _COS_PATH . "/modules/$module/$module.ini";
        } else {
            // template
            $ini_file = _COS_PATH . "/htdocs/templates/$module/$module.ini";
        }
        
        if (!file_exists($ini_file)) {
            return;
        }
                
        self::$iniSettings[$module] = config::getIniFileArray($ini_file, true);
        if (is_array(self::$iniSettings[$module])){
            config::$vars['coscms_main']['module'] = array_merge(
                config::$vars['coscms_main']['module'],
                self::$iniSettings[$module]
            );
        }

        // check if development settings exists.
        if (isset(self::$iniSettings[$module]['development'])){
            // check if we are on a development server.
            // Note: Development needs to be set in main config/config.ini
            if (

                config::$vars['coscms_main']['development']['server_name']
                    ==
                @$_SERVER['SERVER_NAME']){


                // we are on development, merge and overwrite normal settings with
                // development settings.
                config::$vars['coscms_main']['module'] =
                    array_merge(
                        config::$vars['coscms_main']['module'],
                        self::$iniSettings[$module]['development']
                    );
            }
        }
        
        // check if development settings exists.
        if (isset(self::$iniSettings[$module]['stage'])){
            
            // check if we are on a development server.
            // Note: Development needs to be set in main config/config.ini
            if (

                config::$vars['coscms_main']['stage']['server_name']
                    ==
                @$_SERVER['SERVER_NAME']){


                // we are on development, merge and overwrite normal settings with
                // development settings.
                config::$vars['coscms_main']['module'] =
                    array_merge(
                        config::$vars['coscms_main']['module'],
                        self::$iniSettings[$module]['stage']
                    );
            }
        }
    }

    /**
     * method for getting modules pre content. pre content is content shown
     * before the real content of a page. E.g. admin options if any. 
     * 
     * @param array $modules the modules which we want to get pre content from
     * @param array $options spseciel options to be send to the sub module
     * @return string   the parsed modules pre content as a string
     */
    public static function subModuleGetPreContent ($modules, $options) {
        $str = '';
        $ary = array();
        if (!is_array($modules)) return;
        foreach ($modules as $val){
            $str = '';
            if (method_exists($val, 'subModulePreContent') && moduleLoader::isInstalledModule($val)){
                $str = $val::subModulePreContent($options);
                if (!empty($str)) {
                    $ary[] = $str;
                }
            }
        }
        
        return $ary;
        //return self::parsePreContent($ary);
    }
    
    /**
     * method for getting modules pre content. pre content is content shown
     * before the real content of a page. E.g. admin options if any. 
     * 
     * @param array $modules the modules which we want to get pre content from
     * @param array $options spseciel options to be send to the sub module
     * @return string   the parsed modules pre content as a string
     */
    public static function subModuleGetAdminOptions ($modules, $options) {
        $str = '';
        $ary = array();
        if (!is_array($modules)) return array ();
        foreach ($modules as $val){
            if (method_exists($val, 'subModuleAdminOption') && moduleLoader::isInstalledModule($val)){
                $str = $val::subModuleAdminOption($options);
                if (!empty($str)) $ary[] = $str;
            }
        }
        return $ary;
    }
    
    public static function buildReferenceURL ($base, $params) {
        if (isset($params['id'])) {
            $extra = $params['id'];
        } else {
            $extra = 0;
        }
        
        $url = $base . "/$params[parent_id]/$extra/$params[reference]";
        return $url;
    }
    
    /**
     * method for parsing the admin options. As there can be more modules
     * we iritate over an array of sub modules and return the admin menu
     * as a string. 
     * 
     * @param array $ary the array of strings
     * @return string $str string
     */
    public static function parseAdminOptions ($ary = array()){
        $num = count($ary);
        $str = "<div id =\"content_menu\">\n";
        $str.= "<ul>\n";
        foreach ($ary as $val){
            $num--;
            if ($num) {
                $str.= "<li>" . $val . MENU_SUB_SEPARATOR .  "</li>\n";
            } else {
                $str.= "<li>" . $val . "</li>\n";
            }
        }
        $str.= "</ul>\n";
        $str.= "</div>\n";
        return $str;
    }

    /**
     * method for setting inline content
     * @param array $modules
     * @param array $options
     * @return string 
     */
    public static function subModuleGetInlineContent ($modules, $options){
        $ary = array ();
        if (!is_array($modules)) return $ary;
        foreach ($modules as $val){
            if (method_exists($val, 'subModuleInlineContent') && moduleLoader::isInstalledModule($val)){
                $str = $val::subModuleInlineContent($options);
                if (!empty($str)) $ary[] = $str;
            }
        }
        return $ary;
    }

    /**
     * method for getting post content of some modules
     * @param type $modules
     * @param type $options
     * @return string the post content as a string. 
     */
    public static function subModuleGetPostContent ($modules, $options){

        $ary = array ();
        if (!is_array($modules)) return $ary;
        foreach ($modules as $val){
            if (method_exists($val, 'subModulePostContent') && moduleLoader::isInstalledModule($val)){
                $str = $val::subModulePostContent($options);
                if (!empty($str)) $ary[] = $str;
            }
        }
        return $ary;
        
    }

    /**
     *method for including modules
     * @param array $modules
     * @return false|void   false if no modules where given.  
     */
    public static function includeModules ($modules) {
        if (!is_array($modules)) return false;
        foreach ($modules as $key => $val) {
            lang::loadModuleLanguage($val);
            moduleLoader::includeModule ($val);
        }
    }
    
    public static function includeModuleFromStaticCall ($call){
        $call = explode ('::', $call);
        $module = $call[0];
        return include_module($module);
    }
     
    public static function includeModule ($module) {
        return include_module($module);
    }
    
    public static function includeTemplateCommon ($template) {
        include_template_inc($template);
    }
    
    public static function includeModel ($model) {
        include_model($model);
    }
    
    public static function includeController ($controller) {
        include_controller($controller);
    }
    
    public static function includeFilters ($filters) {
        include_filters($filters);
    }
    
    public static function getFiltersHelp ($filters) {
        return get_filters_help($filters);
    }
    
    public static function getFilteredContent ($filters, $content) {
        return get_filtered_content($filters, $content);
    }
}








/**
 * function for including a templates function file, which is always placed in
 * /templates/template_name/common.inc
 * @param string $template the template name which we want to load.  
 */
function include_template_inc ($template){
    include_once _COS_PATH . "/htdocs/templates/$template/common.inc";
}

/**
 * function for including a compleate module
 * with configuration, view, language, and model file
 *
 * @param   string  $module the name of the module to include
 */
function include_module($module){

    static $modules = array ();
    if (isset($modules[$module])){
        // module has been included
        return true;
    }

    $module_path = config::$vars['coscms_base'] . '/modules/' . $module;
    $ary = explode('/', $module);
    $last = array_pop($ary);
    $model_file = $module_path . '/' . "model.$last.inc";  
    $view_file = $module_path . '/' . "view.$last.inc";
    $ary = explode('/', $module);

    lang::loadModuleLanguage($ary[0]);
    moduleLoader::setModuleIniSettings($ary[0]);

    if (file_exists($view_file)){
        include_once $view_file;
    }
    if (file_exists($model_file)){
        include_once $model_file;
        $modules[$module] = true;
        return true;
    } else {
        return false;
    }

}

/**
 * function for including the model file only
 * @param   string   $module the module where the model file exists 
 *                   e.g. (content/article)
 */
function include_model($module){
    $module_path = 'modules/' . $module;
    $ary = explode('/', $module);
    $last = array_pop($ary);
    $model_file = $module_path . '/' . "model.$last.inc";
    include_once $model_file;
}



/**
 * function for including a controller
 * @param string    $controller the controller to include (e.g. content/article/add)
 */
function include_controller($controller){
    $module_path = config::$vars['coscms_base']  . '/modules/' . $controller;
    $controller_file = $module_path . '.php';
    include_once $controller_file;
}

/**
 * function for including a filter module
 * @param   array|string   $filter string or array of string with 
 *                         filters to include
 *
 */
function include_filters ($filter){
    static $loaded = array();

    if (!is_array($filter)){
        $class_path = _COS_PATH . "/modules/filter_$filter/$filter.inc";
        include_once $class_path;
        moduleLoader::setModuleIniSettings("filter_$filter");
        $loaded[$filter] = true;
    }

    if (is_array ($filter)){
        foreach($filter as  $val){
            if (isset($loaded[$val])) continue;
            $class_path = _COS_PATH . "/modules/filter_$val/$val.inc";
            include_once $class_path;
            moduleLoader::setModuleIniSettings("filter_$val");
            $loaded[$val] = true;
        }
    }
}

/**
 * function for getting filters help string
 * @param string|array $filters the filter or filters from were we wnat to get
 *                     help strings
 * @return string $string the help strings of all filters. 
 */
function get_filters_help ($filters) {
    moduleLoader::includeFilters($filters);
    $str = '<span class="small-font">';
    $i = 1;

    foreach($filters as $key => $val) {

        $str.= $i . ") " .  lang::translate("filter_" . $val . "_help") . "<br />";
        $i++;
    }
    $str.='</span>';
    return $str;
    
}

/**
 * function for filtering content
 * @param  string|array    $filters the string or array of filters to use
 * @param  string|array    $content the string or array (to use filters on)
 * @return string|array    $content the filtered string or array of strings
 */
function get_filtered_content ($filter, $content){   
    if (!is_array($filter)){
        moduleLoader::includeFilters($filter);
        $class = 'filter_' . $filter;
        $filter_class = new $class;

        if (is_array($content)){
            foreach ($content as $key => $val){
                $content[$key] = $filter_class->filter($val);
            }
        } else {
            $content = $filter_class->filter($content);
        }
        
        return $content;
    }

    if (is_array ($filter)){
        foreach($filter as $key => $val){
            moduleLoader::includeFilters($val);
            $class = 'filter_' .$val; 
            $filter_class = new $class;
            if (is_array($content)){
                foreach ($content as $key => $val){
                    $content[$key] = $filter_class->filter($val);
                }
            } else {
                $content = $filter_class->filter($content);
            }
        }
        return $content;
    }
    return '';
}
