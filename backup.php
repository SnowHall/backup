<?php
/**
* $Id$
*
* S3 class usage
*/
$current_dir = dirname(__FILE__);

if (!class_exists('S3')) require_once $current_dir.'/aws/S3.php';
require_once $current_dir.'/config.inc.php';
require_once $current_dir.'/aws/functions.php';

execute_s3($config);

?>