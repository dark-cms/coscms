<?php

/**
 * @package shell
 *
 */

/**
 * adds an user by prompt
 * @param array $options
 * @return int $res
 */
function useradd_add ($options = null){

    $values['email'] = cos_readline("Enter Email of super user (you will use this as login): ");
    $values['password'] = cos_readline ("Enter password: ");
    $values['password'] = md5($values['password']);
    $values['username'] = $values['email'];
    $values['verified'] = 1;
    $values['admin'] = 1;
    $values['super'] = 1;
    $res = useradd_db_insert($values);
    if ($res) { 
        return 0;
    } else {
        return 1;
    }
}


/**
 * function for inserting user
 * @param   array   $values
 * @return  boolean $res
 */
function useradd_db_insert ($values){
    $db = new db();
    //print_r(db::$debug);
    $res = $db->insert('account', $values);
    //echo "res" . $res; 
    return $res;
}

mainCli::setCommand('useradd', array(
    'description' => 'Will help you create a super user for your install',
));

mainCli::setOption('useradd_add', array(
    'long_name'   => '--add',
    'description' => 'Add user with prompt answers.',
    'action'      => 'StoreTrue'
));


