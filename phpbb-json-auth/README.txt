Generic JSON based authenticator for phpbb3
Copyright Chris McCormick, 2009
chris@mccormick.cx

This plugin allows phpbb to authenticate against an external site using a generic JSON based interface. Essentially, you share the session cookie that your site uses with phpbb and then provide a page which returns JSON data saying whether that session cookie is for an authenticated user or not.

I used some previous code I had for authenticating in this way against Django, and also had a look at the "django-login-for-phpbb" project on Google Code.

Requires php5-curl.

Files
-----

* auth_json.php - The phpbb plugin which does the job of checking the remote page to see if the user is authenticated or not.

* login_body.html - A replacement template for the login HTML to direct the user to the external site to log in.

Usage
-----

1. Put auth_json.php inside your phpBB installation, under includes/auth. This can probably be a symlink.

2. Customise login_body.html to point the login and signup links at the right place, and include it in your style.

3. IMPORTANT: edit the file adm/index.php under the phpBB root, and remove the
   lines that require you to log in a second time to go to the administration
   panel; they are (in my version) lines 31-34, and look like this:

     if (!isset($user->data['session_admin']) || !$user->data['session_admin'])
     {
        login_box('', $user->lang['LOGIN_ADMIN_CONFIRM'], $user->lang['LOGIN_ADMIN_SUCCESS'], true, false);
     }

