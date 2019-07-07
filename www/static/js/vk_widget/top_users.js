define(['jquery', 'utils', 'emojionearea'], function ($, utils) {
//

$('.emojionearea-source').each(function () {
	var el = $(this);
	
	el.emojioneArea({
		pickerPosition: "bottom", 
		filtersPosition: "bottom", 
		autocomplete: false, 
		attributes: {
			spellcheck:		true, 
			rows:			el.attr("rows")
		},
		tonesStyle: "checkbox"
	});
});

//
});
