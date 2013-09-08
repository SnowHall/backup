<?php

// Check if script runs from concole or browser
$cli = 0;
if (PHP_SAPI === 'cli') $cli=1;

// Current working directory
$current_dir = dirname(__FILE__);

include_once($current_dir.'/aws/S3.php');
include_once($current_dir.'/config.inc.php');

$s3 = new S3($config['amazon']['access_key_id'], $config['amazon']['secret_access_key'], false, $config['amazon']['access_point']);
S3::$useSSL = false;

//Script run from concole and have file name to download
if($cli && isset($argv[1])) {
	$timestart = time();
	$fileinfo = pathinfo($argv[1]);
	$s3->getObject($config['amazon']['bucket'], $fileinfo['basename'], $current_dir.'/'.$fileinfo['basename']);
	$filesize = filesize($current_dir.'/'.trim($argv[1]));
	$downloadtime = time() - $timestart;
	if($downloadtime == 0) $downloadtime = 1;
	echo "\nDownloaded \"".trim($argv[1]).'" file size '.FileSizeConvert($filesize, false).' in '.$downloadtime.' seconds ('.FileSizeConvert($filesize / $downloadtime, false)."/sec)\n\n";
	die();
}

// Display instructions to download, extract archive, import to MySQL
if(isset($_GET['download'])) {
	$result .= '<table cellpadding="0" cellspacing="1">
	<thead>
		<tr>
			<td colspan="2">Run the commands to process</td>
		</tr>
	</thead>
	<tbody>';

	$result .= '<tr class="file"><td class="time" nowrap>Download File: </td><td class="name" nowrap> php '.$current_dir.'/s3download.php '.$_GET['download'].'</td></tr>';
	$fileinfo = pathinfo($_GET['download']);
	$result .= '<tr class="file"><td class="time" nowrap>Extract File: </td><td class="name" nowrap> tar -';
	if(strtolower($fileinfo['extension']) == 'gz') $result .= 'z';
	  elseif(strtolower($fileinfo['extension']) == 'bz2') $result .= 'j';
	$result .= 'xf '.$_GET['download'].'</td></tr>';
	
	if(isset($config['database']) && is_array($config['database'])) {
		foreach($config['database'] as $id => $db) {
			$result .= '<tr class="file"><td class="time" nowrap>Import MySQL: </td><td class="name" nowrap> mysql -u '.$db['username'].' -p'.$db['password'].' '.$db['database'].' < '.$fileinfo['filename'].'</td></tr>';
		}
	}
	$result .= '<tr><td colspan="2"><a href="s3download.php">Back to Listing</a></td></tr></tbody></table>';
	displaypage($result);
	die();
}

$usecache = true;
$result = '';
// parameter to force listing reload
if(isset($_GET['updatelisting']) && $_GET['updatelisting'] == 1) $usecache = false;

//if we do not have the cache or it's old then we update listing.
if(!is_file('./cache.txt') || (GetCorrectMTime('./cache.txt') + 5*3600 < time())) $usecache = false;

if($usecache) {
	//Get listing from cache file
	$files = json_decode(file_get_contents('./cache.txt'), true);
} else {
	//Get listing from S3 Amazon
	$files = $s3->getBucket($config['amazon']['bucket']);
	//Save all data to cache file
	file_put_contents('./cache.txt', json_encode($files));
}

if(is_array($files)) {
	$result .= '<table cellpadding="0" cellspacing="1">
	<thead>
		<tr>
			<td colspan="4">'.($usecache ? 'Cached ':'').'Directory listing for '.$config['amazon']['bucket'].' (<a href="s3download.php?updatelisting=1">reload</a>)</td>
		</tr>
	</thead>
	<tbody>
	<tr class="file">
		<td class="name">Name (Storage Type)</td>
		<td class="size" nowrap>Size</td>
		<td class="time" nowrap>Upload Date</td>
		<td class="dl" nowrap>Download</td>
	</tr>';
	
	foreach($files as $id => $f) {
		$result .= '<tr class="file">
			<td class="name">'.$f['name'].' ('.$f['storageclass'].')</td>
			<td class="size" nowrap>'.FileSizeConvert($f['size']).'</td>
			<td class="time" nowrap>'.date("Y-d-m H:i", $f['time']).'</td>
			<td class="dl" nowrap><a href="#">PC</a> / <a href="s3download.php?download='.$f['name'].'">Server</a></td>
		</tr>';
	}
	
} else 
	$result .= '<tr><td colspan="4" align="center">No Files</td></tr>';
	
$result .= '</tbody></table>';
displaypage($result);


/** 
* Converts bytes into human readable file size. 
* 
* @param string $bytes 
* @return string human readable file size (2,87 Мб)
* @author Mogilev Arseny 
*/ 
function FileSizeConvert($bytes, $style = true)
{
    $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );

    foreach($arBytes as $arItem)
    {
        if($bytes >= $arItem["VALUE"])
        {
            $result = $bytes / $arItem["VALUE"];
            $result = str_replace(".", "," , strval(round($result, 2)));
            if($style) $result .= ' <span>'.$arItem["UNIT"].'</span>';
              else $result .= ' '.$arItem["UNIT"];
            break;
        }
    }
    return $result;
}

function GetCorrectMTime($filePath)
{
    $time = filemtime($filePath);

    $isDST = (date('I', $time) == 1);
    $systemDST = (date('I') == 1);

    $adjustment = 0;

    if($isDST == false && $systemDST == true)
        $adjustment = 3600;
    
    else if($isDST == true && $systemDST == false)
        $adjustment = -3600;

    else
        $adjustment = 0;

    return ($time + $adjustment);
}

function displaypage($content) {
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>S3 Amazon files download page</title>
<style type="text/css">
body {font-family: "Lucida Grande",Calibri,Arial; font-size: 9pt; color: #333; background: #f8f8f8;}
a {color: #b00; font-size: 11pt; font-weight: bold; text-decoration: none;}
a:hover {color: #000;}
img {vertical-align: bottom; padding: 0 3px 0 0;}
sup {color: #999;}
table {margin: 0 auto; padding: 0; width: 800px;}
table td {padding: 5px;}
thead td {padding-left: 0; font-family: "Trebuchet MS"; font-size: 11pt; font-weight: bold;}
tbody td.name {width: 99%;}
tbody .folder td {border: solid 1px #f8f8f8;}
tbody .file td {background: #fff; border: solid 1px #ddd;}
tbody tr.file:hover td {background: #ffff9d;}
tbody .file td.size, tbody .file td.time, tbody .file td.dl {white-space: nowrap; padding: 5px 10px;}
tbody .file td.size span {color: #999; font-size: 8pt;}
tbody .file td.time {color: #555;}
</style></head><body>';

echo $content;

echo '</body></html>';
}

?>