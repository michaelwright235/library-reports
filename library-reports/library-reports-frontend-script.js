jQuery(document).ready(function($) {

    // Создание календаря datepicker
    let date = new Date();
    let month = date.getMonth()+1;
    if(month < 10) month = "0" + month;
    let day = date.getDate();
    if(day < 10) day = "0" + day;
    let datepickerParams = {
        minDate: library_reports_edit_time,
        maxDate: library_reports_max_date,
        defaultDate: 0,
        dateFormat: "yy-mm-dd",
        beforeShowDay: function (date) {
            if(dayHasReport(getFormattedDate(date))) {
                return [true, 'dayHasReport'];
            } else {
                return [true, ''];
            }
        },
        onSelect: (date) => {
            if(dayHasReport(date)) {
                getSingleReport(date);
            } else {
                clearForm();
            }
        }
    };
    
    $( "#datepicker" ).datepicker(datepickerParams);
    document.querySelector("#library").addEventListener('change',() => {
        $( "#datepicker" ).datepicker(datepickerParams);
        if( dayHasReport( document.querySelector("#datepicker").value ) ) {
            getSingleReport(document.querySelector("#datepicker").value);
        } else {
            clearForm();
        }
    });

    // Ставим сегодняшнюю дату
    let currentDate = getFormattedDate(new Date());
    $( "#datepicker" ).val(currentDate);
    if(dayHasReport(currentDate)) {
        getSingleReport(currentDate);
    }

    function dayHasReport(date) {
        let currentLib = document.querySelector("#library").value;
        if(library_reports_completed_reports[currentLib].includes(date) )
            return true;
        return false;
    }

    // Проверка всех полей на цифру > 0
    let inputs = document.querySelectorAll("input");
    inputs.forEach(element => {
        element.addEventListener('change', (event) => {
            let val = event.target.value;
            if(!/^\d+$/.test(val)) {
                event.target.classList.add('wrong-value');
            } else {
                event.target.classList.remove('wrong-value');
            }
        })
    });

    // Получить дату в формату Y-m-d
    function getFormattedDate(date) {
        let month = date.getMonth()+1;
        if(month < 10) month = '0' + month;
        let day = date.getDate();
        if(day < 10) day = '0' + day;
        let d = date.getFullYear() + '-' + month + '-' + day;
        return d;
    }

    // Проверка на наличие пустых полей
    function checkIfEmpty() {
        let result = false;
        inputs.forEach(element => {
            if(element.value == "" || element.value == null) result = true;
        });
        return result;
    }

    // Проверка сумм в стационаре
    let in1 = document.querySelector("input[name=tPplIn14]");
    let in2 = document.querySelector("input[name=tPplIn1530]");
    let in3 = document.querySelector("input[name=tPplIn30]");
    let in4 = document.querySelector("input[name=tPplInFree]");
    let in5 = document.querySelector("input[name=tPplInPaid]");
    let statusInside = document.getElementById("sumStatusInside");
    in1.onchange =
        in2.onchange =
        in3.onchange =
        in4.onchange =
        in5.onchange = () => {
        checkSum(in1, in2, in3, in4, in5, statusInside);
    };

    // Проверка сумм вне стационара
    let out1 = document.querySelector("input[name=tPplOut14]");
    let out2 = document.querySelector("input[name=tPplOut1530]");
    let out3 = document.querySelector("input[name=tPplOut30]");
    let out4 = document.querySelector("input[name=tPplOutFree]");
    let out5 = document.querySelector("input[name=tPplOutPaid]");
    let statusOutside = document.getElementById("sumStatusOutside");
    out1.onchange =
        out2.onchange =
        out3.onchange =
        out4.onchange =
        out5.onchange = () => {
        checkSum(out1, out2, out3, out4, out5, statusOutside);
    };

    function checkSum(input1, input2, input3, input4, input5, statusEl) {
        // Проверяем, все ли значения правильны
        if (isWrongInput(input1, input2, input3, input4, input5)) return;
        
        let sum1 = parseInt(input1.value) + parseInt(input2.value) + parseInt(input3.value);
        let sum2 = parseInt(input4.value) + parseInt(input5.value);
        if(sum1 != sum2) {
            statusEl.innerText = "❌ Сумма неверна: "+sum1+" ≠ "+sum2;
            return false;
        } else {
            statusEl.innerText = "✅ Сумма верна";
            return true;
        }
    }

    function isWrongInput(...inputs) {
        let result = false;
        inputs.forEach(el => {
            if(el.classList.contains("wrong-value") ||
            el.value == "" ||
            el.value == null) {
                result = true;
                return;
            }
        });
        return result;
    }

    // Проверка формы перед отправкой
    let form = document.getElementById('library-reports-report-form');
    let statusP = document.getElementById("library-reports-form-status");
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        
        if(form.querySelectorAll("input.wrong-value").length > 0) {
            statusP.innerText = "Проверьте форму";
            return;
        }
        if(checkIfEmpty()) {
            statusP.innerText = "Одно из полей не заполнено";
            return;
        }
        if(!checkSum(in1, in2, in3, in4, in5, statusInside) ||
            !checkSum(out1, out2, out3, out4, out5, statusOutside)) {
            statusP.innerText = "Проверьте сумму людей";
            return;
        }
        statusP.innerText = "";
        
        if(dayHasReport(document.querySelector("#datepicker").value)) {
            document.querySelector("#form_action").value = "library_reports_update";
        } else {
            document.querySelector("#form_action").value = "library_reports_submit";
        }

        $.ajax({
            url:      ajaxurl,
            type:     "POST",
            data: $("#library-reports-report-form").serialize(),
            success: (response) => { 
                document.querySelector("#resultsFor").innerHTML = response;
                makeNoticesDismissible();
                window.scroll({
                    top: 0, 
                    left: 0, 
                    behavior: 'smooth' 
                });
                console.log(response);
             },
            error: function(response) {
                console.log(response);
            },
        });
    });

    function getSingleReport(date) {
        let data = 'library='+$("#library").val()+
        '&date='+date+
        '&action=library_reports_get_single_report'+
        '&_wpnonce='+$("#_wpnonce").val();
        $.ajax({
            url:      ajaxurl,
            type:     "POST",
            data: data,
            success: (response) => { 
                let jsonR = JSON.parse(response);
                putCompletedReport(JSON.parse(jsonR[0].content));
             },
            error: function(response) {
                console.log(response);
            },
        });
    }

    function putCompletedReport(content) {
        console.log(content);
        for(let name in content) {
            $("#"+name).val(content[name]);
        }
    }

    function clearForm() {
        let toClear = ['tBookPeople', 'booksOut', 'booksIn', 'tEvntIn', 'tPplIn14', 'tPplIn1530', 'tPplIn30', 'tPplInFree', 'tPplInPaid', 'tPplOut', 'tPplOut14', 'tPplOut1530', 'tPplOut30', 'tPplOutFree', 'tPplOutPaid', 'tIncome', 'ecb'];
        toClear.forEach((val) => {
            $("#"+val).val('');
        });
    }

    /**
	 * Makes notices dismissible.
	 *
	 * @since 4.4.0
	 *
	 * @return {void}
	 */
	function makeNoticesDismissible() {
		$( '.notice.is-dismissible' ).each( function() {
			var $el = $( this ),
				$button = $( '<button type="button" class="notice-dismiss"><span class="screen-reader-text"></span></button>' );

			if ( $el.find( '.notice-dismiss' ).length ) {
				return;
			}

			// Ensure plain text.
			$button.find( '.screen-reader-text' ).text( wp.i18n.__( 'Dismiss this notice.' ) );
			$button.on( 'click.wp-dismiss-notice', function( event ) {
				event.preventDefault();
				$el.fadeTo( 100, 0, function() {
					$el.slideUp( 100, function() {
						$el.remove();
					});
				});
			});

			$el.append( $button );
		});
	}
});
