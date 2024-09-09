<?php
/*
Plugin Name: Custom CSV Uploader
Description: Un plugin pour uploader un fichier CSV et mapper des colonnes sur des custom fields.
Version: 1.0
Author: Votre Nom
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Ajouter une page dans le menu de l'admin
add_action('admin_menu', 'csv_uploader_menu');

function csv_uploader_menu() {
    add_menu_page(
        'CSV Uploader', 
        'CSV Uploader', 
        'manage_options', 
        'csv-uploader', 
        'csv_uploader_page'
    );
}

// Page du plugin dans le back-office
function csv_uploader_page() {
    // Récupérer tous les CPT
    $args = array(
        'public'   => true,
        '_builtin' => false
    );
    $output = 'names';
    $operator = 'and';
    $post_types = get_post_types($args, $output, $operator);
    ?>
    <div class="wrap">
        <h1>Upload CSV et Mappage des Méta-données</h1>
        <form enctype="multipart/form-data" method="POST" action="">
            <label for="post_type">Sélectionnez un Custom Post Type:</label>
            <select name="post_type" id="post_type">
                <?php foreach ($post_types as $post_type) { ?>
                    <option value="<?php echo $post_type; ?>"><?php echo $post_type; ?></option>
                <?php } ?>
            </select><br><br>

            <label for="csv_file">Uploader un fichier CSV :</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv"><br><br>

            <label for="csv_column">Nom de la colonne du CSV :</label>
            <input type="text" name="csv_column" id="csv_column"><br><br>

            <label for="meta_key">Nom de la Meta Key :</label>
            <input type="text" name="meta_key" id="meta_key"><br><br>

            <label for="csv_id_column">Colonne de l'identifiant du CSV :</label>
            <input type="text" name="csv_id_column" id="csv_id_column"><br><br>

            <label for="meta_key_id">Meta Key de l'identifiant :</label>
            <input type="text" name="meta_key_id" id="meta_key_id"><br><br>

            <input type="submit" name="submit_csv" value="Valider">
        </form>
    </div>
    <?php

    if (isset($_POST['submit_csv'])) {
        handle_csv_upload();
    }
}

// Gérer l'upload du fichier CSV et mettre à jour les meta values
function handle_csv_upload() {
    if ( isset($_FILES['csv_file']) && !empty($_POST['post_type']) && !empty($_POST['csv_column']) && !empty($_POST['meta_key']) ) {
        $csv_file = $_FILES['csv_file']['tmp_name'];
        $csv_column = strtolower(trim(sanitize_text_field($_POST['csv_column'])));
        $meta_key = sanitize_text_field($_POST['meta_key']);
        $csv_id_column = strtolower(trim(sanitize_text_field($_POST['csv_id_column'])));
        $meta_key_id = sanitize_text_field($_POST['meta_key_id']);
        $post_type = sanitize_text_field($_POST['post_type']);
        
        // Ouvrir le fichier CSV avec un point-virgule comme séparateur
        if (($handle = fopen($csv_file, "r")) !== FALSE) {
            // Lire la première ligne (header) et retirer les caractères invisibles
            $header = array_map('trim', fgetcsv($handle, 1000, ";"));
            $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]); // Suppression des caractères BOM ou non imprimables
            $header = array_map('strtolower', $header); // Tout mettre en minuscules

            // Debug : afficher les en-têtes du CSV et la colonne entrée
            echo '<pre>';
            echo 'En-têtes du CSV : ' . print_r($header, true) . '<br>';
            echo 'Colonne entrée : ' . $csv_column . '<br>';
            echo 'Colonne ID entrée : ' . $csv_id_column . '<br>';
            echo '</pre>';

            // Trouver l'index des colonnes
            $csv_column_index = array_search($csv_column, $header);
            $csv_id_column_index = array_search($csv_id_column, $header);

            if ($csv_column_index === false) {
                echo "<div class='error'>La colonne '$csv_column' est introuvable dans le CSV. Veuillez vérifier que le nom correspond exactement à celui du fichier CSV.</div>";
                return;
            }

            if ($csv_id_column_index === false) {
                echo "<div class='error'>La colonne d'identifiant '$csv_id_column' est introuvable dans le CSV. Veuillez vérifier que le nom correspond exactement à celui du fichier CSV.</div>";
                return;
            }

            // Lire et traiter chaque ligne du fichier CSV
            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                $csv_value = $data[$csv_column_index];
                $csv_id_value = $data[$csv_id_column_index];

                // Rechercher le post par meta_key_id et csv_id_value
                $args = array(
                    'post_type' => $post_type,
                    'meta_query' => array(
                        array(
                            'key' => $meta_key_id,
                            'value' => $csv_id_value,
                            'compare' => '='
                        )
                    )
                );

                $query = new WP_Query($args);
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        $post_id = get_the_ID();
                        update_post_meta($post_id, $meta_key, $csv_value);
                    }
                }
                wp_reset_postdata();
            }

            fclose($handle);
            echo "<div class='updated'>Mise à jour terminée avec succès !</div>";
        }
    } else {
        echo "<div class='error'>Veuillez remplir tous les champs.</div>";
    }
}
