document.addEventListener("DOMContentLoaded", () => {
    const mainImageBtn = document.querySelector("input#mainImageBtn");
    const mainImageId = document.querySelector("input#mainImageId");
    const mainImagePreview = document.querySelector("div#mainImagePreview");
    const restImagesBtn = document.querySelector("input#restImagesBtn");
    const restImagesIds = document.querySelector("input#restImagesIds");
    const restImagesPreview = document.querySelector("div#restImagesPreview");
    const addEventBtn = document.querySelector("#addEventBtn");

    jQuery("#datepicker").datepicker({dateFormat: "yy-mm-dd", maxDate: 0});
    
    /* Выбор главной фотографии */
    const selectMainImageFrame = wp.media({
        title: 'Выберите главную фотографию',
        multiple : false,
        library : {
             type : 'image',
         }
    });

    const selectRestImagesFrame = wp.media({
        title: 'Выберите остальные фотографии',
        library : {
             type : 'image',
        },
        multiple : 'add',
    });

    selectMainImageFrame.on('select', () => {
        let selection = selectMainImageFrame.state().get('selection');
        let gallery_ids = [];
        selection.each(function(attachment) {
            if(attachment['id'].length != 0)
                gallery_ids[gallery_ids.length] = attachment['id'];
        });
        let ids = gallery_ids.join(",");
        if(ids.length === 0) return; //if closed withput selecting an image
        mainImageId.value = ids;
        refreshImages(gallery_ids, mainImagePreview);
    });

    selectMainImageFrame.on('open', () => {
        let selection = selectMainImageFrame.state().get('selection');
        let ids = mainImageId.value.split(',');
        ids.forEach((id) => {
            let attachment = wp.media.attachment(id);
            attachment.fetch();
            selection.add( attachment ? [ attachment ] : [] );
        });
    });

    mainImageBtn.addEventListener("click", (e) => {
        e.preventDefault();
        selectMainImageFrame.open();
    });

    /* Выбор остальных фотографий */

    selectRestImagesFrame.on('select', () => {
        let selection = selectRestImagesFrame.state().get('selection');
        let gallery_ids = [];
        selection.each(function(attachment) {
            gallery_ids[gallery_ids.length] = attachment['id'];
        });
        let ids = gallery_ids.join(",");
        if(ids.length === 0) {
            restImagesIds.value = "";
            restImagesPreview.innerHTML = "";
            return; //if closed withput selecting an image
        }
        restImagesIds.value = ids;
        refreshImages(gallery_ids, restImagesPreview);
    });

    selectRestImagesFrame.on('open', () => {
        let selection = selectRestImagesFrame.state().get('selection');
        let ids = restImagesIds.value.split(',');
        ids.forEach((id) => {
            let attachment = wp.media.attachment(id);
            attachment.fetch();
            selection.add( attachment ? [ attachment ] : [] );
        });
    });

    restImagesBtn.addEventListener("click", (e) => {
        e.preventDefault();
        selectRestImagesFrame.open();
    });

    function refreshImages(ids, element) {
        element.innerHTML = "";
        ids.forEach((id) => {
            fetch(ajaxurl + '?' + new URLSearchParams({
                action: 'library_reports_get_image_action',
                id: id
            }))
            .then((response) => {
                if(response.ok)
                    response.json().then(json => element.innerHTML += json.data.image);
            })
        });
    }

    // Нажатие на кнопку Добавить
    document.querySelector("#library-reports-add-event-form").addEventListener('submit', (e) => {
        e.preventDefault();
        window.tinyMCE.triggerSave();
        addEventBtn.disabled = true;
        const editorBody = window.tinyMCE.get('eventDescription').getContent();
        let body = new FormData(e.currentTarget);
        body.set('eventDescription', editorBody);
        fetch(ajaxurl, {
            method: "POST",
            body: body
        })
        .then((response) => {
            if(response.ok)
                response.json().then(json => printResult(json));
        })
    });

    function printResult(json) {
        document.querySelector("#lr_notifications").innerHTML = json.data;
        window.scroll({
            top: 0, 
            left: 0, 
            behavior: 'smooth' 
        });
        document.dispatchEvent(new Event("wp-updates-notice-added"));
        if(!json.success)
            addEventBtn.disabled = false;
    }
});