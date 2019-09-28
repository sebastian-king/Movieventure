<?php
require("../template/top.php");
if (isset($_GET['delete'])) {
	do {
		if (!preg_match("/[0-9]+/", $_GET['delete'])) {
			header("Location: /me/notifications", true, 301);
			die();
		}
		$id = $_GET['delete'];
		$q = mysql_query("SELECT * FROM notifications WHERE id = '".mysql_real_escape_string($id)."' AND uid = '".mysql_real_escape_string($uid)."' LIMIT 1");
		if (mysql_num_rows($q) == 1) {
			mysql_query("DELETE FROM notifications WHERE id = '".mysql_real_escape_string($id)."' AND uid = '".mysql_real_escape_string($uid)."' LIMIT 1");
			header("Location: /me/notifications#delete_success", true, 301);
			die();
		} else {
			header("Location: /me/notifications#delete_error", true, 301);
			die();
		}
		break;
	} while (false);
}
functions();
function mod_secs_to_h($secs)
{
        $units = array(
                "week"   => 7*24*3600,
                "day"    =>   24*3600,
                "hour"   =>      3600,
                "min" =>        60,
                "sec" =>         1,
        );
        if ( $secs == 0 ) return "0 seconds";
        $s = "";
        foreach ( $units as $name => $divisor ) {
                if ( $quot = intval($secs / $divisor) ) {
                        $s .= "$quot $name";
                        $s .= (abs($quot) > 1 ? "s" : "") . ", ";
                        $secs -= $quot * $divisor;
						break;
                }
        }
        return substr($s, 0, -2);
}
head("Your notifications", true, true);

	$pagelimit = 10; // MUST BE EVEN <<<<<
	$page = $_GET['page'];
	if ($page == "") {
		$page = 1;
	}
	$pageul = $pagelimit * $page;
	$pagell = $pageul - $pagelimit;
	if (count($_GET) > 0) {
		$gpx = '&';
	} else {
		$gpx = '?';
	}

$q = mysql_query("SELECT * FROM notifications WHERE 
(uid = '".mysql_real_escape_string($uid)."' OR uid = 'all') 
AND uid != '' LIMIT $pagell , $pageul");
if (mysql_num_rows($q) == 0) {
	if ($page == 1) {
		imsg("You do not have any notifications.", false);
		footer();
	}
	emsg("You do not have any notifications on page ".htmlspecialchars($page).".", false);
	footer();
}
$total = mysql_query("SELECT * FROM notifications WHERE 
(uid = '".mysql_real_escape_string($uid)."' OR uid = 'all') 
AND uid != ''");
$total = mysql_num_rows($total);
?>
<div class="row">		
				<div class="col-lg-12">
					<div class="box">
						<div class="box-header" data-original-title="">
							<h2><i class="icon-warning-sign"></i><span class="break"></span>Your notifications</h2>
						</div>
						<div class="box-content">
                            <table class="table table-striped table-bordered bootstrap-datatable datatable dataTable" id="DataTables_Table_0" aria-describedby="DataTables_Table_0_info">
							  <thead>
								  <tr role="row">
                                  
                                  <th class="_asc" role="" tabindex="0" aria-controls="" rowspan="1" colspan="1" style="width: 336px;">From</th>
                                  <th class="_asc" role="" tabindex="0" aria-controls="" rowspan="1" colspan="1" style="width: 268px;">Time</th>
                                  <th class="_asc" role="" tabindex="0" aria-controls="" rowspan="1" colspan="1" style="width: 268px;">Text</th>
                                  <th class="_asc" role="" tabindex="0" aria-controls="" rowspan="1" colspan="1" style="width: 159px;">Type</th>
                                  <th class="_asc" role="" tabindex="0" aria-controls="" rowspan="1" colspan="1" style="width: 100px;">Actions</th></tr>
							  </thead>   
							  
						  <tbody role="alert" aria-live="polite" aria-relevant="all">
                          <?php
						  $n=0;
						  while ($r = mysql_fetch_array($q)) {
						  $n++;
						  ?>
                          		<tr class="">
									<td class=" _1"><?php echo userinfo("username", $r['from']); ?></td>
									<td class="center "><?php echo date("d M, Y @ H:i:s a T", $r['timesafe']); ?></td>
                                    <td class="center ">
										<?php echo $r['text']; ?>
									</td>
									<td class="center ">
										<span class="label label-default"><?php echo ucfirst($r['type']); ?></span>
									</td>
									<td class="center">
                                    <center>
                                    	<?php if ($r['url']) {
											?>
                                            	<a class="btn btn-success" href="<?php echo $r['url']; ?>">
												<i class="icon-zoom-in "></i>                                            
												</a>
                                        	<?php
											}
											?>
										<a class="btn btn-danger" href="/me/notifications/delete/<?php echo $r['id']; ?>">
											<i class="icon-trash "></i> 
										</a>
                                    </center>
									</td>
								</tr>
                           <?php
						   }
						   ?>
                                
                                </tbody>
                                </table>
                                <center>
                                <div class="dataTables_info" id="DataTables_Table_0_info">Showing <?php echo $init = (($page*$pagelimit)-($pagelimit-1)); ?> to <?php echo ($init-1) + $n; ?> of <?php echo $total; ?> entries</div>
<?php $newurl = "/me/notifications"; ?>
<ul class="pagination">
    <li<?php if ($page == 1) { echo ' class="disabled"'; } ?>>
        <a href="<?php echo "{$newurl}/page/".($page - 1); ?>"><span>&laquo;</span></a>
    </li>
    <?php
	$lastpage = false;
	$qlc = $total;
	$lp = $total;
	$i = $page - ($pagelimit / 2);
	while ($i <= ($page + ($pagelimit / 2))) {
		if ($i <= 0 || $i > ceil(($lp / $pagelimit))) {
			$i++;
			//echo ceil(($lp / $pagelimit))." - ".($lp / $pagelimit)."<br />";
			continue;
		}
		?>
        <?php if ($i == $page) { echo '<li class="active" style="">'; } else { echo "<li style=''>"; } ?>
        <a href="<?php echo "{$newurl}/page/".$i; ?>" class=""><?php echo $i; ?></a>
    </li>
    <?php 
	$i++;
	} 
	?>
    <?php  ?>
    <li <?php if ($page == ceil(($lp / $pagelimit))) { echo ' class="disabled"'; } ?>>
        <a href="<?php echo "{$newurl}/page/".($page + 1); ?>" class=""><span>&raquo;</span></a>
    </li>
</ul>
</center>
                                <div class="row"><div class="col-lg-12"></div><div class="col-lg-12 center"></div></div></div>            
						</div>
					</div>
				</div><!--/col-->
			
			</div>
<?php
footer(false);
?>
<script src="assets/js/jquery.noty.min.js"></script>
<script>
$(document).ready(function() {
	hash = window.location.hash;
	if (hash == "#delete_success") {
		var n = noty({text: 'The notification was successfully deleted.', type: "success"});
		window.location.hash = "";
	}
	if (hash == "#delete_error") {
		var n = noty({text: 'Error while trying to delete: You do not have permisssion to delete this notification.', type: "error"});
		window.location.hash = "";
	}
});
</script>