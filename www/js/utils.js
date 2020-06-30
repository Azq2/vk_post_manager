define(function () {
//

var self = {
	pad: function pad(str, n, c) {
		n = n || 2;
		c = c || "0";
		str = str + "";
		n = n - str.length;
		for (var i = 0; i < n; ++i)
			str = c + str;	
		return str;
	}, 
	
	getHumanDate: function (size) {
		if (size >= 1024 * 1024)
			return Math.ceil(size / 1024 / 1024).toFixed(2) + " Mb";
		return Math.ceil(size / 1024).toFixed(2) + " Kb";
	}, 
	
	getHumanDate: function (unix, format) {
		var now = new Date(), 
			time = new Date(), 
			yesterday = new Date();
		
		time.setTime(unix * 1000);
		yesterday.setDate(yesterday.getDate() - 1);
		
		var months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
		
		var date_key = time.getDate() + ' ' + months[time.getMonth()] + ' ' + time.getFullYear(), 
			now_key = now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear(), 
			yesterday_key = yesterday.getDate() + ' ' + months[yesterday.getMonth()] + ' ' + yesterday.getFullYear();
		
		if (format == "date") {
			if (date_key == now_key) {
				return "сегодня";
			} else if (date_key == yesterday_key) {
				return "вчера";
			} else {
				return date_key;
			}
		} else if (format == "datetime") {
			if (date_key == now_key) {
				return self.pad(time.getHours()) + ":" + self.pad(time.getMinutes());
			} else if (date_key == yesterday_key) {
				return "вчера в " + self.pad(time.getHours()) + ":" + self.pad(time.getMinutes());
			} else {
				return date_key + " в " + self.pad(time.getHours()) + ":" + self.pad(time.getMinutes());
			}
		} else { // auto
			if (date_key == now_key) {
				return self.pad(time.getHours()) + ":" + self.pad(time.getMinutes());
			} else if (date_key == yesterday_key) {
				return "вчера в " + self.pad(time.getHours()) + ":" + self.pad(time.getMinutes());
			} else if (time.getFullYear() > now.getFullYear()) {
				return date_key + ' в ' + self.pad(time.getHours()) + ":" + self.pad(time.getMinutes());
			} else if (time.getFullYear() == now.getFullYear()) {
				return time.getDate() + ' ' + months[time.getMonth()] + ' в ' + self.pad(time.getHours()) + ":" + self.pad(time.getMinutes());
			} else {
				return date_key;
			}
		}
	}, 
	
	htmlWrap: function (str) {
		var map = {"<": "lt", ">": "gt", "\"": "quot"};
		str = str + "";
		return str.replace(/["'<>]/gim, function (m) {
			return '&' + (map[m] || ('#' + m.charCodeAt(0))) + ';';
		});
	}, 
	
	createObjectURL: function () {
		var url = !window.URL || !window.URL.createObjectURL ? window.webkitURL : window.URL;
		if (url && url.createObjectURL)
			return url.createObjectURL(file);
		return null;
	}
};
return self;

//
});
