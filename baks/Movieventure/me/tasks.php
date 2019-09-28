<?php
require("../template/top.php");
if (isset($_GET['delete'])) {
	do {
		if (!preg_match("/[0-9]+/", $_GET['delete'])) {
			header("Location: /me/tasks", true, 301);
			die();
		}
		$id = $_GET['delete'];
		$q = mysql_query("SELECT * FROM sessions WHERE id = '".mysql_real_escape_string($id)."' AND uid = '".mysql_real_escape_string($uid)."' LIMIT 1");
		if (mysql_num_rows($q) == 1) {
			mysql_query("DELETE FROM sessions WHERE id = '".mysql_real_escape_string($id)."' AND uid = '".mysql_real_escape_string($uid)."' LIMIT 1");
			header("Location: /me/tasks#delete_success", true, 301);
			die();
		} else {
			header("Location: /me/tasks#delete_error", true, 301);
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
head("Your tasks", true, true);

	$pagelimit = 10; // MUST BE EVEN <<<<<
	$page = @$_GET['page'];
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

$q = mysql_query("SELECT * FROM sessions WHERE 
(uid = '".mysql_real_escape_string($uid)."' OR uid = 'all') 
AND uid != '' ORDER BY created DESC LIMIT $pagell , $pagelimit");
if (mysql_num_rows($q) == 0) {
	if ($page == 1) {
		imsg("You do not have any tasks.", false);
		footer();
	}
	emsg("You do not have any tasks on page ".htmlspecialchars($page).".", false);
	footer();
}
$total = mysql_query("SELECT * FROM sessions WHERE 
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
                                  
                                  <th class="_asc" role="" tabindex="0" aria-controls="" rowspan="1" colspan="1" style="width: 336px;">Description</th>
                                  <th class="_asc" role="" tabindex="0" aria-controls="" rowspan="1" colspan="1" style="width: 268px;">Time</th>
                                  <th class="_asc" role="" tabindex="0" aria-controls="" rowspan="1" colspan="1" style="width: 268px;">Status</th>
                                  <th class="_asc" role="" tabindex="0" aria-controls="" rowspan="1" colspan="1" style="width: 159px;">Type</th>
                                  <th class="_asc" role="" tabindex="0" aria-controls="" rowspan="1" colspan="1" style="width: 100px;">Actions</th></tr>
							  </thead>   
							  
						  <tbody role="alert" aria-live="polite" aria-relevant="all">
                          <?php
						  $n=0;
						  while ($r = mysql_fetch_array($q)) {
						  $n++;
						  switch ($r['status']) {
								case 6:
								$level = "goto";
								$state = "Completed";
								break;
								case 5:
								$level = "encode";
								$state = "Encoding";
								break;
								case 4:
								$level = "download";
								$state = "Downloading";
								break;
								case 3:
								$level = "find";
								$state = "Finding";
								break;
								case 2:
								$level = "get";
								$state = "Verifying";
								break;
								case 1:
								$level = "search";
								$state = "Searching";
								break;
								case 0:
								$level = "search";
								$state = "Not yet started";
								break;
							}
						  ?>
                          		<tr class="">
									<td class=" _1"><?php 
									if ($r['type'] == "find" && $r['status'] == 6) { 
										echo "Added movie <a href='http://www.imdb.com/title/{$r['imdbid']}' target='_blank'>".($r['apititle'] ? $r['apititle'] : $r['imdbid'])."</a>"; 
									} else {
										echo "Getting movie <a href='http://www.imdb.com/title/{$r['imdbid']}' target='_blank'>".($r['apititle'] ? $r['apititle'] : $r['imdbid'])."</a>"; 
									} ?></td>
									<td class="center "><?php echo date("d M, Y @ H:i:s a T", $r['created']); ?></td>
                                    <td class="center ">
										<?php echo $state." ({$r['progress']}%)"; ?>
									</td>
									<td class="center ">
										<span class="label label-default"><?php echo ucfirst($r['type']); ?></span>
									</td>
									<td class="center">
                                    <center>
                                    	<?php if ($r['url']) {
											?>
                                            	<a class="btn btn-success" href="<?php if ($r['type'] == "find") { echo "/movies/find#".$level."={$r['imdbid']}&session={$r['sessionid']}"; } ?>">
												<i class="icon-zoom-in "></i>                                            
												</a>
                                        	<?php
											}
											?>
										<a class="btn btn-danger" href="/me/tasks/delete/<?php echo $r['id']; ?>">
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
<?php $newurl = "/me/tasks"; ?>
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
<script>
$(document).ready(function() {
	hash = window.location.hash;
	if (hash == "#delete_success") {
		var n = noty({text: 'The task was successfully deleted.', type: "success"});
		window.location.hash = "";
	}
	if (hash == "#delete_error") {
		var n = noty({text: 'Error while trying to delete: You do not have permisssion to delete this notification.', type: "error"});
		window.location.hash = "";
	}
});
</script>