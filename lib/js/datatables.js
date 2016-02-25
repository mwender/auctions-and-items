jQuery(document).ready( function ($) {
    // BEGIN Pipelining function for Datatables
    $.fn.dataTable.pipeline = function ( opts ) {
        // Configuration options
        var conf = $.extend( {
            pages: 3,     // number of pages to cache
            url: '',      // script url
            data: null,   // function or object with parameters to send to the server
                          // matching how `ajax.data` works in DataTables
            method: 'GET' // Ajax HTTP method
        }, opts );

        //console.log(conf);

        // Private variables for storing the cache
        var cacheLower = -1;
        var cacheUpper = null;
        var cacheLastRequest = null;
        var cacheLastJson = null;

        return function ( request, drawCallback, settings ) {
            var ajax          = false;
            var requestStart  = request.start;
            var drawStart     = request.start;
            var requestLength = request.length;
            var requestEnd    = requestStart + requestLength;

            if ( settings.clearCache ) {
                // API requested that the cache be cleared
                ajax = true;
                settings.clearCache = false;
            }
            else if ( cacheLower < 0 || requestStart < cacheLower || requestEnd > cacheUpper ) {
                // outside cached data - need to make a request
                ajax = true;
            }
            else if ( JSON.stringify( request.order )   !== JSON.stringify( cacheLastRequest.order ) ||
                      JSON.stringify( request.columns ) !== JSON.stringify( cacheLastRequest.columns ) ||
                      JSON.stringify( request.search )  !== JSON.stringify( cacheLastRequest.search )
            ) {
                // properties changed (ordering, columns, searching)
                ajax = true;
            }

            // Store the request for checking next time around
            cacheLastRequest = $.extend( true, {}, request );

            if ( ajax ) {
                // Need data from the server
                if ( requestStart < cacheLower ) {
                    requestStart = requestStart - (requestLength*(conf.pages-1));

                    if ( requestStart < 0 ) {
                        requestStart = 0;
                    }
                }

                cacheLower = requestStart;
                cacheUpper = requestStart + (requestLength * conf.pages);

                request.start = requestStart;
                request.length = requestLength*conf.pages;

                // Provide the same `data` options as DataTables.
                if ( $.isFunction ( conf.data ) ) {
                    // As a function it is executed with the data object as an arg
                    // for manipulation. If an object is returned, it is used as the
                    // data object to submit
                    var d = conf.data( request );
                    if ( d ) {
                        $.extend( request, d );
                    }
                }
                else if ( $.isPlainObject( conf.data ) ) {
                    // As an object, the data given extends the default
                    $.extend( request, conf.data );
                }

                settings.jqXHR = $.ajax( {
                    "type":     conf.method,
                    "url":      conf.url,
                    "data":     request,
                    "dataType": "json",
                    "cache":    false,
                    "success":  function ( json ) {
                        cacheLastJson = $.extend(true, {}, json);

                        if ( cacheLower != drawStart ) {
                            json.data.splice( 0, drawStart-cacheLower );
                        }

                        json.data.splice( requestLength, json.data.length );

                        drawCallback( json );
                    }
                } );
            }
            else {
                json = $.extend( true, {}, cacheLastJson );
                json.draw = request.draw; // Update the echo for each response
                json.data.splice( 0, requestStart-cacheLower );
                json.data.splice( requestLength, json.data.length );

                drawCallback(json);
            }
        }
    };

    // Register an API method that will empty the pipelined data, forcing an Ajax
    // fetch on the next draw (i.e. `table.clearPipeline().draw()`)
    $.fn.dataTable.Api.register( 'clearPipeline()', function () {
        return this.iterator( 'table', function ( settings ) {
            settings.clearCache = true;
        } );
    } );
    // END Pipelining Functions

    //* Hide default WordPress loop
    $('.genesis-loop').hide();

    //* Display toggle
    $('.auction-display-toggle a').click(function(e){
        e.preventDefault();
        var selected = $(this).attr('class');
        console.log('selected = ' + selected);
        if( 'view-table' == selected ){
            $('.auction-table.overlay').addClass('show');
            $('#auction-thumbnails_wrapper').hide();
        }
    });

    //* Close button
    $('.close-auction').click(function(e){
        e.preventDefault();
        $('.auction-table').removeClass('show');
        $('#auction-thumbnails_wrapper').slideDown();
    });

    //console.log( '[DT] wpvars.show_realized = ' + wpvars.show_realized );
    var columnOrder = [ 1, 'asc' ];
    if( 1 == wpvars.show_realized ){
        console.log( '[DT] Showing Realized Prices column.' );
        columnOrder = [ 5, 'desc' ];
        columnDefinitions = [
            { defaultContent: '', className: 'control dt-body-center', orderable: false, targets: 0 },
            { name: "lotnum", data: 'lotnum', type: 'num', className: 'dt-body-center dt-head-nowrap', targets: 1 },
            { name: "image", data: 'image', orderable: false, targets: 2 },
            { name: "title", data: 'title', targets: 3 },
            { name: "desc", data: 'desc', visible: false, className: 'none', targets: 4 },
            { name: "price", data: 'price', type: 'num-fmt', className: 'dt-body-right dt-head-nowrap', orderSequence: [ 'desc', 'asc' ], targets: 5 }
        ];
    } else {
        columnDefinitions = [
            { defaultContent: '', className: 'control dt-body-center', orderable: false, targets: 0 },
            { name: "lotnum", data: 'lotnum', type: 'num', className: 'dt-body-center dt-head-nowrap', targets: 1 },
            { name: "image", data: 'image', orderable: false, targets: 2 },
            { name: "title", data: 'title', targets: 3 },
            { name: "desc", data: 'desc', visible: false, className: 'none', targets: 4 },
            { name: "low_est", data: 'low_est', type: 'num-fmt', className: 'dt-body-right dt-head-nowrap', orderSequence: [ 'desc', 'asc' ], targets: 5 },
            { name: "high_est", data: 'high_est', type: 'num-fmt', className: 'dt-body-right dt-head-nowrap', orderSequence: [ 'desc', 'asc' ], targets: 6 },
        ];
    }

    // Table View
    var oldStart = 0;
    var table = $('#auction-datatable').DataTable({
    	fixedHeader: true,
        dom: 'fpilrtip',
        processing: true,
        stateSave: true,
        language: {
            processing: '<div class="dataTables_processing_text">Loading...</div>'
        },
        serverSide: true,
        responsive: {
            details: {
                type: 'column'
            }
        },
        ajax: $.fn.dataTable.pipeline({
            url: wpvars.ajax_url,
            method: 'POST',
            data: {
                'action': 'query_items',
                'auction': wpvars.auction,
                'show_realized': wpvars.show_realized
            }
        }),
        order: columnOrder,
        columnDefs: columnDefinitions,
        drawCallback: function(o){
            var newStart = this.api().page.info().start;

            if( newStart != oldStart ){
                $('.auction-table.overlay').animate({scrollTop: 0}),
                oldStart = newStart;
            }
        }
    });

    // Echo returned AJAX object
    /*
    table.on( 'xhr.dt', function(e,settings,json){
        console.log( 'Ajax event occured. Returned object:' );
        console.log(json);
    });
    /**/

    // Listen for search events
    /*
    table.on( 'search.dt', function(e, settings){
        console.log( '[DT] table.search() = ' + table.search() );
    });
    */

    //* Add `Clear` button to search
    var addClear = true;
    table.on( 'draw.dt', function(){
        if( true == addClear ){
            $('#auction-datatable_wrapper .dataTables_filter').append('<a class="filter_button table" href="#">Clear</a>');
            $('.filter_button.table').click(function(e){
                e.preventDefault();
                table.search('').columns().search('').draw();
            });
            addClear = false;
        }

    });

    // Thumbnail View
    var oldThumbnailStart = 0;
    var tableThumbnails = $('#auction-thumbnails').DataTable({
        fixedHeader: true,
        dom: 'fpilrtip',
        processing: true,
        stateSave: true,
        language: {
            processing: '<div class="dataTables_processing_text">Loading...</div>'
        },
        serverSide: true,
        lengthMenu: [[20,40,100],[20,40,100]],
        responsive: {
            details: {
                type: 'column'
            }
        },
        ajax: $.fn.dataTable.pipeline({
            url: wpvars.ajax_url,
            method: 'POST',
            data: {
                'action': 'query_items',
                'auction': wpvars.auction,
                'show_realized': wpvars.show_realized
            }
        }),
        columnDefs: [
            { name: "thumbnail", data: 'thumbnail', className: 'dt-body-center dt-head-nowrap', orderable: false, targets: 0 }
        ],
        drawCallback: function(o){
            var newThumbnailStart = this.api().page.info().start;

            if( newThumbnailStart != oldThumbnailStart ){
                var targetOffset = ( $( '#auction-thumbnails' ).offset().top ) - 300;
                $('html,body').animate({scrollTop: targetOffset}),
                oldThumbnailStart = newThumbnailStart;
            }
        }
    });

    //* Add `Clear` button to search
    var addClear2 = true;
    tableThumbnails.on( 'draw.dt', function(){
        if( true == addClear2 ){
            $('#auction-thumbnails_wrapper .dataTables_filter').append('<a class="filter_button thumbnails" href="#">Clear</a>');
            $('.filter_button.thumbnails').click(function(e){
                e.preventDefault();
                tableThumbnails.search('').columns().search('').draw();
            });
            addClear2 = false;
        }

    });

});