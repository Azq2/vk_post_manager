define(function () {
/*
	Псевдоклассы. 
	
	var ClassName = Class({
		Extends: Класс,_от_которого_наследовать, 
		Static: { статические поля класса, доступ через Класс.ПОЛЕ }, 
		Constructor: function () { конструктор }, 
		Implements: { классы, с которых скопировать методы }, 
		...методы...
	});
	
	Вызов парентного конструктора:
		ParentClass.apply(this, ...);
	
	Вызов парентого метода:
		ParentClass.method.apply(this, ...)
*/

return function (opts) {
	opts = opts || {};
	
	var noop = function () { };
	var new_class = function () {
		if (new_class.prototype.Constructor) {
			new_class.prototype.Constructor.apply(this, arguments);
		} else {
			if (new_class.$parent)
				new_class.$parent.apply(this, arguments);
		}
	};
	
	new_class.prototype = {constructor: new_class};
	if (opts.Static) {
		if (opts.Extends)
			_extend_static(new_class, opts.Extends);
		_extend(new_class, opts.Static);
		delete opts.Static;
	}
	
	var extended = false;
	if (opts.Extends) {
		new_class.$parent = opts.Extends;
		new_class.prototype.$parent = opts.Extends;
		_extend(new_class.prototype, opts.Extends.prototype);
		delete opts.Extends;
		extended = true;
	}
	
	delete new_class.prototype.Constructor;
	_extend(new_class.prototype, opts);
	
	if (opts.Implements) {
		for (var i = 0; i < opts.Implements.length; ++i) {
			var o = opts.Implements[i];
			_extend_static(new_class.prototype, 'prototype' in o ? o.prototype : o);
		}
		delete opts.Implements;
	}
	
	return new_class;
};

function _extend(obj, static_vars) {
	for (var k in static_vars) {
		if (!Object.prototype.hasOwnProperty.call(static_vars, k) || (obj[k] !== undefined && !Object.prototype.hasOwnProperty.call(obj, k)) || k == 'prototype' || k == '$parent')
			continue;
		obj[k] = static_vars[k];
	}
	return obj;
}

//
});
