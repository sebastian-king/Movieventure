<?php
require("../../template/top.php");

if ($auth = auth()) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://api.pushbullet.com/oauth2/token"); 
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$payload = json_encode( array( 'client_id' => 'RTYgx0ydJuuKksliMretq5fbsrZ8WcTa', 'client_secret' => 'zPrnSEjYj64yyIj5L34lB5Zy9uk1zVea', 'code' => $_GET['code'], 'grant_type' => 'authorization_code' ) );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
	$response = json_decode(curl_exec($ch));
	curl_close($ch);

	$userinfo = $auth[0];
	var_dump($userinfo);
	
	if (isset($response->access_token)) {
		$db->query("UPDATE users SET pushbullet = '" . $db->real_escape_string($response->access_token) . "' WHERE id = '{$userinfo['id']}' LIMIT 1") or die($db->error);
		
		die(header("Location: /me/pushbullet"));
	}
}
?>
An error occurred. Please contact support.