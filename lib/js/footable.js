jQuery(document).ready( function ($) {

    $('.footable').footable();
    $('.clear-filter').click(function(e){
		var footableFilter = $('table.footable').data('footable-filter');
    	footableFilter.clearFilter();
    	$('#search-highlights').val('');
    	e.preventDefault();
    });
});