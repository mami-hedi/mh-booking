<?php
/**
 * Plugin Name: MH Booking - TidyCal Advanced
 * Description: Réservation WordPress style TidyCal avec créneaux disponibles, couleurs par type, multi-view et confirmation visuelle.
 * Version: 1.3
 * Author: MH Digital Solution
 */

if(!defined('ABSPATH')) exit;

/** === Enqueue Assets === */
function mh_booking_enqueue_assets(){
    wp_enqueue_style('fullcalendar-css','https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css');
    wp_enqueue_style('mh-booking-css',plugin_dir_url(__FILE__).'assets/mh-booking.css');
    wp_enqueue_script('fullcalendar-js','https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',['jquery'],null,true);
    wp_enqueue_script('mh-booking-js',plugin_dir_url(__FILE__).'assets/mh-booking.js',['jquery','fullcalendar-js'],null,true);

    wp_localize_script('mh-booking-js','mhBookingAjax',[
        'ajax_url'=>admin_url('admin-ajax.php'),
        'nonce'=>wp_create_nonce('mh_booking_nonce')
    ]);
}
add_action('wp_enqueue_scripts','mh_booking_enqueue_assets');

/** === Tables à l'activation === */
function mh_booking_create_tables(){
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table_bookings = $wpdb->prefix.'mh_bookings';
    $table_types = $wpdb->prefix.'mh_booking_types';

    $sql1 = "CREATE TABLE $table_bookings (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        type_id mediumint(9) NOT NULL,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(50),
        datetime datetime NOT NULL,
        message text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id)
    ) $charset;";

    $sql2 = "CREATE TABLE $table_types (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        type_name varchar(100) NOT NULL,
        slot_interval int DEFAULT 60,
        color varchar(7) DEFAULT '#00a000',
        PRIMARY KEY(id)
    ) $charset;";

    require_once(ABSPATH.'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);

    // Valeurs initiales
    $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table_types");
    if(!$exists){
        $wpdb->insert($table_types,['type_name'=>'Création','slot_interval'=>60,'color'=>'#0073aa']);
        $wpdb->insert($table_types,['type_name'=>'Refonte','slot_interval'=>60,'color'=>'#ff6600']);
        $wpdb->insert($table_types,['type_name'=>'Maintenance','slot_interval'=>60,'color'=>'#009900']);
    }
}
register_activation_hook(__FILE__,'mh_booking_create_tables');

/** === Menu Admin === */
function mh_booking_admin_menu(){
    add_menu_page('MH Booking','MH Booking','manage_options','mh-booking','mh_booking_admin_page','dashicons-calendar-alt',26);
    add_submenu_page('mh-booking','Types de rendez-vous','Types','manage_options','mh-booking-types','mh_booking_types_page');
}
add_action('admin_menu','mh_booking_admin_menu');

/** === Liste réservations admin === */
function mh_booking_admin_page(){
    global $wpdb;
    $table = $wpdb->prefix.'mh_bookings';
    $types_table = $wpdb->prefix.'mh_booking_types';
    $bookings = $wpdb->get_results("SELECT b.*, t.type_name FROM $table b LEFT JOIN $types_table t ON b.type_id=t.id ORDER BY datetime DESC"); ?>
    <div class="wrap"><h1>Réservations MH Booking</h1>
        <table class="wp-list-table widefat fixed striped">
            <tr><th>ID</th><th>Type</th><th>Nom</th><th>Email</th><th>Téléphone</th><th>Date/Heure</th><th>Message</th></tr>
            <?php foreach($bookings as $b): ?>
            <tr>
                <td><?= esc_html($b->id) ?></td>
                <td><?= esc_html($b->type_name) ?></td>
                <td><?= esc_html($b->name) ?></td>
                <td><?= esc_html($b->email) ?></td>
                <td><?= esc_html($b->phone) ?></td>
                <td><?= esc_html($b->datetime) ?></td>
                <td><?= esc_html($b->message) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php }

/** === Admin types === */
function mh_booking_types_page(){
    global $wpdb;
    $table = $wpdb->prefix.'mh_booking_types';
    if(isset($_POST['new_type']) && !empty($_POST['new_type'])){
        $wpdb->insert($table,[
            'type_name'=>sanitize_text_field($_POST['new_type']),
            'slot_interval'=>intval($_POST['slot_interval']),
            'color'=>sanitize_text_field($_POST['color'])
        ]);
    }
    $types = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC"); ?>
    <div class="wrap">
        <h1>Types de rendez-vous</h1>
        <form method="POST">
            <input type="text" name="new_type" placeholder="Nom du type" required>
            <input type="number" name="slot_interval" value="60" placeholder="Intervalle minutes" required>
            <input type="color" name="color" value="#0073aa" required>
            <input type="submit" class="button button-primary" value="Ajouter">
        </form><br>
        <table class="wp-list-table widefat fixed striped">
            <tr><th>ID</th><th>Nom</th><th>Intervalle</th><th>Couleur</th></tr>
            <?php foreach($types as $t): ?>
            <tr>
                <td><?= esc_html($t->id) ?></td>
                <td><?= esc_html($t->type_name) ?></td>
                <td><?= esc_html($t->slot_interval) ?></td>
                <td><div style="width:20px;height:20px;background:<?=esc_html($t->color)?>"></div></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php }

/** === Shortcode Booking === */
function mh_booking_form_shortcode(){
    global $wpdb;
    $types = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."mh_booking_types ORDER BY id ASC");
    ob_start(); ?>
    <div class="mh-booking-card">
        <h2>Réservez votre rendez-vous</h2>
        <form id="mh-booking-form">
            <!-- Step 1 : Type -->
            <div class="mh-step" data-step="1" style="display:block;">
                <label>Type de rendez-vous</label>
                <select name="type_id" id="mh-type" required>
                    <option value="">Sélectionnez...</option>
                    <?php foreach($types as $t): ?>
                        <option value="<?=esc_attr($t->id)?>" data-color="<?=esc_attr($t->color)?>" data-interval="<?=esc_attr($t->slot_interval)?>">
                            <?=esc_html($t->type_name)?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="mh-next">Suivant</button>
            </div>
            <!-- Step 2 : Date & Heure -->
            <div class="mh-step" data-step="2" style="display:none;">
                <label>Sélectionnez une date</label>
                <div id="mh-calendar"></div>
                <p id="mh-selected-datetime" style="margin-top:10px; font-weight:bold; color:#0073aa;">Aucune date sélectionnée</p>
                <input type="hidden" name="datetime" id="mh-datetime" required>
                <button type="button" class="mh-prev">Précédent</button>
                <button type="button" class="mh-next">Suivant</button>
            </div>
            <!-- Step 3 : Infos client -->
            <div class="mh-step" data-step="3" style="display:none;">
                <label>Nom complet</label><input type="text" name="name" required>
                <label>Email</label><input type="email" name="email" required>
                <label>Téléphone</label><input type="text" name="phone">
                <label>Message</label><textarea name="message" placeholder="Décrivez votre projet..."></textarea>
                <button type="button" class="mh-prev">Précédent</button>
                <button type="submit">Confirmer</button>
            </div>
            <div id="mh-booking-response"></div>
        </form>
    </div>
<?php return ob_get_clean();
}
add_shortcode('mh_booking','mh_booking_form_shortcode');

/** === AJAX submit === */
function mh_booking_handle_ajax(){
    check_ajax_referer('mh_booking_nonce','nonce');
    global $wpdb;
    $table = $wpdb->prefix.'mh_bookings';

    $type_id = intval($_POST['type_id']);
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $datetime = sanitize_text_field($_POST['datetime']);
    $message = sanitize_textarea_field($_POST['message']);

    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE datetime=%s AND type_id=%d",$datetime,$type_id));
    if($exists) wp_send_json_error("Ce créneau est déjà réservé pour ce type.");

    $wpdb->insert($table,compact('type_id','name','email','phone','datetime','message'));

    wp_mail(get_option('admin_email'),"Nouvelle réservation MH Booking",
        "Nom: $name\nEmail: $email\nType ID: $type_id\nDate: $datetime\nMessage: $message");

    wp_mail($email,"Confirmation réservation","Bonjour $name,\nVotre réservation pour le type sélectionné le $datetime est confirmée.");

    wp_send_json_success("Réservation envoyée !");
}
add_action('wp_ajax_mh_booking_submit','mh_booking_handle_ajax');
add_action('wp_ajax_nopriv_mh_booking_submit','mh_booking_handle_ajax');

/** === AJAX get events === */
function mh_booking_get_events_ajax(){
    check_ajax_referer('mh_booking_nonce','nonce');
    global $wpdb;
    $table = $wpdb->prefix.'mh_bookings';
    $type_id = intval($_POST['type_id']);

    $type = $wpdb->get_row($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."mh_booking_types WHERE id=%d",$type_id));
    $slot_interval = $type ? intval($type->slot_interval) : 60;
    $color = $type ? $type->color : '#00a000';

    $booked = $wpdb->get_results($wpdb->prepare("SELECT datetime FROM $table WHERE type_id=%d",$type_id));
    $booked_slots = array_map(fn($b)=>$b->datetime,$booked);

    $events=[];
    $today = new DateTime();
    for($i=0;$i<60;$i++){
        $day = clone $today; $day->modify("+$i day");
        $dateStr = $day->format('Y-m-d');
        // Créneaux horaires standard
        $hours = ['09:00','10:00','11:00','14:00','15:00','16:00','17:00'];
        foreach($hours as $h){
            $dt = $dateStr.'T'.$h;
            if(in_array($dt,$booked_slots)){
                $events[]=['title'=>'Réservé','start'=>$dt,'color'=>'#ff4d4d','display'=>'background'];
            } else {
                $events[]=['title'=>'Disponible','start'=>$dt,'color'=>$color];
            }
        }
    }
    wp_send_json($events);
}
add_action('wp_ajax_mh_booking_get_events','mh_booking_get_events_ajax');
add_action('wp_ajax_nopriv_mh_booking_get_events','mh_booking_get_events_ajax');

