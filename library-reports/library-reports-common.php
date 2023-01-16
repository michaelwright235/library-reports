<?php

/**
 * Перечисление типов полей отчета
 */
enum ReportFieldType {
    /**
     * Отображается только название поля слева в таблице (заглушка)
     */
    case FieldNameOnly;
    /**
     * Отображается только значение поля справа в таблице (заглушка)
     */
    case FieldValueOnly;
    /**
     * Числовое поле: type="text" autocomplete="off" inputmode="numeric"
     */
    case Numeric;
    /**
     * Поле для ввода даты через DatePicker (jQuery)
     */
    case Date;
    /**
     * Список библиотек
     */
    case Library;
}

/**
 * Класс для одного поля отчета
 */
class ReportField {
    private string $name, $desc;
    private ReportFieldType $type;

    public function __construct(string $name, string $desc, ReportFieldType $type) {
        $this->name = $name;
        $this->desc = $desc;
        $this->type = $type;
    }

    /**
     * Создает \<label\> для текущего поля
     *
     * @return string
     */
    private function get_label() : string { 
        return sprintf('<label for="%s">%s</label>', $this->name, $this->desc);
    }

    /**
     * Создает числовое поле inputmode="numeric"
     *
     * @return string
     */
    private function get_numeric_input() : string {
        return sprintf('<input type="text" autocomplete="off" inputmode="numeric" name="%1$s" id="%1$s">', $this->name);
    }

    /**
     * Создает поле ввода даты через DatePicker jQuery
     *
     * @return string
     */
    private function get_date_input() : string {
        return sprintf('<input type="text" autocomplete="off" id="datepicker" readonly name="%s">', $this->name);
    }

    /**
     * Смотрите LibraryReportsCommon::create_library_select_input
     *
     * @return string
     */
    private function get_library_select_input() : string {
        return LibraryReportsCommon::create_library_select_input($this->name);
    }

    /**
     * Возвращает название поля в виде \<label\>.
     * В случае FieldNameOnly выводится $desc, FieldValueOnly - пустая строка.
     *
     * @return string
     */
    public function get_field_name() : string {
        if ($this->type == ReportFieldType::FieldNameOnly)
            return $this->desc;
        if ($this->type == ReportFieldType::FieldValueOnly)
            return '';
        return self::get_label();
    }

    /**
     * Возвращает \<input\> в соответствие с указанным типом поля.
     * В случае FieldNameOnly - пустая строка, FieldValueOnly - $desc, 
     *
     * @return string
     */
    public function get_field_value() : string {
        $html = '';
        switch($this->type) {
            case ReportFieldType::Numeric:
                $html = self::get_numeric_input();
                break;
            case ReportFieldType::Library:
                $html = self::get_library_select_input();
                break;
            case ReportFieldType::Date:
                $html = self::get_date_input();
                break;
            case ReportFieldType::FieldValueOnly:
                $html = $this->desc;
                break;
        }
        return $html;
    }

    public static function array_to_fields(array $arr) : array {
        $fields = [];
        foreach($arr as $f) {
            array_push($fields, new self($f[0], $f[1], $f[2]));
        }
        return $fields;
    }
}

/**
 * Класс общих для других функций и констант
 */
class LibraryReportsCommon {
    /**
     * @var array<string,string,ReportFieldType> Поля отчета в формате [$name, $description, $type : ReportFieldType].
     */
    const FIELDS = [
        ['library', 'Библиотека', ReportFieldType::Library],
        ['date', 'Дата', ReportFieldType::Date],
        ['tBookPeople','Число посещений для получения библиотечно-информационных услуг', ReportFieldType::Numeric],
        ['booksOut','Количество выданной литературы', ReportFieldType::Numeric],
        ['booksIn','Количество сданной литературы', ReportFieldType::Numeric],
        ['', '<h2>Мероприятия в стационаре</h2>', ReportFieldType::FieldNameOnly],
        ['tEvntIn','Количество мероприятий в стационаре', ReportFieldType::Numeric],
        ['tPplIn14','Посетили мероприятия в стационаре до 14 лет', ReportFieldType::Numeric],
        ['tPplIn1530','Посетили мероприятия в стационаре 15-30 лет', ReportFieldType::Numeric],
        ['tPplIn30','Посетили мероприятия в стационаре старше 30 лет', ReportFieldType::Numeric],
        ['', '<strong>Важно! "до 14 лет" + "15-30 лет" + "старше 30 лет" = "бесплатно" + "платно"</strong>', ReportFieldType::FieldNameOnly],
        ['tPplInFree','Посетили мероприятия в стационаре бесплатно', ReportFieldType::Numeric],
        ['tPplInPaid','Посетили мероприятия в стационаре платно', ReportFieldType::Numeric],
        ['', '<p id="sumStatusInside"></p>', ReportFieldType::FieldValueOnly],
        ['', '<h2>Мероприятия вне стационара</h2>', ReportFieldType::FieldNameOnly],
        ['tPplOut','Количество мероприятий вне стационара', ReportFieldType::Numeric],
        ['tPplOut14','Посетили мероприятия вне стационара до 14 лет', ReportFieldType::Numeric],
        ['tPplOut1530','Посетили мероприятия вне стационара 15-30 лет', ReportFieldType::Numeric],
        ['tPplOut30','Посетили мероприятия вне стационара старше 30 лет', ReportFieldType::Numeric],
        ['', '<strong>Важно! "до 14 лет" + "15-30 лет" + "старше 30 лет" = "бесплатно" + "платно"</strong>', ReportFieldType::FieldNameOnly],
        ['tPplOutFree','Посетили мероприятия вне стационара бесплатно', ReportFieldType::Numeric],
        ['tPplOutPaid','Посетили мероприятия вне стационара платно', ReportFieldType::Numeric],
        ['', '<p id="sumStatusOutside"></p>', ReportFieldType::FieldValueOnly],
        ['', '<h2>---</h2>', ReportFieldType::FieldNameOnly],
        ['tIncome','Общий объем доходов от оказания платных услуг', ReportFieldType::Numeric],
        ['ecb','ЭЧБ', ReportFieldType::Numeric],
        ['tRegPplWithAgreement','Количество заполненных соглашений об обработке персональных данных', ReportFieldType::Numeric],
    ];

    /**
     * Возвращает все поля, кроме пустых заглушек, полей даты и библиотеки.
     *
     * @return array<string,string,ReportFieldType>
     */
    public static function get_valueble_fields() : array{
        $fields = [];
        foreach(self::FIELDS as $f) {
            if ($f[0] == '' || $f[0] == 'date' || $f[0] == 'library')
                continue;
            array_push($fields, $f);
        }
        return $fields;
    }

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
     * Отправляет на e-mail пользователя отправленный отчет
     *
     * @param array $report
     * @return void
     */
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
        foreach(LibraryReportsCommon::get_valueble_fields() as $f) {
            $fields[$f[0]] = $report[$f[0]];
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

    /**
     * Возвращает HTML версию уведомления WordPress
     *
     * @param string $text Текст для вывода
     * @param boolean $isError Если ошибка, то будет красное уведомление, если нет - зеленое
     * @return string HTML уведомления
     */
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
