
<?php
class netpayclient
{
private static $VERSION = 2.0;
private $DES_KEY;
private $HASH_PAD;
private $private_key;
function __construct()
{
$this->DES_KEY = "SCUBEPGW";
$this->private_key = array();
$this->HASH_PAD = "0001ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff003021300906052b0e03021a05000414";
bcscale(0);
}
function __destruct()
{}
public function getVerstion()
{
return $this->VERSION;
}
private function hex2bin($hexdata)
{
$bindata = '';
if (strlen($hexdata) %2 == 1) {
$hexdata = '0'.$hexdata;
}
for ($i = 0;$i <strlen($hexdata);$i += 2) {
$bindata .= chr(hexdec(substr($hexdata,$i,2)));
}
return $bindata;
}
private function padstr($src,$len = 256,$chr = '0',$d = 'L')
{
$ret = trim($src);
$padlen = $len -strlen($ret);
if ($padlen >0) {
$pad = str_repeat($chr,$padlen);
if (strtoupper($d) == 'L') {
$ret = $pad .$ret;
}else {
$ret = $ret .$pad;
}
}
return $ret;
}
private function bin2int($bindata)
{
$hexdata = bin2hex($bindata);
return $this->bchexdec($hexdata);
}
private function bchexdec($hexdata)
{
$ret = '0';
$len = strlen($hexdata);
for ($i = 0;$i <$len;$i ++) {
$hex = substr($hexdata,$i,1);
$dec = hexdec($hex);
$exp = $len -$i -1;
$pow = bcpow('16',$exp);
$tmp = bcmul($dec,$pow);
$ret = bcadd($ret,$tmp);
}
return $ret;
}
private function bcdechex($decdata)
{
$s = $decdata;
$ret = '';
while ($s != '0') {
$m = bcmod($s,'16');
$s = bcdiv($s,'16');
$hex = dechex($m);
$ret = $hex .$ret;
}
return $ret;
}
private function sha1_128($string)
{
$hash = sha1($string);
$sha_bin = hex2bin($hash);
$sha_pad = hex2bin($this->HASH_PAD);
return $sha_pad .$sha_bin;
}
private function mybcpowmod($num,$pow,$mod)
{
if (function_exists('bcpowmod')) {
return bcpowmod($num,$pow,$mod);
}
return $this->emubcpowmod($num,$pow,$mod);
}
private function emubcpowmod($num,$pow,$mod)
{
$result = '1';
do {
if (!bccomp(bcmod($pow,'2'),'1')) {
$result = bcmod(bcmul($result,$num),$mod);
}
$num = bcmod(bcpow($num,'2'),$mod);
$pow = bcdiv($pow,'2');
}while (bccomp($pow,'0'));
return $result;
}
private function rsa_encrypt($input)
{
$p = $this->bin2int($this->private_key["prime1"]);
$q = $this->bin2int($this->private_key["prime2"]);
$u = $this->bin2int($this->private_key["coefficient"]);
$dP = $this->bin2int($this->private_key["prime_exponent1"]);
$dQ = $this->bin2int($this->private_key["prime_exponent2"]);
$c = $this->bin2int($input);
$cp = bcmod($c,$p);
$cq = bcmod($c,$q);
$a = $this->mybcpowmod($cp,$dP,$p);
$b = $this->mybcpowmod($cq,$dQ,$q);
if (bccomp($a,$b) >= 0) {
$result = bcsub($a,$b);
}else {
$result = bcsub($b,$a);
$result = bcsub($p,$result);
}
$result = bcmod($result,$p);
$result = bcmul($result,$u);
$result = bcmod($result,$p);
$result = bcmul($result,$q);
$result = bcadd($result,$b);
$ret = $this->bcdechex($result);
$ret = strtoupper($this->padstr($ret));
return (strlen($ret) == 256) ?$ret : false;
}
private function rsa_decrypt($input)
{
$check = $this->bchexdec($input);
$modulus = $this->bin2int($this->private_key["modulus"]);
$exponent = $this->bchexdec("010001");
$result = bcpowmod($check,$exponent,$modulus);
$rb = $this->bcdechex($result);
return strtoupper($this->padstr($rb));
}
public function buildKey($key)
{
if (count($this->private_key) >0) {
foreach ($this->private_key as $name =>$value) {
unset($this->private_key[$name]);
}
}
$ret = false;
$key_file = parse_ini_file($key);
if (!$key_file) {
return $ret;
}
$hex = "";
if (array_key_exists("MERID",$key_file)) {
$ret = $key_file["MERID"];
$this->private_key["MERID"] = $ret;
$hex = substr($key_file["prikeyS"],80);
}else {
if (array_key_exists("PGID",$key_file)) {
$ret = $key_file["PGID"];
$this->private_key["PGID"] = $ret;
$hex = substr($key_file["pubkeyS"],48);
}else {
return $ret;
}
}
$bin = hex2bin($hex);
$this->private_key["modulus"] = substr($bin,0,128);
if (array_key_exists("MERID",$key_file)) {
$cipher = MCRYPT_DES;
$iv = str_repeat("\x00",8);
$prime1 = substr($bin,384,64);
$enc = $this->decryptDesCBC($prime1,$this->DES_KEY);
$this->private_key["prime1"] = $enc;
$prime2 = substr($bin,448,64);
$enc = $this->decryptDesCBC($prime2,$this->DES_KEY);
$this->private_key["prime2"] = $enc;
$prime_exponent1 = substr($bin,512,64);
$enc = $this->decryptDesCBC($prime_exponent1,$this->DES_KEY);
$this->private_key["prime_exponent1"] = $enc;
$prime_exponent2 = substr($bin,576,64);
$enc = $this->decryptDesCBC($prime_exponent2,$this->DES_KEY);
$this->private_key["prime_exponent2"] = $enc;
$coefficient = substr($bin,640,64);
$enc = $this->decryptDesCBC($coefficient,$this->DES_KEY);
$this->private_key["coefficient"] = $enc;
}
return $ret;
}
public function newSignData_PHP_Client($msg)
{
if (!array_key_exists("MERID",$this->private_key)) {
return -9999;
}
$hb = $this->sha1_128($msg);
$tmp = bin2hex($hb);
$result = $this->rsa_encrypt($hb);
if ($result == false) {
return -9998;
}
return $result;
}
public function sign($msg)
{
return $this->newSignData_PHP_Client($msg);
}
public function verify($plain,$checkValue)
{
return $this->newVeriSignData_PHP_Client($plain,$checkValue);
}
public function newEncData_PHP_Client($msg)
{
$deskey1 = $this->myRandomString(8);
$deskey2 = $this->myRandomString(8);
$iv = "FIEf124H";
if (strlen($msg) >9999) {
return -9999;
}
$sMsgKey = $this->OAEP_Two($deskey1,$deskey2,$iv);
$trKey = $deskey1 .$deskey2;
$strOut = $this->DES3b($msg,$trKey);
$sMsgKey = $this->rsa_decrypt(bin2hex($sMsgKey));
$len = $this->padstr(strlen($msg),4);
$result = $sMsgKey .$len .bin2hex($strOut);
$result = strtoupper($result);
return $result;
}
public function newDecData_PHP_Client($encMsg)
{
if (empty($encMsg)) {
return -9999;
}
$msgKeyCipher = substr($encMsg,0,256);
$sLen = substr($encMsg,256,4);
$encData = substr($encMsg,260);
if ($sLen <= 0) {
return -9998;
}
$desKey = $this->getMsgKey($msgKeyCipher);
if ($desKey <0) {
return -9997;
}
$trKey = $this->checkOAEP($desKey);
if ($trKey <0) {
return $trKey;
}
$plainMsg = $this->_DES3b($encData,$trKey);
$plainMsg = trim($plainMsg);
return $plainMsg;
}
public function newEncPin_PHP_Client($pin,$merId,$transDate,$seqId,$cardId)
{
if (strlen($cardId) <7) {
return -9999;
}
$length = strlen($pin);
$plain = "";
if ($length >8) {
$plain = substr($pin,0,8);
}
$padLength = 8 -($length %8);
$totalLength = $length +$padLength;
$plain = str_pad($pin,$totalLength," ",STR_PAD_RIGHT);
$desKey = substr($cardId,strlen($cardId) -8);
$tmpResult = $this->encrypt1Des($plain,$desKey);
if ($tmpResult <0) {
return -9998;
}
$result = strtoupper(bin2hex($tmpResult));
return $result;
}
function signOrder($merid,$ordno,$amount,$curyid,$transdate,$transtype)
{
if (strlen($merid) != 15)
return false;
if (strlen($ordno) != 16)
return false;
if (strlen($amount) != 12)
return false;
if (strlen($curyid) != 3)
return false;
if (strlen($transdate) != 8)
return false;
if (strlen($transtype) != 4)
return false;
$plain = $merid .$ordno .$amount .$curyid .$transdate .$transtype;
return $this->newSignData_PHP_Client($plain);
}
public function newVeriSignData_PHP_Client($plain,$check)
{
if (!array_key_exists("PGID",$this->private_key)) {
return false;
}
if (strlen($check) != 256) {
return false;
}
$hb = $this->sha1_128($plain);
$hbhex = strtoupper(bin2hex($hb));
$rbhex = $this->rsa_decrypt($check);
return $hbhex == $rbhex ?true : false;
}
public function verifyTransResponse($merid,$ordno,$amount,$curyid,$transdate,$transtype,$ordstatus,$check)
{
if (strlen($merid) != 15)
return false;
if (strlen($ordno) != 16)
return false;
if (strlen($amount) != 12)
return false;
if (strlen($curyid) != 3)
return false;
if (strlen($transdate) != 8)
return false;
if (strlen($transtype) != 4)
return false;
if (strlen($ordstatus) != 4)
return false;
if (strlen($check) != 256)
return false;
$plain = $merid .$ordno .$amount .$curyid .$transdate .$transtype .$ordstatus;
return $this->newVeriSignData_PHP_Client($plain,$check);
}
private function OAEP_Two($deskey1,$deskey2,$iv)
{
$padding = "ffFIEFlw81f03frL8f2lfsg";
$tmpRandom = hex2bin("0100");
for ($i = 1;$i <= 126;$i ++) {
$chr = chr(mt_rand(-128,127));
$tmpRandom = $tmpRandom .$chr;
}
$randomHexStr0 = substr($tmpRandom,0,10) .$padding .substr($tmpRandom,33);
$randomHexStr1 = substr($randomHexStr0,0,103) .hex2bin("00") .substr($randomHexStr0,104);
$randomHexStr2 = substr($randomHexStr1,0,104) .$deskey1 .substr($randomHexStr1,112);
$randomHexStr3 = substr($randomHexStr2,0,112) .$deskey2 .substr($randomHexStr2,120);
$randomHexStr4 = substr($randomHexStr3,0,120) .$iv;
return $randomHexStr4;
}
private function checkOAEP($desKey)
{
$first2Byte = substr($desKey,0,4);
if ($first2Byte != '0100') {
return -2000;
}
$tmpStr = "ffFIEFlw81f03frL8f2lfsg";
$R = hex2bin($desKey);
$gettmpStr = substr($R,10,strlen($tmpStr));
if ($gettmpStr != $tmpStr) {
return -1009;
}
$IV = "FIEf124H";
$getIV = substr($R,120,strlen($IV));
if ($getIV != $IV) {
return -1008;
}
$trKey = substr($R,104,16);
return $trKey;
}
private function myRandomString($length)
{
$tmpRandom = "";
for ($i = 1;$i <= $length;$i ++) {
$chr = chr(mt_rand(-128,127));
$tmpRandom = $tmpRandom .$chr;
}
return $tmpRandom;
}
private function DES3b($msg,$desKey)
{
$iv_temp = "ChinaPay";
$tail = "         ";
$len = strlen($msg);
$k = -1;
if ($len %8 == 0) {
$k = $len / 8;
}else {
$k = $len / 8 +1;
}
$bText = $msg .$tail;
$d_text = "";
for ($i = 0;$i <$k;$i ++) {
$buf2 = "";
$buf1 = substr($bText,$i * 8,8);
$buf1 = $iv_temp ^$buf1;
$buf2 = $this->encrypt3Des($buf1,$desKey);
if (strlen($buf2) >8) {
$buf2 = substr($buf2,0,8);
}
$iv_temp = $buf2;
$d_text = $d_text .$buf2;
}
return $d_text;
}
private function _DES3b($input,$desKey)
{
$iv_temp = "ChinaPay";
$encData = hex2bin($input);
$len = strlen($encData);
$k = -1;
if ($len %8 == 0) {
$k = $len / 8;
}else {
$k = $len / 8 +1;
}
$plainData = "";
for ($i = 0;$i <$k;$i ++) {
$buf1 = substr($encData,$i * 8,8);
$buf2 = $this->decrypt3Des($buf1,$desKey);
$buf2 = $iv_temp ^$buf2;
$iv_temp = $buf1;
$plainData = $plainData .bin2hex($buf2);
}
return $this->hexdecode($plainData);
}
private function encrypt3Des($input,$key)
{
$size = mcrypt_get_block_size(MCRYPT_3DES,MCRYPT_MODE_ECB);
$input = $this->pkcs5_pad($input,$size);
$desKey = "";
if (strlen($key) >24) {
return "-9999";
}else {
if (strlen($key) == 16) {
$desKey = $key .substr($key,0,8);
}else {
$desKey = str_pad($key,24,'0');
}
}
$td = mcrypt_module_open(MCRYPT_3DES,'',MCRYPT_MODE_ECB,'');
$iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td),MCRYPT_RAND);
@mcrypt_generic_init($td,$desKey,$iv);
$data = mcrypt_generic($td,$input);
mcrypt_generic_deinit($td);
mcrypt_module_close($td);
$data = substr($data,0,8);
return $data;
}
private function encrypt1Des($input,$key)
{
$size = mcrypt_get_block_size(MCRYPT_DES,MCRYPT_MODE_ECB);
$input = $this->pkcs5_pad($input,$size);
$desKey = "";
if (strlen($key) >8) {
return -1;
}
$desKey = $key;
$td = mcrypt_module_open(MCRYPT_DES,'',MCRYPT_MODE_ECB,'');
$iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td),MCRYPT_RAND);
@mcrypt_generic_init($td,$desKey,$iv);
$data = mcrypt_generic($td,$input);
mcrypt_generic_deinit($td);
mcrypt_module_close($td);
$data = substr($data,0,8);
return $data;
}
private function decryptDesCBC($input,$key)
{
$size = mcrypt_get_block_size(MCRYPT_DES,MCRYPT_MODE_CBC);
$input = $this->pkcs5_pad($input,$size);
$desKey = "";
if (strlen($key) >8) {
return -1;
}
$desKey = $key;
$td = mcrypt_module_open(MCRYPT_DES,'',MCRYPT_MODE_CBC,'');
$iv = str_repeat("\x00",8);
@mcrypt_generic_init($td,$desKey,$iv);
$data = mdecrypt_generic($td,$input);
mcrypt_generic_deinit($td);
mcrypt_module_close($td);
if(strlen($data)>8){
$data = substr($data,0,strlen($data) -8);
}
return $data;
}
private function decrypt3Des($encrypted,$key)
{
$desKey = "";
if (strlen($key) >24) {
return "-9999";
}else {
if (strlen($key) == 16) {
$desKey = $key .substr($key,0,8);
}else {
$desKey = str_pad($key,24,'0');
}
}
$td = mcrypt_module_open(MCRYPT_3DES,'',MCRYPT_MODE_ECB,'');
$desKey = substr($desKey,0,mcrypt_enc_get_key_size($td));
$iv = @mcrypt_create_iv(mcrypt_enc_get_iv_size($td),MCRYPT_RAND);
$ks = mcrypt_enc_get_key_size($td);
@mcrypt_generic_init($td,$desKey,$iv);
$decrypted = mdecrypt_generic($td,$encrypted);
mcrypt_generic_deinit($td);
mcrypt_module_close($td);
return $decrypted;
}
private function pkcs5_pad($text,$blocksize)
{
$pad = $blocksize -(strlen($text) %$blocksize);
return $text .str_repeat(chr($pad),$pad);
}
private function pkcs5_unpad($text)
{
$pad = ord($text{strlen($text) -1});
if ($pad >strlen($text)) {
return false;
}
if (strspn($text,chr($pad),strlen($text) -$pad) != $pad) {
return false;
}
return substr($text,0,-1 * $pad);
}
private function pkcs7_pad($data)
{
$block_size = mcrypt_get_block_size(MCRYPT_DES,MCRYPT_MODE_CBC);
$padding_char = $block_size -(strlen($data) %$block_size);
$data .= str_repeat(chr($padding_char),$padding_char);
return $data;
}
private function pkcs7_unpad($text)
{
$pad = ord($text[(strlen($text)) -1]);
if ($pad >strlen($text)) {
return false;
}
if (strspn($text,chr($pad),strlen($text) -$pad) != $pad) {
return false;
}
return substr($text,0,-1 * $pad);
}
private function getMsgKey($cipherTxt)
{
if (strlen($cipherTxt) != 256) {
return -2;
}
$cpt = hex2bin($cipherTxt);
$rb = $this->rsa_encrypt($cpt);
if ($rb == false) {
return -3;
}
return $rb;
}
private function hexdecode($input)
{
$result = hex2bin($input);
return $result;
}
}
?>