<?php
require("../template/top.php");
require_once __DIR__ . '/../lib/config.php';

$APP_NAME   = config('app.name',   'Movieventure');
$APP_DOMAIN = config('app.domain', 'example.com');

if (isset($_POST['email'])) {
	do {
		if (!isset($_POST["email"]) || !strlen($_POST["email"]) > 0) {
			$error = "You must enter your e-mail address.";
			break;
		}
		$q = mysql_query("SELECT * FROM `users` WHERE `email` = '".mysql_real_escape_string($_POST['email'])."' LIMIT 1");
		if (!mysql_num_rows($q) > 0) {
			$error = "The e-mail address you entered is not in our database.";
			break;
		} else {
			$r = mysql_fetch_array($q);
			$iid = uniqid(md5(rand()), true);
			$time = time();
			date_default_timezone_set("UTC");

			$timeInD = date('l jS \of F Y h:i:s A T', $time);
			$ip = $_SERVER['REMOTE_ADDR'];
			$host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
			mysql_query("DELETE FROM lostpass WHERE user = '{$r['username']}'");
			mysql_query("INSERT INTO lostpass (iid, timereset, ip, hostname, user, email) VALUES('$iid','$time','$ip','$host','{$r['username']}','{$r['email']}')") or die(mysql_error());
			$headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			$headers .= "From: $APP_NAME <no-reply@$APP_DOMAIN>" . "\r\n" .
			"Reply-To: no-reply@$APP_DOMAIN" . "\r\n" .
			'X-Mailer: PHP/' . phpversion();

			$reset_link = "https://www.$APP_DOMAIN/auth/reset-password?token=$iid";
			$body = "<html>Hello {$r['username']}, <br><br>Your password was requested to be reset from $host ($ip)<br>on {$timeInD},<br>here is your reset link:<br><a href=\"$reset_link\">$reset_link</a><br><br>Best regards,<br>$APP_NAME</body></html>";

			if (email($r['email'], "$APP_NAME password reset", $body)) {
				$success = "Recovery instructions have been sent to your e-mail address.";
			} else {
				$success = "Unable to send e-mail, please contact support.";
			}
		}
	} while (false);
}
?><!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Forgot password | Movieventure</title>
 	<link href="//fonts.googleapis.com/css?family=Open+Sans:300,400,600,700&amp;subset=latin" rel="stylesheet">
	<link href="/auth/css/bootstrap.min.css" rel="stylesheet">
	<link href="/auth/css/nifty.min.css" rel="stylesheet">
	<link href="/auth/css/font-awesome.min.css" rel="stylesheet">
	<link href="/auth/css/pace.min.css" rel="stylesheet">
	<script src="/auth/js/pace.min.js"></script>
</head>
<body>
	<div id="container" class="cls-container">
		<div class="cls-header cls-header-lg">
			<div class="cls-brand">
				<a class="box-inline" href="/">
					<span class="brand-title">Movie<span class="text-thin">venture</span></span>
				</a>
			</div>
		</div>
        <?php
        if (@$error || @$success) {
			echo "<center><div class='well well-small' style='width:auto; display:inline-block;'>".@$error.@$success."</div></center>";
		}
		?>
		<div class="cls-content">
			<div class="cls-content-sm panel">
				<div class="panel-body">
					<p class="pad-btm">Enter your email address to recover your password. </p>
					<form action="" method="POST">
						<div class="form-group">
							<div class="input-group">
								<div class="input-group-addon"><i class="fa fa-envelope"></i></div>
								<input type="email" class="form-control" placeholder="Email" name="email">
							</div>
						</div>
						<div class="form-group text-right">
							<button class="btn btn-primary text-uppercase" type="submit">Reset Password</button>
						</div>
					</form>
				</div>
			</div>
			<div class="pad-ver">
				<a href="login" class="btn-link mar-rgt">Back to Login</a>
			</div>
		</div>
	</div>
	<script src="/auth/js/jquery-2.1.1.min.js"></script>
	<script src="/auth/js/bootstrap.min.js"></script>
	<script src="/auth/js/fastclick.min.js"></script>
	<script src="/auth/js/nifty.min.js"></script>
</body>
</html>