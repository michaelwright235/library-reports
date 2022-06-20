<?php

class LibraryReportsSettings {

    const SETTINGS_SECTION_NAME = 'library-reports-general-section';
    const SETTINGS_PAGE = 'library-reports-settings';
    const SETTINGS_OPT_LIBRARIES = 'library_reports_libraries';
    const SETTINGS_OPT_EDIT_TIME = 'library_reports_edit_time';
    const SETTINGS_IMPORT_ACTION = 'library_reports_import';

    public static function create_menu() { 
        add_submenu_page(
            LibraryReportsFrontend::PAGE_NAME,
            'Настройки отчетности',
            'Настройки', 'manage_options',
            self::SETTINGS_PAGE,
            array('LibraryReportsSettings', 'draw_page'));
    }

    public static function draw_page() {
        // проверка роли пользователя
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
    
        // add error/update messages
        // check if the user have submitted the settings
        // WordPress will add the "settings-updated" $_GET parameter to the url
        if ( isset( $_GET['settings-updated'] ) ) {
            // add settings saved message with the class of "updated"
            add_settings_error( 'library_reports_messages', 'library_reports_message', 'Настройки обновлены', 'updated' );
        }
    
        // show error/update messages
        settings_errors( 'library_reports_messages' );
        ?>
        <script>
            var libraryReportsUsers = 
            <?php 
                $users = get_users();
                echo '[';
                foreach($users as $user) {
                    echo "{id: '$user->id', name: '$user->display_name'},";
                }
                echo ']';
            ?>;
            var libraryReportsCategories = 
            <?php
            $categories = get_categories( [
                'taxonomy'     => 'category',
                'type'         => 'post',
                'orderby'      => 'name',
                'order'        => 'ASC',
            ] );
            echo '[';
            foreach($categories as $category) {
                echo "{id: '$category->term_id', name: '$category->name'},";
            }
            echo ']';
            ?>;

        </script>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post" id="library-reports-settings-form">
                <?php
                // output security fields for the registered setting "wporg"
                settings_fields( self::SETTINGS_PAGE );
                // output setting sections and their fields
                // (sections are registered for "wporg", each field is registered to a specific section)
                do_settings_sections( self::SETTINGS_PAGE );
                // output save settings button
                submit_button( 'Сохранить настройки' );
                ?>
            </form>
            <form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post" enctype="multipart/form-data">
                <input type="file" name="importReportsFile">
                <input type="hidden" name="action" value="<?php echo self::SETTINGS_IMPORT_ACTION ?>"/>
                <?php wp_nonce_field(self::SETTINGS_IMPORT_ACTION); ?>
                <button type="submit">Импорт</button>
            </form>
        </div>
        <?php
    }

    public static function init_settings() {
        add_settings_section(
            self::SETTINGS_SECTION_NAME, 
            '',  
            null,    
            self::SETTINGS_PAGE                   
        );

        add_settings_field(
            self::SETTINGS_OPT_LIBRARIES,
            'Библиотеки',
            array('LibraryReportsSettings', 'libraries_callback'),
            self::SETTINGS_PAGE,
            self::SETTINGS_SECTION_NAME,
            array(
                'id' => self::SETTINGS_OPT_LIBRARIES, 
                'option_name' => self::SETTINGS_OPT_LIBRARIES,
            )
        );
        register_setting(
            self::SETTINGS_PAGE,
            self::SETTINGS_OPT_LIBRARIES,
            array(
                'sanitize_callback' => array('LibraryReportsSettings', 'sanitize_libraries'),
                'default' => '{}'
            )
        );

        add_settings_field(
                self::SETTINGS_OPT_EDIT_TIME,
                'Время редактирования (в днях)',
                array('LibraryReportsSettings', 'edit_time_callback'),
                self::SETTINGS_PAGE,
                self::SETTINGS_SECTION_NAME,
                array(
                    'option_name' => self::SETTINGS_OPT_EDIT_TIME,
                    'label_for' => self::SETTINGS_OPT_EDIT_TIME
                )
        );
        register_setting(
            self::SETTINGS_PAGE,
            self::SETTINGS_OPT_EDIT_TIME,
            array(
                'sanitize_callback' => array('LibraryReportsSettings', 'sanitize_edit_time'),
                'default' => '1'
            )
        );
    }

    public static function sanitize_edit_time($val) {
        if(preg_match("/^\d+$/", $val) !== 1) {
            return "1";
        }
        return $val;
    }

    // Проверка введенных данных библиотек перед записью
    public static function sanitize_libraries($val) {
        $val = json_decode($val);
        if ($val == null) {
            return '{}';
        }

        $sanitizedLines = [];
        foreach($val as $line) {
            $sanitizedLine = [];

            if(!is_numeric($line->id)) continue;
            foreach($sanitizedLines as $sLine) {
                if($sLine['id'] === $line->id) continue;
            }
            $sanitizedLine['id'] = trim($line->id);

            $sanitizedLine['name'] = esc_html(trim($line->name));

            $rights = explode(',', $line->rights);
            $newRights = [];
            foreach($rights as $userId) {
                $userId = trim($userId);
                if(get_user_by('id', $userId) && !in_array($userId, $newRights)) {
                    $newRights[] = $userId;
                }
            }
            if(count($newRights) === 0) continue;
            $sanitizedLine['rights'] = implode(',', $newRights);

            $sanitizedLine['category'] = trim($line->category);

            $sanitizedLines[] = $sanitizedLine;
        }

        if(count($sanitizedLines) == 0) return '{}';
        return json_encode($sanitizedLines);
    }

    // Вывод таблицы библиотек
    public static function libraries_callback($val) {
        $data = get_option($val['option_name']);
        if (json_decode($data) == null) {
            $data = '{}';
        }
        echo "<script>const JSONdata = $data;</script>"
        ?>
    
        <table id="libraries_table">
            <tr>
                <td>ID (число)</td>
                <td>Название</td>
                <td>Права на добавление/редактирование</td>
                <td>Категория</td>
            </tr>
        </table>
        <button type="button" id="library_plus_btn">+</button>
        <input type="hidden" id="libraries" name="<?php echo $val['option_name']; ?>" value="<?php echo $data; ?>"/>
        <?php
    }

    // Вывод поля редактирования времени редактирования
    public static function edit_time_callback($val) {
        $option_name = $val['option_name'];
        ?>
        <input 
            type="text" 
            name="<?php echo $option_name; ?>" 
            id="<?php echo $option_name; ?>" 
            value="<?php echo esc_attr( get_option($option_name) ); ?>" 
        /> 
        <?php
    }

    public static function enqueue_styles_scripts() {
        wp_enqueue_style('library-reports-style', plugins_url('library-reports/library-reports-styles.css'));
        wp_enqueue_script('library_report_script', plugins_url('library-reports/library-reports-settings-script.js'));    
    }
}

add_action( 'admin_menu', array('LibraryReportsSettings','create_menu'));
add_action( 'admin_init', array('LibraryReportsSettings','init_settings'));

add_action('admin_post_'.LibraryReportsSettings::SETTINGS_IMPORT_ACTION, function() {
    if(count($_FILES) === 1 && $_FILES['importReportsFile']) {
        LibraryReportsDb::import_reports(file_get_contents($_FILES['importReportsFile']['tmp_name']));
    }
    wp_redirect($_POST['_wp_http_referer']);
});

if(isset($_GET['page']) && $_GET['page'] == 'library-reports-settings') {
    add_action( 'admin_enqueue_scripts', array('LibraryReportsSettings', 'enqueue_styles_scripts') );
}
