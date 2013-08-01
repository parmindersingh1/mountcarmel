;(function($){
	function lock(selector) {
		$(selector).attr('readonly','readonly');
	}
	function unlock(selector) {
		$(selector)
			.attr('readonly','')
			.focus();
	}

	$(function(){
		var _lock = $('input.lock[type=text][id]');
		var _tokenProfileSubmit = $('#token-profile-submit');

		_lock
			.attr('readonly','readonly')
			.each(function(){
				var _this = $(this);
				var _lockId = '#' + _this.attr('id');

				$('<a id="ppt-unlock" class="unlock" href="' + _lockId + '" title="Locked: click to make changes.">edit</a>')
					.insertAfter(_this)
					.click(function(){
						unlock(_lockId);
						_tokenProfileSubmit.slideDown('fast');
						$(this).hide();
						return false;
					});
			});

		_tokenProfileSubmit.hide();
	
		$('a.lock-cancel[href]').click(function(){
			lock($(this).attr('href'));
			$(_tokenProfileSubmit).slideUp();
			$('#ppt-unlock').show();
			return false;
		});
	});
})(jQuery);