<?php
require("../template/top.php");
require_once __DIR__ . '/../lib/config.php';
include("$base/template/functions/hash.php");
if (isset($_POST['username'])) {
	do {
		$required = array("username", "password");
		foreach ($required as $key => $val) {
			if (!isset($_POST[$val]) || !strlen($_POST[$val]) > 0) {
				$error = "You must define a $val.";
				break 2;
			}
		}
		$q = $db->query("SELECT * FROM `users` WHERE username = '" . $db->real_escape_string($_POST['username']) . "' LIMIT 1");
		if (!$q->num_rows > 0) {
			$error = "Incorrect username or password.";
			break;
		} else {
			$r = $q->fetch_array(MYSQLI_ASSOC);
		}
		if (!password_verify($_POST['password'], $r['password'])) {
			$error = "Incorrect username or password.";
			break;
		} else {
			if (@$_POST['remember-me'] == 1) {
				$expires = strtotime("+10 years"); // almost never?
			} else {
				$expires = 0;
			}
			$fingerprint = md5($_SERVER['HTTP_USER_AGENT']);
			$auth_session_id = obfuscate_hash(sha1($fingerprint . session_id())); // based on IP, time, /dev/urandom and a PHP PRNG (PLCG) and fingerprint calculated above
			session_regenerate_id();
			// possibly an odd solution but it works, the hashes are random and hard to read and should be relatively unique per device
			$auth_session_name = obfuscate_hash(bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM))); // just really random
			$db->query("INSERT INTO auth_sessions
			(session_id,
			session_name,
			fingerprint,
			uid,
			expires,
			session)
			VALUES
			('" . $db->real_escape_string($auth_session_id) . "',
			'" . $db->real_escape_string($auth_session_name) . "',
			'" . $db->real_escape_string($fingerprint) . "',
			'" . $db->real_escape_string($r['id']) . "',
			'" . $db->real_escape_string($expires) . "',
			'" . $db->real_escape_string(intval(!@$_POST['remember-me'])) . "')
			") or die($db->error); // remove this for security

			setcookie("MOVIEVENTURE_SESSION_ID", $auth_session_id, $expires, '/', config('app.domain', 'example.com'), true, true);
			setcookie("MOVIEVENTURE_SESSION_NAME", $auth_session_name, $expires, '/', config('app.domain', 'example.com'), true, true);
			setcookie("mvr", $r['username'], time() * 2, '/auth/');

			if (isset($_GET['returnto'])) {
				header("Location: //{$_SERVER['SERVER_NAME']}/" . preg_replace("/^\//i", "", $_GET['returnto']));
			} else {
				header("Location: /");
			}
			die();
		}
	} while (false);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Sign in | Movieventure</title>
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
		<?php if (@$error) {
			echo "<center><div class='well well-small' style='width:auto; display:inline-block;'>$error</div></center>";
		} ?>
		<div class="cls-content">
			<div class="cls-content-sm panel">
				<div class="panel-body">
					<p class="pad-btm">Sign in to your account</p>
					<form action="" method="POST">
						<div class="form-group">
							<div class="input-group">
								<div class="input-group-addon"><i class="fa fa-user"></i></div>
								<input type="text" class="form-control" placeholder="Username" name="username" value="<?php echo @$_POST['username']; ?>">
							</div>
						</div>
						<div class="form-group">
							<div class="input-group">
								<div class="input-group-addon"><i class="fa fa-asterisk"></i></div>
								<input type="password" class="form-control" placeholder="Password" name="password" value="<?php echo @$_POST['password']; ?>">
							</div>
						</div>
						<div class="row">
							<div class="col-xs-8 text-left checkbox">
								<label class="form-checkbox form-icon">
									<input type="checkbox" name="remember-me" value="1" <?php if (@$_POST['remember-me'] || @$_COOKIE['mvr'] == "true") {
																							echo "checked='checked'";
																						} ?>> Remember me
								</label>
							</div>
							<div class="col-xs-4">
								<div class="form-group text-right">
									<button class="btn btn-primary text-uppercase" type="submit">Sign In</button>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
			<div class="pad-ver">
				<a href="forgot-password" class="btn-link mar-rgt">Forgot password?</a>
			</div>
		</div>
	</div>
	<script src="/auth/js/jquery-2.1.1.min.js"></script>
	<script src="/auth/js/bootstrap.min.js"></script>
	<script src="/auth/js/fastclick.min.js"></script>
	<script src="/auth/js/nifty.min.js"></script>
</body>

</html>