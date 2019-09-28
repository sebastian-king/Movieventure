<?php
require("../template/top.php");
head("Pushbullet Settings", true, true, array("Home" => "/", "My account" => "/me", "Pushbullet Settings" => "/me/pushbullet"));
if (!is_null($userinfo['pushbullet'])) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://api.pushbullet.com/v2/devices?active=true"); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_USERPWD, "{$userinfo['pushbullet']}:");
	$pushbullet = json_decode(curl_exec($ch));
	curl_close($ch);
}
?>
<div id="page-content">
    <div class="row">
    	<div class="col-lg-6">
								<div class="panel">
									<div class="panel-heading">
										<h3 class="panel-title">
											Pushbullet Controls
										</h3>
									</div>
									<div class="panel-body">
                                        <?php
										if (isset($pushbullet)) {
										?>
                                    	<p><button class="btn btn-info" id="pushbullet-test"><i class='fa fa-cloud'></i> Send a test Push</button></p>
                                        <p><button class="btn btn-danger" id="pushbullet-disconnect"><i class='fa fa-plug'></i> Disconnect Pushbullet</button></p>
                                        
										<?php
										} else {
										?>
                                        <p><button class="btn btn-success" id="pushbullet-connect"><img src="/img/pushbullet.png" style="
width: 15px;"> Connect Pushbullet</button></p>
										<?php
										}
										?>
									</div>
									<div class="panel-footer">
                                    	<a class="pushbullet-subscribe-widget" data-channel="movieventure" data-widget="button" data-size="small"></a>
										<script type="text/javascript">(function(){var a=document.createElement('script');a.type='text/javascript';a.async=true;a.src='https://widget.pushbullet.com/embed.js';var b=document.getElementsByTagName('script')[0];b.parentNode.insertBefore(a,b);})();</script></div>
								</div>
        </div>
        <div class="col-lg-6">
									<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
									<div class="panel panel-dark panel-colorful">
										<div class="panel-heading">
											<h3 class="panel-title">Pushbullet Devices</h3>
										</div>
										<div class="pad-ver">
                                    		<p class="pad-lft pad-top">Select which devices you want to push notifications to be sent to.</p>
											<ul class="list-group bg-trans list-todo mar-no">
                                            <?php
											$a = unserialize($userinfo['pushbullets']);
											$i = 0;
											foreach ($pushbullet->devices as $key => $val) {
												$i++;
												?>
												<li class="list-group-item">
													<label class="form-checkbox form-icon form-text" style="width:100%;">
														<input type="checkbox" id="iden-<?php echo $val->iden; ?>" <?php echo ((in_array($val->iden, $a)) ? "checked='checked'" : "") ?>>
														<span><?php echo $val->nickname; ?><span class="label label-default pull-right"><?php echo $val->model; ?></span></span>
													</label>
												</li>
                                                <?php
											}
											if ($i == 0) {
												echo "<div class='pad-all'><em>No devices to display.</em></div>";
											}
											?>
											</ul>
										</div>
									</div>
									<!--~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~-->
		</div>
	</div>
</div>
<?php
footer(false);
?>
<script>
$(document).ready(function() {
	<?php if ($_SESSION['pushbullet_disconnected']) {
		?>
		$.niftyNoty({
			type: "success",
			container: 'page',
			html: "Pushbullet has been successfully disconnected from your account.",
			timer: 0
		})
		<?php
		$_SESSION['pushbullet_disconnected'] = false;
	}
	?>
	$('input[type="checkbox"][id^="iden-"]').change(function() {
		var a = [];
		$('input[type="checkbox"][id^="iden-"]').each(function(index, element) {
			if ($(element).prop("checked")) {
				a.push(element.id.replace("iden-", ""));
			}
		});
		window.websocket.send(JSON.stringify({"switch":"pushbullet_devices","val":a}));
	});
});
$("#pushbullet-connect").click(function() {
	window.location = '/me/pushbullet/connect';
});
$("#pushbullet-disconnect").click(function() {
	window.location = '/me/pushbullet/disconnect';
});
$("#pushbullet-test").click(function() {
	$(this).html("<i class='fa fa-spinner fa-spin'></i>" + $(this).text());
	var a = [];
	$('input[type="checkbox"][id^="iden-"]').each(function(index, element) {
		if ($(element).prop("checked")) {
			a.push(element.id.replace("iden-", ""));
		}
	});
	if (a.length > 0) {
		window.websocket.send(JSON.stringify({"switch":"test_pushbullet","val":a}));
	} else {
		$.niftyNoty({
			type: "danger",
			container: 'page',
			html: "You must select at least one device.",
			timer: 3000
		});
		$("#pushbullet-test").html("<i class='fa fa-cloud'></i>" + $("#pushbullet-test").text());
	}
});
</script>