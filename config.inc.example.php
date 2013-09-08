<?php
/**
 * Default config files. Copy to config.inc.php
 */

// Amazon account details
$config['amazon'] = array(
	'bucket'			=> 's3 bucket name', // Must be created manually
	'access_point'		=> 's3.amazonaws.com', // you can set other access point
	'access_key_id'		=> 'access key here',
	'secret_access_key'	=> 'secret key here');


$config['paths'] = array(
	'archive_path'	=> '/tmp/s3backups', // temporary folder, used for backup and archive.
);

// Backup a database with mysqldump
// Repeat this block if you need to back up multiple databases.
$config['database'][] = array(
	'username'	=> 'mysql_username',
	'password'	=> 'mysql_password',
	'hostname'	=> 'localhost',
	'database'	=> 'name_of_database',
	'compress'	=> 'bzip2', // compress options - gzip, bzip2
);


// Upload all files from this folder to S3. Use it when want to upload exists backups
$config['upload_files'] = array(
    'folder' => '/path/to/files',
);


?>