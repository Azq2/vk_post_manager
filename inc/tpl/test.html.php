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
		image: "https://pp.userapi.com/c840334/v840334201/4be66/fLGcqne-oVw.jpg", 
		width: 700, 
		height: 435
	});
});
//
});
</script>
