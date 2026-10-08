<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/services/LeavePdfDocumentService.php';
function check(bool $test,string $message): void {
    if(!$test)throw new RuntimeException('FAILED: '.$message);
}
$b=LeavePdfDocumentService::boxMm(['x'=>25,'y'=>50,'width'=>50,'height'=>10],210,297);
check(abs($b['x']-52.5)<0.0001,'X mm');
check(abs($b['y']-148.5)<0.0001,'Y mm');
check(abs($b['width']-105)<0.0001,'width mm');
check(abs($b['height']-29.7)<0.0001,'height mm');
foreach([['x'=>99,'y'=>0,'width'=>20,'height'=>10],['x'=>-1,'y'=>0,'width'=>20,'height'=>10]] as $bad) {
    $thrown=false;
    try {LeavePdfDocumentService::boxMm($bad+['x'=>0,'y'=>0,'width'=>20,'height'=>10],210,297);}
    catch(InvalidArgumentException $e){$thrown=true;}
    check($thrown,'bounds rejection');
}
echo "PASS: percentage to PDF mm mapping and bounds.\n";
try {
    $config=LeavePdfDocumentService::prerequisites();
    echo "PASS: PDF dependencies and font: ".$config['font']."\n";
} catch(Throwable $e) {
    fwrite(STDERR,"PREREQUISITE REQUIRED: ".$e->getMessage()."\n");
    exit(2);
}
