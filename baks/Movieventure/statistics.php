<?php
require("template/top.php");
head("Home", true);
?>
<div id="page-content">
	<h3 class="text-thin"><pre><?php var_dump($_COOKIE); var_dump($session, $userinfo); ?></pre></h3>
</div>
<?php
footer();
?>