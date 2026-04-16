/**
 * Ultimate WP Live Search - Admin Scripts
 * This script handles the "Create Data" functionality in the WordPress admin.
 */
// Load SweetAlert2 via CDN if not already loaded
if (typeof Swal === 'undefined') {
    document.write('<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/10.13.3/sweetalert2.min.js"></script>');
    document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/10.13.3/sweetalert2.min.css">');
}

jQuery(document).ready(function($) {
    // Handle click on "Tạo dữ liệu" (Create Data) button
    $('.uwls-btn-create-cache').click(function() {
        Swal.fire({
            title: 'Tạo dữ liệu',
            text: 'Bạn chắc chắn muốn thực hiện thao tác này?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Tiến hành',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                // Perform AJAX request to create search data
                $.ajax({
                    url: uwls_js.url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'create_data_search'
                    },
                    beforeSend: function() {
                        // Global loading spinner function (defined in index.php)
                        if (typeof loading_snipper === 'function') {
                            loading_snipper(true);
                        }
                    },
                    success: function(response) {
                        if (response.success) {
                            let timerInterval;
                            Swal.fire({
                                icon: 'success',
                                title: response.data.message,
                                html: 'Trang sẽ được tải lại sau <b>5</b> giây để hoàn tất',
                                timer: 5000,
                                timerProgressBar: true,
                                didOpen: () => {
                                    Swal.showLoading();
                                    const b = Swal.getHtmlContainer().querySelector('b');
                                    timerInterval = setInterval(() => {
                                        b.textContent = (Swal.getTimerLeft() / 1000).toFixed(0);
                                    }, 1000);
                                },
                                willClose: () => {
                                    clearInterval(timerInterval);
                                }
                            }).then((result) => {
                                // Reload page to update the data file path in footer
                                if (typeof loading_snipper === 'function') {
                                    loading_snipper(false);
                                }
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: 'Đã có lỗi xảy ra!'
                            });
                            if (typeof loading_snipper === 'function') {
                                loading_snipper(false);
                            }
                        }
                    },
                    error: function(error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Đã có lỗi xảy ra!'
                        });
                        if (typeof loading_snipper === 'function') {
                            loading_snipper(false);
                        }
                    }
                });
            }
        });
    });
});
