requirejs.config({
	urlArgs:		"r=" + document.getElementById('loader_modules').getAttribute('data-revision'), 
	waitSeconds:	9999, 
	baseUrl:		"/js/", 
	paths: {
		"jquery":				"https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min",
		"emojionearea":			"https://cdn.jsdelivr.net/npm/emojionearea@3.4.2/dist/emojionearea.min", 
		"emojione":				"https://cdn.jsdelivr.net/npm/emojione@4.5.0/lib/js/emojione.min", 
		"howler":				"https://cdn.jsdelivr.net/npm/howler@2.2.0/dist/howler.min", 
		
		"picker":				"https://cdn.jsdelivr.net/npm/pickadate@3.6.4/lib/compressed/picker", 
		"picker.date":			"https://cdn.jsdelivr.net/npm/pickadate@3.6.4/lib/compressed/picker.date", 
		"picker.ru_RU":			"https://cdn.jsdelivr.net/npm/pickadate@3.6.4/lib/compressed/translations/ru_RU", 
		
		"tui-image-editor":		"https://cdn.jsdelivr.net/npm/tui-image-editor@3.9.0/dist/tui-image-editor.min", 
		"tui-code-snippet":		"https://cdn.jsdelivr.net/npm/tui-code-snippet@1.5.2/dist/tui-code-snippet.min", 
		"fabric":				"https://cdn.jsdelivr.net/npm/fabric@3.6.3/dist/fabric.min", 
		"tui-color-picker":		"https://cdn.jsdelivr.net/npm/tui-color-picker@2.2.6/dist/tui-color-picker", 
		
		// amcharts
		"amcharts":				"https://cdn.jsdelivr.net/npm/amcharts3@3.21.15/amcharts/amcharts",
		"amcharts.funnel":		"https://cdn.jsdelivr.net/npm/amcharts3@3.21.15/amcharts/funnel",
		"amcharts.gauge":		"https://cdn.jsdelivr.net/npm/amcharts3@3.21.15/amcharts/gauge",
		"amcharts.pie":			"https://cdn.jsdelivr.net/npm/amcharts3@3.21.15/amcharts/pie",
		"amcharts.radar":		"https://cdn.jsdelivr.net/npm/amcharts3@3.21.15/amcharts/radar",
		"amcharts.serial":		"https://cdn.jsdelivr.net/npm/amcharts3@3.21.15/amcharts/serial",
		"amcharts.xy":			"https://cdn.jsdelivr.net/npm/amcharts3@3.21.15/amcharts/xy", 
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
		"tui-image-editor": {
			"deps": ["jquery", "tui-code-snippet", "fabric", "tui-color-picker"]
		},
		
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
