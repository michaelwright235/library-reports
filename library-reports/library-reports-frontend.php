<?php

class LibraryReportsFrontend {
    const ACTION_NAME = 'library_reports_submit';
    const SINGLE_REPORT_ACTION_NAME = 'library_reports_get_single_report';
    const PAGE_NAME = 'library-reports';

    public static function create_menu() { 
        add_menu_page('Отчет по дню', 'Отчет по дню', 'edit_posts', self::PAGE_NAME, array('LibraryReportsFrontend', 'draw_page'), 'dashicons-media-spreadsheet', 2);
    }

    public static function draw_page() {
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
            <div id="resultsFor"></div>
            <a href="javascript:window.print();" id="reportPrint"><span class="dashicons dashicons-printer"></span></a>
            <form action="<?php echo admin_url( 'admin.php' ); ?>" method="post" id="library-reports-report-form">
                <table class="library-report-table form-table" role="presentation">
                    <?php 
                    $fields = ReportField::array_to_fields(LibraryReportsCommon::FIELDS);
                    foreach($fields as $field) {
                        ?>
                    <tr>
                        <th scope="row"><?php echo $field->get_field_name(); ?></th>
                        <td><?php echo $field->get_field_value(); ?></td>
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
    
    public static function ajax_get_single_report() {
        if(!wp_verify_nonce($_POST['_wpnonce'], self::ACTION_NAME) ||
            !LibraryReportsCommon::is_library_available($_POST['library']) ||
            !DateTime::createFromFormat('Y-m-d', $_POST['date'])) {
            wp_send_json_error();
        }
        wp_send_json_success( 
            LibraryReportsDb::get_single_day_report($_POST['library'], $_POST['date'])
        );
    }

    public static function enqueue_styles_scripts() {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.13.1/themes/base/jquery-ui.css');  
        wp_enqueue_style('library-reports-style', plugins_url('library-reports/library-reports-styles.css'));
        wp_enqueue_script('library_report_script', plugins_url('library-reports/library-reports-frontend-script.js'));
    }
}

add_action('admin_menu', array('LibraryReportsFrontend', 'create_menu'));

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
