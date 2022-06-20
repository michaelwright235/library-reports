<?php
class LibraryReportsCommon {
    const LIBRARY_INPUT_DESC = 'Библиотека';
    const LIBRARY_DATE_DESC = 'Дата';
    /**
     * @var array Поля отчета в формате $name => $description.
     */
    const FIELDS = array(
        'tBookPeople' => 'Число посещений для получения библиотечно-информационных услуг',
        'booksOut' => 'Количество выданной литературы',
        'booksIn' => 'Количество сданной литературы',
        'tEvntIn' => 'Количество мероприятий в стационаре',
        'tPplIn14' => 'Посетили мероприятия в стационаре до 14 лет',
        'tPplIn1530' => 'Посетили мероприятия в стационаре 15-30 лет',
        'tPplIn30' => 'Посетили мероприятия в стационаре старше 30 лет',
        'tPplInFree' => 'Посетили мероприятия в стационаре бесплатно',
        'tPplInPaid' => 'Посетили мероприятия в стационаре платно',
        'tPplOut' => 'Количество мероприятий вне стационара',
        'tPplOut14' => 'Посетили мероприятия вне стационара до 14 лет',
        'tPplOut1530' => 'Посетили мероприятия вне стационара 15-30 лет',
        'tPplOut30' => 'Посетили мероприятия вне стационара старше 30 лет',
        'tPplOutFree' => 'Посетили мероприятия вне стационара бесплатно',
        'tPplOutPaid' => 'Посетили мероприятия вне стационара платно',
        'tIncome' => 'Общий объем доходов от оказания платных услуг',
        'ecb' => 'ЭЧБ'
    );

    /**
     * Получение всех доступных библиотек для текущего пользователя
     *
     * @return array Массив с объектами библиотек. Объект содержит поля id, name, rights.
     */
    public static function get_available_libraries() : array {
        $optLibs = json_decode(get_option(LibraryReportsSettings::SETTINGS_OPT_LIBRARIES));
        if ( current_user_can( 'manage_options' ) ) {
            return $optLibs; // Разрешаем админам все :)
        }
        $currentUserId = get_current_user_id();
        $availableLibraries = [];
        foreach($optLibs as $lib) {
            $rights = explode(',', $lib->rights);
            if(!in_array($currentUserId, $rights)) continue;
            $availableLibraries[] = $lib;
        }
        return $availableLibraries;
    }

    /**
     * Доступна ли библиотека для текущего пользователя
     *
     * @param string $id ID библиотеки
     * @return boolean
     */
    public static function is_library_available(string $id) : bool {
        $availableLibraries = self::get_available_libraries();
        foreach($availableLibraries as $lib) {
            if($id === $lib->id) return true;
        }
        return false;
    }

    /**
     * Создать HTML-элемент \<select\> со всеми доступными для текущего
     * пользователя библиотеками
     *
     * @param string $inputName Тег name для элемента
     * @return string HTML-код
     */
    public static function create_library_select_input(string $inputName) : string {
        $availableLibraries = self::get_available_libraries();
        $selectHtml = sprintf('<select name="%1$s" id="%1$s">', $inputName);
        foreach($availableLibraries as $lib) {
            $selectHtml .= sprintf('<option value="%s">%s</option>', $lib->id, $lib->name);
        }
        $selectHtml .= '</select>';
        return $selectHtml;
    }

    /**
     * Получение массива дат, для которых уже был отправлен отчет
     *
     * @param integer $daysAgo На сколько дней искать назад 
     * @return array
     */
    public static function get_completed_reports(int $daysAgo) : array {
        $completed_reports = [];
        $availableLibraries = self::get_available_libraries();
        foreach($availableLibraries as $lib) {
            $dateTo = new DateTime('now');
            $dateFrom = new DateTime('now'); 
            $dateFrom->modify("-$daysAgo day");
            $completed_reports[$lib->id] = LibraryReportsDb::get_reports_between_dates(
                $lib->id,
                $dateFrom->format('Y-m-d'),
                $dateTo->format('Y-m-d'),
            );
        }
        return $completed_reports;
    }

    public static function get_all_completed_reports() {
        $completed_reports = [];
        $availableLibraries = self::get_available_libraries();
        foreach($availableLibraries as $lib) {
            $completed_reports[$lib->id] = LibraryReportsDb::get_all_library_reports_dates($lib->id);
        }
        return $completed_reports;
    }

    /**
     * Создать HTML-элемент \<label\> для поля с данным именем
     * из массива FIELDS
     *
     * @param string $name Название поля
     * @return string
     */
    public static function get_label_for(string $name) : string { 
        return sprintf('<label for="%s">%s</label>', $name, self::FIELDS[$name]);
    }

    public static function sendReportViaMail($report) {
        if(current_user_can( 'manage_options' )) {
            return;
        }
        $content = '';

        $libId = $report['library'];
        $optLibs = json_decode(get_option(LibraryReportsSettings::SETTINGS_OPT_LIBRARIES));
        $libName = '';
        foreach($optLibs as $lib) {
            if($lib->id == $libId) $libName = $lib->name;
        }
        $content .= "<h2>$libName</h2>";
        $content .= "<h3>Дата: " .$report['date']. "</h3>";
        $content .= '<table border="1" bordercolor="#858585" cellspacing="0">';

        $fields = [];
        foreach(LibraryReportsCommon::FIELDS as $f => $v) {
            $fields[$f] = $report[$f];
        }
        foreach($fields as $f => $v) {
            $content .= "<tr>";
            $content .= '<th style="text-align:left;">' . LibraryReportsCommon::FIELDS[$f] . "</th>";
            $content .= '<td style="text-align:left;">' . $v . '</td>';
            $content .= "</tr>";
        }
        $content .= "</table>";

        $email = wp_get_current_user()->user_email;
        $subject = 'Отчет за ' . $report['date'];

        add_filter( 'wp_mail_content_type', function( $content_type ) {
            return "text/html";
        } );
        
        wp_mail( $email, $subject, $content );
    }

    public static function get_wp_notification(string $text, bool $isError = true) : string {
        ob_start();
        $type = $isError ? 'error' : 'updated';
        add_settings_error( 'library_reports_messages', 'library_reports_message', $text, $type );
        settings_errors( 'library_reports_messages' );
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
}
