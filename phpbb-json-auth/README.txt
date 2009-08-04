Generic JSON based authenticator for phpbb3
Copyright Chris McCormick, 2009
chris@mccormick.cx

GPLv3 licensed, see the file COPYING for details.

This plugin allows phpbb to authenticate against your site using a
generic JSON based interface. Essentially, you share the session cookie
that your site uses with phpbb and then provide a page which returns JSON
data saying whether the currently logged in user is authenticated or not.

I used some previous code I had for authenticating in this way against
Django, and also had a look at the "django-login-for-phpbb" project on
Google Code.

Requires php5-curl.

Files
-----

* auth_json.php - The phpbb plugin which does the job of checking the remote
page to see if the user is authenticated or not.

* login_body.html - A replacement template for the login HTML to direct the
user to the external site to log in.

* django-example-view.py - An example implementation of a Django view which
outputs the JSON neccessary.

Usage
-----

1. Put auth_json.php inside your phpBB installation, under includes/auth. This
can be a symlink to the file in this project.

2. Customise login_body.html to point the login and signup links at the right
place, and include it in your style templates. This will redirect a normal user
to your login page.

3. Make sure your site has a JSON authentication info URL like the one in the
django-example-view.py

