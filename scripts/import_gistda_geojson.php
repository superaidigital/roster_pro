<?php
declare(strict_types=1);
/**
 * Download and normalize GISTDA administrative polygons for Data43 Thailand Map.
 * CLI only. Default: dry-run. Use --write after verifying source/coverage/licence.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
ini_set('memory_limit','1024M');
set_time_limit(0);
const BASE = 'https://gistdaportal.gistda.or.th/arcgis/rest/services/%E0%B8%82%E0%B9%89%E0%B8%AD%E0%B8%A1%E0%B8%B9%E0%B8%A5%E0%B9%80%E0%B8%82%E0%B8%95%E0%B8%81%E0%B8%B2%E0%B8%A3%E0%B8%9B%E0%B8%81%E0%B8%84%E0%B8%A3%E0%B8%AD%E0%B8%87/MapServer';
const LAYERS = [
 'province'=>[2,'provinces.geojson',['P_code','P_Name_T']],
 'amphoe'=>[3,'amphoes.geojson',['P_code','A_code','P_Name_T','A_Name_T']],
 'tambon'=>[4,'tambons.geojson',['P_code','A_code','T_code','P_Name_T','A_Name_T','T_Name_T']]
];
function bad(string $reason): never { throw new RuntimeException($reason); }
function request(string $url,array $post): array {
 $c=curl_init($url);
 if (!$c) bad('cURL init failed');
 curl_setopt_array($c,[
  CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post),
  CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/x-www-form-urlencoded'],
  CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_TIMEOUT=>150,
  CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2
 ]);
 $body=curl_exec($c); $status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);
 $error=curl_error($c);curl_close($c);
 if ($body===false || $status!==200) bad("GISTDA HTTP {$status}: {$error}");
 $v=json_decode($body,true,512,JSON_THROW_ON_ERROR);
 if (!is_array($v)||isset($v['error'])) bad('GISTDA API error: '.substr((string)$body,0,300));
 return $v;
}
function code($value,string $field): string {
 $val=trim((string)$value);
 if (!preg_match('/^[0-9]{1,2}$/D',$val)) bad("Invalid {$field}: ".$val);
 return str_pad($val,2,'0',STR_PAD_LEFT);
}
function nameThai($v,string $field): string {
 $s=trim((string)$v);
 if (!preg_match('/^.{1,160}$/usD',$s)) bad("Invalid Thai name: {$field}");
 return $s;
}
function geometry(array $geo): array {
 $type=$geo['type']??'';
 if (!in_array($type,['Polygon','MultiPolygon'],true)) bad('Not Polygon or MultiPolygon');
 $coords=$geo['coordinates']??null;
 if (!is_array($coords)||!$coords) bad('Missing geometry');
 $polys=$type==='Polygon'?[$coords]:$coords;
 foreach($polys as $polygon){
  if (!is_array($polygon)||!$polygon) bad('Empty polygon');
  foreach($polygon as $ring){
   if (!is_array($ring)||count($ring)<4) bad('Invalid polygon ring');
   $start=$ring[0];$end=$ring[count($ring)-1];
   if (!is_array($start)||!is_array($end)||($start[0]??null)!==($end[0]??null)||($start[1]??null)!==($end[1]??null))bad('Unclosed ring');
   foreach($ring as $xy){
    if(!is_array($xy)||!is_numeric($xy[0]??null)||!is_numeric($xy[1]??null))bad('Invalid vertex');
    $x=(float)$xy[0];$y=(float)$xy[1];
    if(!is_finite($x)||!is_finite($y)||$x<95||$x>107||$y<5||$y>22)bad('Not Thailand EPSG:4326 coordinates');
   }
  }
 }
 return ['type'=>$type,'coordinates'=>$coords];
}
function normalized(array $feature,string $level): array {
 $p=$feature['properties']??[];
 $cw=code($p['P_code']??null,'P_code');
 $ap=$level==='province'?'':code($p['A_code']??null,'A_code');
 $tb=$level==='tambon'?code($p['T_code']??null,'T_code'):'';
 $pr=nameThai($p['P_Name_T']??'','P_Name_T');
 $am=$level==='province'?'':nameThai($p['A_Name_T']??'','A_Name_T');
 $ta=$level==='tambon'?nameThai($p['T_Name_T']??'','T_Name_T'):'';
 $areaCode=$cw.$ap.$tb;
 return ['type'=>'Feature','geometry'=>geometry((array)($feature['geometry']??[])),
  'properties'=>[
   'area_code'=>$areaCode,'code'=>$areaCode,'name_th'=>$level==='province'?$pr:($level==='amphoe'?$am:$ta),
   'province_code'=>$cw,'amphoe_code'=>$ap,'tambon_code'=>$tb,
   'province_name'=>$pr,'amphoe_name'=>$am,'tambon_name'=>$ta
  ]
 ];
}
function collect(string $level,string $province): array {
 [$id,$file,$fields]=LAYERS[$level];$url=BASE.'/'.$id;
 $meta=request($url,['f'=>'json']);
 if(($meta['geometryType']??'')!=='esriGeometryPolygon')bad('Unexpected GISTDA layer geometry');
 $names=array_column($meta['fields']??[],'name');
 foreach($fields as $field)if(!in_array($field,$names,true))bad("GISTDA missing {$field}");
 $ids=request($url.'/query',['f'=>'json','where'=>$province==='all'?'1=1':"P_code='{$province}'",'returnIdsOnly'=>'true']);
 if(!is_array($ids['objectIds']??null)||!$ids['objectIds'])bad('No object IDs at GISTDA layer '.$id);
 $found=[];$total=count($ids['objectIds']);$counter=0;
 foreach(array_chunk($ids['objectIds'],20) as $chunk){
  $part=request($url.'/query',[
   'f'=>'geojson','where'=>'1=1','objectIds'=>implode(',',array_map('intval',$chunk)),
   'outFields'=>implode(',',$fields),'returnGeometry'=>'true','outSR'=>'4326','geometryPrecision'=>'6'
  ]);
  if(($part['type']??null)!=='FeatureCollection'||!is_array($part['features']??null))bad('Invalid source GeoJSON');
  if(count($part['features'])!==count($chunk))bad('Missing GISTDA features');
  foreach($part['features'] as $raw){
   $f=normalized($raw,$level);$key=$f['properties']['area_code'];
   if(isset($found[$key])){
    if($found[$key]['properties']['name_th']!==$f['properties']['name_th'])bad('Duplicate code/name conflict '.$key);
    $old=$found[$key]['geometry'];$new=$f['geometry'];
    $polys1=$old['type']==='Polygon'?[$old['coordinates']]:$old['coordinates'];
    $polys2=$new['type']==='Polygon'?[$new['coordinates']]:$new['coordinates'];
    $found[$key]['geometry']=['type'=>'MultiPolygon','coordinates'=>array_merge($polys1,$polys2)];
   }else{$found[$key]=$f;}
  }
  $counter+=count($chunk);
  if($counter%200<20)echo "{$level}: {$counter}/{$total}\n";
  usleep(120000);
 }
 ksort($found,SORT_STRING);
 return ['type'=>'FeatureCollection','features'=>array_values($found)];
}
function verify(array $sets,string $province): void {
 $count=array_map(static fn($v)=>count($v['features']),$sets);
 if($province==='all'&&($count['province']<70||$count['amphoe']<850||$count['tambon']<6900))
  bad('Nationwide boundary coverage incomplete: '.json_encode($count));
 if($province!=='all'&&($count['province']!==1||$count['amphoe']<1||$count['tambon']<1))
  bad('Filtered province coverage incomplete');
 $p=[];$a=[];
 foreach($sets['province']['features'] as $f)$p[$f['properties']['area_code']]=true;
 foreach($sets['amphoe']['features'] as $f){
  $v=$f['properties']['area_code'];
  if(!isset($p[substr($v,0,2)]))bad('Orphan district '.$v);
  $a[$v]=true;
 }
 foreach($sets['tambon']['features'] as $f){
  $v=$f['properties']['area_code'];
  if(!isset($a[substr($v,0,4)]))bad('Orphan tambon '.$v);
 }
}
function publish(array $sets,string $dir,string $province): void {
 if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))bad('Cannot make output directory');
 $staged=[];$previous=[];
 try {
  foreach(LAYERS as $level=>$cfg){
   $path=$dir.DIRECTORY_SEPARATOR.$cfg[1];
   $tmp=tempnam($dir,'.gis-stage-');if($tmp===false)bad('Staging failed');
   $staged[$path]=$tmp;
   $json=json_encode($sets[$level],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
   if(file_put_contents($tmp,$json,LOCK_EX)!==strlen($json))bad('Incomplete write');
  }
  foreach($staged as $path=>$tmp){
   if(is_file($path)){
    $backup=$path.'.bak-'.gmdate('YmdHis');
    if(!rename($path,$backup))bad('Backup failed: '.$path);
    $previous[$path]=$backup;
   }
   if(!rename($tmp,$path))bad('Publish failed: '.$path);
   unset($staged[$path]);echo "Wrote {$path}\n";
  }
  $manifest=['source'=>BASE,'credit'=>'GISTDA','retrieved_utc'=>gmdate('c'),
   'province'=>$province,'crs'=>'EPSG:4326','files'=>[]];
  foreach(LAYERS as $level=>$cfg){
   $file=$dir.DIRECTORY_SEPARATOR.$cfg[1];
   $manifest['files'][$cfg[1]]=['sha256'=>hash_file('sha256',$file),'areas'=>count($sets[$level]['features'])];
  }
  file_put_contents($dir.'/gis-import-manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
 }catch(Throwable $e){
  foreach($previous as $file=>$backup){if(is_file($file))unlink($file);rename($backup,$file);}
  throw $e;
 }finally{foreach($staged as $tmp)if(is_file($tmp))unlink($tmp);}
}
try{
 if(!function_exists('curl_init'))bad('Enable PHP cURL extension in php.ini');
 $province='all';$write=false;
 $dir=dirname(__DIR__).'/assets/geojson/thailand';
 foreach(array_slice($argv,1) as $arg){
  if($arg==='--write'){$write=true;continue;}
  if(str_starts_with($arg,'--province=')){$province=substr($arg,11);continue;}
  if(str_starts_with($arg,'--output-dir=')){$dir=substr($arg,13);continue;}
  if($arg==='--help'){echo "--province=all|33 [--write] [--output-dir=path]\n";exit;}
  bad('Unknown argument: '.$arg);
 }
 if($province!=='all'&&!preg_match('/^[0-9]{2}$/D',$province))bad('Invalid province filter');
 echo "GISTDA DOWNLOAD ".($write?'WRITE':'DRY RUN')." province={$province}\n";
 $sets=[];
 foreach(LAYERS as $level=>$config){
  $sets[$level]=collect($level,$province);
  echo $level.': '.count($sets[$level]['features'])." areas\n";
 }
 verify($sets,$province);
 if($write)publish($sets,$dir,$province);
 else echo "Validation passed. Re-run with --write to publish.\n";
 echo "DONE\n";
}catch(Throwable $e){fwrite(STDERR,'FAILED: '.$e->getMessage()."\n");exit(1);}
