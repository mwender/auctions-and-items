jQuery(document).ready( function ($) {
    //* Display toggle
    $('.auction-display-toggle a').click(function(e){
        e.preventDefault();
        var selected = $(this).attr('class');
        console.log('selected = ' + selected);
        if( 'view-table' == selected ){
            $('.auction-table.overlay').addClass('show');
            //$('.genesis-loop').fadeOut();
        }
    });

    //* Close button
    $('.close-auction').click(function(e){
        e.preventDefault();
        $('.genesis-loop').slideDown();
        $('.auction-table').removeClass('show');
    });

    console.log( '[DT] wpvars.past = ' + wpvars.past );
    var columnOrder = [ 1, 'asc' ];
    if( 1 == wpvars.past ){
        console.log( '[DT] This is a past auction.' );
        //table.order( [ 5, 'desc' ] ).draw();
        columnOrder = [ 5, 'desc' ];
    }

    var table = $('#auction-datatable').DataTable({
    	fixedHeader: true,
        dom: 'fpilrtip',
        processing: true,
        serverSide: true,
        responsive: {
            details: {
                type: 'column'
            }
        },
        ajax: {
            url: wpvars.ajax_url,
            type: 'POST',
            data: {
                'action': 'query_items',
                'auction': wpvars.auction
            }
        },
        order: columnOrder,
        columnDefs: [
            { defaultContent: '', className: 'control dt-body-center', orderable: false, targets: 0 },
            { name: "lotnum", data: 'lotnum', type: 'num', className: 'dt-body-center dt-head-nowrap', targets: 1 },
            { name: "image", data: 'image', orderable: false, targets: 2 },
            { name: "title", data: 'title', targets: 3 },
            { name: "desc", data: 'desc', visible: false, className: 'none', targets: 4 },
            { name: "price", data: 'price', type: 'num-fmt', className: 'dt-body-right dt-head-nowrap', orderSequence: [ 'desc', 'asc' ], targets: 5 }
        ]
    });

    // Echo returned AJAX object
    table.on( 'xhr.dt', function(e,settings,json){
        console.log( 'Ajax event occured. Returned object:' );
        console.log(json);
    });

    // Listen for search events
    table.on( 'search.dt', function(e, settings){
        console.log( '[DT] table.search() = ' + table.search() );
    });

    //* Add `Clear` button to search
    var addClear = true;
    table.on( 'draw.dt', function(){
        if( true == addClear ){
            $('.dataTables_filter').append('<a class="filter_button" href="#">Clear</a>');
            $('.filter_button').click(function(e){
                e.preventDefault();
                table.search('').columns().search('').draw();
            });
            addClear = false;
        }

    });

    var tableThumbnails = $('#auction-thumbnails').DataTable({
        fixedHeader: true,
        dom: 'fpilrtip',
        processing: true,
        serverSide: true,
        responsive: {
            details: {
                type: 'column'
            }
        },
        ajax: {
            url: wpvars.ajax_url,
            type: 'POST',
            data: {
                'action': 'query_items',
                'auction': wpvars.auction
            }
        },
        columnDefs: [
            { name: "thumbnail", data: 'thumbnail', className: 'dt-body-center dt-head-nowrap', orderable: false, targets: 0 }
        ]
    });

});

/*
,
        "columns": [
            { "type": "num", 'width': '10%' },
            { "orderable": false, 'width': '20%' },
            { 'width': '55%'},
            { "visible": false, 'width': '0%' },
            { "type": "num-fmt", className: "dt-body-right", 'width': '15%' }
        ]
*/