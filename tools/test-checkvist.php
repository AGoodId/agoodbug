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
 class WP_Error { public function get_error_message(){return 'Offline';} }
 function is_wp_error($x){return $x instanceof WP_Error;}
 function wp_remote_retrieve_response_code($r){return $r['code'];}
 function wp_remote_retrieve_body($r){return $r['body'];}
 $requests=[];$responses=[];
 function wp_remote_post($url,$args){global $requests,$responses;$requests[]=[$url,$args];return array_shift($responses);}
 $class='AGoodBug\\Integrations\\Checkvist';
 foreach(['955149'=> '955149',' https://checkvist.com/checklists/955149#task_42 '=>'955149','https://checkvist.com/checklists/955149/?x=1'=>'955149','0'=>'','-1'=>'','955149/../../evil'=>'','https://evil.test/checklists/955149'=>'','https://checkvist.com.evil.test/checklists/955149'=>'','https://checkvist.com@evil.test/checklists/955149'=>'','http://checkvist.com/checklists/955149'=>'','https://checkvist.com/checklists/955149/more'=>''] as $in=>$want)ok($class::normalize_list_id($in)===$want,'Normalize '.$in);
 ok($class::normalize_list_id(['955149'])==='','Reject array');
 \AGoodBug\Plugin::$settings=['checkvist_username'=>'test@example.invalid','checkvist_api_key'=>'synthetic-secret','checkvist_list_id'=>'https://checkvist.com/checklists/955149'];
 $responses=[['code'=>201,'body'=>'{"id":123}'],['code'=>201,'body'=>'{"id":456}']];
 $result=(new $class)->send(['comment'=>'Synthetic regression','url'=>'https://example.invalid/page','email'=>'test@example.invalid'],'https://example.invalid/screenshot.png',1);
 ok($result==='123','Task ID');ok(count($requests)===2,'Task and note');
 foreach($requests as[$url,$args]){ok(strpos($url,'?')===false,'No credentials in URL');ok($args['headers']['Authorization']==='Basic '.base64_encode('test@example.invalid:synthetic-secret'),'Basic auth');ok($args['redirection']===0,'No credential redirects');ok($args['timeout']===30,'Bounded timeout');}
 ok($requests[0][0]==='https://checkvist.com/checklists/955149/tasks.json','Fixed task endpoint');ok($requests[1][0]==='https://checkvist.com/checklists/955149/tasks/123/comments.json','Fixed note endpoint');
 $body=json_decode($requests[1][1]['body'],true);ok(strpos($body['comment']['comment'],'https://example.invalid/screenshot.png')!==false,'Screenshot preserved');
 $requests=[];$responses=[['code'=>401,'body'=>'synthetic-secret should never be logged']];ok((new $class)->send(['comment'=>'x','url'=>'x'],'',1)===false,'Auth failure');ok(count($requests)===1,'No note after failed task');
 $requests=[];$responses=[new WP_Error()];ok((new $class)->send(['comment'=>'x','url'=>'x'],'',1)===false,'Network failure');
 \AGoodBug\Plugin::$settings['checkvist_list_id']='https://evil.test/checklists/955149';$requests=[];ok((new $class)->send(['comment'=>'x','url'=>'x'],'',1)===false,'Invalid list fails');ok(!$requests,'Invalid list makes no request');
 echo "Checkvist normalization/auth/task/note/failure tests passed\n";
}
