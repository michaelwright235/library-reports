<?php

class LibraryReportsExport {
    const EXPORT_ACTION = 'library_reports_export_action';
    const PAGE_NAME = 'library-reports-export';

    public static function create_menu() {
        add_submenu_page(
            LibraryReportsFrontend::PAGE_NAME,
            'Выгрузка отчетов',
            'Выгрузка',
            'edit_posts',
            self::PAGE_NAME,
            array('LibraryReportsExport', 'draw_page'));
    }
    public static function draw_page() {
        ?>
        <script>
            var LIBRARY_REPORTS_FIELD_NAMES = {};
            <?php
                    foreach(LibraryReportsCommon::get_valueble_fields() as $field) {
                        $desc = esc_html($field[1]);
                        echo "LIBRARY_REPORTS_FIELD_NAMES['$field[0]'] = '$desc';\n";
                    }
            ?>
            var LIBRARY_REPORTS_ADDITIONAL_FIELD_NAMES = {};
            LIBRARY_REPORTS_ADDITIONAL_FIELD_NAMES['totalUsers'] = '<i>Общее количество пользователей</i>';
            LIBRARY_REPORTS_ADDITIONAL_FIELD_NAMES['totalFreeUsers'] = '<i>Общее на безвозмездной основе</i>';
            LIBRARY_REPORTS_ADDITIONAL_FIELD_NAMES['totalEvents'] = '<i>Общее количество мероприятий</i>';
            var LIBRARY_REPORTS_LIBS = <?php echo get_option(LibraryReportsSettings::SETTINGS_OPT_LIBRARIES);?>;
        </script>
        <style>
        @media print {
            body {
                background: #fff;
            }
            div#reportResults {
                display: block;
            }
            div#wrap1, div#adminmenumain, #wpfooter, #reportPrint {
                display: none;
            }
            #wpcontent {
                margin-left: 0;
            }
        }
        </style>
        <div class="wrap" id="wrap1">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="<?php echo admin_url( 'admin.php' ); ?>" method="post" id="library-reports-export-form">
                <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Библиотека</th>
                    <td>
                        <?php
                        echo LibraryReportsCommon::create_library_select_input('library');
                        ?>
                    </td>
                    </tr>
                    <tr>
                        <th scope="row">Выгрузить</th>
                        <td>
                            <table class="no-td-padding">
                                <tr><td style="vertical-align:top">
                                    <input type="radio" id="exportInterval" name="whatToExport" value="exportInterval" checked>
                                </td>
                                <td>
                                    <label for="exportInterval">Промежуток</label>
                                    <p><input type="text" id="datepickerFrom" readonly name="dateFrom"></p>
                                    <p><input type="text" id="datepickerTo" readonly name="dateTo"></p>
                                    <p><input type="checkbox" id="exportSeparatly" name="exportSeparatly">
                                    <label for="exportSeparatly">Выгрузить отчеты отдельно</label></p>
                                </td></tr>
                                <tr><td style="vertical-align:top">
                                <input type="radio" id="exportSingleDate" name="whatToExport" value="exportSingleDate">
                                </td>
                                <td>
                                    <label for="exportSingleDate">Один день</label>
                                    <p><input type="text" id="datepickerSingleDate" readonly name="singleDate"></p>
                                </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="action" value="<?php echo self::EXPORT_ACTION ?>" />
                <?php
                wp_nonce_field(self::EXPORT_ACTION);
                submit_button( 'Выгрузить' );
                ?>
            </form>
        </div>
        <div class="wrap" id="wrap2"><div id="reportResults"></div></div>

        <?php
    }

    public static function export_submitted() {
        if(!wp_verify_nonce($_POST['_wpnonce'], self::EXPORT_ACTION))
            wp_send_json_error();

        $lib = $_POST['library'];
        if(!LibraryReportsCommon::is_library_available($lib)) exit();
        $whatToExport = $_POST['whatToExport'];

        // Экспорт одной даты
        if($whatToExport == 'exportSingleDate') {
            $date = DateTime::createFromFormat('Y-m-d', $_POST['singleDate']);
            if(!$date) wp_send_json_error();
            $result = LibraryReportsDb::get_single_day_report(
                $lib,
                $date->format('Y-m-d')
            );
            wp_send_json_success($result);
        }

        // Экспорт интервала дат
        if($whatToExport == 'exportInterval') {
            $dateFrom = DateTime::createFromFormat('Y-m-d', $_POST['dateFrom']);
            $dateTo = DateTime::createFromFormat('Y-m-d', $_POST['dateTo']);
            $dateTo->modify('+1 day');
            if(!$dateFrom || !$dateTo)
                wp_send_json_success();

            $reports = [];
            while($dateFrom->format('Y-m-d') != $dateTo->format('Y-m-d')) {
                $date = $dateFrom->format('Y-m-d');
                $result = LibraryReportsDb::get_single_day_report($lib, $date);
                if(count($result) != 0) {
                    $reports[] = $result;
                }
                $dateFrom->modify('+1 day');
            }
            //if(count($result) == 1) $result = $result[0];
            wp_send_json_success($reports);
        }
    }

    public static function enqueue_styles_scripts() {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.13.1/themes/base/jquery-ui.css');  
        wp_enqueue_script('library_report_script', plugins_url('library-reports/library-reports-export-script.js'));
        wp_enqueue_style('library-reports-style', plugins_url('library-reports/library-reports-styles.css'));
    }
}

add_action( 'admin_menu', array('LibraryReportsExport', 'create_menu'));

if(isset($_GET['page']) && $_GET['page'] == LibraryReportsExport::PAGE_NAME) {
    add_action( 'admin_enqueue_scripts', array('LibraryReportsExport', 'enqueue_styles_scripts') );
}

if( wp_doing_ajax() ) {
    add_action( 'wp_ajax_'.LibraryReportsExport::EXPORT_ACTION, array('LibraryReportsExport', 'export_submitted') );
}
