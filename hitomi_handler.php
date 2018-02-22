<?php
# -*- coding: utf-8 -*-
/*
  [Structure]
    artist/<artist>/<page>/<title>/<filename>
    tag/<keyword>/<page>/<title>/<filename>
*/

$is_debug = false;
$ROOT_URL = "http://hitomi.la";

$SEP_CODE    = "___";

$request_uri = $_SERVER['REQUEST_URI'];
$request_path = parse_url($request_uri, PHP_URL_PATH);
$request_path = urldecode($request_path);
debug("request_path: ".$request_path);

$path_pt = explode('/', trim($request_path, '/'));
$arg = array_shift($path_pt);
if ($arg !== "hitomi") die;
$level = count($path_pt);
debug("level: ".$level);
$id_str = trim(end($path_pt));
debug("last str: ".$id_str);

$children = array();
$add_index = false;

################################################################
# Stage 1: Parsing
################################################################
if ($level == 0) {
    # top menu
    $children[] = "artist";
    $children[] = "tag";
} elseif ($level == 1) {
    if ($path_pt[0] == "artist") {
        $children[] = "cuvie";
        $children[] = "hazuki kaoru";
        $children[] = "hisasi";
        $children[] = "maybe";
        $children[] = "oh great";
        $children[] = "okama";
        $children[] = "tosh";
    } elseif ($path_pt[0] == "tag") {
        $children[] = "tankoubon";
        $children[] = "webtoon";
    } else {
        die;
    }
} elseif ($level == 2) {
    for ($idx=1; $idx <= 10; $idx++) {
        $children[] = $idx;
    }
} elseif ($level == 3) {
    $url = $ROOT_URL.sprintf("/%s/%s-%s-%d.html", $path_pt[0], $path_pt[1], "korean", intval($path_pt[2]));
    debug($url);
    $html = @file_get_contents($url);
    $doc = new DOMDocument;
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXpath($doc);
    $items = $xpath->query("//h1/a");
    foreach ($items as $item) {
        $title = trim($item->textContent);
        sscanf($item->getAttribute('href'), "/galleries/%d.html", $gid);
        $children[] = array($title, $gid);
    }
    $add_index = true;
} elseif ($level == 4) {
    $gid = (int)(explode($SEP_CODE, $path_pt[$level-1])[1]);
    $url = $ROOT_URL.sprintf("/galleries/%d.js", $gid);
    debug($url);
    $doc = @file_get_contents($url);
    preg_match_all("/\"name\":\"(.*?)\"/", $doc, $match);
    debug(count($match[1]));
    $children = $match[1];
} elseif ($level == 5) {
    $gid = (int)(explode($SEP_CODE, $path_pt[$level-2])[1]);
    $fname = $path_pt[$level-1];
    $imgurl = sprintf("http://%s.hitomi.la/galleries/%d/%s", chr(97+$gid%2)."a", $gid, $fname);
    debug($imgurl);
    # proxy to load image file
    $head = array_change_key_case(get_headers($imgurl, TRUE));
    debug(end($head['content-type']));
    debug(end($head['content-length']));
    if ($is_debug) exit;
    header('Content-Type: '.end($head['content-type']));
    header('Content-Length: '.end($head['content-length']));
    readfile($imgurl);
    exit;
}
################################################################
# Stage 2: Generate List
################################################################
$count = 1;
foreach ($children as $child) {
    if ($add_index) {
        $prefix = sprintf("%d_", $count++);
    } else {
        $prefix = "";
    }
    if (is_array($child)) {
        echo $prefix.$child[0].$SEP_CODE.$child[1]."\n";
    } else {
        echo $prefix.$child."\n";
    }
}
################################################################
# Print debug message
################################################################
function debug($str) {
    global $is_debug;
    if ($is_debug) {
        echo "<i>".$str."</i><br>";
    }
}
?>
