<?php

class LibraryReportsFrontend {
    const ACTION_NAME = 'library_reports_submit';
    const SINGLE_REPORT_ACTION_NAME = 'library_reports_get_single_report';
    const PAGE_NAME = 'library-reports';

    public function create_menu() { 
        add_menu_page('Отчет по дню', 'Отчет по дню', 'edit_posts', self::PAGE_NAME, array($this, 'draw_page'), 'dashicons-media-spreadsheet', 2);
    }

    public function draw_page() {
        $fields = [
            LibraryReportsCommon::LIBRARY_INPUT_DESC => LibraryReportsCommon::create_library_select_input('library'),
            LibraryReportsCommon::LIBRARY_DATE_DESC => '<input type="text" autocomplete="off" id="datepicker" readonly name="date">',
            LibraryReportsCommon::get_label_for('tBookPeople') => self::create_numeric_input('tBookPeople'),
            LibraryReportsCommon::get_label_for('booksOut') => self::create_numeric_input('booksOut'),
            LibraryReportsCommon::get_label_for('booksIn') => self::create_numeric_input('booksIn'),
            '<h2>Мероприятия в стационаре</h2>' => '',
            LibraryReportsCommon::get_label_for('tEvntIn') => self::create_numeric_input('tEvntIn'),
            LibraryReportsCommon::get_label_for('tPplIn14') => self::create_numeric_input('tPplIn14'),
            LibraryReportsCommon::get_label_for('tPplIn1530') => self::create_numeric_input('tPplIn1530'),
            LibraryReportsCommon::get_label_for('tPplIn30') => self::create_numeric_input('tPplIn30'),
            '<strong>Важно! "до 14 лет" + "15-30 лет" + "старше 30 лет" = "бесплатно" + "платно"</strong>' => '',
            LibraryReportsCommon::get_label_for('tPplInFree') => self::create_numeric_input('tPplInFree'),
            LibraryReportsCommon::get_label_for('tPplInPaid') => self::create_numeric_input('tPplInPaid') . '<br><p id="sumStatusInside"></p>',
            '<h2>Мероприятия вне стационара</h2>' => '',
            LibraryReportsCommon::get_label_for('tPplOut') => self::create_numeric_input('tPplOut'),
            LibraryReportsCommon::get_label_for('tPplOut14') => self::create_numeric_input('tPplOut14'),
            LibraryReportsCommon::get_label_for('tPplOut1530') => self::create_numeric_input('tPplOut1530'),
            LibraryReportsCommon::get_label_for('tPplOut30') => self::create_numeric_input('tPplOut30'),
            '<strong>Важно! "до 14 лет" + "15-30 лет" + "старше 30 лет" = "бесплатно" + "платно"</strong> ' => '',
            LibraryReportsCommon::get_label_for('tPplOutFree') => self::create_numeric_input('tPplOutFree'),
            LibraryReportsCommon::get_label_for('tPplOutPaid') => self::create_numeric_input('tPplOutPaid') . '<br><p id="sumStatusOutside"></p>',
            '<h2>---</h2>' => '',
            LibraryReportsCommon::get_label_for('tIncome') => self::create_numeric_input('tIncome'),
            LibraryReportsCommon::get_label_for('ecb') => self::create_numeric_input('ecb')
        ];

        ?>
        <style>
        @media print {
            body {
                background: #fff;
            }
            div#adminmenumain, #wpfooter, #submit, #reportPrint {
                display: none;
            }
            #wpcontent {
                margin-left: 0;
            }
            .library-report-table td, .library-report-table th {
                padding: 0;
                font-size: 12px;
                padding: 0 5px;
                vertical-align: middle;
                border: 1px solid rgb(133, 133, 133);
            }
            .wp-admin input[type=text], .wp-admin select {
                padding: 0 !important;
                background: none !important;
                border: none !important;
                font-size: 12px;
            }
            .library-report-table h2 {
                margin: 5px 0;
            }
            .wrap {
                margin:0;
            }
            html.wp-toolbar {
                padding-top: 0;
            }
        }
        </style>
        <script>
            <?php
            // Ищем, в какие дни не было отчетов (в течение месяца)
            if(current_user_can( 'manage_options' )) { // если админ
                echo 'let library_reports_completed_reports = '
                    . json_encode( LibraryReportsCommon::get_all_completed_reports() ) . ";\n";
                echo "library_reports_edit_time = null;\n";
                echo "library_reports_max_date = null;\n";
            } else {
                echo 'let library_reports_completed_reports = '
                    . json_encode(LibraryReportsCommon::get_completed_reports(31) ) . ";\n";
                echo 'let library_reports_edit_time = -'
                    . get_option( LibraryReportsSettings::SETTINGS_OPT_EDIT_TIME ) . ";\n";
                echo "let library_reports_max_date = 0;\n";
            }
            ?>
        </script>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <a href="javascript:window.print();" id="reportPrint"><span class="dashicons dashicons-printer"></span></a>
            <div id="resultsFor"></div>
            <form action="<?php echo admin_url( 'admin.php' ); ?>" method="post" id="library-reports-report-form">
                <table class="library-report-table form-table" role="presentation">
                    <?php foreach($fields as $name => $html) {?>
                    <tr>
                        <th scope="row"><?php echo $name; ?></th>
                        <td><?php echo $html; ?></td>
                    </tr>
                    <?php } ?>
                </table>
                <p id="library-reports-form-status"></p>
                <input type="hidden" name="action" value="<?php echo self::ACTION_NAME ?>" id="form_action"/>
                <?php
                wp_nonce_field(self::ACTION_NAME);
                submit_button( 'Отправить отчет' );
                ?>
            </form>
        </div>
        <?php
    } 

    public function create_numeric_input($name) {
        return sprintf('<input type="text" autocomplete="off" inputmode="numeric" name="%1$s" id="%1$s">', $name);
    }

    public static function ajax_get_single_report() {
        if(!wp_verify_nonce($_POST['_wpnonce'], self::ACTION_NAME)) {
            exit();
        }
        if( !LibraryReportsCommon::is_library_available($_POST['library']) ||
            !DateTime::createFromFormat('Y-m-d', $_POST['date']) ) {
            exit();
        }

        echo json_encode(LibraryReportsDb::get_single_day_report($_POST['library'], $_POST['date']));
        exit();
    }

    public static function enqueue_styles_scripts() {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.13.1/themes/base/jquery-ui.css');  
        wp_enqueue_style('library-reports-style', plugins_url('library-reports/library-reports-styles.css'));
        wp_enqueue_script('library_report_script', plugins_url('library-reports/library-reports-frontend-script.js'));
    }
}

add_action('admin_menu', function() {
    ( new LibraryReportsFrontend() )->create_menu();
});

if(isset($_GET['page']) && $_GET['page'] == LibraryReportsFrontend::PAGE_NAME) {
    add_action(
        'admin_enqueue_scripts',
        array('LibraryReportsFrontend', 'enqueue_styles_scripts')
    );
}

if( wp_doing_ajax() ) {
    add_action(
        'wp_ajax_'.LibraryReportsFrontend::ACTION_NAME,
        array('LibraryReportsDb', 'create_db_entity')
    );
    add_action(
        'wp_ajax_'.LibraryReportsFrontend::SINGLE_REPORT_ACTION_NAME,
        array('LibraryReportsFrontend', 'ajax_get_single_report')
    );
}
