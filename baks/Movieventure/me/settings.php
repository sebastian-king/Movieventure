<?php
require("../template/top.php");
$head = head("Settings", true, true, array("Home" => "/", "My account" => "/me/", "Settings" => "/me/settings/"), "effect mainnav-lg", true);
if (isset($_POST['submit'])) {
		do {
			// family filter, timezone
			if (!isset($_POST['family_filter'])) {
				$_POST['family_filter'] = 0;
			} else {
				$_POST['family_filter'] = 1;
			}
			if (!array_key_exists($_POST['timezone'], $timezones)) {
				$error = "Please choose a valid timezone.";
				break;
			} else {
				$db->query("UPDATE users SET timezone = '" . $db->real_escape_string($timezones[$_POST['timezone']]) . "', family_filter = '" . $db->real_escape_string($_POST['family_filter']) . "' WHERE id = '{$userinfo['id']}' LIMIT 1") or die($db->error);
				$userinfo['timezone'] = $timezones[$_POST['timezone']];
				$userinfo['family_filter'] = $_POST['family_filter'];
				$success = "Your account has been successfully updated.";
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
	<div class="row">
        <div class="col-lg-12">
            <div class="panel">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        Your settings
                    </h3>
                </div>
				<form class="form-horizontal" method="POST">
					<div class="panel-body">
						<p class="text-thin mar-btm">Family filter</p>
						<div class="box-inline mar-rgt">
							<input id="family-filter-checkbox-toggle" type="checkbox" name="family_filter" <?php if ($userinfo['family_filter'] == 1) { echo "checked"; } ?> value="1">
						</div>
						<hr>
						<p class="text-thin mar-btm">Desktop notifications <em>(Chrome/Firefox/Opera/Safari)</em></p>
						<div class="box-inline mar-rgt">
							<input id="webkit-notifications-checkbox-toggle" type="checkbox" name="notifications">
						</div>
						<hr>
						<p class="text-thin mar-btm">Timezone</p>
						<div class="box-inline mar-rgt">
							<select data-placeholder="Choose a Country..." id="choose-timezone" tabindex="2" name="timezone">
							<?php
							foreach ($timezones as $key => $val) {
								if ($userinfo['timezone'] == $val) {
									echo "<option selected='selected' value='$key'>$val</option>";
								} else {
									echo "<option value='$key'>$val</option>";
								}
							}
							?>
							</select>
						</div>
						<hr>
						<p><?php if (is_null($userinfo['pushbullet'])) { ?>
						<p><a class="btn btn-success" href="/me/pushbullet/connect"><img src="/img/pushbullet.png" style="width: 15px;"> Connect Pushbullet</a></p>
						<?php } else { ?>
						<p><a class="btn btn-success" href="/me/pushbullet"><img src="/img/pushbullet.png" style="width: 15px;"> Manage Pushbullet</a></p>
						<?php } ?></p>
						<hr> 
						<a class="btn btn-default" href="/me/change-password">Change password</a>
					</div>
					<div class="panel-footer">
						<div class="form-actions">
							<button type="submit" class="btn btn-primary" name="submit">Save changes</button>
						</div>
					</div>
				</form>
            </div>
        </div>
	</div>
</div>
<script>
docReady	(function() {
	$('#choose-timezone').chosen();
    new Switchery(document.getElementById("family-filter-checkbox-toggle"), {color:'#35b9e7'});
	document.getElementById("family-filter-checkbox-toggle").onchange = function() {
		//alert(document.getElementById("family-filter-checkbox-toggle").checked);
	};
	if (Notification.permission !== "granted" || localStorage.getItem("NotificationPermission") == "false") {
		$("#webkit-notifications-checkbox-toggle").prop("checked", false);
	} else {
		$("#webkit-notifications-checkbox-toggle").prop("checked", true);
	}
	new Switchery(document.getElementById("webkit-notifications-checkbox-toggle"), {color:'#35b9e7'});
	document.getElementById("webkit-notifications-checkbox-toggle").onchange = function() {
		if (document.getElementById("webkit-notifications-checkbox-toggle").checked == true) {
			if (!Notification) {
				alert('Desktop notifications not available in your browser. Try Chrome/Chromium.'); 
				return;
			} else {
				Notification.requestPermission();
				localStorage.setItem("NotificationPermission", "true");
			}
		} else {
			localStorage.setItem("NotificationPermission", "false");
		}
	};
});
</script>
<?php
footer();
?>