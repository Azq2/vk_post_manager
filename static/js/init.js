requirejs.config({
	urlArgs:		"r=" + document.getElementById('loader_modules').getAttribute('data-revision'), 
	waitSeconds:	9999, 
	baseUrl:		"/static/js/", 
	paths: {
		"jquery":				"vendor/jquery",
		"emojionearea":			"vendor/emojionearea", 
		"emojione":				"vendor/emojione", 
		
		// amcharts
		"amcharts":				"//cdnjs.cloudflare.com/ajax/libs/amcharts/3.21.12/amcharts",
		"amcharts.funnel":		"//cdnjs.cloudflare.com/ajax/libs/amcharts/3.21.12/funnel",
		"amcharts.gauge":		"//cdnjs.cloudflare.com/ajax/libs/amcharts/3.21.12/gauge",
		"amcharts.pie":			"//cdnjs.cloudflare.com/ajax/libs/amcharts/3.21.12/pie",
		"amcharts.radar":		"//cdnjs.cloudflare.com/ajax/libs/amcharts/3.21.12/radar",
		"amcharts.serial":		"//cdnjs.cloudflare.com/ajax/libs/amcharts/3.21.12/serial",
		"amcharts.xy":			"//cdnjs.cloudflare.com/ajax/libs/amcharts/3.21.12/xy", 
	}, 
	
	"shim": {
		/*
			Модули проекта
		*/
		feed: {
			deps: ['jquery', 'class', 'utils', 'emojione']
		}, 
		
		/*
			Внешние модули
		*/
		"emojionearea": {
			"deps": ["emojione"]
		},
		
		"emojione": {
			"exports": "emojione"
		}, 
		
		// amcharts
		"amcharts.funnel": {
			"deps": ["amcharts"],
			"exports": "AmCharts",
			"init": function () {
				AmCharts.isReady = true;
			}
		},
		"amcharts.gauge": {
			"deps": ["amcharts"],
			"exports": "AmCharts",
			"init": function () {
				AmCharts.isReady = true;
			}
		},
		"amcharts.pie": {
			"deps": ["amcharts"],
			"exports": "AmCharts",
			"init": function () {
				AmCharts.isReady = true;
			}
		},
		"amcharts.radar": {
			"deps": ["amcharts"],
			"exports": "AmCharts",
			"init": function () {
				AmCharts.isReady = true;
			}
		},
		"amcharts.serial": {
			"deps": ["amcharts"],
			"exports": "AmCharts",
			"init": function () {
				AmCharts.isReady = true;
			}
		},
		"amcharts.xy": {
			"deps": ["amcharts"],
			"exports": "AmCharts",
			"init": function () {
				AmCharts.isReady = true;
			}
		}
	}
});
