jQuery(document).ready(function($) {
	var d = new Date();
	var time_current = d.getTime();
	var time_saved = sessionStorage.getItem('dmrpreadcookie_'+dmrp.id)

	if( (time_current - time_saved ) > dmrp.cookie_timeout ) {
		sessionStorage.setItem('dmrpreadcookie_'+dmrp.id, time_current);
		dmrp_count();
	}
});

function dmrp_count() {
	var data = {
		'action': 'dmrp_count',
		'nonce': dmrp.nonce,
		'id': dmrp.id
	};

	jQuery.post(dmrp.ajax_url, data, function(response) {});
}
