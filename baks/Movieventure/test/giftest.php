<script src="/js/jquery-2.1.1.min.js"></script>
<script src="/js/libgif.js"></script>
<div class="loading_spinner_special_container1" style="display: none;">
	<img id="special_loader1" src="/img/loading_spinner_begin.gif" rel:auto_play="0" />
</div>
<div class="loading_spinner_special_container2" style="display: none;">
	<img id="special_loader2" src="/img/loading_spinner_middle.gif" rel:auto_play="0" />
</div>
<div class="loading_spinner_special_container3" style="display: none;">
	<img id="special_loader3" src="/img/loading_spinner_end.gif" rel:auto_play="0" />
</div>

<script type="text/javascript">
	var gif1 = new SuperGif({
		gif: document.getElementById("special_loader1"),
		on_end: function(data) {
			console.log('iteration finished');
			$(".loading_spinner_special_container1").css('display', 'none');
			gif1.pause();
			$(".loading_spinner_special_container2").css('display', 'block');
			gif2.play();
		},
		progressbar_height: 0
	});
	gif1.load(function(data) {
		console.log('loaded');
		$(".loading_spinner_special_container1").css('display', 'block');
		gif1.play();
	});
	
	var gif2 = new SuperGif({
		gif: document.getElementById("special_loader2"),
		on_end: function(data) {
			console.log('iteration finished');
			if (window.goto_end === true) {
				$(".loading_spinner_special_container2").css('display', 'none');
				gif2.pause();
				$(".loading_spinner_special_container3").css('display', 'block');
				gif3.play();
			}
		},
		progressbar_height: 0
	});
	gif2.load(function(data) {
		console.log('loaded');
	});
	
	var gif3 = new SuperGif({
		gif: document.getElementById("special_loader3"),
		on_end: function(data) {
			console.log('iteration finished');
			gif3.pause();
		},
		progressbar_height: 0
	});
	gif3.load(function(data) {
		console.log('loaded');
	});	
</script>