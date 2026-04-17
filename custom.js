/**
 * Ultimate WP Live Search - Admin Scripts (v1.0.3)
 */
jQuery(document).ready(function($) {
    // Handle click on "Tạo dữ liệu" (Create Data) button
    $('.uwls-btn-create-cache').on('click', function(e) {
        e.preventDefault();
        
        if (typeof Swal === 'undefined') {
            alert('Thư viện SweetAlert2 chưa được tải. Vui lòng F5 trang.');
            return;
        }

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
                console.log('Sending AJAX to:', uwls_js.url);
                $.ajax({
                    url: uwls_js.url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'uwls_do_create_data',
                        nonce: uwls_js.nonce
                    },
                    beforeSend: function() {
                        if (typeof loading_snipper === 'function') {
                            loading_snipper(true);
                        }
                    },
                    success: function(response) {
                        console.log('Response:', response);
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: response.data.message,
                                text: 'Trang sẽ tự động tải lại...',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Lỗi',
                                text: response.data.message || 'Đã có lỗi xảy ra!'
                            });
                            if (typeof loading_snipper === 'function') {
                                loading_snipper(false);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.log('XHR Response:', xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Lỗi kết nối (400/500). Vui lòng kiểm tra console hoặc thử Lưu cài đặt trước.'
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
