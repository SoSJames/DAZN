<?php
//$update_id = file_get_contents ("http://127.0.0.1:18001/update.php");

	$server = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['PHP_SELF']}";
	$path_server = str_replace ('dashboard.php' , 'api.php' , $server);
	$row = explode(PHP_EOL , file_get_contents("id.txt"));
	$rr = json_decode(file_get_contents('http://127.0.0.1:18001/index.php'), true);
	$ids = [];

	foreach ($rr['channels'] as $key => $r) {
		$ids[] = $key;
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>DAZN | Control Panel</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap.min.css">
  <link rel="stylesheet" href="jquery.toast.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/js/bootstrap.min.js"></script>
  <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap.min.js"></script>
  <script src="jquery.toast.min.js"></script>
</head>
<body>

<div class="container">
  <h2><a href="dashboard.php">DAZN Panel</a> <small>2023</small></h2>
  <hr/>
  <p>Total channels ONLINE: <?= count($ids) ?></p>
  <p>
    <a href="#" onclick="refresh();">[ Refresh ]</a>
  	&nbsp;&nbsp;|&nbsp;&nbsp;
  	<a href="<?= 'http://' . $_SERVER['SERVER_ADDR'] . ':18001/playlist.m3u' ?>" target="_blank">[ Get channels ONLINE M3U playlist ]</a>
	  &nbsp;&nbsp;|&nbsp;&nbsp;
  	<a href="<?= 'http://' . $_SERVER['SERVER_ADDR'] . ':18001/playlist.php' ?>" target="_blank">[ Get channels ONLINE RAW playlist ]</a>
	&nbsp;&nbsp;|&nbsp;&nbsp;
	<a href="#" onclick="showOnlyON();">[ Show ON channels ]</a>
	&nbsp;&nbsp;|&nbsp;&nbsp;
	<a href="#" onclick="showOnlyOFF();">[ Show OFF channels ]</a>
	&nbsp;&nbsp;|&nbsp;&nbsp;
	<a href="#" onclick="showALL();">[ Show ALL ]</a>
  </p>
  <br/><br/>
  <table id="channels" class="table">
    <thead>
		<tr>
			<th>ID</th>
			<th>Channel Group</th>
			<th>Channel Title</th>
			<th>Status</th>
			<th>Action</th>
		</tr>
    </thead>
    <tbody>
	<?php
	for ($i=0; $i < count($row); $i++)
	{
		$path = explode(";" , $row[$i]);
		$name = $path[1];
		$group = $path[2];
		$id = $path[0];
		$status = "OFF";
		if (in_array($id,$ids))
		{
			$status = "ON";
		}

		if (trim($id) == "")
		{
			continue;
		}

		if (trim($group) == "")
		{
			$group = "Unassigned";
		}
	?>
      <tr class="<?= $status ?> <?= $group ?>">
        <td><?= $id ?></td>
		<td><?= ucwords(strtolower($group)) ?></td>
		<td><?= ucwords(strtolower($name)) ?></td>
		<td><?= ($status == "ON") ? '<span class="label label-success">Online</span>' : '<span class="label label-warning">Offline</span>' ?></td>
        <td>
			<?php
			if ($status == "OFF"){	?>
			<a href="#" onclick="DoAction('<?= $path_server."?id=".$id."&type=dazn&action=START" ?>');" title="Click here to start the channel">Start</a>
			<?php } else { ?>
			<a href="#" onclick="DoAction('<?= $path_server."?id=".$id."&type=dazn&action=STOP" ?>');" title="Click here to shutdown the channel">Shutdown</a>
			&nbsp;&nbsp;|&nbsp;&nbsp;
			<a target="_blank" href="<?= 'http://' . $_SERVER['SERVER_ADDR'] . ':18000/' . $id . '/hls/playlist.m3u8' ?>" title="Click here to open the channel M3U link">M3U link</a>
			<?php } ?>
		</td>
      </tr>
	<?php
	}
	?>
    </tbody>
  </table>
</div>

<script>
	var table;

	$(document).ready(function() {
		table = $('#channels').DataTable({ stateSave: true });
		table.order( [ 1, 'asc' ], [ 2, 'asc' ] ).draw();
	} );

	function showOnlyON()
	{
		table.search( "Online" ).draw();
		return false;
	}

	function showOnlyOFF()
	{
		table.search( "Offline" ).draw();
		return false;
	}

	function showALL()
	{
		table.search( "" ).draw();
		return false;
	}

	function refresh()
	{
		location.reload();
		return false;
	}

	function DoAction(url)
	{
		var xmlHttp = new XMLHttpRequest();
		xmlHttp.open( "GET", url, false );
		xmlHttp.send( null );
		var response =  JSON.parse(xmlHttp.responseText);
		if (response.status == true)
		{
			$.toast({
				heading: 'Information',
				text: 'The requested action is being executed, please wait around 30-60 secs.',
				icon: 'success',
				loader: true,
				loaderBg: '#9EC600'
			});
			refresh();
		}
		else
		{
			$.toast({
				heading: 'Error',
				text: 'Oops!, something goes wrong when trying to do the requested action.',
				icon: 'error',
				loader: true,
				loaderBg: '#9EC600'
			});
		}
	}

</script>

</body>
</html>