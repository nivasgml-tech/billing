/* assets/js/main.js */
$(document).ready(function () {
    // Toggle Sidebar
    $("#menu-toggle").click(function (e) {
        e.preventDefault();
        $("#wrapper").toggleClass("toggled");
    });

    // Submenu toggles
    $('.has-submenu').click(function (e) {
        e.preventDefault();
        let expanded = $(this).attr('aria-expanded') === 'true';
        $(this).attr('aria-expanded', !expanded);
        $(this).next('.submenu').slideToggle(250);

        let icon = $(this).find('.fa-chevron-down, .fa-chevron-up');
        if (!expanded) {
            icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        } else {
            icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        }
    });

    // Initialize Select2 if present
    if ($('.select2').length) {
        $('.select2').select2({
            theme: 'bootstrap-5'
        });
    }

    // Initialize DataTables if present
    if ($('.datatable').length) {
        $('.datatable').DataTable({
            responsive: true,
            dom: "<'row mb-3 align-items-center'<'col-sm-12 col-md-4'l><'col-sm-12 col-md-4 text-center'B><'col-sm-12 col-md-4'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                { extend: 'excel', className: 'btn btn-sm btn-success', text: '<i class="fas fa-file-excel me-1"></i>Excel' },
                { extend: 'pdf', className: 'btn btn-sm btn-danger', text: '<i class="fas fa-file-pdf me-1"></i>PDF' },
                { extend: 'print', className: 'btn btn-sm btn-info text-white', text: '<i class="fas fa-print me-1"></i>Print' }
            ],
            order: [], // Use the sorting order coming from the database
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search records..."
            }
        });
    }
});

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
})
