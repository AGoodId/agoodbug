<?php
/** Hermetic Checkvist contract tests. No network or credentials. */
namespace AGoodBug {
 class Plugin { public static $settings=[]; public static function get_settings(){return self::$settings;} }
}
namespace {
 require __DIR__.'/../includes/integrations/class-checkvist.php';
 function ok($v,$m){if(!$v)throw new \RuntimeException($m);}
 function wp_get_current_user(){return (object)['ID'=>0];}
 function wp_json_encode($x){return json_encode($x);}
 function wp_date($f){return date($f,0);}
 function __($s,$d=null){return $s;}
 function get_option($key){return 'test@example.invalid';}
 function get_bloginfo($key){return 'Synthetic site';}
 function sanitize_text_field($s){return trim(strip_tags($s));}
 function sanitize_textarea_field($s){return trim(strip_tags($s));}
 function sanitize_email($s){return $s;}
 function esc_url_raw($s){return $s;}
 function absint($n){return abs((int)$n);}
 class WP_Error { public function get_error_message(){return 'Offline';} }
 function is_wp_error($x){return $x instanceof WP_Error;}
 function wp_remote_retrieve_response_code($r){return $r['code'];}
 function wp_remote_retrieve_body($r){return $r['body'];}
 $requests=[];$responses=[];
 function wp_remote_post($url,$args){global $requests,$responses;$requests[]=[$url,$args];return array_shift($responses);}
 $class='AGoodBug\\Integrations\\Checkvist';
 foreach(['955149'=> '955149',' https://checkvist.com/checklists/955149#task_42 '=>'955149','https://checkvist.com/checklists/955149/?x=1'=>'955149','0'=>'','-1'=>'','955149/../../evil'=>'','https://evil.test/checklists/955149'=>'','https://checkvist.com.evil.test/checklists/955149'=>'','https://checkvist.com@evil.test/checklists/955149'=>'','http://checkvist.com/checklists/955149'=>'','https://checkvist.com/checklists/955149/more'=>''] as $in=>$want)ok($class::normalize_list_id($in)===$want,'Normalize '.$in);
 ok($class::normalize_list_id(['955149'])==='','Reject array');
 require __DIR__.'/../includes/class-settings.php';
 require __DIR__.'/../includes/class-network-settings.php';
 $site_settings=new \AGoodBug\Settings();
 $network_settings=new \AGoodBug\Network_Settings();
 $network_sanitize=new \ReflectionMethod($network_settings,'sanitize');$network_sanitize->setAccessible(true);
 $node='https://checkvist.com/checklists/955149/tasks/75603537';
 $destinations=[
  '955149'=>['955149',''],
  'https://checkvist.com/checklists/955149'=>['955149',''],
  $node=>[$node,'75603537'],
  ' HTTPS://CHECKVIST.COM/checklists/955149/tasks/75603537/?x=1#task_42 '=>[$node,'75603537'],
 ];
 foreach($destinations as $input=>[$saved,$parent]){
  ok($class::normalize_destination($input)===$saved,'Normalize destination '.$input);
  foreach([
   $site_settings->sanitize_settings(['checkvist_list_id'=>$input]),
   $network_sanitize->invoke($network_settings,['checkvist_list_id'=>$input]),
  ] as $settings){
   ok($settings['checkvist_list_id']===$saved,'Saved destination retains parent');
   \AGoodBug\Plugin::$settings=array_merge($settings,['checkvist_username'=>'test@example.invalid','checkvist_api_key'=>'synthetic-secret']);
   $requests=[];$responses=[['code'=>201,'body'=>'{"id":123}'],['code'=>201,'body'=>'{"id":456}']];
   ok((new $class)->send(['comment'=>'Saved settings report','url'=>'https://example.invalid/page'],'',1)==='123','Send saved destination');
   ok($requests[0][0]==='https://checkvist.com/checklists/955149/tasks.json','Create in selected list');
   $task=json_decode($requests[0][1]['body'],true)['task'];
   ok(($task['parent_id']??'')===$parent,'Correct parent from saved settings');
   if($parent==='')ok(!array_key_exists('parent_id',$task),'List-only reports omit parent');
   ok($task['tags']==='todo,agoodbug','Parent and list reports retain native tags');
   ok($requests[1][0]==='https://checkvist.com/checklists/955149/tasks/123/comments.json','Note attaches to new report, not parent');
  }
 }
 foreach(['https://evil.test/checklists/955149/tasks/75603537','https://checkvist.com@evil.test/checklists/955149/tasks/75603537','https://checkvist.com/checklists/955149/tasks/0','https://checkvist.com/checklists/955149/tasks/-1','https://checkvist.com/checklists/955149/tasks/75603537/more','https://checkvist.com/checklists/955149/tasks/','https://checkvist.com/checklists/955149/tasks/../42',[],false] as $invalid){
  ok($class::normalize_destination($invalid)==='','Invalid destination rejected');
  ok($site_settings->sanitize_settings(['checkvist_list_id'=>$invalid])['checkvist_list_id']==='','Site settings reject invalid destination');
  ok($network_sanitize->invoke($network_settings,['checkvist_list_id'=>$invalid])['checkvist_list_id']==='','Network settings reject invalid destination');
  \AGoodBug\Plugin::$settings=['checkvist_username'=>'test@example.invalid','checkvist_api_key'=>'synthetic-secret','checkvist_list_id'=>$invalid];
  $requests=[];ok((new $class)->send(['comment'=>'x','url'=>'x'],'',1)===false,'Invalid node fails');ok(!$requests,'Invalid node makes no request');
 }
 $requests=[];
 \AGoodBug\Plugin::$settings=['checkvist_username'=>'test@example.invalid','checkvist_api_key'=>'synthetic-secret','checkvist_list_id'=>'https://checkvist.com/checklists/955149'];
 $responses=[['code'=>201,'body'=>'{"id":123}'],['code'=>201,'body'=>'{"id":456}']];
 $result=(new $class)->send(['comment'=>'Synthetic regression','url'=>'https://example.invalid/page','email'=>'test@example.invalid'],'https://example.invalid/screenshot.png',1);
 ok($result==='123','Task ID');ok(count($requests)===2,'Task and note');
 foreach($requests as[$url,$args]){ok(strpos($url,'?')===false,'No credentials in URL');ok($args['headers']['Authorization']==='Basic '.base64_encode('test@example.invalid:synthetic-secret'),'Basic auth');ok($args['redirection']===0,'No credential redirects');ok($args['timeout']===30,'Bounded timeout');}
 ok($requests[0][0]==='https://checkvist.com/checklists/955149/tasks.json','Fixed task endpoint');ok($requests[1][0]==='https://checkvist.com/checklists/955149/tasks/123/comments.json','Fixed note endpoint');
 $task=json_decode($requests[0][1]['body'],true)['task'];
 ok($task['tags']==='todo,agoodbug','Native Checkvist comma-separated tags without #');
 ok($task['content']==='Synthetic regression','Report text preserved without appended date syntax');
 ok($task['due_date']==='today','Due date preserved');
 $body=json_decode($requests[1][1]['body'],true);ok(strpos($body['comment']['comment'],'https://example.invalid/screenshot.png')!==false,'Screenshot preserved');
 ok(strpos($body['comment']['comment'],'URL: https://example.invalid/page')!==false,'Page URL preserved');
 // A resend uses the same send method and must supply native tags again, even for multiline text.
 \AGoodBug\Plugin::$settings['checkvist_list_id']=$node;
 $requests=[];$responses=[['code'=>201,'body'=>'{"id":124}'],['code'=>201,'body'=>'{"id":457}']];
 $comment="Rapport med #todo och #agoodbug\nAndra raden";
 ok((new $class)->send(['comment'=>$comment,'url'=>'https://example.invalid/page'],'',1)==='124','Resend task ID');
 $task=json_decode($requests[0][1]['body'],true)['task'];
 ok($task['tags']==='todo,agoodbug','Resends always set native tags');
 ok($task['parent_id']==='75603537','Resends retain selected parent');
 ok($task['content']===$comment,'Multiline report text with hashtags preserved without appended date syntax');
 ok($task['due_date']==='today','Resends set due date through the API field');
 $requests=[];$responses=[['code'=>422,'body'=>'{"error":"Parent not found"}']];
 ok((new $class)->send(['comment'=>'x','url'=>'x'],'',1)===false,'Rejected parent fails delivery');
 ok(count($requests)===1,'No fallback to top level after parent rejection');
 $requests=[];$responses=[['code'=>401,'body'=>'synthetic-secret should never be logged']];ok((new $class)->send(['comment'=>'x','url'=>'x'],'',1)===false,'Auth failure');ok(count($requests)===1,'No note after failed task');
 $requests=[];$responses=[new WP_Error()];ok((new $class)->send(['comment'=>'x','url'=>'x'],'',1)===false,'Network failure');
 \AGoodBug\Plugin::$settings['checkvist_list_id']='https://evil.test/checklists/955149';$requests=[];ok((new $class)->send(['comment'=>'x','url'=>'x'],'',1)===false,'Invalid list fails');ok(!$requests,'Invalid list makes no request');
 echo "Checkvist destination/settings/parent/auth/task/tags/resend/note/failure tests passed\n";
}
