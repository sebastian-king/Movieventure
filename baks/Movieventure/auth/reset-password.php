<?php
require("../template/top.php");
if (isset($_POST['password'])) {
	do {
		if (isset($_GET['token']) && strlen($_GET['token']) > 0) {
			$q = $db->query("SELECT * FROM forgot_password WHERE reset_token = '" . $db->real_escape_string($_GET['token']) . "' AND expires > UNIX_TIMESTAMP() LIMIT 1");
			if ($q->num_rows == 1) {
				$r = $q->fetch_array();
				if (strlen($_POST['password']) < 1 || strlen($_POST['password_confirmation']) < 1) {
					$error = "You must enter both passwords.";
					break;
				} else if ($_POST['password'] != $_POST['password_confirmation']) {
					$error = "The passwords that you entered do not match";
					break;
				} else {
					$password = password_hash("{$_POST['password']}", PASSWORD_BCRYPT, array('cost' => 12));
					$db->query("UPDATE users SET password = '" . $db->real_escape_string($password) . "' WHERE email = '" . $db->real_escape_string($r['email']) . "' LIMIT 1");
					$db->query("DELETE FROM forgot_password WHERE uid = '" . $db->real_escape_string($r['uid']) . "' LIMIT 1");
					$success = "Password successfully updated, you may now <a href='/auth/login' style='text-decoration:underline;'>log in here</a>.";
				}
			} else {
				$error = "Invalid token, please use <a href='/auth/forgot-password' style='text-decoration:underline;'>this page</a> again to obtain a new token.";
			}
		}
	} while (false);
}
?><!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Reset your password | Movieventure</title>
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
					<p class="pad-btm">Enter a new password</p>
					<form action="" method="POST">
						<div class="form-group">
							<div class="input-group">
								<div class="input-group-addon"><i class="fa fa-asterisk"></i></div>
								<input type="password" class="form-control" placeholder="Password" name="password" value="<?php echo @$_POST['password']; ?>">
							</div>
						</div>
						<div class="form-group">
							<div class="input-group">
								<div class="input-group-addon"><i class="fa fa-asterisk"></i></div>
								<input type="password" class="form-control" placeholder="Password confirmation" name="password_confirmation" value="<?php echo @$_POST['password_confirmation']; ?>">
							</div>
						</div>
						<div class="row">
                            <div class="form-group text-right">
                            <button class="btn btn-primary text-uppercase" type="submit">Set new password</button>
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
