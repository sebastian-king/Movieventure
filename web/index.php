<?php
require("template/top.php");
head("Home", true);
?>
<div id="page-content">
	<pre>
	<?php
	var_dump(md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['HTTP_ACCEPT']));
	var_dump($_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_ACCEPT']);
	//var_dump($_COOKIE); var_dump($session, $userinfo);
	?>
	</pre>
</div>
<?php
footer();
?>