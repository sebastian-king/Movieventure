<?php
require("../template/top.php");
if (isset($_COOKIE["MOVIEVENTURE_SESSION_ID"]) || isset($_COOKIE['MOVIEVENTURE_SESSION_NAME'])) {
	setcookie("MOVIEVENTURE_SESSION_ID", false, 1, '/', "movieventure.net", true, true);
	setcookie("MOVIEVENTURE_SESSION_NAME", false, 1, '/', "movieventure.net", true, true);
	
	$db->query("DELETE FROM auth_sessions WHERE session_id = '" . $db->real_escape_string($_COOKIE['MOVIEVENTURE_SESSION_ID']) . "' OR session_name = '" . $db->real_escape_string($_COOKIE['MOVIEVENTURE_SESSION_NAME']) . "' LIMIT 1");
}

session_regenerate_id();
session_unset();
session_destroy();



?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Logged out | Movieventure</title>
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
        <center><div class='well well-small' style='width:auto; display:inline-block;'>You have been logged out.</div></center>
		<div class="cls-content">
			<div class="cls-content-sm panel">
				<div class="panel-body">
					<p class="pad-btm">Sign in to your account</p>
					<form action="/auth/login" method="POST">
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
								<input type="checkbox" name="remember-me" value="1" <?php if (@$_POST['remember-me'] || @$_COOKIE['mvr'] == "true") { echo "checked='checked'"; } ?>> Remember me
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