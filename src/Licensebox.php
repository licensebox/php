<?php
declare(strict_types=1);
namespace Licensebox;
final class LicenseboxException extends \RuntimeException {
    public function __construct(public readonly string $errorCode, string $detail = '') { parent::__construct('licensebox: '.($detail ?: $errorCode)); }
}
/** Trusted platform hooks. Never populate these from untrusted request data. */
final class Hooks {
    public function __construct(public ?\Closure $clock = null, public ?\Closure $machineBinding = null,
        public ?\Closure $onSignIn = null, public ?\Closure $cancelled = null) {}
}
final class Native {
    public static function api(): \FFI {
        static $api;
        return $api ??= \FFI::cdef('typedef struct lb_client lb_client;
          typedef struct {void *context; void (*on_sign_in)(void*,const char*,const char*); double (*clock)(void*); int (*machine_binding)(void*,char*,unsigned long); int (*cancelled)(void*);} lb_callbacks;
          int lb_client_create_json(const char*,const lb_callbacks*,lb_client**);
          int lb_client_call_json(lb_client*,const char*,const char*,char**);
          void lb_client_destroy(lb_client*); void lb_free(void*);
          const char *lb_result_code(int); const char *lb_error_code(lb_client*); const char *lb_error_message(lb_client*);
          int lb_derive_binding(const char*,const char*,const char*,char*,unsigned long);
          int lb_native_binding(const char*,char*,unsigned long);
          int lb_verify_lease_json(const char*,const char*,const char*,double,char**);',
          getenv('LICENSEBOX_LIBRARY') ?: (getenv('LICENSEBOX_LIB_DIR') ? getenv('LICENSEBOX_LIB_DIR').'/liblicensebox.so' : 'liblicensebox.so'));
    }
    public static function arg(string $s): string { if (str_contains($s,"\0")) throw new LicenseboxException('invalid_argument'); return $s; }
    public static function text(mixed $s): string {return is_string($s) ? $s : \FFI::string($s);}
    public static function json(mixed $v): string {return json_encode($v, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
    public static function keys(array $keys): string {foreach($keys as $k=>$v){self::arg((string)$k);self::arg($v);}return self::json((object)$keys);}
    public static function error(int $r, mixed $h = null): LicenseboxException {
        $f=self::api(); $code=self::text($f->lb_result_code($r)); $detail=$code;
        if ($h !== null && $code !== 'invalid_argument') { $saved=self::text($f->lb_error_code($h)); if($saved!=='ok')$code=$saved; $detail=self::text($f->lb_error_message($h)); }
        return new LicenseboxException($code,$detail);
    }
    public static function output(mixed $p): mixed {
        if (\FFI::isNull($p)) throw new LicenseboxException('protocol_error');
        try {return json_decode(\FFI::string($p),true,512,JSON_THROW_ON_ERROR);} finally {self::api()->lb_free($p);}
    }
}
final class Client {
    public const VERSION = '0.3.0';
    private mixed $handle;
    private mixed $callbacks;
    private array $closures=[];
    private ?\Throwable $failure=null;
    private bool $busy=false;
    public function __construct(string $product,array $keys,string $version='',?string $server=null,?string $directory=null,?Hooks $hooks=null,?string $caBundle=null) {
        $f=Native::api(); $opts=['product'=>Native::arg($product),'keys'=>json_decode(Native::keys($keys)),'version'=>Native::arg($version)];
        foreach(['server'=>$server,'directory'=>$directory,'ca_bundle'=>$caBundle] as $k=>$v)if($v!==null)$opts[$k]=Native::arg($v);
        $this->callbacks=$f->new('lb_callbacks'); $hooks ??= new Hooks;
        if($hooks->onSignIn) $this->callbacks->on_sign_in=$this->closures[]=function($ctx,$url,$code)use($hooks):void {try{($hooks->onSignIn)(Native::text($url),Native::text($code));}catch(\Throwable $e){$this->failure=$e;}};
        if($hooks->clock) $this->callbacks->clock=$this->closures[]=function($ctx)use($hooks):float {try{$v=(float)($hooks->clock)();if(!is_finite($v))throw new \InvalidArgumentException('clock must be finite');return $v;}catch(\Throwable $e){$this->failure=$e;return 0;}};
        if($hooks->machineBinding) $this->callbacks->machine_binding=$this->closures[]=function($ctx,$out,$n)use($hooks):int {try{$s=Native::arg(($hooks->machineBinding)());if(strlen($s)>=$n)return 1;\FFI::memcpy($out,$s."\0",strlen($s)+1);return 0;}catch(\Throwable $e){$this->failure=$e;return 1;}};
        $this->callbacks->cancelled=$this->closures[]=function($ctx)use($hooks):int {try{return ($this->failure || ($hooks->cancelled && ($hooks->cancelled)()))?1:0;}catch(\Throwable $e){$this->failure=$e;return 1;}};
        $out=$f->new('lb_client*');$r=$f->lb_client_create_json(Native::json($opts),\FFI::addr($this->callbacks),\FFI::addr($out));
        $this->handle=\FFI::isNull($out)?null:$out;
        if($r || $this->failure){$this->close();if($this->failure)throw new LicenseboxException('callback_error',$this->failure->getMessage());throw Native::error($r);}
    }
    private function call(string $op,array $args=[]): mixed {
        if($this->busy || $this->handle===null)throw new LicenseboxException('invalid_argument','client closed or reentered');
        $this->busy=true;$f=Native::api();$out=$f->new('char*');
        try {
            $r=$f->lb_client_call_json($this->handle,$op,Native::json((object)$args),\FFI::addr($out));
            if($r || $this->failure){$f->lb_free($out);if($this->failure){$e=$this->failure;$this->failure=null;throw new LicenseboxException('callback_error',$e->getMessage());}throw Native::error($r,$this->handle);}
            return Native::output($out);
        }finally{$this->busy=false;}
    }
    public function close(): void {if($this->busy)throw new LicenseboxException('invalid_argument','callback reentry');if(isset($this->handle)){Native::api()->lb_client_destroy($this->handle);$this->handle=null;}$this->closures=[];$this->callbacks=null;}
    public function __destruct() {if(!$this->busy)$this->close();}
    public function lease(): mixed{return $this->call('lease');}
    public function heartbeat(string $module): mixed{return $this->call('heartbeat',['module'=>Native::arg($module)]);}
    public function requireModule(string $module): mixed{return $this->call('require',['module'=>Native::arg($module)]);}
    public function status(): mixed{return $this->call('status');}
    public function signIn(float $timeout=0): mixed{if(!is_finite($timeout))throw new LicenseboxException('invalid_argument');return $this->call('sign_in',['timeout'=>$timeout]);}
    public function logout(): void{$this->call('logout');}
    public function modules(): array{return $this->call('modules');}
    public function requests(): array{return $this->call('requests');}
    public function requestAccess(array $modules,string $note=''): mixed{foreach($modules as $v)Native::arg($v);return $this->call('request',['modules'=>$modules,'note'=>Native::arg($note)]);}
    public function accessLink(): string{return $this->call('access-link');}
    public function record(string $command,bool $success,float $elapsed): void{if(!is_finite($elapsed)||$elapsed<0)throw new LicenseboxException('invalid_argument');$this->call('record',['command'=>Native::arg($command),'success'=>$success,'elapsed'=>$elapsed]);}
    public static function deriveBinding(string $product,string $source,string $raw): string {
        $raw=rtrim($raw,"\0");if(str_contains($raw,"\0"))throw new LicenseboxException('identity_unavailable');
        $f=Native::api();$out=$f->new('char[128]');$r=$f->lb_derive_binding(Native::arg($product),Native::arg($source),$raw,$out,128);if($r)throw Native::error($r);return \FFI::string($out);
    }
    public static function nativeBinding(string $product): string{$f=Native::api();$out=$f->new('char[128]');$r=$f->lb_native_binding(Native::arg($product),$out,128);if($r)throw Native::error($r);return \FFI::string($out);}
    public static function verifyLease(string $token,string $product,array $keys,float $now): mixed{if(!is_finite($now))throw new LicenseboxException('invalid_argument');$f=Native::api();$out=$f->new('char*');$r=$f->lb_verify_lease_json(Native::arg($token),Native::arg($product),Native::keys($keys),$now,\FFI::addr($out));if($r){$f->lb_free($out);throw Native::error($r);}return Native::output($out);}
}
