#!/usr/bin/php
<?php
$_site = require_once(getenv("SITELOADNAME"));

$path = $_site->path; //getcwd();
//error_log("path: $path");

$other = "$path/other";

$date = date("Y-m-d");

if(file_exists("$other/Sitemap.$date.xml.gz")) {
  if($argv[1] != 'remove') {
    echo "The Sitemap.xml file is up to date. Exiting and doing nothing\n";
    exit();
  }
}
   
$file = file_get_contents($path ."/sitemap-new.txt");
//error_log("file: $file");

$files = json_decode($file);

//error_log("files: " . print_r($files, true));

$sitemap = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<urlset  xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
                      http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

EOF;

foreach($files as $file) {
  $sitemap .= "\t<url>\n\t\t<loc>https://www.{$_site->siteDomain}/{$file->item}</loc>\n";
  if($file->lastmod != "no") {
    $thisfile = $file->item;
    $time = gmdate("Y-m-d\TH:i:s\Z", filemtime($path ."/$thisfile"));
    $sitemap .= "\t\t<lastmod>$time</lastmod>\n";
  }
  $sitemap .= "\t\t<changefreq>$file->frequency</changefreq>\n";
  $sitemap .= "\t\t<priority>$file->priority</priority>\n";
  $sitemap .= "\t</url>\n";
}
$sitemap .= "</urlset>\n";

function create_gzip($file_path) {
  // Name of the file we're compressing
  $file = $file_path;

  // Name of the gz file we're creating
  $gzfile = $file_path . '.gz';

  // Open the gz file (w9 is the highest compression)
  $fp = gzopen($gzfile, 'w9');

  // Compress the file
  gzwrite($fp, file_get_contents($file));

  // Close the gz file and we're done
  gzclose($fp);
}

rename("$path/Sitemap.xml", "$other/Sitemap.$date.xml");
create_gzip("$other/Sitemap.$date.xml");
unlink("$other/Sitemap.$date.xml");

file_put_contents("$path/Sitemap.xml", $sitemap);

// Cull the field to only three

exec("find $other -mtime +3 -exec rm '{}' \;");

if($argv[1] == "echo" || $argv[2] == "echo") echo $sitemap;
