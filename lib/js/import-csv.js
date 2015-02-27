jQuery(document).ready( function($){

	// Media upload handler
	$('#upload_csv_button').click(function() {
		formfield = $('#upload_csv').attr('name');
		tb_show('Upload a CSV', 'media-upload.php?type=file&amp;TB_iframe=true');
		return false;
	});

	window.send_to_editor = function(html) {
		loadCSVList();
		tb_remove();
	}

	loadCSVList();
});

/**
* deleteCSV() - deletes a CSV from the media library
*/
function deleteCSV(id,title){
	var bkgrd_color = jQuery('tr#row' + id).css('background-color');
	jQuery('tr#row' + id).css('background-color','rgb(245,202,202)');
	var answer = confirm('You are about to delete ' + title + '. Do you want to continue?');
	if(answer){
		var data = {
			'action': 'auction_importer',
			'cb_action': 'delete_csv',
			'csvID': id
		};

		jQuery.post( ajax_vars.ajax_url, data, function(response){
			var data = response.data;
			if( data['deleted'] == true ){
				loadCSVList();
			}
		});
	} else {
		jQuery('tr#row' + id).css('background-color',bkgrd_color);
	}
	return false;
}

/**
* Imports a slice of a CSV according to the specified offset
*/
function importCSV( id, offset ){

	// We've clicked on the CSV file's title and loaded a
	// preview. So, we'll hide the preview once we start
	// the import.
	if( jQuery('#run-import:contains("start-import")') ){
		jQuery('#csvimport').slideUp();
		jQuery('#import-table').fadeOut(); // remove the CSV preview
		jQuery('#run-import').html( '' ); // clear the "Start Import" button
		jQuery('#import-progress').fadeIn(); // show the progress bar container
	} else {
		jQuery('#import-table').fadeOut();
		jQuery('#import-progress').fadeIn();
	}
	jQuery('#csv_list').fadeOut();
	jQuery('#upload-csv').fadeOut();

	console.log( '[ImportCSV] Running importCSV(' + id + ',' + offset + ')' );

	var data = {
		'action': 'auction_importer',
		'cb_action': 'import_csv',
		'csvID': id,
		'csvoffset': offset
	};

	var jqXHR = jQuery.post( ajax_vars.ajax_url, data, function(response){

		//console.log( response );
		console.log( '[ImportCSV] offset = ' + offset + ', ' + response.current_offset + ', images = ' + response.images );
		if( jQuery('#import-progress h2').html() == '' ) jQuery('#import-progress h2').html('Importing <em>' + response.title + '</em>');

		// Attach images to the auction item
		if( response.images instanceof Array ) {
			var images = response.images;
			for( i = 0; i < images.length; i++ ) {
				importImage( response.post_ID, images[i], response.imgdir );
			}
		}

		var rows = response.csv['rows'];
		var html = '';

		if(response.selected_rows > 0){
			jQuery('#import-stats').html('(' + response.current_offset + ' out of ' + response.total_rows + ')');

			var width = percent( offset, response.csv['row_count'], 0, 100 );
			jQuery('#import-percent').html( width + '%' );
			width = 2 * Math.round( width );
			jQuery( '#import-progress-bar' ).width( width ); // adjust the width of the progress bar
			importCSV( response.id, response.offset );
		} else {
			jQuery('#import-percent').html('Import complete!');
			jQuery('#import-progress-bar').css( 'background-image', 'none' );
			jQuery('#import-progress-bar').css( 'border', 'none' );
			jQuery('#import-note').html('You may now reload or leave this page.');
		}
	}).fail( function( errorObj, status, error ){
		console.log( '[ImportCSV] ERROR: importCSV() ' + status + ' (' + error + ').' );
		console.log( errorObj );
		if( 'timeout' == error ) {
			console.log( '[ImportCSV] Retrying in 5 seconds...' );
			setTimeout( importCSV( id, offset ), 5000 );
		}
	});
}

/**
 * Imports a specified image as an attachment for a given post ID.
 *
 * @since 1.0.0
 *
 * @param int $post_ID Post ID.
 * @param str $image Image filename.
 * @param str $imgdir Path to image.
 */
function importImage( post_ID, image, imgdir ) {
	jQuery.ajax({
		url: ajax_vars.ajax_url,
		type: 'POST',
		dataType: 'json',
		data: { action: 'auction_importer', cb_action: 'import_image', itemID: post_ID, image: image, imgdir: imgdir },
		success: function( response ) {
			console.log( '[ImportCSV] ' + response.message );
			if( response.status == true ) {
				jQuery( '#import-image-stats' ).html( 'Imported ' + response.image + '.' );
			}
		},
		timeout: 25000,
		error: function( errorObj, status, error ){
			console.log( '[ImportCSV] Unable to import ' + image + '. ' + status + ' (' + error + ').' );
			if( 'timeout' == error ) {
				console.log( '[ImportCSV] Retrying import of ' + image + ' in 5 seconds...' );
				jQuery( '#import-image-stats' ).html( 'Retrying import of ' + image + ' in 5 seconds...' );
				setTimeout( importImage( post_ID, image, imgdir ), 5000 );
			}
		}
	});
}

function loadCSVList(){
	var data = {
		'action': 'auction_importer',
		'cb_action': 'get_csv_list'
	};

	jQuery.post( ajax_vars.ajax_url, data, function(response){
		jQuery('#csv_list tbody').empty();
		var imgfolders = response.data['imgfolders'];
		var csvs = response.data['csv'];

		if( jQuery.isArray( csvs ) ){
			var row = '';
			for(var i = 0; i < csvs.length; i++){
				var cssclass = '';
				if(i % 2){
					cssclass = ' class="alternate"';
				}
				var imgselect = '<option value="">Select a folder... </option>';
				if(imgfolders != null){
					for(j = 0; j < imgfolders.length; j++){
						if(imgfolders[j] == csvs[i].image_folder){
							var selected = ' selected="selected"';
						} else {
							var selected = '';
						}
						imgselect = imgselect + '<option value="' + imgfolders[j] + '"' + selected + '>' + imgfolders[j] + '</option>';
					}
				}
				var row = row + '<tr' + cssclass + ' id="row' + csvs[i].id + '"><td><strong><a class="load-csv" onclick="loadCSV(' + csvs[i].id + '); return false;" title="Preview the CSV data for '+ csvs[i].filename +'" href="#">'+ csvs[i].post_title + '</a></strong><br />'+ csvs[i].filename +'<br />'+ csvs[i].timestamp +'</td><td class="images"><select name="imgfolder-' + csvs[i].id + '" onchange="updateCSVImages(' + csvs[i].id + ',this.options[this.selectedIndex].value);">' + imgselect + '</select>&nbsp;&nbsp;<span class="status"></span></td><td class="auction">' + csvs[i].auction + '&nbsp;&nbsp;<span class="status"></span></td><td><a href="#" onclick="importCSV(' + csvs[i].id + ', ' + csvs[i].last_import + '); return false;">Continue at '+ csvs[i].last_import +'</a></td><td class="center"><a class="load-csv" onclick="deleteCSV(' + csvs[i].id + ',\'' + csvs[i].post_title + '\'); return false;" href="#">Delete</a></td></tr>';

			}
			jQuery(row).appendTo(jQuery('#csv_list tbody'));
		} else {
			var row = '<tr><td colspan="5" style="text-align: center;">No CSVs found. Upload one via the dialog below.</td></tr>';
			jQuery(row).appendTo(jQuery('#csv_list tbody'));
		}
	});
}

/**
* Loads a CSV in preparation for running importCSV
*/
function loadCSV(id){
	var data = {
		'action': 'auction_importer',
		'cb_action': 'load_csv',
		'csvID': id
	};
	jQuery('#import-table').fadeIn();

	jQuery.post( ajax_vars.ajax_url, data, function(response){

		jQuery('#csvimport thead tr').empty();
		jQuery('#csvimport tbody').empty();

		var columns = response.csv['csv']['columns'];
		var headings = '';
		for(var i = 0; i < columns.length; i++){
			headings = headings + '<th scope="col" class="manage-column">' + columns[i] + '</th>' + "\n";
		}
		jQuery(headings).appendTo(jQuery('#csvimport thead tr'));

		var rows = response.csv['csv']['rows'];
		var html = '<tr class="alternate"><td colspan="' + response.csv['csv']['column_count'] + '" style="text-align: center;"><strong>Auction:</strong> ' + response.csv['auction'] + '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>Image Folder:</strong> /' + response.csv['imgpath'] + '/</td></tr>';
		var cols = 5;
		if(rows.length < cols){
			var counter = rows.length;
		} else {
			var counter = cols;
		}
		for(var i = 0; i < counter; i++){
			var row = rows[i];
			var cssclass = '';
			if(i % 2){
				cssclass = ' class="alternate"';
			}
			html = html + "\n" + '<tr' + cssclass + '>';
			for(j = 0; j < columns.length; j++){
				if( typeof row[j] == 'undefined' ){
					html = html + "\n\t" + '<td>&nbsp;</td>';
				} else {
					html = html + "\n\t" + '<td>' + row[j].substring(0,100) + '</td>';
				}
			}
			html = html + "\n" + '</tr>';
		}
		jQuery(html).appendTo(jQuery('#csvimport tbody'));
		jQuery('#csv-name').html(response.csv['title']);
		jQuery('#import-progress h2').html('Importing <em>' + response.csv['title'] + '</em>');
		jQuery('#stats').html('(Showing <span class="count">' + counter + '</span> rows/<span class="count">' + response.csv['csv']['row_count'] + '</span> total rows)');
		jQuery('#run-import').html('<div id="start-import" style="text-align: center"><a href="#" class="button" onclick="importCSV(' + response.csv['id'] + ', ' + response.csv['offset'] + '); return false;">Click here to import the file previewed below:</a></div>');
		jQuery('#csvimport').fadeIn('slow');
	});
}

/**
 * Updates the auction taxonomy custom field for a CSV file
 */
function updateCSVAuction(id,auction_id){
	var data = {
		'action': 'auction_importer',
		'cb_action': 'updateauction',
		'auction': auction_id,
		'csvID'	: id
	};

	jQuery('#row' + id + ' td.auction span.status').fadeIn(1000).html('updating...');
	jQuery.post( ajax_vars.ajax_url, data, function(response){
		jQuery('#row' + id + ' td.auction span.status').html(response.data['status']).fadeOut(1000);
	});
}

/**
 * Updates the image path custom field for a CSV file
 */
function updateCSVImages(id, folder){
	var data = {
		'action': 'auction_importer',
		'cb_action': 'updateimgpath',
		'csvID': id,
		'imgpath': folder
	};

	jQuery('#row' + id + ' td.images span.status').fadeIn(1000).html('updating...');
	jQuery.post( ajax_vars.ajax_url, data, function(response){
		jQuery('#row' + id + ' td.images span.status').html(response.data['status']).fadeOut(1000);
	});
}


// Overwrite Thickbox.tb_remove()
window.tb_remove = function() {
	jQuery("#TB_imageOff").unbind("click");
	jQuery("#TB_closeWindowButton").unbind("click");
	jQuery("#TB_window").fadeOut("fast",function(){jQuery('#TB_window,#TB_overlay,#TB_HideSelect').trigger("unload").unbind().remove();});
	jQuery("#TB_load").remove();
	if (typeof document.body.style.maxHeight == "undefined") {//if IE 6
		jQuery("body","html").css({height: "auto", width: "auto"});
		jQuery("html").css("overflow","");
	}
	window.loadCSVList();
	document.onkeydown = "";
	document.onkeyup = "";
	return false;
}

// Return a number as a percent
function percent(number, whole, inverse, rounder){
	whole = parseFloat(whole);
	if( !whole ){ whole = 100; };
	number = parseFloat( number );
	if( !number ){ number = 0; };
	if( !whole || !number ){ return 0; };
	rounder = parseFloat( rounder );
	rounder = ( rounder && ( !( rounder%10 ) || rounder == 1 ) ) ? rounder:100;
	return (!inverse)? Math.round( ((number*100)/whole) *rounder)/rounder: Math.round( ((whole*number)/100) *rounder)/rounder;
}