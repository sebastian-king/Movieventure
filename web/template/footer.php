<!--===================================================-->
<!--End page content-->


</div>
<!--===================================================-->
<!--END CONTENT CONTAINER-->



<!--MAIN NAVIGATION-->
<!--===================================================-->
<nav id="mainnav-container">
	<div id="mainnav">

		<!--Menu-->
		<!--================================-->
		<div id="mainnav-menu-wrap">
			<div class="nano">
				<div class="nano-content">
					<ul id="mainnav-menu" class="list-group">

						<!--Category name-->
						<li class="list-header">Navigation</li>

						<!--Menu list item-->
						<li>
							<a href="/">
								<i class="fa fa-home"></i>
								<span class="menu-title">
									<strong>Home</strong>
								</span>
							</a>
						</li>
						<li>
							<a href="/statistics">
								<i class="fa fa-bar-chart"></i>
								<span class="menu-title">
									<strong>Statistics</strong>
								</span>
							</a>
						</li>

						<li class="list-divider"></li>

						<!--Category name-->
						<li class="list-header">Movies</li>
						<li>
							<a href="/movie/browse">
								<i class="fa fa-film"></i>
								<span class="menu-title">Browse</span>
							</a>
						</li>
						<li>
							<a href="/movie/add">
								<i class="fa fa-plus"></i>
								<span class="menu-title">Add a movie</span>
							</a>
						</li>
						<li>
							<a href="/movie/random">
								<i class="fa fa-random"></i>
								<span class="menu-title">Random</span>
							</a>
						</li>

						<li class="list-divider"></li>

						<!--Category name-->
						<li class="list-header">Television</li>
						<li>
							<a href="/tv/browse">
								<i class="fa fa-television"></i>
								<span class="menu-title">Browse</span>
							</a>
						</li>
						<li>
							<a href="/tv/add">
								<i class="fa fa-plus"></i>
								<span class="menu-title">Add a TV show</span>
							</a>
						</li>
						<li>
							<a href="/tv/random">
								<i class="fa fa-random"></i>
								<span class="menu-title">Random</span>
							</a>
						</li>
					</ul>


					<!--Widget-->
					<!--================================-->
					<div class="mainnav-widget">

						<!-- Show the button on collapsed navigation -->
						<div class="show-small">
							<a href="#" data-toggle="menu-widget" data-target="#demo-wg-server">
								<i class="fa fa-desktop"></i>
							</a>
						</div>

						<!-- Hide the content on collapsed navigation -->
						<div id="demo-wg-server" class="hide-small mainnav-widget-content">
							<ul class="list-group">
								<li class="list-header pad-no pad-ver">Server Status</li>
								<li class="mar-btm">
									<span id="cpu_usage_val" class="label label-primary pull-right">0%</span>
									<p>CPU Usage</p>
									<div class="progress progress-sm">
										<div id="cpu_usage_bar" class="progress-bar progress-bar-primary" style="width: 0%;">
											<span class="sr-only">0%</span>
										</div>
									</div>
								</li>
								<li class="mar-btm">
									<span id="bandwidth_usage_val" class="label label-purple pull-right">0%</span>
									<p>Bandwidth</p>
									<div class="progress progress-sm">
										<div id="bandwidth_usage_bar" class="progress-bar progress-bar-purple" style="width: 0%;">
											<span class="sr-only">0%</span>
										</div>
									</div>
								</li>
							</ul>
						</div>
					</div>
					<!--================================-->
					<!--End widget-->

				</div>
			</div>
		</div>
		<!--================================-->
		<!--End menu-->

	</div>
</nav>
<!--===================================================-->
<!--END MAIN NAVIGATION-->

</div>



<!-- FOOTER -->
<!--===================================================-->
<footer id="footer">



	<!-- ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ -->
	<!-- Remove the class name "show-fixed" and "hide-fixed" to make the content always appears. -->
	<!-- ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ -->

	<p class="pad-lft">Movieventure 2014 - <?php echo date("Y"); ?></p>



</footer>
<!--===================================================-->
<!-- END FOOTER -->


<!-- SCROLL TOP BUTTON -->
<!--===================================================-->
<button id="scroll-top" class="btn"><i class="fa fa-chevron-up"></i></button>
<!--===================================================-->



</div>
<!--===================================================-->
<!-- END OF CONTAINER -->


<!--JAVASCRIPT-->
<!--=================================================-->

<!--jQuery [ REQUIRED ]-->
<script src="/js/jquery-2.1.1.min.js"></script>


<!--BootstrapJS [ RECOMMENDED ]-->
<script src="/js/bootstrap.min.js"></script>


<!--Fast Click [ OPTIONAL ]-->
<script src="/assets/fast-click/fastclick.min.js"></script>


<!--Nifty Admin [ RECOMMENDED ]-->
<script src="/js/nifty.min.js"></script>


<!--Switchery [ OPTIONAL ]-->
<script src="/assets/switchery/switchery.min.js"></script>


<!--Bootstrap Select [ OPTIONAL ]-->
<script src="/assets/bootstrap-select/bootstrap-select.min.js"></script>

<script src="/assets/switchery/switchery.min.js"></script>
<script src="/assets/chosen/chosen.jquery.min.js"></script>

<!-- websocket -->

<script src="/js/ReconnectingWebsocket.js"></script>
<script language="javascript" type="text/javascript">
	$(document).ready(function() {

		$(document).keypress(function(e) {
			if ((e.which == 102 || e.which == 70) && e.shiftKey && !$(":focus").is('[contenteditable="true"]') && !$(":focus").is("input[type!='radio'][type!='checkbox'][type!='date']:not(:disabled):not([readonly]), textarea:text:not(:disabled):not([readonly])")) {
				$("#search-box").focus();
				e.preventDefault();
			}
		});

		window.websocket = new ReconnectingWebSocket("wss://" + window.location.hostname + ":8888", "notifications");

		window.websocket_initialised = false;

		websocket.onopen = function(evt) {
			console.log("WebSocket: CONNECTED");
			clearInterval(window.overlay_counter);
			$(".overlay").fadeOut(function() {
				if (window.websocket_initialised == false) {
					window.websocket_initialised = true;
					$('.overlay').html('<div class="text">\
		<div class="a-17"><b>R</b><b>E</b><b>C</b><b>O</b><b>N</b><b>N</b><b>E</b><b>C</b><b>T</b><b>I</b><b>N</b><b>G</b></div>\
		<h3>Your browser has lost connection to our server.</h3>\
		<p>We are attempting to automatically reconnect... next reconnection attempt in: <span class="timeout">10</span><span>s</span>.</p>\
		<p>You may also try refreshing the page by <a href="javascript:history.go(0);">clicking here</a>.</p>\
		<div class="delay">\
			<hr>\
			<p style="font-style: italic;">If this takes more than a minute or two, you may check the status of our services <a href="https://status.<?php echo COOKIE_ROOT_DOMAIN; ?>/" target="_blank">here</a>.</p>\
			<p style="font-style: italic;">Or please e-mail us at <a href="mailto:help@<?php echo COOKIE_ROOT_DOMAIN; ?>">help@<?php echo COOKIE_ROOT_DOMAIN; ?></a> for support.</p>\
		</div>\
	</div>').css({
						'background-image': 'none',
						'background-color': 'rgba(18,56,88,0.75)'
					});
				}
			}).removeClass("active");
		};

		websocket.onclose = function(evt) {
			//console.log("Close", evt);
			console.log("WebSocket: DISCONNECTED");
			$(".overlay").fadeIn().addClass("active");

			window.overlay_counter = setInterval(function() {
				var time_remaining = precisionRound(parseFloat($(".overlay .timeout").text()) - 0.1, 1);
				if (isNaN(time_remaining)) {
					time_remaining = 0;
				}
				if (time_remaining <= 0) {
					time_remaining = "<em>right now</em>";
					if ($(".overlay .timeout + span").is(":visible")) {
						$(".overlay .timeout + span").css("display", "none");
					}
				} else if (!$(".overlay .timeout + span").is(":visible")) {
					$(".overlay .timeout + span").css("display", "inline-block");
				}
				$(".overlay .timeout").html(isNaN(time_remaining) ? time_remaining : time_remaining.toFixed(1));
			}, 100);
		};

		websocket.onmessage = function(evt) {
			if (JSON.parse(evt.data)) {
				var data = JSON.parse(evt.data);
				if (typeof(data.cpu_usage) == "number" && typeof(data.bandwidth_usage) == "number") {
					$("#cpu_usage_bar").css("width", Math.round(data.cpu_usage) + "%").children().text(Math.round(data.cpu_usage) + "%");
					$("#cpu_usage_val").text(Math.round(data.cpu_usage) + "%");
					$("#bandwidth_usage_bar").css("width", Math.round(data.bandwidth_usage) + "%").children().text(Math.round(data.bandwidth_usage) + "%");
					$("#bandwidth_usage_val").text(Math.round(data.bandwidth_usage) + "%");
				} else if (typeof(data.test_pushbullet) == "string") {
					if (data.test_pushbullet == "message sent") {
						$.niftyNoty({
							type: "success",
							container: 'page',
							html: "A test push notification has been sent to your selected devices.",
							timer: 5000
						})
					} else {
						$.niftyNoty({
							type: "danger",
							container: 'page',
							html: "An error occurred when attempting to send a notification to your selected devices.",
							timer: 5000
						})
					}
					$("#pushbullet-test").html("<i class='fa fa-cloud'></i>" + $("#pushbullet-test").text());
				}
				//console.log("WebSocket JSON message: ");
				//console.log(JSON.parse(evt.data));
			} else {
				console.log("WebSocket message: " + evt.data);
			}
		};
		websocket.onerror = function(evt) {
			//console.log("Error", evt);
			//console.log(websocket);
			// this error could be a result of the uncatchable 403 not authorised
			$.get("/ajax/auth", function(data) {
				if (data.auth_status !== true) {
					session_expired();
				}
			});
		};
	});

	function session_expired() {
		window.location = '/auth/login?returnto=' + encodeURIComponent(window.location.pathname);
	}

	$(document).on("click", "a", function(e) {
		var href = $(this).attr('href');

		if (!/^#/.test(href)) { // it is just a hash change, no need to reload the ajax
			if (/^\/(me\/[^/]+|[^/]+)?$/.test(href)) {
				//if (window.location.pathname != href) {
				console.log('a navigable a tag clicked: ' + this, href);

				$('#container').removeClass('aside-in');
				$('#content-container').html('<div class="ajax-nav"><img class="loader" src="/img/reel-loader.gif"/><p>Loading...</p></div>');

				var url;
				if (href.indexOf('?') != -1) {
					url = href + '&ajax_nav=true';
				} else {
					url = href + '?ajax_nav=true';
				}
				$.get(url, function(data) {
					var new_content = $('#content-container', data).html();
					var container_classes = $(data).filter('#container').attr('class');
					var new_title = $(data).filter('title').text();

					$('#content-container').html(new_content);
					$('#container').attr('class', container_classes);

					history.pushState([new_content, new_title, container_classes], new_title, href);
					history_update_title(new_title);
				}).fail(function() {
					var content = '<div class="alert alert-danger" style="text-align: center;"><strong>This page failed to load. Please try refreshing the page.</div>';
					var title = 'Unable to load page | Movieventure';
					var container_classes = $('#container').attr('class');

					$('#content-container').html(content);
					history.pushState([content, title, container_classes], title, href);
					history_update_title(title);
				});
				// title
				// css
				// js
				// body container classes
				//}
				e.preventDefault();
			} else {
				alert('uh:' + e.target);
			}
		}
	});

	window.addEventListener('popstate', function(event) {
		console.log('popstate fired!');

		$('body > #container > .boxed > #content-container').html(event.state[0]);
		$('#container').attr('class', event.state[2]);
		history_update_title(event.state[1]);
	});

	function history_update_title(title) {
		try {
			document.getElementsByTagName('title')[0].innerHTML = title.replace('<', '&lt;').replace('>', '&gt;').replace(' & ', ' &amp; ');
		} catch (Exception) {}
		document.title = title;
	}

	$(document).ready(function() {
		history.replaceState([$('body > #container > .boxed > #content-container').html(), document.title, $('#container').attr('class')], document.title, document.location.href);
	});

	function precisionRound(number, precision) {
		var factor = Math.pow(10, precision);
		return Math.round(number * factor) / factor;
	}
</script>

<div style="display: none;">
	<script type="text/javascript">
		<!--//
		-->
	<![CDATA[//><!--
				if (document.images) {
					var img = new Image();
					img.src = "/img/reel-loader.gif";
				}
			//--><!]]>
	</script>
</div>

</body>

</html>