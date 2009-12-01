<?php
/* 
	Creates a new php friendly nginx entry, based on desired configurations
	Author: Philip Olson
	License: BSD
	
	TODO:
	- Consider provide shell arguments for options
	- Better deal with chmod/chown
	- Determine if the ngnix configuration should be improved
	- Consider handling add_www automagically (bar.com yes vs foo.bar.com no)

	Notes:
	- The hostname becomes a directory name within root_www
	- Assumes /etc/nginx/fastcgi_params exists with various CGI variable definitions
	- See the definition for other assumptions that are made, like port 9000
*/

// Should a www.* variant be added?
define ('ADD_WWW',		true);

// The hostname being added
define ('HOSTNAME',		'example.com');

// Root of all hostnames
define ('ROOT_WWW',		'/var/www');

// Directory where nginx configuration files are stored
define ('ROOT_CONF',	'/etc/nginx/sites-enabled');

// Location of log storage
define ('ROOT_LOGS',	'/var/logs/nginx');

// Name of the desired document root directory, which might be www, public_html, htdocs, etc.
define ('HTDOCS',		'htdocs');

// Restart nginx after creating this?
define ('RESTART_WWW',	false);

// Add a few skeleton files?
define ('ADD_SKELS',	false);

$conf = get_default_configuration(HOSTNAME);

$root_path = ROOT_WWW . '/' . HOSTNAME . '/';
$doc_path  = $root_path . HTDOCS . '/';

// Ensure these files and directories don't already exist
if (file_exists(ROOT_CONF . '/' . HOSTNAME)) {
	echo "ERROR: Configuration file for " . HOSTNAME . " already exists.\n";
	exit;
}
if (is_dir($root_path)) {
	echo "ERROR: The root path ($root_path) already exists.\n";
	exit;
}
if (is_dir($doc_path)) {
	echo "ERROR: The doc path ($doc_path) already exists.\n";
	exit;
}

file_put_contents(ROOT_CONF . '/' . HOSTNAME, $conf);

if (!mkdir ($root_path)) {
	echo "ERROR: Unable to create hostname root ($root_path) directory\n";
	exit;
}
if (!mkdir ($doc_path)) {
	echo "ERROR: Unable to create hostname doc ($doc_path) directory\n";
	exit;
}

shell_exec("chmod ug+rwx " . $root_path);
shell_exec("chmod ug+rwx " . $doc_path);

// Add few files to begin with
if (ADD_SKELS) {
	file_put_contents($doc_path . 'pinfo.php',		'<?php phpinfo(); ?>');
	file_put_contents($doc_path . 'favicon.ico',	'');
	file_put_contents($doc_path . '404.php',		'Oops');
	file_put_contents($doc_path . 'index.php',		HOSTNAME);
}

if (RESTART_WWW) {
	shell_exec("/etc/init.d/nginx restart");
}

echo "\nINFO: Finished with apparent success.\n";

/*******************************************/
function get_default_configuration ($hostname) {

	$htdocs		= HTDOCS;
	$root_logs	= ROOT_LOGS;
	$root_www	= ROOT_WWW;

	$default = <<<FOO
server {

	listen 80;

	server_name {MYHOSTNAME}{WWW_MYHOSTNAME};

	access_log {$root_logs}/{MYHOSTNAME}.access_log main;
	error_log  {$root_logs}/{MYHOSTNAME}.error_log notice;

	error_page 404 = /404.php;
	fastcgi_intercept_errors on;

	root {$root_www}/{MYHOSTNAME}/{$htdocs};

	index index.php index.html;
	fastcgi_index index.php;

	location ~ \.php$ {
		include	 /etc/nginx/fastcgi_params;
		fastcgi_param   SCRIPT_FILENAME  {$root_www}/{MYHOSTNAME}/{$htdocs}\$fastcgi_script_name;
		fastcgi_pass    127.0.0.1:9000;
	}
}
FOO;

	$tmp = $default;
	if (ADD_WWW) {
		$tmp = str_replace('{WWW_MYHOSTNAME}', ' www.' . $hostname, $tmp);
	} else {
		$tmp = str_replace('{WWW_MYHOSTNAME}', '', $tmp);
	}

	return str_replace('{MYHOSTNAME}', $hostname, $tmp);
}

