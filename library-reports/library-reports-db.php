<?php

class LibraryReportsDb {
    const DB_NAME = 'library_reports';

    public static function create_reports_db() {      
        global $wpdb; 
        $charset_collate = $wpdb->get_charset_collate();
    
        $library_reports_db = $wpdb->prefix . self::DB_NAME;  // table name
        $library_reports_sql = "CREATE TABLE $library_reports_db (
                id int(11) UNSIGNED NOT NULL auto_increment,
                library_id int(2) UNSIGNED NOT NULL,
                report_creation_date datetime NOT NULL,
                report_date date NOT NULL,
                content text NOT NULL,
                UNIQUE KEY id (id)
        ) $charset_collate;";
    
        //Check to see if the table exists already, if not, then create it
        if($wpdb->get_var( "show tables like '$library_reports_sql'" ) != $library_reports_db ) 
        {
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $library_reports_sql );
        }
    }

    public static function create_db_entity() {
        global $wpdb; 
        $library_reports_db = $wpdb->prefix . self::DB_NAME;  // table name

        // Проверка кода
        if(!wp_verify_nonce($_POST['_wpnonce'], LibraryReportsFrontend::ACTION_NAME)) {
            add_settings_error( 'library_reports_messages', 'library_reports_message', "Ошибка, попробуйте еще раз", 'error' );
            settings_errors( 'library_reports_messages' );
            exit();
        }

        // Проверка отчета
        if(($checkResult = self::check_report($_POST)) !== true) {
            add_settings_error( 'library_reports_messages', 'library_reports_message', "Ошибка: $checkResult", 'error' );
            settings_errors( 'library_reports_messages' );
            exit();
        }

        $content = [];
        foreach(LibraryReportsCommon::FIELDS as $f => $v) {
            $content[$f] = $_POST[$f];
        }

        $insertion = array(
            'library_id' => $_POST['library'],
            'report_creation_date' => date("Y-m-d H:i:s.u"),
            'report_date' => $_POST['date'],
            'content' => json_encode($content)
        );
        $format = array(
            '%d',
            '%s',
            '%s',
            '%s'
        );
        $result = $wpdb->insert($library_reports_db, $insertion, $format);
        
        add_settings_error( 'library_reports_messages', 'library_reports_message', "Отчет успешно добавлен", 'updated' );
        settings_errors( 'library_reports_messages' );

        LibraryReportsCommon::sendReportViaMail($_POST);

        exit();
    }

    public static function update_db_entity() {
        global $wpdb; 
        $library_reports_db = $wpdb->prefix . self::DB_NAME;  // table name

        // Проверка кода
        if(!wp_verify_nonce($_POST['_wpnonce'], LibraryReportsFrontend::ACTION_NAME)) {
            add_settings_error( 'library_reports_messages', 'library_reports_message', "Ошибка, попробуйте еще раз", 'error' );
            settings_errors( 'library_reports_messages' );
            exit();
        }

        // Проверка отчета
        if(($checkResult = self::check_report($_POST, false)) !== true) {
            add_settings_error( 'library_reports_messages', 'library_reports_message', "Ошибка: $checkResult", 'error' );
            settings_errors( 'library_reports_messages' );
            exit();
        }

        $content = [];
        foreach(LibraryReportsCommon::FIELDS as $f => $v) {
            $content[$f] = $_POST[$f];
        }

        $insertion = array(
            'content' => json_encode($content)
        );
        $where = array(
            'library_id' => $_POST['library'],
            'report_date' => $_POST['date'],
        );
        $format = array(
            '%s'
        );
        $formatWhere = array(
            '%d',
            '%s'
        );
        $wpdb->update(
            $library_reports_db,
            $insertion,
            $where,
            $format,
            $formatWhere
        );
        add_settings_error( 'library_reports_messages', 'library_reports_message', "Отчет успешно обновлен", 'updated' );
        settings_errors( 'library_reports_messages' );
        LibraryReportsCommon::sendReportViaMail($_POST);
        exit();
    }

    public static function import_reports($JSONstring) {
        /*
        $_contentJ = file_get_contents(__DIR__ . "/exportReports.txt");
        $r = LibraryReportsDb::import_reports($_contentJ);
        echo "Внесено: $r отчетов";
        */
        global $wpdb; 
        $library_reports_db = $wpdb->prefix . self::DB_NAME;  // table name

        $json = json_decode($JSONstring);
        $format = array(
            '%d',
            '%s',
            '%s',
            '%s'
        );
        $i = 0;
        foreach($json as $obj) {
            $insertion = array(
                'library_id' => $obj->library,
                'report_creation_date' => date("Y-m-d H:i:s.u"),
                'report_date' => $obj->date,
                'content' => $obj->content
            );
            $result = $wpdb->insert($library_reports_db, $insertion, $format);
            if($result !== false) $i++;
        }
        return $i;
    }

    public static function check_report($report, $checkIfExists = true) {
        // Проверка на возможность вставки отчета пользователем
        $libId = $report['library'];
        if(!LibraryReportsCommon::is_library_available($libId))
            return "У вас нет прав на добавление отчета в эту библиотеку";

        // Проверка даты
        $rDate = $report['date'];
        $date = DateTime::createFromFormat('Y-m-d', $rDate);
        if(!$date) {
            return "Указана неверная дата";
        }
        if($checkIfExists && self::report_exists($libId, $rDate)) {
            return "Отчет за $rDate уже существует";
        }

        // Проверка на возможность создания отчета за эту дату
        if(!current_user_can( 'manage_options' )) {
            $editTime = get_option(LibraryReportsSettings::SETTINGS_OPT_EDIT_TIME);
            $minDate = new DateTime();
            $minDate->modify("-$editTime day");
            $found = false;
            for($i = -$editTime-1; $i<0; $i++) {
                if($minDate->format('Y-m-d') === $rDate) $found = true;
                $minDate->modify("+1 day");
            }
            if(!$found) {
                return "Невозможно создать отчет за этот день";
            }
        }

        // Проверка полей число >0 
        $toVerify = [];
        foreach(LibraryReportsCommon::FIELDS as $f => $v) {
            $toVerify[] = $report[$f];
        }
        foreach($toVerify as $f) {
            if(preg_match("/^\d+$/", $f) !== 1) {
                return "Одно из полей заполнено неправильно";
            }
        }

        // Проверка суммы
        $sum1 = intval($report['tPplIn14']) +
                intval($report['tPplIn1530']) +
                intval($report['tPplIn30']);
        $sum2 = intval($report['tPplInFree']) +
                intval($report['tPplInPaid']);
        if($sum1 !== $sum2) return "Ошибка суммы в стационаре";

        $sum3 = intval($report['tPplOut14']) +
                intval($report['tPplOut1530']) +
                intval($report['tPplOut30']);
        $sum4 = intval($report['tPplOutFree']) +
                intval($report['tPplOutPaid']);
        if($sum3 !== $sum4) return "Ошибка суммы вне стационара";

        return true;
    }

    public static function report_exists($lib, $date) {
        global $wpdb; 
        $library_reports_db = $wpdb->prefix . self::DB_NAME;  // table name

        $dateSql = "SELECT COUNT(*) as `c` FROM `$library_reports_db` WHERE `library_id` = $lib AND `report_date` = '$date'";
        $dateResult = $wpdb->get_results($dateSql);
        if($dateResult[0]->c > 0) {
            return true;
        }
        return false;
    }

    public static function get_reports_between_dates($lib, $dateFrom, $dateTo) {
        global $wpdb; 
        $library_reports_db = $wpdb->prefix . self::DB_NAME;  // table name
        $sql = "SELECT `report_date` FROM `$library_reports_db` WHERE `library_id` = $lib AND (`report_date` BETWEEN '$dateFrom' AND '$dateTo')";
        $dateResult = $wpdb->get_results($sql);
        if(count($dateResult) > 0) {
            $newResult = [];
            foreach($dateResult as $r) {
                $newResult[] = $r->report_date;
            }
            return $newResult;
        }
        return false;
    }

    public static function get_all_library_reports_dates($lib) {
        global $wpdb; 
        $library_reports_db = $wpdb->prefix . self::DB_NAME;  // table name
        $dateSql = "SELECT `report_date` FROM `$library_reports_db` WHERE `library_id` = $lib";
        $dateResult = $wpdb->get_results($dateSql);
        if(count($dateResult) > 0) {
            $newResult = [];
            foreach($dateResult as $r) {
                $newResult[] = $r->report_date;
            }
            return $newResult;
        }
        return false;
    }

    public static function get_single_day_report($lib, $date) {
        global $wpdb; 
        $library_reports_db = $wpdb->prefix . self::DB_NAME;
        $sql = "SELECT * FROM `$library_reports_db` WHERE `library_id` = $lib AND `report_date` = '$date'";
        $dateResult = $wpdb->get_results($sql);
        return $dateResult;
    }

}
