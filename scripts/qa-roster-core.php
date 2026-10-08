<?php
/** Static, database-free integrity check for Roster Pro after removing its retired module. */
declare(strict_types=1);
$root=dirname(__DIR__);
$required=[
 'index.php','controllers/AuthController.php','controllers/DashboardController.php',
 'controllers/RosterController.php','controllers/LeaveController.php',
 'controllers/SwapController.php','controllers/HospitalsController.php',
 'controllers/UsersController.php','views/layouts/header.php',
 'views/layouts/sidebar.php','views/roster/index.php','views/leave/index.php',
 'views/swap/index.php','views/hospitals/index.php'
];
$removed=[
 'controllers/Data43Controller.php','models/Data43SubmissionModel.php',
 'services/Data43ImportService.php','services/Data43SystemHealthService.php',
 'views/data43/index.php','assets/js/data43-thailand-map.js'
];
$errors=[];
foreach($required as $path)if(!is_file($root.'/'.$path))$errors[]='Missing core file: '.$path;
foreach($removed as $path)if(is_file($root.'/'.$path))$errors[]='Retired file still present: '.$path;
foreach(['views/layouts/sidebar.php','views/layouts/header.php'] as $path){
 $body=@file_get_contents($root.'/'.$path);
 if($body===false||stripos($body,'data43')!==false||str_contains($body,'43 แฟ้ม'))$errors[]='Obsolete menu or context: '.$path;
}
$router=@file_get_contents($root.'/index.php');
if($router===false||!str_contains($router,"if ($". "c === 'data43')"))$errors[]='Router missing retired-module block';
if(!is_file($root.'/.htaccess'))$errors[]='Missing Apache access-control file';
if($errors){foreach($errors as $err)fwrite(STDERR,"FAIL: {$err}\n");exit(1);}
echo "PASS: preserved core, removed health module, blocked old URLs and Apache safeguards.\n";
