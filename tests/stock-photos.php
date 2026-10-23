<?php
declare(strict_types=1);
require __DIR__.'/../backend/Domain.php';require __DIR__.'/../backend/StockPhotos.php';
use Juzimder\StockPhotos;
function photoCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$page=['pageid'=>7,'title'=>'File:Pottery.jpg','imageinfo'=>[['mime'=>'image/jpeg','thumburl'=>'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Pottery.jpg/960px-Pottery.jpg','extmetadata'=>['ImageDescription'=>['value'=>'<b>A loaf of bread</b>'],'Artist'=>['value'=>'<b>Example author</b>'],'LicenseShortName'=>['value'=>'CC BY-SA 4.0']]]]];
$photos=StockPhotos::candidates(['query'=>['pages'=>[$page]]]);photoCheck(count($photos)===1&&$photos[0]['author']==='Example author'&&$photos[0]['licenseUrl']==='https://creativecommons.org/licenses/by-sa/4.0/','Licence, author and source retained without metadata HTML');
foreach(['http://upload.wikimedia.org/a.jpg','https://upload.wikimedia.org.evil.test/a.jpg','https://user:pass@upload.wikimedia.org/a.jpg','https://upload.wikimedia.org:8443/a.jpg','https://127.0.0.1/a.jpg'] as $url){$bad=$page;$bad['imageinfo'][0]['thumburl']=$url;photoCheck(StockPhotos::candidates(['query'=>['pages'=>[$bad]]])===[],'Untrusted image URL rejected');}
foreach(['All rights reserved','CC BY-NC 4.0','unknown',''] as $license){$bad=$page;$bad['imageinfo'][0]['extmetadata']['LicenseShortName']['value']=$license;photoCheck(StockPhotos::candidates(['query'=>['pages'=>[$bad]]])===[],'Unsupported licence rejected');}
$bad=$page;$bad['imageinfo'][0]['mime']='image/svg+xml';photoCheck(StockPhotos::candidates(['query'=>['pages'=>[$bad]]])===[],'Only raster photos accepted');

photoCheck(StockPhotos::broaderQuery('bread loaf on wooden table')==='"bread" OR "loaf" OR "wooden" OR "table"','Empty search can broaden bounded keywords');
photoCheck(StockPhotos::broaderQuery('bread')==='bread','Single subject is not retried');

photoCheck($photos[0]['description']==='A loaf of bread','Catalogue description is retained as bounded plain text for subject matching');
