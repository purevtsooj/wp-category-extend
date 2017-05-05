(function($){
	'use strict';

	$(document).ready(function(){

		$('.wce-update-category').on('click', function(e){
			var _btn = $(this);

			if( _btn.hasClass('active') ){
				return false;
			}

			_btn.addClass('active');

			$.post(ajaxurl, { action: 'wce_update_category', nonce: wce_options.nonce }, function(response){
				try{
					var _json = $.parseJSON(response);

					$('.wce-update-wrap pre').html( _json.message );
					$('.wce-update-wrap pre').removeClass('error');

					if( _json.result!='success' ){
						$('.wce-update-wrap pre').addClass('error');
					}

					$('.wce-update-wrap pre').slideDown();

					setTimeout(function(){
						$('.wce-update-wrap pre').slideUp();
					}, 3000);
				}
				catch(e){

				}

				_btn.removeClass('active');
			});

		});

	});

})(jQuery);