<?php
/**
 * Plugin Name: VK Trial Auto Supporter
 * Plugin URI:
 * Description: お試し申請の対応を自動化するプラグインです
 * Version: 0.0.0
 * Author:
 * Author URI:
 * Text Domain: vk-trial-auto-supporter
 */

/****
 * 復元する wp-cliコマンド
 * 03f928d85c1cはnonceでバックアップを作ると自動で作られる識別子 
 * 管理画面から確認する

 */
function vk_wp_cli() {
	// exec('wp updraftplus restore 03f928d85c1c');
}
add_action( 'vk_trial_cli', 'vk_wp_cli' );

/***
 * 定期的に実行する用テスト
 * これでは誰かがサイトに訪れた時にしか動かないので、お問い合わせを頂いた時点でのユーザー登録がなくなる可能性がある
 */
function cron_add_1min( $schedules ) {
  $schedules['1min'] = array(
    'interval' => 1*60, //インターバルの時間
    'display' => __( 'Once every one minutes' )
  );
  return $schedules;
}
add_filter( 'cron_schedules', 'cron_add_1min' );

if ( ! wp_next_scheduled( 'my_task_hook' ) ) {
  date_default_timezone_set('Asia/Tokyo');
  //スタートする時間 リリースする時に02:00:00にすればOK
  $start_time = strtotime("2020/5/21 14:07:00");
  wp_schedule_event( $start_time, '1min', 'my_task_hook' );
}

function do_stuff(){
  // exec('wp post create --post_title=CLI投稿テスト --post_status=draft --porcelain');
}
add_action( 'my_task_hook', 'do_stuff' );


/**
 * 管理画面 
 * */
function register_custom_settings() {
  register_setting('original-field-vk-trial', 'vk_trial_auto_supporter_nonce');
  register_setting('original-field-vk-trial', 'vk_trial_auto_supporter_time');
}
add_action('admin_init', 'register_custom_settings');

function vk_trial_setting_menu() {
	$custom_page = add_options_page(
		'VK Trial Auto Supporter',  // Name of page
		'VK Trial Auto Supporter',  // Label in menu
		'edit_theme_options',       // Capability required　このメニューページを閲覧・使用するために最低限必要なユーザーレベルまたはユーザーの種類と権限。
		'vk_trial_options',     // ユニークなこのサブメニューページの識別子スラッグ
		function() { include(dirname(__FILE__) . '/admin/vk_trial_options_manage.php'); },  // 管理画面のページの定義関数
	);
	if ( ! $custom_page ) {
		return;
	}
}
add_action( 'admin_menu', 'vk_trial_setting_menu' );

function my_katawara_header_before() {
  echo "<p>ゲットオプション内容</p>";
  $vk_trial_auto_supporter_nonce = get_option('vk_trial_auto_supporter_nonce', 'ここ');
  echo $sitename_main;
}
add_action('katawara_header_before', 'my_katawara_header_before');