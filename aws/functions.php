<?php

function execute_s3($config = '') {
	// Instantiate the class
	$s3 = new S3($config['amazon']['access_key_id'], $config['amazon']['secret_access_key'], false, $config['amazon']['access_point']);
	S3::$useSSL = false;
	
	// Dump MySQL databases and upload to Amazon S3
	if (isset($config['database']) && count($config['database'])) {
		foreach($config['database'] as $id => $d) {
			$tmpDbPath = createTmpFolder($config['paths']['archive_path']);
			$dump = mysqlDump($d, $tmpDbPath);
			$s3->putObjectFile($dump, $config['amazon']['bucket'], basename($dump), S3::ACL_PRIVATE);
			unlink($dump);
			rmdir($tmpDbPath);
		}
	}
	
	// Upload all files from selected folder.
	$allfiles = $s3->getBucket($config['amazon']['bucket']); // get all files in bucket. Upload only new files and with different size (upload was interrupted last time)
	if (isset($config['upload_files']) && count($config['upload_files'])) {
		foreach($config['upload_files'] as $id => $u) {
			if ($dir = opendir($u)) {
				while (($file = readdir($dir)) !== false) {
					if ($file == '.' || $file == '..') continue;
					$dump = $u . '/' . $file;
					$file_size = filesize($dump);
					if(!isset($allfiles[$file]) || $allfiles[$file]['size'] != $file_size) {
						$s3->putObjectFile($dump, $config['amazon']['bucket'], basename($dump), S3::ACL_PRIVATE);
					}
				}
				closedir($dir);
			}
		}
	}
}

function createTmpFolder($tmpfolder = '') {
	do {
		$path = $tmpfolder.'/'.mt_rand(0, 9999999);
	} while (!mkdir($path, 0700));
	return $path;
}

function mysqlDump($database = '', $tmpfolder = '') {
	$dbfile = 'db-' . $database['database'] . '-' . date('Ymd-Hi') . '.sql';
	$tarball = $tmpfolder . '/' . $dbfile;
	$compress = '';
	if (!isset($database['compress']) || $database['compress'] == 'gzip') {
		$extension = '.gz';
		$compress = 'z';
	}
	else if (isset($database['compress']) && $database['compress'] == 'bzip2') {
		$extension = '.bz2';
		$compress = 'j';
	}
	chdir($tmpfolder);
	$cmd = 'nice mysqldump --routines';
	$cmd .= ' -h '.$database['hostname'];
	$cmd .= ' -u '.$database['username'];
	$cmd .= ' -p' .$database['password'];
	$cmd .= ' ' . $database['database'] . ' > ' . $tarball;
	exec($cmd); // do DB backup to SQL text file.
	exec("tar -c{$compress}vf {$tarball}{$extension} {$dbfile}"); // compress DB backup
	unlink($tarball);
	return $tarball.$extension;
}

?>