<?php
namespace AGoodBug {
 class Plugin {
  public static $settings = array('allow_anonymous'=>true);
  public static function get_settings(){return self::$settings;}
  public static function user_can_report(){return false;}
 }
}
namespace {
 define('AGOODBUG_PLUGIN_URL','https://example.test/wp-content/plugins/agoodbug/');
 define('AGOODBUG_VERSION','1.8.44');
 function is_admin(){return $GLOBALS['admin']??false;}
 function is_user_logged_in(){return false;}
 function wp_enqueue_script(...$a){$GLOBALS['scripts'][$a[0]]=$a;}
 function wp_enqueue_style(...$a){$GLOBALS['styles'][$a[0]]=$a;}
 function wp_localize_script($handle,$name,$value){$GLOBALS['config']=$value;}
 function wp_add_inline_script(...$a){}
 function add_query_arg($key,$value,$url){return $url.'?'.$key.'='.$value;}
 function rest_url($s){return 'https://example.test/wp-json/'.$s;}
 function admin_url($s){return 'https://example.test/wp-admin/'.$s;}
 function wp_create_nonce($s){return 'fixture-nonce';}
 function __($s,$domain){return $s;}
 require __DIR__.'/../includes/class-frontend.php';
 function check($ok,$label){if(!$ok)throw new \RuntimeException($label);}
 $frontend=new \AGoodBug\Frontend();$frontend->enqueue_assets();
 check(array_keys($GLOBALS['scripts'])===array('agoodbug'),'Initial page only enqueues the widget, not its optional rasterizer.');
 check($GLOBALS['scripts']['agoodbug'][2]===array(),'Widget has no eager screenshot dependency.');
 check($GLOBALS['config']['html2canvasUrl']===AGOODBUG_PLUGIN_URL.'vendor/html2canvas.min.js?ver=1.4.1','Fallback URL remains local, exact and versioned.');
 check($GLOBALS['config']['ajaxAction']==='agoodbug_submit_feedback'&&$GLOBALS['config']['nonce']==='fixture-nonce','Submission action and nonce stay configured.');
 $tag='<script src="https://example.test/widget.js"></script>';
 $filtered=$frontend->add_script_loader_attributes($tag,'agoodbug','unused');
 check(strpos($filtered,'data-no-optimize="1"')!==false,'Widget retains optimization exclusions.');
 check($filtered===$frontend->add_script_loader_attributes($filtered,'agoodbug','unused'),'Optimization attributes are idempotent.');
 $GLOBALS['scripts']=array();$GLOBALS['styles']=array();\AGoodBug\Plugin::$settings=array('allow_anonymous'=>false);
 $frontend->enqueue_assets();check(!$GLOBALS['scripts']&&!$GLOBALS['styles'],'Visitors without reporting permission receive no resources.');
 $GLOBALS['admin']=true;\AGoodBug\Plugin::$settings=array('allow_anonymous'=>true,'show_in_admin'=>false);
 $frontend->enqueue_assets();check(!$GLOBALS['scripts']&&!$GLOBALS['styles'],'Admin visibility setting remains authoritative.');
 echo "Frontend assets: lazy local fallback, submission config and access boundaries passed.\n";
}
