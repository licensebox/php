<?php
require __DIR__.'/../src/Licensebox.php';
use Licensebox\Client;use Licensebox\Hooks;use Licensebox\LicenseboxException;
function run(array $r):mixed {
 switch($r['op']){case 'derive':return Client::deriveBinding($r['product'],$r['source'],$r['raw_id']);case 'native':return Client::nativeBinding($r['product']);case 'verify':return Client::verifyLease($r['token'],$r['product'],$r['keys'],$r['now']);}
 $c=new Client($r['product'],$r['keys'],'conformance',$r['server'],$r['directory'],new Hooks(fn()=>(float)$r['now'],fn()=>$r['binding'],fn($u,$c)=>null),$r['ca_bundle']??null);
 try{return match($r['op']) {
 'require'=>$c->requireModule($r['module']??'core'),'repeat'=>(function()use($c){$c->requireModule('core');return $c->requireModule('core');})(),
 'sign_in'=>$c->signIn(3),'request'=>$c->requestAccess(['export'],'café – test'), 'access-link'=>$c->accessLink(),
 'record'=>(function()use($c){$c->record('export',true,.125);$c->record('export',false,.025);return null;})(),default=>$c->{$r['op']}()};}finally{$c->close();}
}
try{$result=['ok'=>true,'value'=>run(json_decode(file_get_contents('php://stdin'),true,512,JSON_THROW_ON_ERROR))];}catch(LicenseboxException $e){$result=['ok'=>false,'error'=>$e->errorCode];}echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
