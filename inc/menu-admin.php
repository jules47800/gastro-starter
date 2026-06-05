<?php
/**
 * Interface d'administration des menus pour Mon Restaurant
 * Gestion avancée avec glisser-déposer et réorganisation
 *
 * @package Gastro_Starter
 */

if (!defined('ABSPATH')) {
    exit; // Sortie si accès direct
}

/**
 * Ajouter le menu d'administration des menus
 */
function gastro_starter_add_menu_admin() {
    add_submenu_page(
        'edit.php?post_type=daily_menu',
        __('Gestionnaire de Menus', 'gastro-starter'),
        __('Gestionnaire', 'gastro-starter'),
        'manage_options',
        'gastro-starter-menu-manager',
        'gastro_starter_menu_manager_page'
    );
}
add_action('admin_menu', 'gastro_starter_add_menu_admin');

/**
 * Page de gestion des menus avec glisser-déposer
 */
function gastro_starter_menu_manager_page() {
    // Traitement de la mise à jour de l'ordre
    if (isset($_POST['update_menu_order']) && check_admin_referer('update_menu_order', 'menu_order_nonce')) {
        $menu_order = json_decode(stripslashes($_POST['menu_order']), true);
        if (is_array($menu_order)) {
            foreach ($menu_order as $position => $menu_id) {
                wp_update_post(array(
                    'ID' => $menu_id,
                    'menu_order' => $position
                ));
            }
            add_settings_error('gastro_starter_menus', 'order_updated', __('Ordre des menus mis à jour avec succès.', 'gastro-starter'), 'success');
        }
    }

    // Traitement de la suppression en lot
    if (isset($_POST['bulk_delete_menus']) && check_admin_referer('bulk_delete_menus', 'bulk_delete_nonce')) {
        $menu_ids = isset($_POST['selected_menus']) ? array_map('intval', $_POST['selected_menus']) : array();
        $deleted_count = 0;
        
        foreach ($menu_ids as $menu_id) {
            if (wp_delete_post($menu_id, true)) {
                $deleted_count++;
            }
        }
        
        if ($deleted_count > 0) {
            add_settings_error('gastro_starter_menus', 'menus_deleted', 
                sprintf(_n('%d menu supprimé.', '%d menus supprimés.', $deleted_count, 'gastro-starter'), $deleted_count), 'success');
        }
    }

    // Récupérer tous les menus
    $menus = get_posts(array(
        'post_type' => 'daily_menu',
        'posts_per_page' => -1,
        'orderby' => 'menu_order date',
        'order' => 'ASC',
        'post_status' => array('publish', 'draft')
    ));

    // Affichage des messages
    settings_errors('gastro_starter_menus');

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-sortable');
    ?>

    <div class="wrap gastro-starter-menu-manager">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-media-document"></span>
            <?php echo esc_html__('Gestionnaire de Menus', 'gastro-starter'); ?>
        </h1>
        
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=daily_menu')); ?>" class="page-title-action">
            <?php echo esc_html__('Ajouter un nouveau menu', 'gastro-starter'); ?>
        </a>

        <hr class="wp-header-end">

        <!-- Zone de téléchargement rapide -->
        <div class="gastro-starter-quick-upload">
            <div class="upload-section">
                <h2><?php echo esc_html__('Téléchargement Rapide', 'gastro-starter'); ?></h2>
                <div id="quick-upload-drop-zone" class="quick-upload-zone">
                    <div class="upload-icon">
                        <span class="dashicons dashicons-cloud-upload"></span>
                    </div>
                    <h3><?php echo esc_html__('Glissez vos fichiers ici', 'gastro-starter'); ?></h3>
                    <p><?php echo esc_html__('ou cliquez pour sélectionner des fichiers', 'gastro-starter'); ?></p>
                    <p class="supported-formats">
                        <?php echo esc_html__('Formats supportés: PDF, JPG, PNG, DOC, DOCX', 'gastro-starter'); ?>
                    </p>
                    <input type="file" id="quick-upload-input" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>
                <div id="upload-progress-container" class="upload-progress-container" style="display: none;">
                    <div class="upload-progress-bar">
                        <div class="progress-fill"></div>
                        <span class="progress-text">0%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions en lot -->
        <div class="gastro-starter-bulk-actions">
            <form method="post" id="bulk-actions-form">
                <?php wp_nonce_field('bulk_delete_menus', 'bulk_delete_nonce'); ?>
                <div class="bulk-actions-bar">
                    <select name="bulk_action" id="bulk-action-selector">
                        <option value=""><?php echo esc_html__('Actions en lot', 'gastro-starter'); ?></option>
                        <option value="delete"><?php echo esc_html__('Supprimer', 'gastro-starter'); ?></option>
                        <option value="publish"><?php echo esc_html__('Publier', 'gastro-starter'); ?></option>
                        <option value="draft"><?php echo esc_html__('Mettre en brouillon', 'gastro-starter'); ?></option>
                    </select>
                    <button type="button" id="apply-bulk-action" class="button" disabled><?php echo esc_html__('Appliquer', 'gastro-starter'); ?></button>
                </div>
        </div>

        <!-- Liste des menus avec drag & drop -->
        <form method="post" id="menu-order-form">
            <?php wp_nonce_field('update_menu_order', 'menu_order_nonce'); ?>
            <input type="hidden" name="menu_order" id="menu_order_input">
            
            <div class="menu-list-header">
                <h2><?php echo esc_html__('Vos Menus', 'gastro-starter'); ?></h2>
                <div class="header-actions">
                    <label class="select-all-container">
                        <input type="checkbox" id="select-all-menus">
                        <?php echo esc_html__('Tout sélectionner', 'gastro-starter'); ?>
                    </label>
                    <button type="submit" name="update_menu_order" class="button button-primary" disabled id="save-order-btn">
                        <?php echo esc_html__('Sauvegarder l\'ordre', 'gastro-starter'); ?>
                    </button>
                </div>
            </div>

            <?php if (empty($menus)) : ?>
                <div class="no-menus-message">
                    <div class="empty-state">
                        <span class="dashicons dashicons-media-document"></span>
                        <h3><?php echo esc_html__('Aucun menu disponible', 'gastro-starter'); ?></h3>
                        <p><?php echo esc_html__('Commencez par ajouter votre premier menu ou utilisez la zone de téléchargement rapide ci-dessus.', 'gastro-starter'); ?></p>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=daily_menu')); ?>" class="button button-primary">
                            <?php echo esc_html__('Ajouter un menu', 'gastro-starter'); ?>
                        </a>
                    </div>
                </div>
            <?php else : ?>
                <div id="sortable-menu-list" class="sortable-menu-list">
                    <?php foreach ($menus as $menu) : 
                        $menu_pdf = get_post_meta($menu->ID, '_menu_pdf', true);
                        $file_type = '';
                        $file_size = '';
                        
                        if ($menu_pdf) {
                            $file_info = pathinfo($menu_pdf);
                            $file_type = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';
                            
                            // Essayer d'obtenir la taille du fichier
                            $attachment_id = attachment_url_to_postid($menu_pdf);
                            if ($attachment_id) {
                                $file_path = get_attached_file($attachment_id);
                                if ($file_path && file_exists($file_path)) {
                                    $file_size = size_format(filesize($file_path));
                                }
                            }
                        }
                        
                        $status_class = $menu->post_status === 'publish' ? 'published' : 'draft';
                        $status_text = $menu->post_status === 'publish' ? __('Publié', 'gastro-starter') : __('Brouillon', 'gastro-starter');
                    ?>
                        <div class="menu-item <?php echo esc_attr($status_class); ?>" data-menu-id="<?php echo esc_attr($menu->ID); ?>">
                            <div class="menu-item-content">
                                <div class="menu-item-drag-handle">
                                    <span class="dashicons dashicons-move"></span>
                                </div>
                                
                                <div class="menu-item-checkbox">
                                    <input type="checkbox" name="selected_menus[]" value="<?php echo esc_attr($menu->ID); ?>" class="menu-checkbox">
                                </div>

                                <div class="menu-item-preview">
                                    <?php if ($menu_pdf && in_array($file_type, ['jpg', 'jpeg', 'png'])) : ?>
                                        <img src="<?php echo esc_url($menu_pdf); ?>" alt="<?php echo esc_attr($menu->post_title); ?>">
                                    <?php else : ?>
                                        <div class="file-icon">
                                            <span class="dashicons dashicons-media-document"></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="menu-item-details">
                                    <h3 class="menu-title">
                                        <a href="<?php echo esc_url(get_edit_post_link($menu->ID)); ?>">
                                            <?php echo esc_html($menu->post_title); ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="menu-meta">
                                        <span class="menu-date">
                                            <?php echo esc_html(get_the_date('d/m/Y', $menu)); ?>
                                        </span>
                                        
                                        <?php if ($file_size) : ?>
                                            <span class="menu-size"><?php echo esc_html($file_size); ?></span>
                                        <?php endif; ?>
                                        
                                        <span class="menu-type"><?php echo esc_html(strtoupper($file_type ?: 'N/A')); ?></span>
                                        
                                        <span class="menu-status status-<?php echo esc_attr($menu->post_status); ?>">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="menu-item-actions">
                                    <?php if ($menu_pdf) : ?>
                                        <a href="<?php echo esc_url($menu_pdf); ?>" target="_blank" class="button button-small" title="<?php echo esc_attr__('Prévisualiser', 'gastro-starter'); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo esc_url(get_edit_post_link($menu->ID)); ?>" class="button button-small" title="<?php echo esc_attr__('Modifier', 'gastro-starter'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    
                                    <button type="button" class="button button-small delete-menu-btn" data-menu-id="<?php echo esc_attr($menu->ID); ?>" title="<?php echo esc_attr__('Supprimer', 'gastro-starter'); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </form>
        </form>
    </div>

    <style>
    .gastro-starter-menu-manager {
        max-width: 1200px;
    }

    .gastro-starter-quick-upload {
        background: #fff;
        border: 1px solid #e2e4e7;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .quick-upload-zone {
        border: 2px dashed #b4b9be;
        border-radius: 8px;
        padding: 40px 20px;
        text-align: center;
        background: #fafafa;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }

    .quick-upload-zone:hover,
    .quick-upload-zone.dragover {
        border-color: #0073aa;
        background: rgba(0, 115, 170, 0.05);
    }

    .quick-upload-zone .upload-icon .dashicons {
        font-size: 48px;
        color: #b4b9be;
        margin-bottom: 10px;
    }

    .quick-upload-zone h3 {
        margin: 10px 0;
        color: #23282d;
        font-size: 18px;
    }

    .quick-upload-zone p {
        margin: 5px 0;
        color: #666;
    }

    .quick-upload-zone #quick-upload-input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    .upload-progress-container {
        margin-top: 20px;
    }

    .upload-progress-bar {
        position: relative;
        height: 20px;
        background: #e2e4e7;
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(45deg, #0073aa, #005a87);
        border-radius: 10px;
        width: 0%;
        transition: width 0.3s ease;
    }

    .progress-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 12px;
        font-weight: bold;
        color: #fff;
        text-shadow: 0 1px 2px rgba(0,0,0,0.5);
    }

    .gastro-starter-bulk-actions {
        background: #fff;
        border: 1px solid #e2e4e7;
        border-radius: 8px;
        padding: 15px 20px;
        margin: 20px 0;
    }

    .bulk-actions-bar {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .menu-list-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 20px 0;
        padding: 15px 20px;
        background: #fff;
        border: 1px solid #e2e4e7;
        border-radius: 8px;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .sortable-menu-list {
        background: #fff;
        border: 1px solid #e2e4e7;
        border-radius: 8px;
        overflow: hidden;
    }

    .menu-item {
        border-bottom: 1px solid #e2e4e7;
        transition: all 0.2s ease;
        position: relative;
    }

    .menu-item:last-child {
        border-bottom: none;
    }

    .menu-item:hover {
        background: #f8f9fa;
    }

    .menu-item.ui-sortable-helper {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: rotate(2deg);
        border-radius: 8px;
        border: 1px solid #0073aa;
    }

    .menu-item-content {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        gap: 15px;
    }

    .menu-item-drag-handle {
        cursor: move;
        color: #b4b9be;
        padding: 5px;
    }

    .menu-item-drag-handle:hover {
        color: #0073aa;
    }

    .menu-item-preview {
        width: 60px;
        height: 60px;
        border-radius: 6px;
        overflow: hidden;
        background: #f1f1f1;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .menu-item-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .menu-item-preview .file-icon .dashicons {
        font-size: 24px;
        color: #666;
    }

    .menu-item-details {
        flex: 1;
        min-width: 0;
    }

    .menu-title {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
    }

    .menu-title a {
        text-decoration: none;
        color: #23282d;
    }

    .menu-title a:hover {
        color: #0073aa;
    }

    .menu-meta {
        display: flex;
        gap: 15px;
        font-size: 13px;
        color: #666;
    }

    .menu-status.status-publish {
        color: #46b450;
        font-weight: 600;
    }

    .menu-status.status-draft {
        color: #ffb900;
        font-weight: 600;
    }

    .menu-item-actions {
        display: flex;
        gap: 5px;
    }

    .menu-item-actions .button {
        padding: 6px 8px;
        min-height: auto;
        line-height: 1;
    }

    .delete-menu-btn:hover {
        background: #dc3232;
        color: #fff;
        border-color: #dc3232;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #fff;
        border: 1px solid #e2e4e7;
        border-radius: 8px;
    }

    .empty-state .dashicons {
        font-size: 48px;
        color: #b4b9be;
        margin-bottom: 20px;
    }

    .empty-state h3 {
        margin: 0 0 10px 0;
        color: #23282d;
    }

    .empty-state p {
        color: #666;
        margin-bottom: 20px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .menu-item-content {
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .menu-meta {
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .menu-list-header {
            flex-direction: column;
            gap: 15px;
            align-items: stretch;
        }
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        let isOrderChanged = false;

        // Initialiser le drag & drop
        $('#sortable-menu-list').sortable({
            handle: '.menu-item-drag-handle',
            placeholder: 'menu-item-placeholder',
            helper: 'clone',
            tolerance: 'pointer',
            start: function(e, ui) {
                ui.placeholder.html('<div class="placeholder-content">Déposez ici</div>');
            },
            update: function(event, ui) {
                isOrderChanged = true;
                $('#save-order-btn').prop('disabled', false).addClass('button-primary');
                updateMenuOrder();
            }
        });

        // Fonction pour mettre à jour l'ordre des menus
        function updateMenuOrder() {
            const order = [];
            $('#sortable-menu-list .menu-item').each(function(index) {
                order.push($(this).data('menu-id'));
            });
            $('#menu_order_input').val(JSON.stringify(order));
        }

        // Gestion du téléchargement rapide
        const dropZone = $('#quick-upload-drop-zone');
        const fileInput = $('#quick-upload-input');
        const progressContainer = $('#upload-progress-container');
        const progressFill = $('.progress-fill');
        const progressText = $('.progress-text');

        // Événements drag & drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone[0].addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone[0].addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone[0].addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropZone.addClass('dragover');
        }

        function unhighlight() {
            dropZone.removeClass('dragover');
        }

        // Gestion du drop
        dropZone[0].addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const files = e.dataTransfer.files;
            handleFiles(files);
        }

        // Gestion du clic sur la zone
        dropZone.on('click', function() {
            fileInput.click();
        });

        fileInput.on('change', function() {
            handleFiles(this.files);
        });

        // Traitement des fichiers
        function handleFiles(files) {
            if (files.length === 0) return;

            progressContainer.show();
            let uploaded = 0;
            const total = files.length;

            Array.from(files).forEach((file, index) => {
                uploadFile(file, () => {
                    uploaded++;
                    const percent = Math.round((uploaded / total) * 100);
                    updateProgress(percent);

                    if (uploaded === total) {
                        setTimeout(() => {
                            progressContainer.hide();
                            location.reload(); // Recharger pour voir les nouveaux menus
                        }, 1000);
                    }
                });
            });
        }

        function uploadFile(file, callback) {
            const formData = new FormData();
            formData.append('action', 'gastro_starter_quick_upload_menu');
            formData.append('file', file);
            formData.append('security', '<?php echo wp_create_nonce("gastro_starter_quick_upload"); ?>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        console.log('Fichier uploadé:', file.name);
                    } else {
                        console.error('Erreur upload:', response.data.message);
                        alert('Erreur lors du téléchargement de ' + file.name + ': ' + response.data.message);
                    }
                    callback();
                },
                error: function() {
                    console.error('Erreur Ajax pour:', file.name);
                    alert('Erreur lors du téléchargement de ' + file.name);
                    callback();
                }
            });
        }

        function updateProgress(percent) {
            progressFill.css('width', percent + '%');
            progressText.text(percent + '%');
        }

        // Gestion des cases à cocher
        $('#select-all-menus').on('change', function() {
            $('.menu-checkbox').prop('checked', this.checked);
            updateBulkActionButton();
        });

        $('.menu-checkbox').on('change', function() {
            updateBulkActionButton();
            
            const totalCheckboxes = $('.menu-checkbox').length;
            const checkedCheckboxes = $('.menu-checkbox:checked').length;
            
            $('#select-all-menus').prop('checked', checkedCheckboxes === totalCheckboxes);
        });

        function updateBulkActionButton() {
            const hasSelection = $('.menu-checkbox:checked').length > 0;
            $('#apply-bulk-action').prop('disabled', !hasSelection || !$('#bulk-action-selector').val());
        }

        $('#bulk-action-selector').on('change', function() {
            updateBulkActionButton();
        });

        // Application des actions en lot
        $('#apply-bulk-action').on('click', function() {
            const action = $('#bulk-action-selector').val();
            const selectedMenus = $('.menu-checkbox:checked').map(function() {
                return this.value;
            }).get();

            if (!action || selectedMenus.length === 0) return;

            let confirmMessage = '';
            if (action === 'delete') {
                confirmMessage = 'Êtes-vous sûr de vouloir supprimer les menus sélectionnés ?';
            } else if (action === 'publish') {
                confirmMessage = 'Publier les menus sélectionnés ?';
            } else if (action === 'draft') {
                confirmMessage = 'Mettre en brouillon les menus sélectionnés ?';
            }

            if (confirm(confirmMessage)) {
                if (action === 'delete') {
                    $('#bulk-actions-form').find('input[name="bulk_delete_menus"]').remove();
                    $('#bulk-actions-form').append('<input type="hidden" name="bulk_delete_menus" value="1">');
                    $('#bulk-actions-form').submit();
                } else {
                    // Traitement AJAX pour publish/draft
                    $.post(ajaxurl, {
                        action: 'gastro_starter_bulk_update_menu_status',
                        menu_ids: selectedMenus,
                        new_status: action,
                        security: '<?php echo wp_create_nonce("gastro_starter_bulk_update"); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Erreur: ' + response.data.message);
                        }
                    });
                }
            }
        });

        // Suppression individuelle
        $('.delete-menu-btn').on('click', function() {
            const menuId = $(this).data('menu-id');
            const menuTitle = $(this).closest('.menu-item').find('.menu-title a').text();
            
            if (confirm('Êtes-vous sûr de vouloir supprimer le menu "' + menuTitle + '" ?')) {
                $.post(ajaxurl, {
                    action: 'gastro_starter_delete_menu',
                    menu_id: menuId,
                    security: '<?php echo wp_create_nonce("gastro_starter_delete_menu"); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + response.data.message);
                    }
                });
            }
        });

        // Avertissement si l'ordre a changé et que l'utilisateur quitte la page
        $(window).on('beforeunload', function() {
            if (isOrderChanged && $('#save-order-btn').is(':enabled')) {
                return 'Vous avez modifié l\'ordre des menus. Voulez-vous sauvegarder avant de quitter ?';
            }
        });

        // Marquer comme sauvegardé après soumission
        $('#menu-order-form').on('submit', function() {
            isOrderChanged = false;
        });
    });
    </script>
    <?php
}

/**
 * AJAX: Téléchargement rapide de menu
 */
function gastro_starter_quick_upload_menu() {
    check_ajax_referer('gastro_starter_quick_upload', 'security');
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'gastro-starter')));
    }

    if (empty($_FILES['file'])) {
        wp_send_json_error(array('message' => __('Aucun fichier reçu.', 'gastro-starter')));
    }

    $uploaded_file = $_FILES['file'];
    $file_type = wp_check_filetype(basename($uploaded_file['name']));
    
    $allowed_types = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx');
    if (!in_array($file_type['ext'], $allowed_types)) {
        wp_send_json_error(array('message' => __('Type de fichier non autorisé.', 'gastro-starter')));
    }

    // Upload du fichier
    $upload = wp_handle_upload($uploaded_file, array('test_form' => false));
    
    if (isset($upload['error'])) {
        wp_send_json_error(array('message' => $upload['error']));
    }

    // Créer le post menu
    $menu_title = sanitize_file_name(pathinfo($uploaded_file['name'], PATHINFO_FILENAME));
    $menu_id = wp_insert_post(array(
        'post_title' => $menu_title,
        'post_type' => 'daily_menu',
        'post_status' => 'publish',
        'meta_input' => array(
            '_menu_pdf' => $upload['url']
        )
    ));

    if (is_wp_error($menu_id)) {
        wp_send_json_error(array('message' => $menu_id->get_error_message()));
    }

    wp_send_json_success(array(
        'message' => __('Menu créé avec succès.', 'gastro-starter'),
        'menu_id' => $menu_id
    ));
}
add_action('wp_ajax_gastro_starter_quick_upload_menu', 'gastro_starter_quick_upload_menu');

/**
 * AJAX: Mise à jour en lot du statut des menus
 */
function gastro_starter_bulk_update_menu_status() {
    check_ajax_referer('gastro_starter_bulk_update', 'security');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'gastro-starter')));
    }

    $menu_ids = isset($_POST['menu_ids']) ? array_map('intval', $_POST['menu_ids']) : array();
    $new_status = sanitize_text_field($_POST['new_status']);
    
    if (empty($menu_ids) || !in_array($new_status, array('publish', 'draft'))) {
        wp_send_json_error(array('message' => __('Paramètres invalides.', 'gastro-starter')));
    }

    $updated = 0;
    foreach ($menu_ids as $menu_id) {
        $result = wp_update_post(array(
            'ID' => $menu_id,
            'post_status' => $new_status
        ));
        
        if ($result !== 0) {
            $updated++;
        }
    }

    $message = sprintf(_n('%d menu mis à jour.', '%d menus mis à jour.', $updated, 'gastro-starter'), $updated);
    wp_send_json_success(array('message' => $message));
}
add_action('wp_ajax_gastro_starter_bulk_update_menu_status', 'gastro_starter_bulk_update_menu_status');

/**
 * AJAX: Suppression d'un menu
 */
function gastro_starter_delete_menu() {
    check_ajax_referer('gastro_starter_delete_menu', 'security');
    
    if (!current_user_can('delete_posts')) {
        wp_send_json_error(array('message' => __('Permissions insuffisantes.', 'gastro-starter')));
    }

    $menu_id = intval($_POST['menu_id']);
    if (!$menu_id) {
        wp_send_json_error(array('message' => __('ID de menu invalide.', 'gastro-starter')));
    }

    $result = wp_delete_post($menu_id, true);
    if ($result) {
        wp_send_json_success(array('message' => __('Menu supprimé avec succès.', 'gastro-starter')));
    } else {
        wp_send_json_error(array('message' => __('Erreur lors de la suppression.', 'gastro-starter')));
    }
}
add_action('wp_ajax_gastro_starter_delete_menu', 'gastro_starter_delete_menu');


/**
 * ===================================================================
 * Fonctions de la Metabox pour l'édition de menu individuel
 * ===================================================================
 */

/**
 * Ajouter des champs personnalisés pour les menus
 */
function gastro_starter_add_meta_boxes() {
    add_meta_box(
        'daily_menu_details',
        __('Fichier de menu', 'gastro-starter'),
        'gastro_starter_daily_menu_callback',
        'daily_menu',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'gastro_starter_add_meta_boxes');

/**
 * Callback pour afficher les champs personnalisés du menu
 */
function gastro_starter_daily_menu_callback($post) {
    wp_nonce_field(basename(__FILE__), 'daily_menu_nonce');
    
    // Récupérer les valeurs existantes
    $menu_pdf = get_post_meta($post->ID, '_menu_pdf', true);
    
    // Déterminer le type de fichier
    $file_type = '';
    $file_size = '';
    if ($menu_pdf) {
        $file_info = pathinfo($menu_pdf);
        $file_type = isset($file_info['extension']) ? strtolower($file_info['extension']) : '';
        
        // Obtenir la taille du fichier
        $attachment_id = attachment_url_to_postid($menu_pdf);
        if ($attachment_id) {
            $file_path = get_attached_file($attachment_id);
            if ($file_path && file_exists($file_path)) {
                $file_size = size_format(filesize($file_path));
            }
        }
    }
    
    // Texte du bouton adapté au type de fichier
    $view_text = __('Voir le fichier', 'gastro-starter');
    if ($file_type === 'pdf') {
        $view_text = __('Voir le PDF', 'gastro-starter');
    } elseif (in_array($file_type, ['jpg', 'jpeg', 'png'])) {
        $view_text = __('Voir l\'image', 'gastro-starter');
    } elseif (in_array($file_type, ['doc', 'docx'])) {
        $view_text = __('Voir le document', 'gastro-starter');
    } elseif (in_array($file_type, ['xls', 'xlsx'])) {
        $view_text = __('Voir le tableur', 'gastro-starter');
    } elseif (in_array($file_type, ['ppt', 'pptx'])) {
        $view_text = __('Voir la présentation', 'gastro-starter');
    }
    
    ?>
    <div class="gastro-starter-menu-upload">
        <?php if (empty($menu_pdf)) : ?>
            <!-- Zone de téléchargement -->
            <div id="menu-drop-area" class="menu-drop-area">
                <div class="upload-icon">
                    <span class="dashicons dashicons-cloud-upload"></span>
                </div>
                <h4><?php _e('Glissez votre fichier de menu ici', 'gastro-starter'); ?></h4>
                <p><?php _e('ou cliquez pour sélectionner un fichier', 'gastro-starter'); ?></p>
                <p class="file-types"><?php _e('Formats acceptés : PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, PPT, PPTX', 'gastro-starter'); ?></p>
                <input type="file" id="menu-file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.ppt,.pptx" style="display: none;">
                <button type="button" id="select-menu-file-button" class="button button-secondary"><?php _e('Sélectionner un fichier', 'gastro-starter'); ?></button>
            </div>
        <?php else : ?>
            <!-- Aperçu du fichier existant -->
            <div class="current-menu-file">
                <div class="file-preview">
                    <?php if (in_array($file_type, ['jpg', 'jpeg', 'png'])) : ?>
                        <img src="<?php echo esc_url($menu_pdf); ?>" alt="<?php echo esc_attr($post->post_title); ?>" style="max-width: 200px; max-height: 150px; border-radius: 4px;">
                    <?php else : ?>
                        <div class="file-icon-large">
                            <span class="dashicons dashicons-media-document"></span>
                            <span class="file-type"><?php echo esc_html(strtoupper($file_type)); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="file-details">
                    <h4><?php echo esc_html(basename($menu_pdf)); ?></h4>
                    <?php if ($file_size) : ?>
                        <p class="file-size"><?php echo esc_html($file_size); ?></p>
                    <?php endif; ?>
                    
                    <div class="file-actions">
                        <a href="<?php echo esc_url($menu_pdf); ?>" target="_blank" class="button button-secondary">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php echo esc_html($view_text); ?>
                        </a>
                        <button type="button" id="change-menu-file" class="button">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Changer le fichier', 'gastro-starter'); ?>
                        </button>
                        <button type="button" id="remove-menu-file" class="button button-link-delete">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Supprimer', 'gastro-starter'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Zone de remplacement (cachée par défaut) -->
            <div id="replacement-drop-area" class="menu-drop-area" style="display: none;">
                <div class="upload-icon">
                    <span class="dashicons dashicons-cloud-upload"></span>
                </div>
                <h4><?php _e('Glissez votre nouveau fichier ici', 'gastro-starter'); ?></h4>
                <p><?php _e('ou cliquez pour sélectionner un fichier', 'gastro-starter'); ?></p>
                <input type="file" id="replacement-file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.ppt,.pptx" style="display: none;">
                <div class="replacement-actions">
                    <button type="button" id="select-replacement-file" class="button button-secondary"><?php _e('Sélectionner un fichier', 'gastro-starter'); ?></button>
                    <button type="button" id="cancel-replacement" class="button"><?php _e('Annuler', 'gastro-starter'); ?></button>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Champ caché pour l'URL du fichier -->
        <input type="hidden" id="menu_pdf" name="menu_pdf" value="<?php echo esc_attr($menu_pdf); ?>">
        
        <!-- Barre de progression -->
        <div id="menu-upload-progress" class="menu-upload-progress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-fill"></div>
                <span class="progress-text">0%</span>
            </div>
            <p class="progress-message"><?php _e('Téléchargement en cours...', 'gastro-starter'); ?></p>
        </div>
    </div>

    <style>
    .gastro-starter-menu-upload {
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
    }

    .menu-drop-area {
        border: 2px dashed #b4b9be;
        border-radius: 8px;
        padding: 40px 20px;
        text-align: center;
        background: #fafafa;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }

    .menu-drop-area:hover,
    .menu-drop-area.dragover {
        border-color: #0073aa;
        background: rgba(0, 115, 170, 0.05);
    }

    .menu-drop-area .upload-icon .dashicons {
        font-size: 48px;
        color: #b4b9be;
        margin-bottom: 15px;
    }

    .menu-drop-area h4 {
        margin: 10px 0;
        color: #23282d;
        font-size: 16px;
        font-weight: 600;
    }

    .menu-drop-area p {
        margin: 5px 0;
        color: #666;
        font-size: 14px;
    }

    .file-types {
        font-size: 12px !important;
        color: #999 !important;
        font-style: italic;
    }

    .current-menu-file {
        display: flex;
        align-items: center;
        gap: 20px;
        padding: 20px;
        background: #f8f9fa;
        border: 1px solid #e2e4e7;
        border-radius: 8px;
    }

    .file-preview {
        flex-shrink: 0;
    }

    .file-icon-large {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100px;
        height: 100px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .file-icon-large .dashicons {
        font-size: 36px;
        color: #666;
        margin-bottom: 5px;
    }

    .file-type {
        font-size: 11px;
        font-weight: bold;
        color: #999;
    }

    .file-details {
        flex: 1;
    }

    .file-details h4 {
        margin: 0 0 5px 0;
        font-size: 16px;
        color: #23282d;
    }

    .file-size {
        margin: 0 0 15px 0;
        color: #666;
        font-size: 13px;
    }

    .file-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .file-actions .button {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        font-size: 13px;
    }

    .replacement-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        margin-top: 15px;
    }

    .menu-upload-progress {
        margin-top: 20px;
        padding: 15px;
        background: #f0f8ff;
        border: 1px solid #bee5eb;
        border-radius: 6px;
    }

    .progress-bar {
        position: relative;
        height: 20px;
        background: #e2e4e7;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 10px;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(45deg, #0073aa, #005a87);
        border-radius: 10px;
        width: 0%;
        transition: width 0.3s ease;
    }

    .progress-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 12px;
        font-weight: bold;
        color: #fff;
        text-shadow: 0 1px 2px rgba(0,0,0,0.5);
    }

    .progress-message {
        margin: 0;
        text-align: center;
        color: #0073aa;
        font-size: 14px;
        font-weight: 500;
    }

    /* Animation pour le changement de fichier */
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .menu-drop-area {
        animation: slideDown 0.3s ease when visible;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        const dropAreas = ['#menu-drop-area', '#replacement-drop-area'];
        const fileInputs = ['#menu-file-input', '#replacement-file-input'];
        
        // Configuration pour chaque zone de drop
        dropAreas.forEach((dropAreaSelector, index) => {
            const dropArea = $(dropAreaSelector);
            const fileInput = $(fileInputs[index]);
            
            if (!dropArea.length || !fileInput.length) return;
            
            // Événements drag & drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea[0].addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea[0].addEventListener(eventName, () => dropArea.addClass('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropArea[0].addEventListener(eventName, () => dropArea.removeClass('dragover'), false);
            });

            // Gestion du drop
            dropArea[0].addEventListener('drop', function(e) {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleMenuFileUpload(files[0]);
                }
            }, false);

            // Clic sur la zone
            dropArea.on('click', function(e) {
                if (!$(e.target).is('button')) {
                    fileInput.click();
                }
            });

            // Changement de fichier
            fileInput.on('change', function() {
                if (this.files.length > 0) {
                    handleMenuFileUpload(this.files[0]);
                }
            });
        });

        // Boutons
        $('#select-menu-file-button, #select-replacement-file').on('click', function(e) {
            e.stopPropagation();
            const isReplacement = $(this).attr('id') === 'select-replacement-file';
            $(isReplacement ? '#replacement-file-input' : '#menu-file-input').click();
        });

        $('#change-menu-file').on('click', function() {
            $('.current-menu-file').hide();
            $('#replacement-drop-area').show();
        });

        $('#cancel-replacement').on('click', function() {
            $('#replacement-drop-area').hide();
            $('.current-menu-file').show();
            $('#replacement-file-input').val('');
        });

        $('#remove-menu-file').on('click', function() {
            if (confirm('<?php echo esc_js(__('Êtes-vous sûr de vouloir supprimer ce fichier ?', 'gastro-starter')); ?>')) {
                $('#menu_pdf').val('');
                $('.current-menu-file').hide();
                $('#menu-drop-area').show();
            }
        });

        function handleMenuFileUpload(file) {
            // Vérifier le type de fichier
            const allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
            const fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedTypes.includes(fileExtension)) {
                alert('<?php echo esc_js(__('Type de fichier non autorisé. Utilisez : PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, PPT, PPTX', 'gastro-starter')); ?>');
                return;
            }

            // Afficher la barre de progression
            $('#menu-upload-progress').show();
            $('.menu-drop-area').hide();
            $('.current-menu-file').hide();

            // Préparer FormData
            const formData = new FormData();
            formData.append('action', 'gastro_starter_upload_menu_file');
            formData.append('file', file);
            formData.append('security', '<?php echo wp_create_nonce("gastro_starter_upload_file"); ?>');

            // Upload AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                            updateProgress(percentComplete);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        $('#menu_pdf').val(response.data.url);
                        updateProgress(100);
                        
                        setTimeout(function() {
                            $('#menu-upload-progress').hide();
                            location.reload(); // Recharger pour voir l'aperçu
                        }, 1000);
                    } else {
                        alert('Erreur : ' + response.data.message);
                        $('#menu-upload-progress').hide();
                        $('#menu-drop-area').show();
                    }
                },
                error: function() {
                    alert('<?php echo esc_js(__('Erreur lors du téléchargement du fichier.', 'gastro-starter')); ?>');
                    $('#menu-upload-progress').hide();
                    $('#menu-drop-area').show();
                }
            });
        }

        function updateProgress(percent) {
            $('.progress-fill').css('width', percent + '%');
            $('.progress-text').text(percent + '%');
            
            if (percent === 100) {
                $('.progress-message').text('<?php echo esc_js(__('Téléchargement terminé !', 'gastro-starter')); ?>');
            }
        }
    });
    </script>
    <?php
}

/**
 * Enregistrer les données des champs personnalisés
 */
function gastro_starter_save_daily_menu_meta($post_id) {
    // Vérifier le nonce
    if (!isset($_POST['daily_menu_nonce']) || !wp_verify_nonce($_POST['daily_menu_nonce'], basename(__FILE__))) {
        return $post_id;
    }
    
    // Vérifier si l'utilisateur a les droits
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }
    
    // Éviter la sauvegarde automatique
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }
    
    // Mettre à jour les métadonnées
    if (isset($_POST['menu_pdf'])) {
        update_post_meta($post_id, '_menu_pdf', esc_url_raw($_POST['menu_pdf']));
    }
}
add_action('save_post_daily_menu', 'gastro_starter_save_daily_menu_meta');

/**
 * Fonction AJAX pour télécharger un fichier de menu
 */
function gastro_starter_upload_menu_file() {
    // Vérifier les droits
    if (!current_user_can('upload_files')) {
        wp_send_json_error(array('message' => __('Vous n\'avez pas les droits suffisants.', 'gastro-starter')));
    }
    
    // Vérifier nonce
    check_ajax_referer('gastro_starter_upload_file', 'security');
    
    // Gérer le téléchargement
    $uploaded_file = $_FILES['file'];
    
    if (!empty($uploaded_file)) {
        $file_type = wp_check_filetype(basename($uploaded_file['name']));
        
        // Vérifier si le type de fichier est autorisé
        $allowed_types = array(
            'pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'
        );
        
        if (!in_array($file_type['ext'], $allowed_types)) {
            wp_send_json_error(array('message' => __('Type de fichier non autorisé. Formats acceptés : PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, PPT, PPTX.', 'gastro-starter')));
        }
        
        // Préparer l'upload
        $upload = wp_handle_upload($uploaded_file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        } else {
            // Créer une pièce jointe
            $attachment = array(
                'post_mime_type' => $upload['type'],
                'post_title'     => sanitize_file_name($uploaded_file['name']),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );
            
            $attach_id = wp_insert_attachment($attachment, $upload['file']);
            
            if (is_wp_error($attach_id)) {
                wp_send_json_error(array('message' => $attach_id->get_error_message()));
            } else {
                // Générer les métadonnées
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
                
                wp_send_json_success(array(
                    'url'         => $upload['url'],
                    'attachment_id' => $attach_id,
                    'file_type'   => $file_type['ext']
                ));
            }
        }
    } else {
        wp_send_json_error(array('message' => __('Aucun fichier n\'a été téléchargé.', 'gastro-starter')));
    }
}
add_action('wp_ajax_gastro_starter_upload_menu_file', 'gastro_starter_upload_menu_file');
// Pour la rétrocompatibilité, conserver l'ancienne action
add_action('wp_ajax_gastro_starter_upload_menu_pdf', 'gastro_starter_upload_menu_file'); 