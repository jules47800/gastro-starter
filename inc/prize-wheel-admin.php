<?php
/**
 * Mon Restaurant - Prize Wheel Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gastro_Starter_Prize_Wheel_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Roue de la Fortune',
            'Roue de la Fortune',
            'manage_options',
            'gastro-starter-prize-wheel',
            [$this, 'render_admin_page'],
            'dashicons-chart-pie',
            50
        );
    }

    public function register_settings() {
        register_setting('gastro_starter_prize_wheel_group', 'gastro_starter_google_review_url');
        register_setting('gastro_starter_prize_wheel_group', 'gastro_starter_prize_email_subject');
        register_setting('gastro_starter_prize_wheel_group', 'gastro_starter_prize_wheel_prizes'); // Array of prizes
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_gastro-starter-prize-wheel') {
            return;
        }
        // Enqueue QR Code library
        wp_enqueue_script('qrcode-js', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', [], '1.0.0', true);
    }

    public function render_admin_page() {
        $prizes = get_option('gastro_starter_prize_wheel_prizes', []);
        if (empty($prizes)) {
            // Default prizes
            $prizes = [
                ['label' => 'Café offert', 'probability' => 20, 'color' => '#e74c3c', 'is_win' => 1, 'email_message' => 'Un café offert pour tout repas.'],
                ['label' => 'Perdu', 'probability' => 50, 'color' => '#34495e', 'is_win' => 0, 'email_message' => ''],
                ['label' => 'Dessert offert', 'probability' => 10, 'color' => '#f1c40f', 'is_win' => 1, 'email_message' => 'Un dessert offert.'],
            ];
        }
        ?>
        <div class="wrap">
            <h1>Roue de la Fortune</h1>
            
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                
                <!-- Settings Column -->
                <div style="flex: 2; min-width: 300px;">
                    <form method="post" action="options.php">
                        <?php settings_fields('gastro_starter_prize_wheel_group'); ?>
                        <?php do_settings_sections('gastro_starter_prize_wheel_group'); ?>

                        <div class="card" style="padding: 20px; margin-bottom: 20px;">
                            <h2>Configuration Générale</h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Lien Google Avis</th>
                                    <td>
                                        <input type="url" name="gastro_starter_google_review_url" value="<?php echo esc_attr(get_option('gastro_starter_google_review_url')); ?>" class="regular-text" placeholder="https://g.page/..." />
                                        <p class="description">Lien vers lequel l'utilisateur est redirigé pour laisser un avis.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Sujet de l'email</th>
                                    <td>
                                        <input type="text" name="gastro_starter_prize_email_subject" value="<?php echo esc_attr(get_option('gastro_starter_prize_email_subject', 'Votre gain chez Mon Restaurant !')); ?>" class="regular-text" />
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="card" style="padding: 20px;">
                            <h2>Segments de la Roue</h2>
                            <p class="description">Configurez les gains. La somme des probabilités doit faire 100 (ou sera pondérée).</p>
                            
                            <div id="prizes-container">
                                <?php foreach ($prizes as $index => $prize): ?>
                                    <div class="prize-item" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 4px;">
                                        <h3>Segment #<?php echo $index + 1; ?> <button type="button" class="button button-small remove-prize" style="float: right; color: #a00;">Supprimer</button></h3>
                                        
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                            <div>
                                                <label>Label</label><br>
                                                <input type="text" name="gastro_starter_prize_wheel_prizes[<?php echo $index; ?>][label]" value="<?php echo esc_attr($prize['label']); ?>" style="width: 100%;" />
                                            </div>
                                            <div>
                                                <label>Probabilité (Poids)</label><br>
                                                <input type="number" name="gastro_starter_prize_wheel_prizes[<?php echo $index; ?>][probability]" value="<?php echo esc_attr($prize['probability']); ?>" style="width: 100%;" />
                                            </div>
                                            <div>
                                                <label>Couleur (Hex)</label><br>
                                                <input type="color" name="gastro_starter_prize_wheel_prizes[<?php echo $index; ?>][color]" value="<?php echo esc_attr($prize['color']); ?>" style="width: 100%; height: 30px;" />
                                            </div>
                                            <div>
                                                <label>Type</label><br>
                                                <select name="gastro_starter_prize_wheel_prizes[<?php echo $index; ?>][is_win]" style="width: 100%;">
                                                    <option value="1" <?php selected($prize['is_win'], 1); ?>>Gagnant</option>
                                                    <option value="0" <?php selected($prize['is_win'], 0); ?>>Perdant</option>
                                                </select>
                                            </div>
                                            <div style="grid-column: span 2;">
                                                <label>Message Email (si gagnant)</label><br>
                                                <textarea name="gastro_starter_prize_wheel_prizes[<?php echo $index; ?>][email_message]" style="width: 100%;" rows="2"><?php echo esc_textarea($prize['email_message'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" id="add-prize" class="button button-secondary">Ajouter un segment</button>
                        </div>

                        <?php submit_button(); ?>
                    </form>
                </div>

                <!-- QR Code Column -->
                <div style="flex: 1; min-width: 300px;">
                    <div class="card" style="padding: 20px; text-align: center; position: sticky; top: 20px;">
                        <h2>QR Code Restaurant</h2>
                        <p>Scannez pour tester ou imprimez-le.</p>
                        
                        <div id="qrcode" style="margin: 20px auto; display: flex; justify-content: center;"></div>
                        
                        <p><strong>URL Cible :</strong> <a href="<?php echo site_url('/roue-de-la-fortune'); ?>" target="_blank"><?php echo site_url('/roue-de-la-fortune'); ?></a></p>
                        
                        <button type="button" class="button button-primary" onclick="printQRCode()">Imprimer le QR Code</button>
                    </div>
                </div>

            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // QR Code Generation
            var qrcode = new QRCode(document.getElementById("qrcode"), {
                text: "<?php echo site_url('/roue-de-la-fortune'); ?>",
                width: 256,
                height: 256,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });

            // Repeater Logic
            var container = document.getElementById('prizes-container');
            var addButton = document.getElementById('add-prize');

            addButton.addEventListener('click', function() {
                var count = container.children.length;
                var template = `
                    <div class="prize-item" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 4px;">
                        <h3>Segment #${count + 1} <button type="button" class="button button-small remove-prize" style="float: right; color: #a00;">Supprimer</button></h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label>Label</label><br>
                                <input type="text" name="gastro_starter_prize_wheel_prizes[${count}][label]" value="Nouveau Gain" style="width: 100%;" />
                            </div>
                            <div>
                                <label>Probabilité</label><br>
                                <input type="number" name="gastro_starter_prize_wheel_prizes[${count}][probability]" value="10" style="width: 100%;" />
                            </div>
                            <div>
                                <label>Couleur</label><br>
                                <input type="color" name="gastro_starter_prize_wheel_prizes[${count}][color]" value="#3498db" style="width: 100%; height: 30px;" />
                            </div>
                            <div>
                                <label>Type</label><br>
                                <select name="gastro_starter_prize_wheel_prizes[${count}][is_win]" style="width: 100%;">
                                    <option value="1">Gagnant</option>
                                    <option value="0">Perdant</option>
                                </select>
                            </div>
                            <div style="grid-column: span 2;">
                                <label>Message Email</label><br>
                                <textarea name="gastro_starter_prize_wheel_prizes[${count}][email_message]" style="width: 100%;" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', template);
            });

            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-prize')) {
                    if (confirm('Supprimer ce segment ?')) {
                        e.target.closest('.prize-item').remove();
                        // Re-index logic would be needed for clean array submission, 
                        // but PHP handles non-sequential keys fine usually, or we can just rely on array_values on save if needed.
                        // For simplicity in this rough admin, we rely on PHP to just take the posted array.
                    }
                }
            });
        });

        function printQRCode() {
            var printWindow = window.open('', '', 'height=600,width=800');
            var qrImg = document.querySelector('#qrcode img').src;
            printWindow.document.write('<html><head><title>QR Code Roue de la Fortune</title></head><body style="text-align:center;">');
            printWindow.document.write('<h1>Scannez pour jouer !</h1>');
            printWindow.document.write('<img src="' + qrImg + '" style="width: 400px; height: 400px;" />');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
        }
        </script>
        <?php
    }
}

new Gastro_Starter_Prize_Wheel_Admin();
