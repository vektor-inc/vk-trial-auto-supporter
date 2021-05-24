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
 * 毎日一度行うコマンド類
 */
function vk_wp_cli() {
  //テーマをアップデートする
  // exec( 'wp theme update --all' );

  //プラグインをアップデートする
  //exec( 'wp plugin update --all' );

  // プラグインやテーマのアップデートも復元されてしまうので、データベースのみ復元する
	// exec('wp updraftplus restore 03f928d85c1c --components="db"');

  //仕様版ユーザーのパスワードを変更する 仕様版ユーザーID '2'を設定
  // $password = wp_generate_password( 12, true );
  // wp_set_password( $password, 2 );
}
add_action( 'vk_trial_cli', 'vk_wp_cli' );


/**
 * フォーム送信時にユーザー登録をおこなう
 */
function wpcf7_insert_user($contact_form, &$abort, $submission) {
	//送信情報を取得
	$submission = WPCF7_Submission::get_instance();
	if($submission) {
    //送信された情報を取得
		$formdata = $submission->get_posted_data();
    //パスワードを自動生成する
    $password = wp_generate_password( 12, true );
    
    //ユーザーデータをセット
		$userdata = array(
		    'user_login'    => $formdata['your-login'], //ユーザー名
		    'user_email'    => $formdata['your-email'], //メールアドレス
		    'user_pass'     => $password, //パスワード
        'role'          => 'trial_user' //ユーザー権限
		);
		//ユーザーデータ登録
		$user_id = wp_insert_user($userdata);
    /**
     * ユーザー登録に成功した場合
     * $abort = false;
     * 参考：https://wordpress.org/support/topic/what-is-the-right-way-of-stopping-cf7-from-posting-using-wpcf7_before_send_mail/
     */
    if ( ! is_wp_error( $user_id ) ) {
      $abort = false;
      /**
       * 自動返信メールで生成されたパスワードを渡す
       */
      // 管理者宛メール
      $mail = $contact_form->prop( 'mail' ); 
      // 自動返信メール
      $mail2 = $contact_form->prop( 'mail_2' ); 

      // パスワードの文字列を変更する
      $mail['body'] = str_replace("generate_password", $password, $mail['body']);
      $mail2['body'] = str_replace("generate_password", $password, $mail2['body']);

      // set mail property with changed value(s)
      $contact_form->set_properties( array( 'mail' => $mail ) );
      $contact_form->set_properties( array( 'mail_2' => $mail2 ) );

    } else {
      $abort = true;
    }
    return $contact_form;
	}
}
add_action('wpcf7_before_send_mail', 'wpcf7_insert_user',10, 3);


/****
 * フォーム送信時のメッセージの出力を加工する
 * 参考：https://stackoverflow.com/questions/64694718/how-to-dynamically-change-contact-form-7-submission-display-message
 */
function filter_wpcf7_display_message( $message, $status ) { 
  if ('mail_sent_ok' == $status) {
    $message = "申請ありがとうございました。送信頂いたメールアドレスにログイン情報をお送りさせていただいたのでご確認お願いします";
  } else {
    $message = "フォームの送信に失敗しました。";
  }
  return $message; 
}; 
add_filter( 'wpcf7_display_message', 'filter_wpcf7_display_message', 10, 2 ); 
