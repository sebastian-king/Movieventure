<?php
require("../template/top.php");
$head = head("Change Password", true, true, array("Home" => "/", "My account" => "/me/", "Change Password" => "/me/change-password"), "effect mainnav-lg", true);
if (isset($_POST['password'])) {
	do {
			$q = $db->query("SELECT password FROM users WHERE id = '{$userinfo['id']}' LIMIT 1") or die($db->error);
			$r = $q->fetch_array(MYSQLI_ASSOC);
			if (password_verify($_POST['password'], $r['password'])) {
				if ($_POST['new_password'] != $_POST['new_password_confirmation']) {
					$error = "The new passwords did not match, please try again.";
					break;
				}
				if (strlen($_POST['new_password']) == 0) {
					$error = "You must enter a new password.";
					break;
				}
				if (strlen($_POST['new_password']) < 6) {
					$error = "Your password must be atleast 6 characters.";
					break;
				}
				$password = password_hash("{$_POST['new_password']}", PASSWORD_BCRYPT, array('cost' => 12));
				$db->query("UPDATE users SET password = '".$db->real_escape_string($password)."' WHERE id = '{$userinfo['id']}' LIMIT 1") or die($db->error);
				$success = "Your password has been successfully updated.";
				unset($_POST);
				break;
			} else { 
				$error = "The current password you entered was incorrect, please try again.";
				break;
			}
	} while (false);
}
echo $head;
?>
				<div id="page-content">
                <?php
					if (isset($success)) {
						?>
                        <div class="alert alert-success fade in">
                            <button class="close" data-dismiss="alert"></button>
                            <strong>Success!</strong> <?php echo $success; ?>
                        </div>
                        <?php
					}
					if (isset($error)) {
						?>
                        <div class="alert alert-danger fade in">
                            <button class="close" data-dismiss="alert"></button>
                            <strong>Error!</strong> <?php echo $error; ?>
                        </div>
                        <?php
					}
				?>
					<div class="col-lg-12">
                        <div class="panel">
                            <div class="panel-heading">
                                <h3 class="panel-title">Change Password</h3>
                            </div>
                            <form class="form-horizontal" method="POST" action="">
                                <div class="panel-body">
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">Current password</label>
                                        <div class="col-sm-9">
                                            <input type="password" placeholder="Enter current password" name="password" class="form-control">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">New password</label>
                                        <div class="col-sm-9">
                                            <input type="password" placeholder="Enter your new password" name="new_password" class="form-control">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">Confirm new password</label>
                                        <div class="col-sm-9">
                                            <input type="password" placeholder="Confirm your new password" name="new_password_confirmation" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="panel-footer text-right">
                                    <button class="btn btn-info" type="submit">Change password</button>
                                </div>
                            </form>
						</div>
					</div>
				</div>
<?php
footer();
?>