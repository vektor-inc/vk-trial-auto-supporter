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
 * プラグイン有効化時
 * 
 * 以前作ったイベントvk_trial_form_auto_cronを削除する
 */
if ( function_exists( 'register_activation_hook' ) ) {
	register_activation_hook( __FILE__, 'vktas_install_function' );
}
function vktas_install_function() {
	wp_clear_scheduled_hook( 'vk_trial_form_auto_cron' );
}

/**
 * プラグイン停止時
 * 
 * プラグインで作ったoptionsの値を消す
 * vktas_auto_cronイベントを削除する
 */
if ( function_exists( 'register_deactivation_hook' ) ) {
	register_deactivation_hook( __FILE__, 'vktas_uninstall_function' );
}
function vktas_uninstall_function() {
	delete_option( 'vktas_options' );
	wp_clear_scheduled_hook( 'vktas_auto_cron' );
}

/**
 *  深夜２時以降にユーザーからアクセスがあった時に１日１回実行する関数
 *  アクセスが無ければ実行されない
 */
function vktas_auto_job() {
	
	/**
	 * イベント時間が保存されていない または
	 * 保存した時間との差が3600秒(1日)より間隔が空いていたら実行
	 */
	$options    = vktas_get_options();
	$event_date = $options['event_date'];
	$now_date   = date("Y/m/d H:i:s");
	$diff       = strtotime($now_date) - strtotime($event_date);
	if ( empty( $event_date ) || $diff > 3600 ) {
		
		$options = array();
		/**
		 * テーマをアップデートする
		 * https://developer.wordpress.org/cli/commands/theme/update/
		 */
		exec( 'wp theme update --all', $output, $return_var );
	
		/**
		 * テーマアップデートが正常に終わったかどうか
		 */
		if ( $return_var !== 0 ) {
			$theme_update = array(
				'theme_update' => 'false',
			);
		} else {
			$theme_update = array(
				'theme_update' => 'true',
			);
		}
		$options = array_merge( $options, $theme_update );
	
		/**
		 * プラグインをアップデートする
		 * https://developer.wordpress.org/cli/commands/plugin/update/
		 */
		exec( 'wp plugin update --all', $output, $return_var );
	
		/**
		 * プラグインアップデートが正常に終わったかどうか
		 */
		if ( $return_var !== 0 ) {
			$plugin_update = array(
				'plugin_update' => 'false',
			);
		} else {
			$plugin_update = array(
				'plugin_update' => 'true',
			);
		}
		$options = array_merge( $options, $plugin_update );
	
		/**
		 * 復元するコマンド
		 * https://updraftplus.com/wp-cli-updraftplus-documentation/
		 *
		 * e9c9cf068ea5 はnonceでバックアップを作ると自動で作られる識別子 管理画面から確認する
		 * データベースのみ復元する
		 */
		//exec( 'wp updraftplus restore e9c9cf068ea5  --components="db"' , $output, $return_var );
	
		/**
		 * 復元が正常に終わったかどうか
		 */
		if ( $return_var !== 0 ) {
			$updraftplus = array(
				'updraftplus' => 'false',
			);
		} else {
			$updraftplus = array(
				'updraftplus' => 'true',
			);
		}
		$options = array_merge( $options, $updraftplus );
	
		/**
		 * 試用版ユーザーのパスワードを変更する
		 * 試用版ユーザーID '2'を設定
		 */
		$password = wp_generate_password( 12, true );
		wp_set_password( $password, 2 );
	
		$options_password = array(
			'password' => $password,
		);
		$options          = array_merge( $options, $options_password );
	
		/**
		 * 重複実行を避けるため実行された時間を保存する
		 */
		$options_time = array(
			'event_date' => date("Y/m/d H:i:s"),
		);
		$options = array_merge( $options, $options_time );
	
		/**
		 * 自動返信メールで試用ユーザーにパスワードを送るためにDBに保存
		 */
		if ( ! get_option( 'vktas_options' ) ) {
			add_option( 'vktas_options', $options );
		}
		update_option( 'vktas_options', $options );
	
		/**
		 * コマンド実行時に管理者宛にメールを送る
		 */
		$options                = vktas_get_options();
		$vk_theme_update        = $options['theme_update'];
		$vk_plugin_update       = $options['plugin_update'];
		$vk_updraftplus_restore = $options['updraftplus'];
		if ( $vk_theme_update == 'false' ||
			$vk_plugin_update == 'false' ||
			$vk_updraftplus_restore == 'false'
		) {
			$subject = 'Katawaraお試しサイト復元エラー';
		} else {
			$subject = 'Katawaraお試しサイト復元完了';
		}
	
		$to      = get_option( 'admin_email' );
		$message = <<<EOT
		テーマアップデート：$vk_theme_update
		プラグインアップデート：$vk_plugin_update
		復元：$vk_updraftplus_restore
EOT;
		wp_mail( $to, $subject, $message );

	}
}
add_action( 'vktas_auto_cron', 'vktas_auto_job' );

/**
 *  検証用インターバル設定関数
 *  本番は１日おきなのでデフォルトのdailyを使用
 *  vktas_test_intervalの関数は不要
 */
function vktas_test_interval( $schedules ) {
	$schedules['300sec'] = array(
		'interval' => 300,
		'display'  => 'every 300 seconds',
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'vktas_test_interval' );

/**
 * イベントの実行
 * 本番ではstrtotime('2021-05-27 02:00:00')を変更し指定の時刻からスタート
 * 300secをdailyに変える
 */
if ( ! wp_next_scheduled( 'vktas_auto_cron' ) ) {
	date_default_timezone_set( 'Asia/Tokyo' );
	$timestamp  = '2021-06-03 02:00:00';
	// 標準は hourly, twicedaily, daily だが検証用に独自に '300sec' が追加してある
	$recurrence = 'daily';
	$hook       = 'vktas_auto_cron';
	wp_schedule_event( strtotime( $timestamp ), $recurrence, $hook );
}

/**
 * 自動返信でパスワードを送る
 * 参考：https://stackoverflow.com/questions/28857693/wordpress-overwrite-contact-form-mail-2-body
 */
function vktas_wpcf7_post_password( $contact_form ) {
	/**
	 * wp-cronで生成されたパスワード
	 */
	$options  = vktas_get_options();
	$password = $options['password'];

	/**
	 * mailは管理者宛のメール
	 * mail_2は自動返信メール
	 */
	$mail  = $contact_form->prop( 'mail' );
	$mail2 = $contact_form->prop( 'mail_2' );

	/**
	 * メール文章のgenerate_passwordの文字列を現在の設定されているパスワードに変更する
	 */
	$mail['body']  = str_replace( 'generate_password', $password, $mail['body'] );
	$mail2['body'] = str_replace( 'generate_password', $password, $mail2['body'] );

	$contact_form->set_properties( array( 'mail' => $mail ) );
	$contact_form->set_properties( array( 'mail_2' => $mail2 ) );

	return $contact_form;
}
add_action( 'wpcf7_before_send_mail', 'vktas_wpcf7_post_password' );

/**
 * リセット前１時間前くらいからはフォーム申請を停止する
 * https://wpdocs.osdn.jp/%E9%96%A2%E6%95%B0%E3%83%AA%E3%83%95%E3%82%A1%E3%83%AC%E3%83%B3%E3%82%B9/wp_schedule_event
 */
function vktas_filter_wpcf7_acceptance( $true ) {
	$nowdate = date_i18n( 'H' );
	if ( 1 <= $nowdate && $nowdate < 2 ) {
		$true = false;
	} else {
		$true = true;
	}
	return $true;
};
add_filter( 'wpcf7_acceptance', 'vktas_filter_wpcf7_acceptance' );

/**
 * フォームの申請を止めるのでフォーム送信時のメッセージの出力を加工する
 */
function vktas_filter_wpcf7_display_message( $message, $status ) {
	if ( 'mail_sent_ok' == $status ) {
		$message = '申請ありがとうございました。送信頂いたメールアドレスにログイン情報をお送りさせていただいたのでご確認お願いします';
	} else {
		$message = 'フォームの送信に失敗しました。しばらく時間をおいて再度お試しください。';
	}
	return $message;
};
add_filter( 'wpcf7_display_message', 'vktas_filter_wpcf7_display_message', 10, 2 );

/**
 * 管理者用投稿タイプ
 * 試用版ユーザーの申請フォームページのため
 */
function vktas_add_admin_only_post_type_manage() {
	register_post_type(
		'vk_trial',
		array(
			'labels'        => array(
				'name' => '管理者用投稿タイプ',
			),
			'public'        => true,
			'menu_position' => 100,
			'menu_icon'     => 'dashicons-admin-generic',
			'supports'      => array( 'title', 'editor' ),
			'show_in_rest'  => true,
		)
	);
}
add_action( 'init', 'vktas_add_admin_only_post_type_manage' );

/**
 * vktas_optionsを変換
 */
function vktas_get_options() {
	$default = array(
		'theme_update'  => '',
		'plugin_update' => '',
		'updraftplus'   => '',
		'password'      => '',
		'event_date'    => '',
	);
	$options = get_option( 'vktas_options' );
	$options = wp_parse_args( $options, $default );
	return $options;
}