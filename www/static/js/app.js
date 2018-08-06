define(['jquery', 'functions'], function ($) {
//
var tpl = {
	spinner: function (msg) {
		return '<img src="/i/img/spinner2.gif" alt="" class="m" /> <span class="m">' + msg + '</span>';
	}
};

$('.tab[href*="exit"]').on('click', function (e) {
	e.preventDefault();
	var el = $(this);
	
	el.html(tpl.spinner('Выходим...'));
	
	$.ajax({
		url:		el.prop("href"),
		data:		{ajax: 1},
		method:		'POST',
		username:	'logout',
		password:	'logout'
	}).success(function () {
		location.href = location.href;
	}).error(function () {
		location.href = location.href;
	});
});
//
});
