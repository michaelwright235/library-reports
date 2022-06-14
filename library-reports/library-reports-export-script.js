jQuery(document).ready(function($) {
    const reportResults = document.querySelector("#reportResults");
    // Настройка полей дат
    let params = {
        maxDate: 0,
        dateFormat: "yy-mm-dd"
    };
    $("#datepickerFrom").datepicker(params).on("change", function() {
            $("#datepickerTo").datepicker( "option", "minDate", getDate( this ) );
        });
    $("#datepickerTo").datepicker(params).on( "change", function() {
        $("#datepickerFrom").datepicker( "option", "maxDate", getDate( this ) );
    });
    $("#datepickerSingleDate").datepicker(params);
    
    function getDate( element ) {
        let date;
        try {
            date = jQuery.datepicker.parseDate( "yy-mm-dd", element.value );
        } catch( error ) {
            date = null;
            console.log(error);
        }
        return date;
    }

    // Нажатие на кнопку Выгрузить
    document.querySelector("#library-reports-export-form").addEventListener('submit', (e) => {
        e.preventDefault();
        $.ajax({
            url:      ajaxurl,
            type:     "POST",
            data: $("#library-reports-export-form").serialize(),
            success: (response) => { processResponse(response); },
            error: function(response) {
                console.log(response);
            }
        });
    });

    function processResponse(response) {
        let jsonR = JSON.parse(response);
        reportResults.innerHTML = "";
        if (jsonR.length == 1) {
            let r = jsonR[0];
            if(r.content !== undefined) {
                drawOneDayReport(r);
            } else if(r[0] !== undefined) {
                drawIntervalReport(jsonR);
            }
        } 
        else if (jsonR.length > 1) {
            drawIntervalReport(jsonR);
        }
        else {
            reportResults.innerText = "Отчет не найден";
        }
    }

    function drawOneDayReport(report) {
        let libraryId = report.library_id;
        let reportDate = report.report_date;
        let content = JSON.parse(report.content);

        reportResults.innerHTML += "<h2>"+getLibraryName(libraryId)+"</h2>";
        reportResults.innerHTML += "<h3>Дата: "+reportDate+"</h3>";
        drawTable(content);
    }

    function drawIntervalReport(response) {
        let exportSeparatly = document.querySelector("#exportSeparatly").checked;
        if(exportSeparatly) {
            for(let i = 0; i < response.length; i++) {
                drawOneDayReport(response[i][0]);
            }
            return;
        }

        let firstElementContent = JSON.parse(response[0][0].content);

        let libraryId = response[0][0].library_id;
        let dateFrom = document.querySelector("#datepickerFrom").value;
        let dateTo = document.querySelector("#datepickerTo").value;
        reportResults.innerHTML = "";
        reportResults.innerHTML += "<h2>"+getLibraryName(libraryId)+"</h2>";
        reportResults.innerHTML += "<h3>Дата: с "+escapeHtml(dateFrom)+" по "+escapeHtml(dateTo)+"</h3>";

        // Преобразуем в числа, чтобы суммировать
        for(let name in firstElementContent) {
            firstElementContent[name] = parseInt(firstElementContent[name]);
        }
        // Суммируем числа, начиная со второго элемента
        let dates = [];
        dates.push(response[0][0].report_date);
        for(let i = 1; i < response.length; i++) {
            let currentContent = JSON.parse(response[i][0].content);
            dates.push(response[i][0].report_date);
            for(let name in firstElementContent) {
                firstElementContent[name] += parseInt(currentContent[name]);
            }
        }
        reportResults.innerHTML += "<h4>"+dates.join(', ')+"</h4>";
        writeAdditionalRows(firstElementContent);
        drawTable(firstElementContent);
    }

    function writeAdditionalRows(content) {
        content["totalUsers"] =
            content.tBookPeople +
            content.tPplInFree + 
            content.tPplInPaid + 
            content.tPplOutFree + 
            content.tPplOutPaid;
        content["totalFreeUsers"] =
            content.tBookPeople +
            content.tPplInFree +
            content.tPplOutFree;
        content["totalEvents"] =
            content.tEvntIn +
            content.tPplOut;
    }

    function getLibraryName(libraryId) {
        let result = libraryId;
        LIBRARY_REPORTS_LIBS.forEach(element => {
            if(element.id === libraryId) {
                result = element.name;
            }
        });
        return result;
    }

    function drawTable(content) {
        let html = "";
        html += '<p><a href="javascript:window.print();" id="reportPrint"><span class="dashicons dashicons-printer"></span></a></p>';

        html += "<table class='library_export_result'>";
        for(let name in content) {
            html += "<tr>";
            html += "<th>" + LIBRARY_REPORTS_FIELD_NAMES[name] + "</th>";
            html += "<td>" + escapeHtml(content[name]) + "</td>";
            html += "</tr>";
        }
        html += '</table>'
        reportResults.innerHTML += html;

        document.querySelector("#wrap2").scrollIntoView({
            behavior: 'smooth' 
        });
    }

    function escapeHtml(unsafe) {
        if (typeof unsafe === 'string' || unsafe instanceof String)
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        else return unsafe;
    }
});
