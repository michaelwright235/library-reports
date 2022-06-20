<?php

class LibraryReportsDb {
    const DB_NAME = 'library_reports';

    /**
     * Создает базу данных при первой активации плагина
     *
     * @return void
     */
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
    
        // Check to see if the table exists already, if not, then create it
        if($wpdb->get_var( "show tables like '$library_reports_sql'" ) != $library_reports_db ) 
        {
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $library_reports_sql );
        }
    }

    /**
     * Добавляет или обновляет отчет, переданный в запросе POST
     *
     * @return void
     */
    public static function create_db_entity() {
        global $wpdb; 
        $library_reports_db = $wpdb->prefix . self::DB_NAME;  // table name

        // Проверка кода
        if(!wp_verify_nonce($_POST['_wpnonce'], LibraryReportsFrontend::ACTION_NAME))
            wp_send_json_error( 
                LibraryReportsCommon::get_wp_notification("Ошибка, попробуйте еще раз")
            );

        // Проверка отчета
        if(($checkResult = self::check_report($_POST)) !== true)
            wp_send_json_error( 
                LibraryReportsCommon::get_wp_notification("Ошибка: $checkResult")
            );

        $content = [];
        foreach(LibraryReportsCommon::FIELDS as $f => $v) {
            $content[$f] = $_POST[$f];
        }

        $updating = false;
        // Если отчета еще не существует, то добавляем
        if( !self::report_exists($_POST['library'], $_POST['date']) ) {
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
        }
        // Если отчет нужно обновить
        else {
            $updating = true;
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
            $result = $wpdb->update($library_reports_db, $insertion, $where, $format, $formatWhere);
        }
        
        LibraryReportsCommon::sendReportViaMail($_POST); // Посылаем письмо на почту

        if($result === false) 
            wp_send_json_error( 
                LibraryReportsCommon::get_wp_notification('Ошибка запроса базы данных')
            );
        else
            wp_send_json_success( 
                LibraryReportsCommon::get_wp_notification(
                    (!$updating) ? 'Отчет успешно добавлен' : 'Отчет успешно обновлен', false)
            );
    }

    /**
     * Импортирует данные из строки JSON
     *
     * @param string $JSONstring JSON строка
     * @return int Количество внененных записей
     */
    public static function import_reports($JSONstring) {
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

    /**
     * Проверяет отчет на правильность
     *
     * @param array $report Массив с отчетом
     * @return string|true Строка с ошибкой в случае неудачи, true в обратном случае
     */
    public static function check_report($report) {
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

    /**
     * Проверяет, существует ли отчет за указанный день
     *
     * @param string $lib ID библиотеки
     * @param string $date Дата
     * @return bool
     */
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

    /**
     * Получает отчеты данной библиотеки в промежуток дат
     *
     * @param string $lib ID библиотеки
     * @param string $dateFrom Начальная дата
     * @param string $dateTo Конечная дата
     * @return array|false Массив c отчетами, либо false, если не найдено
     */
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

    /**
     * Получает все отчеты данной библиотеки
     *
     * @param string $lib ID библиотеки
     * @return array|false Массив c отчетами, либо false, если не найдено
     */
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

    /**
     * Получает отчет за один день данной библиотеки
     *
     * @param string $lib ID библиотеки
     * @param string $date Дата отчета
     * @return array|false Отчет, либо false, если не найдено
     */
    public static function get_single_day_report($lib, $date) {
        global $wpdb; 
        $library_reports_db = $wpdb->prefix . self::DB_NAME;
        $sql = "SELECT * FROM `$library_reports_db` WHERE `library_id` = $lib AND `report_date` = '$date'";
        $dateResult = $wpdb->get_results($sql);
        return $dateResult;
    }

}
