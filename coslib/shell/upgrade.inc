<?php

/**
 * reloads lang, menus, module conig
 * @param type $options
 */
function cos_upgrade_post ($options = array ()) {
    cos_check_root();
    
    // reload menus
    cos_menu_lang_reload();
    
    // reload all language
    cos_reload_language();
    
    // reload config
    cos_config_reload();
}

mainCli::setCommand('upgrade', array(
    'description' => 'Upgrade a existing system',
));

mainCli::setOption('cos_upgrade_post', array(
    'long_name'   => '--post',
    'description' => 'Installs new menu items, module config, and language files',
    'action'      => 'StoreTrue'
));


mainCli::setArgument('profile',
    array('description'=> 'specify the profile to install',
          'optional' => true));
