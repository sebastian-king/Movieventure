<?php
require("../template/top.php");
include("$base/template/functions/hash.php");
if (count($_POST) > 0) {
	require_once("$base/template/functions/IP2Location.php");
	do {
		// first_name, last_name, username, email, password, password_confirmation, token
		if (empty($_POST['first_name'])) {
			$error = "You must enter a first name.";
			break;
		} else if (empty($_POST['last_name'])) {
			$error = "You must enter a last name.";
			break;
		} else if (empty($_POST['username'])) {
			$error = "You must enter a username.";
			break;
		} else if (empty($_POST['email'])) {
			$error = "You must enter an email.";
			break;
		} else if (empty($_POST['password'])) {
			$error = "You must enter a password.";
			break;
		} else if (empty($_POST['password_confirmation'])) {
			$error = "You must enter both passwords.";
			break;
		} else if (empty($_POST['token'])) {
			$error = "You need a token in order to register.";
			break;
		} else if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
			$error = "The e-mail address that you entered is not valid.";
			break;
		} else if ($_POST['password'] != $_POST['password_confirmation']) {
			$error = "The passwords you entered do match.";
			break;
		} else {
			$q = $db->query("SELECT * FROM invitation_tokens WHERE `token` = '".$db->real_escape_string($_POST['token'])."' AND used != 1 AND expires > NOW()");
			if (!$q->num_rows > 0) {
				$error = "The token you entered was invalid/expired.";
				break;
			}
			$r = $q->fetch_array(MYSQLI_ASSOC);
			if ($r['email'] != $_POST['email']) {
				$error = "Please register using the e-mail address that was invited.";
				break;
			}
			$q = $db->query("SELECT * FROM users WHERE `username` = '".$db->real_escape_string($_POST['username'])."'");
			if ($q->num_rows > 0) {
				$error = "The username that you entered is already taken.";
				break;
			}
			$q = $db->query("SELECT * FROM users WHERE `email` = '".$db->real_escape_string($_POST['email'])."'");
			if ($q->num_rows > 0) {
				$error = "The email address you entered is already taken.";
				break;
			}
			if (!preg_match('/^[a-z0-9]{3,16}$/i', $_POST['username'])) {
				$error = "Your username must be alphanumerical and between 3 to 16 characters.";
				break;
			}
			$password = password_hash("{$_POST['password']}", PASSWORD_BCRYPT, array('cost' => 12));
			$ip = $_SERVER['REMOTE_ADDR'];
			
			$ipdb = FALSE;
			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$ipdb = new \IP2Location\Database("$base/ip2location/IP2LOCATION-LITE-DB11.BIN", \IP2Location\Database::FILE_IO);
			} else if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				$ipdb = new \IP2Location\Database("$base/ip2location/IP2LOCATION-LITE-DB11.IPV6.BIN", \IP2Location\Database::FILE_IO);
			}
			if ($ipdb == FALSE) {
				$timezone = "UTC";
			} else {
				$ipinfo = $ipdb->lookup($_SERVER['REMOTE_ADDR'], \IP2Location\Database::ALL);
				$tzdb = file_get_contents("http://api.timezonedb.com?key=" . TIMEZONEDB_API_KEY . "&lat={$ipinfo['latitude']}&lng={$ipinfo['longitude']}&format=json");
				$timezone = json_decode($tzdb);
				$timezone = $timezone->zoneName;
				if (!in_array($timezone, timezone_identifiers_list())) {
					$timezone = "UTC";
				}
			}
			
			$fingerprint = md5($_SERVER['HTTP_USER_AGENT']);

			$q = $db->query("INSERT INTO `users`
							(`username`,
							`first_name`,
							`last_name`,
							`password`,
							`token_used`,
							`email`,
							`reg_time`,
							`reg_ip`,
							`passkey`,
							`timezone`)
							VALUES
							(
							'".$db->real_escape_string($_POST['username'])."',
							'".$db->real_escape_string($_POST['first_name'])."',
							'".$db->real_escape_string($_POST['last_name'])."',
							'".$db->real_escape_string($password)."',
							'".$db->real_escape_string($_POST['token'])."',
							'".$db->real_escape_string($_POST['email'])."',
							'".$db->real_escape_string(time())."',
							'".$db->real_escape_string($ip)."',
							HEX(AES_ENCRYPT('" . uniqid() . "','" . $db->real_escape_string($_POST['username']) . "." . $db->real_escape_string($fingerprint)."')),
							'$timezone'
							);
				") or die($db->error);
			
				$db->query("UPDATE invitation_tokens SET used = 1 WHERE token = '".$db->real_escape_string($_POST['token'])."'") or die($db->error);
				
				$auth_session_id = obfuscate_hash(sha1($fingerprint . session_id())); // based on IP, time, /dev/urandom and a PHP PRNG (PLCG) and fingerprint calculated above
				session_regenerate_id();
				$auth_session_name = obfuscate_hash(bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM))); // just really random
				
				$db->query("INSERT INTO auth_sessions
				(session_id,
				session_name,
				fingerprint,
				uid,
				expires)
				VALUES
				('".$db->real_escape_string($auth_session_id)."',
				'".$db->real_escape_string($auth_session_name)."',
				'".$db->real_escape_string($fingerprint)."',
				'".$db->real_escape_string($db->insert_id)."',
				'".$db->real_escape_string(0)."')
				") or die($db->error); // remove this for security
				
				setcookie("MOVIEVENTURE_SESSION_ID", $auth_session_id, 0, '/', "example.com", true, true);
				setcookie("MOVIEVENTURE_SESSION_NAME", $auth_session_name, 0, '/', "example.com", true, true);
			
				header("Location: /");
				die();
		}
	} while (false);
}
?><!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Register | Movieventure</title>
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
			<div class="cls-content-lg panel">
				<div class="panel-body">
					<p class="pad-btm">Create an account</p>
					<form action="" method="POST">
						<div class="row">
							<div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<div class="input-group-addon"><i class="fa fa-male"></i></div>
										<input type="text" class="form-control" placeholder="First name" name="first_name" value="<?php echo @$_POST['first_name']; ?>">
									</div>
								</div>
                            </div>
                            <div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<div class="input-group-addon"><i class="fa fa-male"></i></div>
										<input type="text" class="form-control" placeholder="Last name" name="last_name" value="<?php echo @$_POST['last_name']; ?>">
									</div>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<div class="input-group-addon"><i class="fa fa-user"></i></div>
										<input type="text" class="form-control" placeholder="Username" name="username" value="<?php echo @$_POST['username']; ?>">
									</div>
								</div>
                            </div>
                            <div class="col-sm-6">
                            	<div class="form-group">
									<div class="input-group">
										<div class="input-group-addon"><i class="fa fa-envelope"></i></div>
										<input type="text" class="form-control" placeholder="E-mail" name="email" value="<?php echo @$_POST['email']; ?>">
									</div>
								</div>
							</div>
							<div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<div class="input-group-addon"><i class="fa fa-asterisk"></i></div>
										<input type="password" class="form-control" placeholder="Password" name="password" value="<?php echo @$_POST['password']; ?>">
									</div>
								</div>
							</div>
                            <div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<div class="input-group-addon"><i class="fa fa-asterisk"></i></div>
										<input type="password" class="form-control" placeholder="Password confirmation" name="password_confirmation" value="<?php echo @$_POST['password_confirmation']; ?>">
									</div>
								</div>
							</div>
                            <div class="col-sm-12">
								<div class="form-group">
									<div class="input-group">
										<div class="input-group-addon"><i class="fa fa-ticket"></i></div>
										<input type="text" class="form-control" placeholder="Token" name="token" value="<?php echo ((@$_POST['token']) ? $_POST['token'] : rawurldecode($_GET['token'])); ?>">
									</div>
								</div>
							</div>
						</div>
						<div class="row">
                            <div class="form-group text-right">
                                <button class="btn btn-primary text-uppercase" type="submit">Sign Up</button>
                            </div>
						</div>
					</form>
				</div>
			</div>
			<div class="pad-ver">
				Already have an account? <a href="/auth/login" class="btn-link mar-rgt">Sign in</a>
			</div>
		</div>
	</div>
	<script src="/auth/js/jquery-2.1.1.min.js"></script>
	<script src="/auth/js/bootstrap.min.js"></script>
	<script src="/auth/js/fastclick.min.js"></script>
	<script src="/auth/js/nifty.min.js"></script>
</body>
</html>
