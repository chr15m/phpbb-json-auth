<?php
/**
 * 
 * Generic JSON plugin for PHPBB
 * 
 * Copyright 2009, Chris McCormick
 * <chris@mccormick.cx>
 *
 * Allows phpbb to outsource authentication to
 * any service which provides a JSON formatted
 * verification of the current session.
 * 
 * e.g. a failed authentication from the remote server looks like this:
 * {"authenticated": false}
 * and a successful authentication looks something like this:
 * {"username": "chr15m", "admin": false, "authenticated": true, "email": "chrism@mccormick.cx", "avatar": "/media/img/avatar.png"} 
 * 
 * Assumes sharing of cookies between the forum and the authenticating site.
 * 
 * PHPBB variables defined in the admin:
 * json_auth_url
 * json_auth_logout_url
 * json_auth_login_page
 * json_auth_shared_cookie
 * json_auth_cookie
 * 
 */

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
  exit;
}

/**
 * Only allow changing authentication to JSON if we can connect to the server.
 * Called in acp_board while setting authentication plugins.
 * 
 * @return boolean|string false if the user is identified and else an error message
 */
function init_json()
{
    global $config, $user;
    $ch = curl_init($config['json_auth_url']);
    if (!$ch)
        return "Couldn't connect to server at " . $config['json_auth_url'];
}

/**
 * Make a request to a url, using the right session cookie.
 * 
 * @return The result of the request
 */
function jsonauth_do_request($post_data=array())
{
    global $config;
    
    // if we can't connect return an error.
    $ch = curl_init($config['json_auth_url']);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);
    curl_setopt($ch, CURLOPT_COOKIEJAR, tempnam("/tmp", "json_phpbb_cookie_"));
    curl_setopt($ch, CURLOPT_COOKIE, $config['json_auth_cookie'] . "=" . $_COOKIE[$config['json_auth_shared_cookie']]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    if (!empty($post_data)) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    }
    
    $output = curl_exec($ch);
    curl_close($ch);
    
    return $output;
}

/**
 * Call to the auth url to get the JSON data pertaining to the current user.
 * Requires the session cookie to be set for whatever the remote service is.
 * 
 * @return             Array containing auth info
 */
function jsonauth_get_user()
{
    global $config;
    
    return json_decode(jsonauth_do_request($config['json_auth_url']), true);
}

/**
 * Logout a user from the external site.
 * 
 * @return   True if successfully logged out, False otherwise. Always returns True. :/
 */
function logout_json()
{
    global $config;
    
    // Redirect the user to the login page of the application
    header("Location: " . $config['json_auth_logout_url']);
    
    //jsonauth_do_request($config['json_auth_logout_url']);
    //$result = json_decode($output, true);
    //return !$result['auth'];
    return true;
}

/**
 * Attempt to log a user in.
 * Actually this has no effect and shouldn't happen since the login box should just redirect to your own site.
 * 
 * @param    username    Username
 * @param    password    Password
 * @return   Array containing the auth info 
 */
function login_json(&$username, &$password)
{
    global $config;
    
    /*$post['username'] = $username;
    $post['password'] = $password;*/
    
    $vals = jsonauth_get_user();
    $row = autologin_json();
    
    if ($vals['authenticated'])
    {
        // Successful login... set user_login_attempts to zero...
        return array(
            'status'             => LOGIN_SUCCESS,
            'error_msg'          => false,
            'user_row'           => $row,
        );
    }
    else
    {
        // Redirect the user to the login page of the application
        header("Location: " . $config['json_auth_login_page']);
    }
    
    // Give status about wrong password...
    /*return array(
        'status'                => LOGIN_ERROR_PASSWORD,
        'error_msg'             => 'LOGIN_ERROR_PASSWORD',
        'user_row'              => $row,
    );*/
}

/**
 * Test whether this user should currently be considered logged in (this is the important bit).
 *
 * @returns User row array.
 */
function autologin_json()
{
    global $db, $config;
    if (!isset($_COOKIE[$config['json_auth_shared_cookie']]))
    {
        return array();
    }
    
    $vals = jsonauth_get_user();
    
    // are they authenticated already on the remote server?
    if (!empty($vals['username']) && $vals['authenticated'])
    {
        $sql = 'SELECT *
                FROM ' . USERS_TABLE . "
                WHERE username = '" . $db->sql_escape($vals['username']) . "'";
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        
	// if this user exists in the database already then go ahead and return them
        if ($row)
        {
            $row = array_merge($row, user_row_json($vals));
            return ($row['user_type'] == USER_INACTIVE || $row['user_type'] == USER_IGNORE) ? array() : $row;
        }
        
        // make sure we have the right functions for creating a new user
        if (!function_exists('user_add') || !function_exists('group_user_add'))
        {
            global $phpbb_root_path, $phpEx;
            include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
        }
        
        // create this user if they do not exist yet (but are authenticated on the remote server)
        $id = user_add(user_row_json($vals));
        $sql = 'SELECT *
                FROM ' . USERS_TABLE . "
                WHERE username_clean = '" . $db->sql_escape(utf8_clean_string($vals['username'])) . "'";
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        // if they were created successfully, return the new user's data
        if ($row)
        {
            return $row;
        }
    }
    return array();
}

function get_group($name)
{
    global $db;
    // first retrieve default group id
    $sql = 'SELECT group_id
            FROM ' . GROUPS_TABLE . "
            WHERE group_name = '" . $db->sql_escape($name) . "'
                    AND group_type = " . GROUP_SPECIAL;
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    return $row['group_id'];
}

function user_row_json($vals)
{
    $username = $vals['username'];
    $email = $vals['email'];
    $admin = $vals['admin'];
    global $db, $config, $user;
    
    if ($admin)
    {
        $sql = 'SELECT user_permissions 
                FROM ' . USERS_TABLE . '
                WHERE user_type = 3 limit 1';
        $result = $db->sql_query($sql);
        $admin_per = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);

        $permissions = $admin_per['user_permissions'];
        $permissions = "";
        $group = get_group("ADMINISTRATORS");
        $user->data['session_admin'] = true;
    }
    else
    {
        $permissions = "";
        $group = get_group("REGISTERED");
    }
    
    // generate user account data
    $row = array(
        'username'              => $username,
        'user_password'         => phpbb_hash(rand()),
        'user_email'            => $email,
        'group_id'              => (int) $group,
        'user_type'             => ($admin) ? USER_FOUNDER : USER_NORMAL,
        'user_ip'               => $user->ip,
        'user_permissions'      => $permissions,
    );
    
    return $row;
}

/*### End of login routines ###*/

/**
* The session validation function checks whether the user is still logged in.
*
* @return boolean true if the given user is authenticated or false if the session should be closed
*/
function validate_session_json(&$user)
{
    global $config;
    
    if (!isset($_COOKIE[$config['json_auth_shared_cookie']]))
    {
        return false;
    }
    
    $vals = jsonauth_get_user();
    return ($vals['username'] === $user['username'] && !empty($vals['authenticated'])) ? true : false;
}

/**
* This function is used to output any required fields in the authentication
* admin panel. It also defines any required configuration table fields.
*/
function acp_json(&$new)
{
    global $user;
    
    if(!$new['json_auth_url'] or empty($new['json_auth_url'])) {
    	$new['json_auth_url'] = 'http://localhost:8000/auth/external/';
    }
    
    if(!$new['json_auth_cookie'] or empty($new['json_auth_cookie'])) {
        $new['json_auth_cookie'] = 'sessionid';
    }
    
    if(!$new['json_auth_shared_cookie'] or empty($new['json_auth_shared_cookie'])) {
        $new['json_auth_shared_cookie'] = 'sessionid';
    }
    
    if(!$new['json_auth_logout_url'] or empty($new['json_auth_logout_url'])) {
        $new['json_auth_logout_url'] = 'http://localhost:8000/auth/logout/?next=http://localhost/forum/';
    }
    
    if(!$new['json_auth_login_page'] or empty($new['json_auth_login_page'])) {
        $new['json_auth_login_page'] = 'http://localhost:8000/auth/login/?next=http://localhost/forum/';
    }
    
    $tpl = '
    <dl>
        <dt><label for="json_auth_url">JSON Auth URL:</label><br /><span>URL where the /auth/external/ JSON page of the remote authenticator is.<br/>That page should return e.g.:{"username": "xxxxxxx", "admin": false, "authenticated": true, "email": "xxxx@xxxxxxx.com", "avatar": "/media/img/xxxx.png"} </span></dt>
        <dd><input type="text" id="json_auth_url" size="40" name="config[json_auth_url]" value="' . $new['json_auth_url'] . '" /></dd>
    </dl>
    <dl>
        <dt><label for="json_auth_shared_cookie">Shared cookie name:</label><br /><span>Name of the cookie which is shared between the remote system and phpbb.</span></dt>
        <dd><input type="text" id="json_auth_cookie" size="40" name="config[json_auth_shared_cookie]" value="' . $new['json_auth_shared_cookie'] . '" /></dd>
    </dl>
    <dl>
        <dt><label for="json_auth_cookie">Remote cookie name:</label><br /><span>Name of the cookie on the remote system (can be the same as the shared cookie name).</span></dt>
        <dd><input type="text" id="json_auth_cookie" size="40" name="config[json_auth_cookie]" value="' . $new['json_auth_cookie'] . '" /></dd>
    </dl>
    <dl>
        <dt><label for="json_auth_logout_url">Location to ping to log the user out:</label><br /><span>URL that we should access with the session cookie in order to log the user out.</span></dt>
        <dd><input type="text" id="json_auth_logout_url" size="40" name="config[json_auth_logout_url]" value="' . $new['json_auth_logout_url'] . '" /></dd>
    </dl>
    <dl>
        <dt><label for="json_auth_login_page">Where to redirect the user to log in:</label><br /><span>Page to send the user to in order to log in on the remote system.</span></dt>
        <dd><input type="text" id="json_auth_login_page" size="40" name="config[json_auth_login_page]" value="' . $new['json_auth_login_page'] . '" /></dd>
    </dl>
    ';
    
    // These are fields required in the config table
    return array(
        'tpl'       => $tpl,
        'config'    => array('json_auth_url', 'json_auth_cookie', 'json_auth_shared_cookie', 'json_auth_logout_url', 'json_auth_login_page')
    );
}

?>
