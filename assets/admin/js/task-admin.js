/**
 * Rejimde Task Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Slug auto-generation from title
        $('#task_title').on('blur', function() {
            var title = $(this).val();
            var slug = $('#task_slug').val();
            
            if (title && !slug) {
                // Generate slug from title
                slug = title.toLowerCase()
                    .replace(/ğ/g, 'g')
                    .replace(/ü/g, 'u')
                    .replace(/ş/g, 's')
                    .replace(/ı/g, 'i')
                    .replace(/ö/g, 'o')
                    .replace(/ç/g, 'c')
                    .replace(/[^a-z0-9]+/g, '_')
                    .replace(/^_+|_+$/g, '');
                
                $('#task_slug').val(slug);
            }
        });
        
        // New Task Form Submission
        $('#rejimde-new-task-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var formData = new FormData(this);
            formData.append('action', 'rejimde_save_task');
            formData.append('nonce', rejimdeTaskAdmin.nonce);
            
            // Disable submit button
            $submitBtn.prop('disabled', true).text('Kaydediliyor...');
            
            $.ajax({
                url: rejimdeTaskAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);
                        // Reset form
                        $form[0].reset();
                        // Redirect to dynamic tasks tab after 1 second
                        setTimeout(function() {
                            window.location.href = '?page=rejimde-tasks&tab=dynamic';
                        }, 1000);
                    } else {
                        showMessage('error', response.data.message || 'Bir hata oluştu');
                    }
                },
                error: function() {
                    showMessage('error', 'Sunucu hatası oluştu');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text('Görevi Kaydet');
                }
            });
        });
        
        // Delete Task
        $('.delete-task').on('click', function() {
            if (!confirm('Bu görevi silmek istediğinizden emin misiniz?')) {
                return;
            }
            
            var $btn = $(this);
            var taskId = $btn.data('task-id');
            var $row = $btn.closest('tr');
            
            $btn.prop('disabled', true).text('Siliniyor...');
            
            $.ajax({
                url: rejimdeTaskAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rejimde_delete_task',
                    nonce: rejimdeTaskAdmin.nonce,
                    task_id: taskId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            // Check if table is empty
                            if ($('tbody tr').length === 0) {
                                location.reload();
                            }
                        });
                        showMessage('success', response.data.message);
                    } else {
                        showMessage('error', response.data.message || 'Görev silinemedi');
                        $btn.prop('disabled', false).text('Sil');
                    }
                },
                error: function() {
                    showMessage('error', 'Sunucu hatası oluştu');
                    $btn.prop('disabled', false).text('Sil');
                }
            });
        });
        
        // Toggle Task Status
        $('.toggle-task').on('click', function() {
            var $btn = $(this);
            var taskId = $btn.data('task-id');
            var $statusCell = $btn.closest('tr').find('td:nth-child(5)');
            
            $btn.prop('disabled', true);
            
            $.ajax({
                url: rejimdeTaskAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rejimde_toggle_task',
                    nonce: rejimdeTaskAdmin.nonce,
                    task_id: taskId
                },
                success: function(response) {
                    if (response.success) {
                        var isActive = response.data.is_active;
                        
                        // Update status cell
                        if (isActive) {
                            $statusCell.html('<span class="status-active">✅ Aktif</span>');
                            $btn.text('Devre Dışı Bırak');
                        } else {
                            $statusCell.html('<span class="status-inactive">⏸️ Pasif</span>');
                            $btn.text('Aktif Et');
                        }
                        
                        showMessage('success', response.data.message);
                    } else {
                        showMessage('error', response.data.message || 'Durum değiştirilemedi');
                    }
                },
                error: function() {
                    showMessage('error', 'Sunucu hatası oluştu');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // Helper function to show messages
        function showMessage(type, message) {
            var $message = $('<div class="rejimde-message ' + type + '">' + message + '</div>');
            $('.rejimde-tab-content').prepend($message);
            
            setTimeout(function() {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    });
    
})(jQuery);
