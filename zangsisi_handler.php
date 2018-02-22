<?php
# -*- coding: utf-8 -*-
/*
    [path]
	/recent
	/ongoing
	/completed
	./___g<num>	=page_id
	./___p<num>
*/
$is_debug      = false;
$ROOT_URL      = "http://zangsisi.net";
$RECENT_STR    = "최신 목록";
$ONGOING_STR   = "연재 목록";
$COMPLETED_STR = "완결 목록";

$SEP_CODE = "___";

$request_uri  = $_SERVER['REQUEST_URI'];
$request_path = parse_url($request_uri, PHP_URL_PATH);
$request_path = urldecode($request_path);
debug("request_path: ".$request_path);

$parts = explode('/', rtrim($request_path, '/'));
$id_str = trim(array_pop($parts));
debug("last str: ".$id_str);

$info  = array();
$add_index = true;

###############################################
# Stage 1: extract info
###############################################
if ($id_str == "zangsisi") {
    # top menu
    array_push($info, $RECENT_STR.$SEP_CODE."recent");
    array_push($info, $ONGOING_STR.$SEP_CODE."ongoing");
    array_push($info, $COMPLETED_STR.$SEP_CODE."g10707");
} elseif (strpos($id_str, $SEP_CODE."recent") !== false) {
    $html = file_get_contents($ROOT_URL);
    $doc = new DOMDocument;
    @$doc->loadHTML($html);
    $xpath = new DOMXpath($doc);
    $items = $xpath->query("//div[@class='contents']//a");
    debug(count($items));
    foreach ($items as $item) {
	$url = $item->getAttribute('href');
	debug($url);
	preg_match("/p=(\d+)/", $url, $match);
	array_push($info, trim($item->textContent).$SEP_CODE."p".$match[1]);
    }
} elseif (strpos($id_str, $SEP_CODE."ongoing") !== false) {
    $html = file_get_contents($ROOT_URL);
    preg_match_all("#href=\"http://zangsisi.net/\?page_id=(\d+)\" *data-title=\"(.*?)\"#", $html, $matches, PREG_SET_ORDER);
    foreach ($matches as $item) {
	array_push($info, $item[2].$SEP_CODE."g".$item[1]);
    }
} elseif (preg_match("/".$SEP_CODE."([pg])(\d+)$/", $id_str, $match)) {
    if ($match[1] == "g") {
	$qurl = $ROOT_URL."/?page_id=".$match[2];
    } elseif ($match[1] == "p") {
	$qurl = $ROOT_URL."/?p=".$match[2];
    } else {
    	echo "<i>Illega ID pattern</i><br>\n";
    	die;
    }
    $temp = parseZangsisiPage($qurl);
    $count = 1;
    foreach ($temp['list'] as $item) {
    	debug($item[0]);
	if (strpos($item[0],"http") !== false) {
	    # external link => image
	    if ($count == 1) {
		$ext = get_file_ext($item[0]);
	    }
	    array_push($info, $count.".".$ext);
	    $add_index = false;
	} else {
	    $tt = explode('=', $item[0]);
	    switch ($tt[0]) {
	    	case "p":       $ll = "p".$tt[1]; break;
	    	case "page_id": $ll = "g".$tt[1]; break;
	    	default: echo "<i>illegal ID</i><br>"; die;
	    }
	    array_push($info, $item[1].$SEP_CODE.$ll);
	}
	$count++;
    }
} else {
    ###############################################
    # load image file
    ###############################################
    # 1. load table for real file paths
    $prev_str = trim(array_pop($parts));
    if (preg_match("/".$SEP_CODE."([pg])(\d+)$/", $prev_str, $match)) {
	if ($match[1] == "g") {
	    $qurl = $ROOT_URL."/?page_id=".$match[2];
	} elseif ($match[1] == "p") {
	    $qurl = $ROOT_URL."/?p=".$match[2];
	} else {
	    echo "<i>Illega ID pattern</i><br>\n";
	    die;
	}
	$temp = parseZangsisiPage($qurl);
	foreach ($temp['list'] as $item) {
	    array_push($info, $item[0]);
	}
    }

    # 2. map virtual name to real file
    $parts = explode('.', $id_str);
    $pnum = (int)$parts[0] - 1;
    debug("image sel: ".$pnum);
    $imgurl = $info[$pnum];
    if (substr($imgurl, 0, 2) == "//") {
	$imgurl = "http:".$imgurl;
    }
    debug("image url: ".$imgurl);

    # 3. proxy to load image file
    $head = array_change_key_case(get_headers($imgurl, TRUE));
    header("Content-Type: ".$head['content-type']);
    header("Content-Length: ".$head['content-length']);
    readfile($imgurl);
    exit;
}

###############################################
# Stage 2: Generate List
###############################################
$count = 1;
foreach ($info as $item) {
    if ($add_index) {
	$title = sprintf("%d_", $count++).$item;
    } else {
	$title = $item;
    }
    echo $title."\n";
}

################################################################
# Parse Page
################################################################
function parseZangsisiPage($page_url) {
    debug($page_url);
    $result = array("title"=>"", "list"=>array());

    $html = file_get_contents($page_url);
    $doc = new DOMDocument;
    @$doc->loadHTML($html);
    $xpath = new DOMXpath($doc);
    $subnode = $xpath->query("//div[@id='post']/div[@class='contents']")->item(0);
    $count = 0;
    ### link to another page
    $items = $xpath->query(".//a", $subnode);
    foreach ($items as $item) {
	$count++;
	$url = $item->getAttribute('href');
	if (preg_match("/(p(?:age_id|))=(\d+)/", $url, $match)) {
	    array_push($result['list'], array($match[0], trim($item->textContent)));
	} else {
	    array_push($result['list'], array($url, $count));	// link to img
	}
    }
    if ($count == 0) {
	$items = $xpath->query(".//img", $subnode);
	foreach ($items as $item) {
	    $count++;
	    $url = $item->getAttribute('src');
	    array_push($result['list'], array($url, $count));
	}
    }
    debug($count);
    ### title of page
    $node = $xpath->query("//span[@class='title']");
    if ($node->length > 0) {
	$result['title'] = $node->item(0)->textContent;
    } else {
	$result['title'] = $xpath->query("//div[@id='recent-post']/a[@class='title']")->item(0)->textContent;
    }
    return $result;
}

################################################################
# File extension
################################################################
function get_file_ext($str) {
    if (preg_match_all("/\.([^\.\?]+)/", $str, $match)) {
	return strtolower($match[1][ count($match[1])-1 ]);
    }
    return false;
}

################################################################
# Print debug message
################################################################
function debug($str) {
    global $is_debug;
    if ($is_debug) {
	echo "<i>".$str."</i><br>", PHP_EOL;
    }
}
?>
