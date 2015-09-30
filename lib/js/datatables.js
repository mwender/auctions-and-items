jQuery(document).ready( function ($) {
    var table = $('#auction-datatable').DataTable({
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
            { defaultContent: '', className: 'control dt-body-center', orderable: false, targets: 0 },
            { data: 'lotnum', type: 'num', className: 'dt-body-center dt-head-nowrap', targets: 1 },
            { data: 'image', orderable: false, targets: 2 },
            { data: 'title', targets: 3 },
            { data: 'desc', visible: false, className: 'none', targets: 4 },
            { data: 'price', type: 'num-fmt', className: 'dt-body-right dt-head-nowrap', targets: 5 }
        ]
    });

    // Listen for search events
    table.on( 'search.dt', function(e, settings){
        console.log( '[DT] table.search() = ' + table.search() );
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