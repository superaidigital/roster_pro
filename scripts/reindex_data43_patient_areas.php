<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {http_response_code(403);exit;}
require_once dirname(__DIR__).'/config/database.php';
require_once dirname(__DIR__).'/services/Data43CryptoService.php';
require_once dirname(__DIR__).'/services/Data43MapPatientService.php';
try{
 $db=(new Database())->getConnection();
 $service=new Data43MapPatientService($db);
 if(!$service->schemaReady())throw new RuntimeException('Run database/migrations/20261008_data43_map_patients.sql first');
 $opts=getopt('', ['hospital:', 'write', 'help']);
 if(isset($opts['help'])){echo "Usage: php scripts/reindex_data43_patient_areas.php --hospital=ID [--write]\n";exit;}
 $hospital=filter_var($opts['hospital']??null,FILTER_VALIDATE_INT);
 if(!$hospital||$hospital<1)throw new RuntimeException('Explicit --hospital=ID is required');
 $write=isset($opts['write']);$cursor=0;$total=0;$valid=0;$invalid=0;
 $crypto=new Data43CryptoService();
 echo 'Processing hospital '.$hospital.' '.($write?'WRITE':'DRY RUN')."\n";
 do{
  $stmt=$db->prepare("SELECT id,payload_ciphertext,payload_nonce,payload_algorithm FROM data43_records
    WHERE hospital_id=? AND file_code='ADDRESS' AND deleted_at IS NULL AND id>? ORDER BY id LIMIT 200");
  $stmt->execute([$hospital,$cursor]);
  $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
  if(!$rows)break;
  foreach($rows as $r){
   $cursor=(int)$r['id'];$total++;
   $address=$crypto->decrypt($r['payload_ciphertext'],$r['payload_nonce'],$r['payload_algorithm']);
   if(Data43MapPatientService::addressCode($address)===null){$invalid++;continue;}
   $valid++;if($write)$service->syncAddress($cursor,$hospital,$address);
  }
 }while(count($rows)===200);
 echo "Address records {$total}, valid area {$valid}, skipped invalid {$invalid}\n";
 if(!$write)echo "Dry run only. Use --write after checking hospital ID and counts.\n";
}catch(Throwable $e){fwrite(STDERR,"ERROR: ".$e->getMessage()."\n");exit(1);}
