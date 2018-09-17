<div id="meme">
	
</div>

<script>
require(['meme'], function (VkFeed) {
//
$(function () {
	$('#meme').on('meme:save', function (e, data) {
		console.log(data);
		
		var a = new FileReader();
		a.onload = function(e) {
			var img = new Image();
			img.src = e.target.result;
			$('body').prepend(img);
		};
		a.readAsDataURL(data.image);
	}).memeEditor({
		image: "https://pp.userapi.com/c846522/v846522142/e1e09/b2blu-tbUEo.jpg", 
		width: 720, 
		height: 960
	});
});
//
});
</script>
