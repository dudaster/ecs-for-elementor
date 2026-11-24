<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Ecs_Notice {
    /**
		 * Message of the notice.
		 *
		 * @since  1.2.2
		 * @access private
		 * @var    string
		 */
		private $message = "";

		/**
		 * The id of the notice.
		 *
		 * @since  1.0.0
		 * @access private
		 * @var    string
		 */
		private $notice = "";
  
 		/**
		 * The id of the notice.
		 *
		 * @since  1.0.0
		 * @access private
		 * @var    string
		 */
		private $type = "info";
  
    /**
		 * The user role.
		 *
		 * @since  1.0.0
		 * @access private
		 * @var    string
		 */
		private $role = 'administrator';
  
    public function __construct($message,$notice="") {
      $this->message = $message;
      $this->notice = $notice;
    }
  
    public function set_role($role){
      $this->role = $role;
    }
    public function set_type($type){
      $this->type = $type;
    }
    public function show(){
       add_action('admin_notices', [$this,'admin_notice']);
    }
    public function admin_notice(){
      $user_id = get_current_user_id();
      if ($this->notice && get_user_meta( $user_id, $this->notice )) return;
      $dismiss_url = wp_nonce_url( add_query_arg( 'ecsn_shown', rawurlencode( $this->notice ) ), 'ecsn_dismiss' );
      $user = wp_get_current_user();
      if ( in_array( $this->role, (array) $user->roles ) ) {
      ?>
      <div class="notice notice-<?php echo esc_attr( $this->type ); ?> " style="padding-right: 38px; position: relative;">
            <p> <img width="30px" src="<?php echo esc_url( ELECS_URL . 'assets/dudaster_icon.png' ); ?>" style="width:32px;margin-right:10px;margin-bottom: -11px;"/> <?php echo wp_kses_post( $this->message ); ?></p>
          <a href="<?php echo esc_url( $dismiss_url ); ?>"><button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'ele-custom-skin' ); ?></span></button></a>
          </div>
      <?php
      }
    }
  
}

function ecs_notice_dismiss() {
    if ( empty( $_GET['ecsn_shown'] ) ) return;
    check_admin_referer( 'ecsn_dismiss' );
    $notice = sanitize_text_field( wp_unslash( $_GET['ecsn_shown'] ) );
    // Only notices that are currently shown can be dismissed.
    if ( ! in_array( $notice, wp_list_pluck( ecs_load_notifications(), 'slug' ), true ) ) return;
    add_user_meta( get_current_user_id(), $notice, 'true', true );
}
add_action( 'admin_init', 'ecs_notice_dismiss' );

function ecs_check_for_notification(){
  if (!is_admin()) return;
  $user_id = get_current_user_id();
  $current_date = gmdate("Y-m-d"); 
  $notifications = ecs_load_notifications();
  $i=0;
  foreach($notifications as $notification){
        $show[$i] = new Ecs_Notice($notification['message'],$notification['slug']);
        $show[$i]->show();
        $i++;
  }
}
function ecs_days_old(){
  $user_id = get_current_user_id();
  $nstall="ecs_instalation";
  if ( !get_user_meta( $user_id, $nstall ,true )){
    add_user_meta( $user_id,  $nstall , time()); 
  }
  return round((time() - get_user_meta( $user_id, $nstall ,true )) / 86400);
}
function ecs_load_notifications(){

  $user_id = get_current_user_id();
  $current_date = gmdate("Y-m-d"); 
  $ecspro = ecs_is_plugin_active('ele-custom-skin-pro.php') ? true : false;
  $nver='ecs_notification_verification';
  $ndata='ecs_notification_data';
  $url='';
  $days = ecs_days_old();
  if ( get_user_meta( $user_id, $nver ,true ) < $current_date){
//get data from url
     $content = ecs_data_get($url);
     $notifications = json_decode($content,true);
// set the data inside user metadata
   if(is_array($notifications)) {
     if(is_array(get_user_meta( $user_id, $ndata,true))) update_user_meta( $user_id,  $ndata , $notifications ); 
       else add_user_meta( $user_id,  $ndata , $notifications ); 
   }
// set the date inside user metadata

   if(get_user_meta( $user_id, $nver,true) )  update_user_meta( $user_id,  $nver ,  $current_date );
       else add_user_meta( $user_id,  $nver ,  $current_date, true );
 }
// load notice data from user metadata 

  $notifications = get_user_meta( $user_id, $ndata,true);

  $return_notification=[];
  if(is_array($notifications)) foreach($notifications as $notification){
    $startdate = isset($notification['startdate']) ? $notification['startdate'] : "";
    $enddate = isset($notification['enddate']) ? $notification['enddate'] :  $current_date;
    $target = isset($notification['target']) ? $notification['target'] : "ecs";
    $notification['message'] = isset($notification['message']) ? $notification['message'] : ""; 
    $notification['days'] = isset($notification['days']) ? $notification['days'] : 0;
    $notification['slug'] = isset($notification['slug']) ? $notification['slug'] : 'ecs'.hash('adler32',$notification['message']);
    if($startdate <= $current_date && $current_date <= $enddate){ 
      if (($target == 'ecspro' && $ecspro) || ($target == 'ecs' && !$ecspro)) {
          if($notification['message'] && ($notification['days'] >= $days || !$notification['days']) ) $return_notification[] = $notification;
      }
    }
  }

  return $return_notification;
}

function ecs_data_get($url){
  if(!$url) return false; 
  $body = false;
  $response = wp_remote_get($url,array( 'timeout' => 2 ));
  if( is_array($response) ) {
    $header = $response['headers']; // array of http header lines
    $body = $response['body']; // use the content
  }
  return $body; // to return content
}
