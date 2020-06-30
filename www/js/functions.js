define(['jquery'], function ($) {
//
var tpl = {
	pagenav: function (data) {
		var html = '';
		if (data.pages.length > 1) {
			html += '<div class="pagenav js-pagenav">';
			if (data.prev || data.next) {
				html += '<div class="pagenav-prevnext">';
				if (data.prev)
					html += '<a href="#prev-' + data.prev + '" class="js-page" data-p="' + data.prev + '">&larr; Предыдущая</a>';
				if (data.prev && data.next) {
					html += ' | ';
				}
				if (data.next)
					html += '<a href="#next-' + data.next + '" class="js-page" data-p="' + data.next + '">Следующая &rarr;</a>';
				html += '</div>';
			}
			html += '<div class="pagenav-pages">';
			for (var i = 0; i < data.pages.length; ++i) {
				var page = data.pages[i];
				if (page.current) {
					html += 
						'<a href="#page-' + page.page + '" data-p="' + page.page + '" class="js-page pagenav-page pagenav-page_current m">' + 
							page.page + 
						'</a>';
				} else if (page.separator) {
					html += '<span class="m grey"> ... </span>';
				} else {
					html += 
						'<a href="#page-' + page.page + '" data-p="' + page.page + '" class="js-page pagenav-page m">' + 
							page.page + 
						'</a>';
				}
			}
			html += '&nbsp;&nbsp;<input type="text" value="" name="page" class="js-page_input m" size="1" /> ' + 
					'<button class="js-page_input_btn btn m">' + 
						'<img src="images/anchor.svg" width="16" height="16" />' + 
					'</button>';
			html += '</div>';
			html += '</div>';
		}
		return html;
	}
};

$.fn.emojify = function () {
	return this.find('.emoji').each(function() {
		$(this).html(emojione.toImage($(this).html()));
	}).removeClass('emoji');
};

function pagenav(url, offset, chunk, total) {
	console.log(url, offset, chunk, total);
	var page = Math.floor(offset / chunk) + 1, 
		max_pages = Math.floor(total / chunk) + 1, 
		neighbors = 4;
	
	page = Math.min(page, max_pages);
	offset = chunk * (page - 1);
	
	var pages = [];
	
	// Ссылка на первую страницу
	if (page > 1)
		pages.push({page: 1});
	
	// Страницы слева активной
	if (offset - 1 > chunk + 1)
		pages.push({separator: 1});
	
	var min_page = Math.max(2, page - neighbors);
	for (var i = min_page; i < page; ++i)
		pages.push({page: i});
	
	pages.push({page: page, current: 1});
	
	// Страницы справа активной
	var max_page = Math.min(max_pages - 1, page + neighbors);
	for (var i = page + 1; i <= max_page; ++i)
		pages.push({page: i});
	
	if (page < max_pages - neighbors - 1)
		pages.push({separator: 1});
	
	// Ссылка на последнюю страницу
	if (page < max_pages)
		pages.push({page: max_pages});
	
	return tpl.pagenav({
		page: page, 
		total: max_pages, 
		pages: pages, 
		next: page < max_pages ? page + 1 : false, 
		prev: page > 1 ? page - 1 : false, 
	});
}
window.pagenav = pagenav;
//
});

function getHumanSize(size) {
	if (size >= 1024 * 1024)
		return Math.ceil(size / 1024 / 1024).toFixed(2) + " Mb";
	return Math.ceil(size / 1024).toFixed(2) + " Kb";
}

function createObjectURL(file) {
	var url = !window.URL || !window.URL.createObjectURL ? window.webkitURL : window.URL;
	if (url && url.createObjectURL)
		return url.createObjectURL(file);
	return null;
}

function modal_window(content) {
	$('#modal_overlay').toggleClass('hide', !content);
	return $('#modal_content').html(content || '');
}

function html_wrap(str) {
	var map = {"<": "lt", ">": "gt", "\"": "quot"};
	str = str + "";
	return str.replace(/["'<>]/gim, function (m) {
		return '&' + (map[m] || ('#' + m.charCodeAt(0))) + ';';
	});
}

function pad(str, n, c) {
	n = n || 2;
	c = c || "0";
	str = str + "";
	n = n - str.length;
	for (var i = 0; i < n; ++i)
		str = c + str;	
	return str;
}

function rand(min, max) {
	return Math.random() * (max - min) + min;
}
