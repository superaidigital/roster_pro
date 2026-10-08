<?php
class CentralSettingsService {
 private PDO $db;
 public function __construct(PDO $db){$this->db=$db;}
 public static function sensitive(string $key):bool{return in_array($key,['line_channel_access_token','line_channel_secret','line_target_id'],true);}
 public function save(string $section,array $raw,array $clear,int $actor):int{
  if(!in_array($section,['general','line_messaging'],true)||$actor<1)throw new InvalidArgumentException('ข้อมูลหมวดไม่ถูกต้อง');
  foreach($raw as $val)if(!is_scalar($val)&&$val!==null)throw new InvalidArgumentException('ค่าที่ส่งมาไม่ถูกต้อง');
  $keys=$section==='general'?['system_name','system_short_name','organization_name','contact_phone','contact_email','system_announcement','maintenance_message','maintenance_mode','system_announcement_enabled']:
    ['line_messaging_enabled','line_channel_access_token','line_channel_secret','line_target_id','line_messaging_on_roster','line_messaging_on_leave','line_messaging_on_swap','line_messaging_on_holiday'];
  $new=array_intersect_key($raw,array_flip($keys));
  if($section==='general'){
   foreach(['maintenance_mode','system_announcement_enabled'] as $k)$new[$k]=isset($raw[$k])?'1':'0';
   foreach($new as $k=>$v){$new[$k]=trim((string)$v);if(mb_strlen($new[$k])>($k==='system_announcement'?1000:($k==='maintenance_message'?400:180)))throw new InvalidArgumentException('ข้อความยาวเกินกำหนด');}
   foreach(['system_name','system_short_name'] as $k)if(empty($new[$k]))throw new InvalidArgumentException('กรุณากรอกชื่อระบบ');
   if(!empty($new['contact_email'])&&!filter_var($new['contact_email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('อีเมลไม่ถูกต้อง');
   $new['app_name']=$new['system_short_name'];$new['app_subtitle']=$new['system_name'];
  }else{
   foreach(['line_messaging_enabled','line_messaging_on_roster','line_messaging_on_leave','line_messaging_on_swap','line_messaging_on_holiday'] as $k)$new[$k]=isset($raw[$k])?'1':'0';
   foreach(['line_channel_access_token','line_channel_secret'] as $k){
    if(!empty($clear[$k]))$new[$k]='';
    elseif(trim((string)($raw[$k]??''))==='')unset($new[$k]);
   }
   if(isset($new['line_target_id'])&&$new['line_target_id']!==''&&!preg_match('/^[UCR][a-f0-9]{32}$/iD',(string)$new['line_target_id']))throw new InvalidArgumentException('รูปแบบ Target ID ไม่ถูกต้อง');
  }
  $this->db->beginTransaction();
  try{
   $this->db->query('SELECT id FROM system_settings_audit LIMIT 0');
   $old=[];foreach($this->db->query('SELECT setting_key,setting_value FROM system_settings FOR UPDATE') as $r)$old[$r['setting_key']]=$r['setting_value'];
   $up=$this->db->prepare('UPDATE system_settings SET setting_value=? WHERE setting_key=?');
   $ins=$this->db->prepare('INSERT INTO system_settings(setting_key,setting_value) VALUES(?,?)');
   $audit=$this->db->prepare('INSERT INTO system_settings_audit(actor_user_id,section_name,setting_key,previous_value,current_value,is_sensitive) VALUES(?,?,?,?,?,?)');
   $n=0;
   foreach($new as $key=>$value){
    $value=(string)$value;if(isset($old[$key])&&$old[$key]===$value)continue;
    if(array_key_exists($key,$old))$up->execute([$value,$key]);else $ins->execute([$key,$value]);
    $secret=self::sensitive($key);$audit->execute([$actor,$section,$key,$secret?null:($old[$key]??null),$secret?null:$value,$secret?1:0]);$n++;
   }
   $this->db->commit();return $n;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function recent():array{
  try{return $this->db->query('SELECT a.*,u.name actor_name FROM system_settings_audit a LEFT JOIN users u ON u.id=a.actor_user_id ORDER BY a.id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);}
  catch(Throwable $e){return [];}
 }
 public function checks(array $settings):array{
  $rows=[];$add=static function($name,$ok,$note)use(&$rows){$rows[]=['name'=>$name,'ok'=>$ok,'note'=>$note];};
  $add('PHP 8.2+',version_compare(PHP_VERSION,'8.2','>='),PHP_VERSION);
  foreach(['pdo_mysql','mbstring','zip','fileinfo'] as $e)$add('Extension '.$e,extension_loaded($e),extension_loaded($e)?'พร้อม':'ยังไม่เปิด');
  $add('Composer PDF',is_file(dirname(__DIR__).'/vendor/autoload.php'),'ตรวจ vendor/autoload.php');
  $add('Storage',is_writable(dirname(__DIR__).'/storage'),'ตรวจสิทธิ์โฟลเดอร์ storage');
  $add('LINE',empty($settings['line_messaging_enabled'])||$settings['line_messaging_enabled']==='0'||(!empty($settings['line_channel_access_token'])&&!empty($settings['line_target_id'])),'ตรวจข้อมูลเชื่อมต่อ ไม่ใช่ผลทดสอบส่ง');
  return $rows;
 }
}
