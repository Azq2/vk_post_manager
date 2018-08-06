define(['jquery'], function ($) {
//

function Url(url, merge_with_current) {
	url = url || "";
	this.parse(url, merge_with_current);
}

$.extend(Url, {
	regexp: /^(([a-z0-9_.-]+\:)?(\/\/([^\/#\?@:]+))?(:\d+)?)?([^\?#]+)?(\?[^#]*)?(#.*)?$/i, 
	onlyHashChanged: function (a, b) {
		if (!a || !b)
			return false;
		if (typeof a == 'string')
			a = new Url(a);
		if (typeof b == 'string')
			b = new Url(b);
		return ((a.hash.length > 0 || b.hash.length > 0) && a.isSame(b));
	}, 
	parseQuery: function (query) {
		if (query.charAt(0) == '?')
			query = query.substr(1);
		
		var params = {},
			pairs = query.split(/&amp;|&|;/);
		for (var i = 0; i < pairs.length; ++i) {
			var k, v = null;
			var idx = pairs[i].indexOf('=');
			if (idx != -1) {
				k = Url.decode(pairs[i].substr(0, idx));
				v = Url.decode(pairs[i].substr(idx + 1));
			} else {
				k = Url.decode(pairs[i]);
				v = null;
			}
			
			if (k.length) {
				if (params[k] !== undefined) {
					if (!(params[k] instanceof Array))
						params[k] = [params[k], v]
					else
						params[k].push(v);
				} else
					params[k] = v;
			}
		}
		return params;
	}, 
	decode: function (str) {
		return decodeURIComponent(str.replace(/%([^a-f0-9]{1,2}|$)/gi, "%25\1").replace(/\+/g, ' '));
	}, 
	encode: function (str) {
		if (typeof str == 'boolean')
			return str ? 1 : 0;
		return encodeURIComponent(str)
			.replace(/%2F/g, '/')
			.replace(/%2C/g, ',')
			.replace(/%5B/g, '[')
			.replace(/%5D/g, ']')
			.replace(/%20/g, '+');
	}, 
	buildQuery: function (query, sep, begin) {
		var first = true;
		var url = "";
		sep = sep || "&";
		begin = begin || "";
		for (var key in query) {
			if (query[key] === undefined)
				continue;
			if (query[key] instanceof Array) {
				for (var i = 0; i < query[key].length; ++i) {
					url += (first ? begin : sep) + Url.encode(key) + "=" + 
						Url.encode(query[key][i]);
					if (first) first = false;
				}
			} else {
				url += (first ? begin : sep) + Url.encode(key) + "=" + 
					Url.encode(query[key]);
				if (first) first = false;
			}
		}
		return url;
	}, 
	serializeForm: function (form, own_object) {
		var form_data = "", object = own_object || {};
		
		if (form instanceof jQuery)
			form = form[0];
		
		var elements = form.elements;
		if (form.tagName.toLowerCase() != 'form')
			elements = $(form).find('textarea, input, button, select');
		
		for (var i = 0, l = elements.length; i < l; ++i) {
			var p = elements[i], 
				type = p.type.toLowerCase();
			
			if (!p.name.length || ((type == "radio" || type == "checkbox") && !p.checked) || 
					(type == 'submit' && p != form.submit_btn))
				continue;
			if (object[p.name] !== undefined) {
				if (!(object[p.name] instanceof Array))
					object[p.name] = [object[p.name]];
				object[p.name].push(p.value);
			} else
				object[p.name] = p.value;
		}
		return object;
	}
});

$.extend(Url.prototype, {
	parse: function (url, merge_with_current) {
		var self = this, 
			m = url.match(Url.regexp) || [];
		
		self.scheme = m[2] || '';
		self.domain = m[4] || '';
		self.port   = m[5] || '';
		self.path   = m[6] || '';
		self.query  = m[7] || '';
		self.hash   = m[8] || '';
		
		if (merge_with_current)
			self._mergeWithCurrent();
		
		self.domain = self.domain.toLowerCase();
		self.scheme = self.scheme.substr(0, self.scheme.length - 1).toLowerCase();
		self.port   = self.port.substr(1);
		self.query  = self.query.substr(1);
		self.hash   = self.hash.substr(1);
		self.query  = Url.parseQuery(self.query);
		
		return self;
	}, 
	isSame: function (url) {
		return this.url(true) === url.url(true);
	}, 
	_mergeWithCurrent: function () {
		var self = this, 
			c = window.location;
		if (!self.scheme.length) {
			self.scheme = c.protocol;
			if (!self.domain.length) {
				self.domain = c.hostname;
				if (!self.path.length) {
					self.path = window.location.pathname;
					if (!self.query.length) {
						self.query = c.search;
						if (!self.hash.length)
							self.hash = c.hash;
					}
				} else if (self.path.substr(0, 1) != '/') {
					self.path = c.pathname + 
						(c.pathname.substr(c.pathname.length - 1) == "/" ? "" : "/") + 
						self.path;
				}
			}
		}
		return self;
	}, 
	val: function (k) {
		var self = this;
		return self.query[k] instanceof Array ? self.query[k][0] : self.query[k];
	}, 
	merge: function (c) {
		var self = this;
		if (!self.scheme.length) {
			self.scheme = c.scheme;
			if (!self.domain.length) {
				self.domain = c.domain;
				if (!self.path.length) {
					self.path = c.path;
					if ($.isEmptyObject(self.query)) {
						self.query = $.extend({}, c.query);
						if (!self.hash.length)
							self.hash = c.hash;
					}
				} else if (self.path.substr(0, 1) != '/') {
					self.path = c.path + 
						(c.path.substr(c.path.length - 1) == "/" ? "" : "/") + 
						self.path;
				}
			}
		}
		return self;
	}, 
	url: function (skip_hash) {
		var self = this, url = "";
		if (self.scheme.length)
			url += self.scheme + ":";
		if (self.domain.length || self.port.length) {
			url += "//";
			if (self.domain.length)
				url += self.domain;
			if (self.port.length)
				url += ":" + self.port;
		}
		if (self.path.length)
			url += self.path;
		if (!$.isEmptyObject(self.query))
			url += Url.buildQuery(self.query, "&", "?");
		if (self.hash.length && !skip_hash)
			url += "#" + self.hash;
		return url;
	}, 
	toString: function () {
		return this.url();
	}, 
	clone: function () {
		var empty = new Url();
		return empty.merge(this);
	}, 
	parseDomain: function() {
		var self = this, m, out = {domain: self.domain, sub_domain: "", sub_domains: [], base_domain: ""};
		if ((m = self.domain.match(/((.*?)\.|^)([^\.]+\.[^\.]+)$/))) {
			if (m[2] !== undefined) {
				out.sub_domain = m[2].toLowerCase();
				out.sub_domains = out.sub_domain.split('.');
			}
			if (m[3] !== undefined)
				out.base_domain = m[3].toLowerCase();
		}
		return out;
	}
});

return Url;

//
});
