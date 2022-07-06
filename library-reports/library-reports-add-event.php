<?php

class LibraryReportsAddEvent {
    const PAGE_NAME = 'library-reports-add-event';
    const GETIMAGE_ACTION = 'library_reports_get_image_action';
    const ADD_EVENT_ACTION = 'library_reports_add_event_action';

    const GALLERY_HTML = <<<EOF
    <!-- wp:gallery {"columns":%1\$s, "linkTo":"media"} -->
        <figure class="wp-block-gallery has-nested-images columns-%1\$s is-cropped">%2\$s</figure>
    <!-- /wp:gallery -->
    EOF;

    const GALLERY_IMAGE_HTML = <<<EOF
    <!-- wp:image {"id":%4\$s,"sizeSlug":"full","linkDestination":"none"} -->
    <figure class="wp-block-image size-full">
        <a href="%1\$s"><img src="%2\$s" alt="%3\$s" class="wp-image-%4\$s"/></a>
    </figure>
    <!-- /wp:image -->
    EOF;
    const MAX_IMAGES_IN_GALLERY_ROW = 3;
    const YOUTUBE_VIDEO_SNIPPET = '<!-- wp:core-embed/youtube {"url":"%1$s","type":"video","providerNameSlug":"youtube","className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->'.
                                  '<figure class="wp-block-embed-youtube wp-block-embed is-type-video is-provider-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">'.
                                  '<div class="wp-block-embed__wrapper">"%1$s</div></figure><!-- /wp:core-embed/youtube -->';


    public static function create_menu() {
        add_submenu_page(
            LibraryReportsFrontend::PAGE_NAME,
            'Добавить мероприятие',
            'Добавить мероприятие',
            'edit_posts',
            self::PAGE_NAME,
            array('LibraryReportsAddEvent', 'draw_page'));
    }

    public static function draw_page() {
        ?>
        <style>
            div#mainImagePreview, div#restImagesPreview {
                padding-top:10px;
            }
            div#mainImagePreview img:not(:first-child),
            div#restImagesPreview img:not(:first-child) {
                padding-left:10px;
            }
            #datepicker {
                background-color: #fff;
                cursor:pointer;
            }
        </style>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <div id="lr_notifications"></div>
            <form action="<?php echo admin_url( 'admin.php' ); ?>" method="post" id="library-reports-add-event-form">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="library">Библиотека</label></th>
                        <td>
                            <?php
                            echo LibraryReportsCommon::create_library_select_input('library');
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Дата</th>
                        <td><input type="text" autocomplete="off" id="datepicker" readonly name="eventDate"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="eventTime">Время</label></th>
                        <td><input type="time" autocomplete="off" name="eventTime" id="eventTime"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="eventName">Название</label></th>
                        <td><input type="text" autocomplete="off" name="eventName" id="eventName" style="width:100%"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="editorid">Описание</label></th>
                        <td>
                            <?php
                            wp_editor( '', 'eventDescription', array(
                                'wpautop'       => 1,
                                'media_buttons' => 0,
                                'textarea_name' => 'eventDescription',
                                'textarea_rows' => 14,
                                'teeny'         => 0,
                                'dfw'           => 0,
                                'tinymce'       => array(
                                    'selector' => "#eventDescription",
                                    'resize'   => 'vertical',
                                    'menubar'  => false,
                                    'wpautop'  => true,
                                    'indent'   => false,
                                    'toolbar1' => 'undo, redo, formatselect, bold, italic, underline, strikethrough, removeformat, bullist, numlist, blockquote, hr, alignleft, aligncenter, alignright, link, unlink'
                                ),
                                'quicktags'     => 0,
                                'drag_drop_upload' => false
                            ) );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Главная фотография</th>
                        <td>
                            <input type="hidden" name="mainImageId" id="mainImageId" value="" />
                            <input type='button' class="button-primary" value="Выбрать изображение" id="mainImageBtn"/>
                            <div id="mainImagePreview"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Остальные фотографии</th>
                        <td>
                            <input type="hidden" name="restImagesIds" id="restImagesIds" value=""/>
                            <input type='button' class="button-primary" value="Выбрать изображения" id="restImagesBtn"/>
                            <div id="restImagesPreview"></div>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="action" value="<?php echo self::ADD_EVENT_ACTION ?>" />
                <?php
                wp_nonce_field(self::ADD_EVENT_ACTION);
                submit_button( 'Добавить мероприятие', 'primary', 'addEventBtn' );
                ?>
            </form>

        </div>
        <?php
    }

    public static function ajax_get_image() {
        if(isset($_GET['id']) ){
            $image = wp_get_attachment_image( filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT ), 'medium', false, array( 'id' => 'myprefix-preview-image' ) );
            $data = array(
                'image'    => $image,
            );
            wp_send_json_success( $data );
        } else {
            wp_send_json_error();
        }
        exit();
    }

    public static function ajax_add_event() {
        if(!wp_verify_nonce($_POST['_wpnonce'], self::ADD_EVENT_ACTION))
            wp_send_json_error(
                LibraryReportsCommon::get_wp_notification("Ошибка, перезагрузите страницу и попробуйте еще раз")
            );

        $post_date = $_POST['eventDate'] . ' ' . $_POST['eventTime'].':00';

        if( !DateTime::createFromFormat('Y-m-d H:i:s', $post_date) )
            wp_send_json_error(
                LibraryReportsCommon::get_wp_notification("Введите верную дату")
            );

        $post_title = sanitize_text_field( $_POST['eventName'] );
        if(strlen($post_title) == 0)
            wp_send_json_error(
                LibraryReportsCommon::get_wp_notification("Введите название мероприятия")
            );

        $post_content = $_POST['eventDescription'];
        if(strlen($post_content) == 0)
            wp_send_json_error(
                LibraryReportsCommon::get_wp_notification("Введите описание мероприятия")
            );

        $thumbnailId = explode(',', $_POST['mainImageId']);
        $meta_input = [];
        if ( count($thumbnailId) != 0 &&
             wp_get_attachment_image( $thumbnailId[0], 'thumbnail' ) ) {
            $meta_input = ['_thumbnail_id' => $_POST['mainImageId']];
        } else {
            wp_send_json_error(
                LibraryReportsCommon::get_wp_notification("Выберите главную фотографию")
            );
        }

        $restImages = explode(',', $_POST['restImagesIds']);
        array_unshift($restImages, $thumbnailId);
        if(count($restImages) != 0) {
            $imagesHtml = '';
            $imagesId = [];
            foreach($restImages as $img) {
                if(!wp_get_attachment_image($img)) continue;
                $imagesId[] = $img;
                $previewImg = wp_get_attachment_image_url($img, 'medium');
                $fullImg = wp_get_attachment_image_url($img, 'full');
                $alt = $post_title;
                $imagesHtml .= sprintf(self::GALLERY_IMAGE_HTML,
                        $fullImg,
                        $previewImg,
                        $alt,
                        $img);
            }

            if($imagesHtml != '') {
                $rows = self::MAX_IMAGES_IN_GALLERY_ROW;
                if($rows > count($restImages)) $rows = count($restImages);
                $imagesHtml = sprintf(
                    self::GALLERY_HTML,
                    $rows,
                    $imagesHtml);
                $post_content .= $imagesHtml;
            }
        }

        $optLibs = json_decode(get_option(LibraryReportsSettings::SETTINGS_OPT_LIBRARIES));
        $category = '1'; // без рубрики
        foreach($optLibs as $lib) {
            if($lib->id == $_POST['library'] 
                && category_exists((int)$lib->category))
                $category = (int)$lib->category;
        }

        $post_data = [
            'post_title'    => $post_title,
            'post_content'  => $post_content,
            'post_type'     => 'post',
            'post_date'     => $post_date,
            'post_status'   => 'publish',
            'post_category' => [$category],
            'meta_input'    => $meta_input
        ];

        $result = wp_insert_post($post_data);
        if(is_wp_error($result))
            wp_send_json_error(
                LibraryReportsCommon::get_wp_notification("Ошибка вставки записи")
            );
        $link = get_post_permalink($result);

        wp_send_json_success(
            LibraryReportsCommon::get_wp_notification("Мероприятие добавлено. <a href='$link'>Посмотреть</a>", false)
        );
    }

    public static function enqueue_styles_scripts() {
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.13.1/themes/base/jquery-ui.css'); 
        wp_enqueue_script('library_report_script', plugins_url('library-reports/library-reports-add-event-script.js')); 
    }
}

add_action( 'admin_menu', array('LibraryReportsAddEvent', 'create_menu'));

if(isset($_GET['page']) && $_GET['page'] == LibraryReportsAddEvent::PAGE_NAME) {
    add_action( 'admin_enqueue_scripts', array('LibraryReportsAddEvent', 'enqueue_styles_scripts') );
}

if( wp_doing_ajax() ) {
    add_action( 'wp_ajax_'.LibraryReportsAddEvent::GETIMAGE_ACTION, array('LibraryReportsAddEvent', 'ajax_get_image'));
    add_action( 'wp_ajax_'.LibraryReportsAddEvent::ADD_EVENT_ACTION, array('LibraryReportsAddEvent', 'ajax_add_event'));
}
