<?php
require __DIR__.'/../src/Licensebox.php';
use Licensebox\Client;use Licensebox\LicenseboxException;
if(!str_starts_with(Client::deriveBinding('demo','linux-machine-id',"12345678123456789abc1234567890ab\0"),'m1:'))throw new Exception('derive failed');
try{Client::verifyLease('bad','demo',[],0);throw new Exception('bad token accepted');}catch(LicenseboxException $e){if($e->errorCode!=='malformed')throw $e;}
echo "PHP binding checks passed\n";
$dir=sys_get_temp_dir().'/licensebox-php-'.bin2hex(random_bytes(8));mkdir($dir,0700);
$c=new Client('demo',['test-key'=>'A6EHv_POEL4dcN0Y50vAmWfk1jCbpQ1fHdyGZBJVMbg'],directory:$dir,hooks:new \Licensebox\Hooks(machineBinding:fn()=>throw new Exception('synthetic callback failure')));
try{try{$c->signIn(.1);throw new Exception('callback failure ignored');}catch(LicenseboxException $e){if($e->errorCode!=='callback_error')throw $e;}}finally{$c->close();$c->close();foreach(scandir($dir)as$file)if($file!=='.'&&$file!=='..')unlink($dir.'/'.$file);rmdir($dir);}
