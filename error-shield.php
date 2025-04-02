<?php
/**
 * Plugin Name: Error Shield
 * Plugin URI: https://github.com/SDN33/error-shield
 * Description: Masque toutes les erreurs PHP et logs d'erreur sur le site client pour une expérience utilisateur plus professionnelle.
 * Version: 1.0.0
 * Author: Stéphane Dei-negri
 * Author URI: https://stillinov.com
 * License: GPL-2.0+
 * Text Domain: error-shield
 */

// Si ce fichier est appelé directement, on sort
if (!defined('ABSPATH')) {
    exit;
}

class Error_Shield {

    /**
     * Constructeur de la classe
     */
    public function __construct() {
        // Désactiver l'affichage des erreurs PHP
        $this->disable_error_display();
        
        // Intercepter les erreurs avant qu'elles soient affichées
        $this->setup_error_handling();
        
        // Ajouter un menu admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enregistrer les paramètres
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Désactiver l'affichage des erreurs PHP
     */
    private function disable_error_display() {
        // Désactiver l'affichage des erreurs au niveau PHP
        @ini_set('display_errors', 0);
        @ini_set('display_startup_errors', 0);
        
        // Définir le niveau de rapport d'erreurs à 0 pour ne pas afficher les erreurs
        error_reporting(0);
    }

    /**
     * Configurer la gestion des erreurs personnalisée
     */
    private function setup_error_handling() {
        // Définir un gestionnaire d'erreurs personnalisé
        set_error_handler(array($this, 'custom_error_handler'));
        
        // Définir un gestionnaire d'exceptions personnalisé
        set_exception_handler(array($this, 'custom_exception_handler'));
        
        // Définir une fonction de fermeture pour capturer les erreurs fatales
        register_shutdown_function(array($this, 'fatal_error_handler'));
        
        // Démarrer la mise en tampon de sortie pour capturer toute erreur qui pourrait être affichée
        ob_start(array($this, 'clean_output'));
    }

    /**
     * Gestionnaire d'erreurs personnalisé
     */
    public function custom_error_handler($errno, $errstr, $errfile, $errline) {
        // Obtenir les options d'enregistrement
        $options = get_option('error_shield_settings', array(
            'log_errors' => true,
            'log_location' => WP_CONTENT_DIR . '/error-shield-logs/'
        ));
        
        // Si l'enregistrement des erreurs est activé
        if ($options['log_errors']) {
            // S'assurer que le répertoire des logs existe
            if (!file_exists($options['log_location'])) {
                wp_mkdir_p($options['log_location']);
            }
            
            // Créer un message d'erreur formaté
            $error_message = sprintf(
                "[%s] Erreur %d: %s dans %s à la ligne %d\n",
                date('Y-m-d H:i:s'),
                $errno,
                $errstr,
                $errfile,
                $errline
            );
            
            // Écrire dans le fichier journal
            error_log($error_message, 3, $options['log_location'] . 'error_shield_' . date('Y-m-d') . '.log');
        }
        
        // Retourner true pour empêcher l'exécution du gestionnaire d'erreurs PHP standard
        return true;
    }

    /**
     * Gestionnaire d'exceptions personnalisé
     */
    public function custom_exception_handler($exception) {
        // Obtenir les options d'enregistrement
        $options = get_option('error_shield_settings', array(
            'log_errors' => true,
            'log_location' => WP_CONTENT_DIR . '/error-shield-logs/'
        ));
        
        // Si l'enregistrement des erreurs est activé
        if ($options['log_errors']) {
            // S'assurer que le répertoire des logs existe
            if (!file_exists($options['log_location'])) {
                wp_mkdir_p($options['log_location']);
            }
            
            // Créer un message d'exception formaté
            $error_message = sprintf(
                "[%s] Exception: %s dans %s à la ligne %d\n%s\n",
                date('Y-m-d H:i:s'),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );
            
            // Écrire dans le fichier journal
            error_log($error_message, 3, $options['log_location'] . 'error_shield_' . date('Y-m-d') . '.log');
        }
        
        // Rediriger vers la page d'accueil si nous sommes sur le front-end
        if (!is_admin()) {
            wp_redirect(home_url());
            exit;
        }
    }

    /**
     * Gestionnaire d'erreurs fatales
     */
    public function fatal_error_handler() {
        $error = error_get_last();
        
        // Si c'est une erreur fatale
        if ($error !== NULL && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR))) {
            // Obtenir les options d'enregistrement
            $options = get_option('error_shield_settings', array(
                'log_errors' => true,
                'log_location' => WP_CONTENT_DIR . '/error-shield-logs/'
            ));
            
            // Si l'enregistrement des erreurs est activé
            if ($options['log_errors']) {
                // S'assurer que le répertoire des logs existe
                if (!file_exists($options['log_location'])) {
                    wp_mkdir_p($options['log_location']);
                }
                
                // Créer un message d'erreur fatale formaté
                $error_message = sprintf(
                    "[%s] Erreur fatale: %s dans %s à la ligne %d\n",
                    date('Y-m-d H:i:s'),
                    $error['message'],
                    $error['file'],
                    $error['line']
                );
                
                // Écrire dans le fichier journal
                error_log($error_message, 3, $options['log_location'] . 'error_shield_' . date('Y-m-d') . '.log');
            }
            
            // Si nous sommes sur le front-end, afficher une page personnalisée si nécessaire
            if (!is_admin()) {
                // Vider la sortie précédente
                ob_end_clean();
                
                // Afficher le contenu du site normalement (sans l'erreur)
                // Vous pouvez également rediriger vers la page d'accueil
                wp_redirect(home_url());
                exit;
            }
        }
    }

    /**
     * Nettoyer la sortie pour supprimer les messages d'erreur
     */
    public function clean_output($buffer) {
        // Rechercher et supprimer les modèles d'erreurs PHP courants
        $patterns = array(
            '/<br \/>\n<b>.*?<\/b>.*?<b>.*?<\/b>.*?<br \/>/is',
            '/<b>.*?Warning.*?<\/b>.*?<br \/>/is',
            '/<b>.*?Notice.*?<\/b>.*?<br \/>/is',
            '/<b>.*?Fatal error.*?<\/b>.*?<br \/>/is',
            '/<b>.*?Parse error.*?<\/b>.*?<br \/>/is',
            '/\nWarning:.*?\n/is',
            '/\nNotice:.*?\n/is',
            '/\nFatal error:.*?\n/is',
            '/\nParse error:.*?\n/is'
        );
        
        // Remplacer les modèles par une chaîne vide
        $clean_buffer = preg_replace($patterns, '', $buffer);
        
        // Si le tampon est vide après le nettoyage, retourner le tampon original
        if (empty($clean_buffer)) {
            return $buffer;
        }
        
        return $clean_buffer;
    }

    /**
     * Ajouter un menu admin
     */
    public function add_admin_menu() {
        add_options_page(
            'Error Shield',
            'Error Shield',
            'manage_options',
            'error-shield',
            array($this, 'admin_page')
        );
    }

    /**
     * Enregistrer les paramètres
     */
    public function register_settings() {
        register_setting('error_shield_settings_group', 'error_shield_settings');
        
        add_settings_section(
            'error_shield_main_section',
            'Paramètres principaux',
            array($this, 'section_callback'),
            'error-shield'
        );
        
        add_settings_field(
            'log_errors',
            'Enregistrer les erreurs',
            array($this, 'log_errors_callback'),
            'error-shield',
            'error_shield_main_section'
        );
        
        add_settings_field(
            'log_location',
            'Emplacement des logs',
            array($this, 'log_location_callback'),
            'error-shield',
            'error_shield_main_section'
        );
    }

    /**
     * Rappel de section
     */
    public function section_callback() {
        echo '<p>Configurez les options d\'Error Shield pour masquer et gérer les erreurs PHP.</p>';
    }

    /**
     * Rappel pour l'option d'enregistrement des erreurs
     */
    public function log_errors_callback() {
        $options = get_option('error_shield_settings', array(
            'log_errors' => true
        ));
        
        printf(
            '<input type="checkbox" id="log_errors" name="error_shield_settings[log_errors]" value="1" %s />',
            checked(1, isset($options['log_errors']) ? $options['log_errors'] : true, false)
        );
        
        echo '<label for="log_errors"> Enregistrer les erreurs dans un fichier journal</label>';
    }

    /**
     * Rappel pour l'option d'emplacement des logs
     */
    public function log_location_callback() {
        $options = get_option('error_shield_settings', array(
            'log_location' => WP_CONTENT_DIR . '/error-shield-logs/'
        ));
        
        printf(
            '<input type="text" id="log_location" name="error_shield_settings[log_location]" value="%s" class="regular-text" />',
            esc_attr($options['log_location'])
        );
        
        echo '<p class="description">Chemin absolu vers le répertoire où les logs d\'erreurs seront stockés.</p>';
    }

    /**
     * Afficher la page d'administration
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('error_shield_settings_group');
                do_settings_sections('error-shield');
                submit_button('Enregistrer les paramètres');
                ?>
            </form>
            
            <div class="card">
                <h2>Vérification du fichier .htaccess</h2>
                <p>Pour une sécurité optimale, vous pouvez également ajouter les lignes suivantes à votre fichier .htaccess :</p>
                <pre style="background: #f1f1f1; padding: 10px; border-radius: 5px;">
php_flag display_errors off
php_flag display_startup_errors off
php_value error_reporting 0
                </pre>
                <?php
                $htaccess_file = ABSPATH . '.htaccess';
                if (file_exists($htaccess_file) && is_readable($htaccess_file)) {
                    $htaccess_content = file_get_contents($htaccess_file);
                    if (strpos($htaccess_content, 'php_flag display_errors off') !== false) {
                        echo '<p style="color: green;">✓ Votre fichier .htaccess contient déjà ces directives.</p>';
                    } else {
                        echo '<p style="color: orange;">⚠ Ces directives ne sont pas dans votre fichier .htaccess.</p>';
                    }
                } else {
                    echo '<p style="color: red;">❌ Impossible de lire le fichier .htaccess.</p>';
                }
                ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Logs d'erreurs</h2>
                <?php
                $options = get_option('error_shield_settings', array(
                    'log_errors' => true,
                    'log_location' => WP_CONTENT_DIR . '/error-shield-logs/'
                ));
                
                if ($options['log_errors']) {
                    if (file_exists($options['log_location'])) {
                        $log_files = glob($options['log_location'] . 'error_shield_*.log');
                        if (!empty($log_files)) {
                            echo '<p>Derniers fichiers de logs :</p>';
                            echo '<ul>';
                            foreach (array_slice($log_files, -5) as $log_file) {
                                echo '<li>' . basename($log_file) . ' - ' . size_format(filesize($log_file)) . '</li>';
                            }
                            echo '</ul>';
                            echo '<p><a href="#" class="button">Télécharger tous les logs</a> <a href="#" class="button">Vider les logs</a></p>';
                        } else {
                            echo '<p>Aucun fichier de log trouvé.</p>';
                        }
                    } else {
                        echo '<p>Le répertoire des logs n\'existe pas encore. Il sera créé automatiquement lorsqu\'une erreur se produira.</p>';
                    }
                } else {
                    echo '<p>L\'enregistrement des erreurs est désactivé.</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }
}

// Instancier la classe
$error_shield = new Error_Shield();
