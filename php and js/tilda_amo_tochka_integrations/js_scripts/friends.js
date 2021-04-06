

// после формирования dom-структуры
document.addEventListener('click', function(e){

	// отсеиваем ненужные клики
    if ($(e.target).parents('#form84033309').length == 0) return
    if ($(e.target).parents('.tn-form__submit').length == 0) return

	console.log('click')

	if ($('#form_amo_error_friends').length == 0) $('#form84033309').append('<div id="form_amo_error_friends"></div>')

	// берем название тарифа
	let name  = $('#form84033309 input[name="name"]').val()

	// берем цену из кнопки оплаты картой онлайн
	let phone = $('#form84033309 input[name="phone"]').val()

	let contactId = ''

	// проверка заполнения полей
	if ((name != '') && (phone != '')) {

		if ($('#form_amo_error_friends').lengh > 0) {

			// информация о просьбе подождать
			$('#form_amo_error_friends').text('Пожалуйста, подождите')
		}

		//формируем ajax-запрос
	    ajaxRequest= $.ajax({
	        url: 'https://itproblem.net/dev/marketingtyumbitru/integrations/friends.php',
	        data: {'settings':{'name':name,'phone':phone}},
	        type: 'POST',
	        dataType: 'json',
	        async: false,
	        success: function(response) {
	        			// id контакта из ajax-запроса
	        			contactId = response['contact_id']

	        			if (contactId == 'error') {
	        				if ($('#form_amo_error_friends').lengh > 0) {
		        				$('#form_amo_error_friends').text('Пожалуйста, проверьте правильность заполнения данных, затем повторно кликните по кнопке, если ошибка повторяется, то обратитесь в тех.поддержку')
		        				console.log(contactId)
	        				}
	        			} else {
	        				console.log(contactId)
	        			}
		        	}
	    });


	}

	console.log(name, phone)
	console.log($('#form84033309').attr('data-success-url'))

}, true);
