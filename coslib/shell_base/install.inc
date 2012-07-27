<?php

/**
 * File containing install functions for shell mode
 *
 * @package     shell
 */

/**
 * @ignore
 */
include_once "coslib/profile.php";
include_once "coslib/db.php";


/**
 * function for installing a profile
 */
function install($options = false) {
    // no options - install clean default
    if (!isset($options['profile'])){
        cos_cli_abort('You need to specifiy a profile');
    }

    // remove old files
    rm_files();
    create_files();

    // load default sql.
    load_db_default();

    // use profile object
    $pro = new profile();
    $pro->setProfileInfo($options['profile']);

    // first we set if home link will be added.
    $pro->setProfileUseHome();

    // install modules
    foreach ($pro->profileModules as $key => $val){
        $options['repo'] = $val['public_clone_url'];
        $options['version'] = $val['module_version'];
        $options['module'] = $val['module_name'];
        git_install($options, 'module');
    }
    
    // install templates
    foreach ($pro->profileTemplates as $key => $val){
        $options['repo'] = $val['public_clone_url'];
        $options['version'] = $val['module_version'];
        $options['template'] = $val['module_name'];
        git_install($options, 'template');
    }

    // load profile ini files
    $pro->loadProfileFiles($options['profile']);

    $pro->setProfileTemplate();

    create_files();
    chmod_files();
}

/**
 * @deprecated not used anywhere
 * wrapper function for reseting install to default one
 * all data will be lost
 */

function reset_install (){
    rm_files();
    drop_db_default();
    create_db();
    load_db_default();
    create_files();
    chmod_files();
    install();
}

/**
 * wrapper function for reloading all languages
 * change language settings in config/config.ini to load another language. 
 */
function install_reload_language(){
    $reload = new moduleInstaller();
    $reload->reloadLanguages();
}

function install_menu_lang_reload(){
    install_reload_language();
    menu_uninstall_all();
    menu_install_all();
}

function install_install_reload(){
    $reload = new moduleInstaller();
    $reload->reloadConfig();
}


mainCli::setCommand('install', array(
    'description' => 'install cos cms system',
));

mainCli::setOption('install', array(
    'long_name'   => '--install',
    'description' => 'Will try and install system',
    'action'      => 'StoreTrue'
));

mainCli::setOption('install_reload_language', array(
    'long_name'   => '--lang-reload',
    'description' => 'Reinstall system language files according to language set in config/config.ini',
    'action'      => 'StoreTrue'
));

mainCli::setOption('install_menu_lang_reload', array(
    'long_name'   => '--menu-lang-reload',
    'description' => 'Reinstall language according to config/config.ini, and reset all custom module menus',
    'action'      => 'StoreTrue'
));

mainCli::setOption('install_install_reload', array(
    'long_name'   => '--config-reload',
    'description' => 'Reloads the module table. E.g. you have made a CLI part of a module',
    'action'      => 'StoreTrue'
));


mainCli::setArgument('profile',
    array('description'=> 'specify the profile to install',
          'optional' => true));