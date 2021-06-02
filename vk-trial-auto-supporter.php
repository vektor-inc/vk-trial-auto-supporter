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

/**
 *  深夜２時以降にユーザーからアクセスがあった時に１日１回実行する関数
 *  アクセスが無ければ実行されない
 */
function vk_trial_auto_function() {
	$result = array();
	/**
	 * テーマをアップデートする
	 * https://developer.wordpress.org/cli/commands/theme/update/
	 */
	exec( 'wp theme update --all', $output, $return_var );

	/**
	 * テーマアップデートが正常に終わったかどうか
	 */
	if ( $return_var !== 0 ){
		$theme_update = array(
			'theme_update' => 'false'
		);
	} else {
		$theme_update =array(
			'theme_update' => 'true'
		);
	}
	$result = array_merge( $result, $theme_update );

	/**
	 * プラグインをアップデートする
	 * https://developer.wordpress.org/cli/commands/plugin/update/
	 */
	exec( 'wp plugin update --all', $output, $return_var );

	/**
	 * テーマアップデートが正常に終わったかどうか
	 */
	if ( $return_var !== 0 ){
		$plugin_update =array(
			'plugin_update' => 'false'
		);
	} else {
		$plugin_update =array(
			'plugin_update' => 'true'
		);
	}
	$result = array_merge( $result, $plugin_update );

	/**
	 * 復元するコマンド
	 * https://updraftplus.com/wp-cli-updraftplus-documentation/
	 * 
	 * 4b574fbf3303はnonceでバックアップを作ると自動で作られる識別子 管理画面から確認する
	 * データベースのみ復元する
	 */
	//exec( 'wp updraftplus restore 4b574fbf3303 --components="db"' , $output, $return_var );

	/**
	 * 復元が正常に終わったかどうか
	 */
	if ( $return_var !== 0 ){
		$updraftplus = array(
			'updraftplus' => 'false'
		);
	} else {
		$updraftplus = array(
			'updraftplus' => 'true'
		);
	}
	$result = array_merge( $result, $updraftplus );

	/**
	 * 試用版ユーザーのパスワードを変更する 
	 * 試用版ユーザーID '2'を設定
	 */
	$password = wp_generate_password( 12, true );
	wp_set_password( $password, 2 );

	$result_password = array (
		'password' => $password
	);
	$result = array_merge( $result, $result_password );

	/**
	 * 自動返信メールで試用ユーザーにパスワードを送るためにDBに保存
	 */
	if ( ! get_option( 'vktas_options' ) ) {
		add_option( 'vktas_options', $result );
	}
	update_option( 'vktas_options', $result );

	/**
	 * コマンドが実行されていなかった場合、管理者宛にメールを送る
	 */
	$options                = vktas_default_options();
	$vk_theme_update        = $options[ 'theme_update' ];
	$vk_plugin_update       = $options[ 'plugin_update' ];
	$vk_updraftplus_restore = $options[ 'updraftplus' ];
	if ( 
		$vk_theme_update        == 'false' ||
		$vk_plugin_update       == 'false' ||
		$vk_updraftplus_restore == 'false'
	) {
		$to = get_option('admin_email');
		$subject = 'お試し申請サイト復元エラー';
		$message = <<<EOT
		テーマエラー：$vk_theme_update
		プラグインエラー：$vk_plugin_update
		復元エラー：$vk_updraftplus_restore
EOT;
		wp_mail( $to, $subject, $message );
	}

}
add_action ( 'vk_trial_form_auto_cron', 'vk_trial_auto_function' );

/**
 *  検証用インターバル設定関数
 *  本番は１日おきなのでデフォルトのdailyを使用
 *  vk_trial_intervalの関数は不要
 */
function vk_trial_interval( $schedules ) {
	$schedules['300sec'] = array(
		'interval' => 300,
		'display' => 'every 300 seconds'
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'vk_trial_interval' );

/**
 * イベントの実行
 * 本番ではstrtotime('2021-05-27 02:00:00')を変更し指定の時刻からスタート
 * 300secをdailyに変える
 */
if ( !wp_next_scheduled( 'vk_trial_form_auto_cron' ) ) {
	date_default_timezone_set( 'Asia/Tokyo' );
	wp_schedule_event( strtotime( '2021-05-27 11:40:00' ), '300sec', 'vk_trial_form_auto_cron' );
}

/**
 * 自動返信でパスワードを送る
 * 参考：https://stackoverflow.com/questions/28857693/wordpress-overwrite-contact-form-mail-2-body
 */
function wpcf7_post_password ( $contact_form ) {
	/**
	 * wp-cronで生成されたパスワード
	 */
	$options  = vktas_default_options();
	$password = $options[ 'password' ];

	/**
	 * mailは管理者宛のメール
	 * mail_2は自動返信メール
	 */
	$mail  = $contact_form->prop( 'mail' );
	$mail2 = $contact_form->prop( 'mail_2' );

	/**
	 * メール文章のgenerate_passwordの文字列を現在の設定されているパスワードに変更する
	 */
	$mail['body']  = str_replace( "generate_password", $password, $mail['body'] );
	$mail2['body'] = str_replace( "generate_password", $password, $mail2['body'] );

	$contact_form->set_properties( array( 'mail' => $mail ) );
	$contact_form->set_properties( array( 'mail_2' => $mail2 ) );

	return $contact_form;
}
add_action( 'wpcf7_before_send_mail', 'wpcf7_post_password' );

/**
 * リセット前１時間前くらいからはフォーム申請を停止する
 * https://wpdocs.osdn.jp/%E9%96%A2%E6%95%B0%E3%83%AA%E3%83%95%E3%82%A1%E3%83%AC%E3%83%B3%E3%82%B9/wp_schedule_event
 */
function filter_wpcf7_acceptance( $true ) { 
	$nowdate = date_i18n('H'); 
	if ( 1 <= $nowdate && $nowdate < 2 ) {
		$true = false;
	} else {
		$true = true;
	}
	return $true;
};
add_filter( 'wpcf7_acceptance', 'filter_wpcf7_acceptance' );

/**
 * フォームの申請を止めるのでフォーム送信時のメッセージの出力を加工する
 */
function filter_wpcf7_display_message( $message, $status ) { 
	if ( 'mail_sent_ok' == $status ) {
		$message = "申請ありがとうございました。送信頂いたメールアドレスにログイン情報をお送りさせていただいたのでご確認お願いします";
	} else {
		$message = "フォームの送信に失敗しました。しばらく時間をおいて再度お試しください。";
	}
	return $message;
};
add_filter( 'wpcf7_display_message', 'filter_wpcf7_display_message', 10, 2 );

/**
 * 管理者用投稿タイプ
 * 試用版ユーザーの申請フォームページのため
 */
function add_admin_only_post_type_manage() {
	register_post_type(
		'vk_trial',
		array(
			'labels'          => array(
				'name'          => '管理者用投稿タイプ',
			),
			'public'          => true,
			'menu_position'   => 100,
			'menu_icon'       => 'dashicons-admin-generic',
			'supports'        => array( 'title' , 'editor' ),
			'show_in_rest'    => true,
		)
	);
}
add_action( 'init', 'add_admin_only_post_type_manage' );

/**
 * vktas_optionsを変換
 */
function vktas_default_options() {
	$default = array(
		'theme_update'  => '',
		'plugin_update' => '',
		'updraftplus'   => '',
		'password'      => '',
	);
	$options = get_option( 'vktas_options' );
	$options = wp_parse_args( $options, $default );
	return $options;
}

/**
 * プラグイン停止時このプラグインで作ったoptionsの値を消す
 */
if ( function_exists( 'register_deactivation_hook' ) ) {
	register_deactivation_hook( __FILE__, 'vktas_uninstall_function' );
}
function vktas_uninstall_function() {
	delete_option( 'vktas_options' );
}