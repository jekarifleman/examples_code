

// клик формы "контакты"
document.addEventListener('click', function(e){

	// отсеиваем ненужные клики
    if ($(e.target).parents('#form83623268').length == 0) return
    if ($(e.target).parents('.tn-form__submit').length == 0) return

	if ($('#form_amo_error').length == 0) $('#form83623268').append('<div id="form_amo_error"></div>')

	// берем название тарифа
	let name  = $('#form83623268 input[name="Name"]').val()

	// берем цену из кнопки оплаты картой онлайн
	let phone = $('#form83623268 input[name="Phone"]').val()

	// берем email
	let email = $('#form83623268 input[name="Email"]').val()

	// id контакта amocrm, возмется из ajax-запроса
	let contactId = ''

	// проверка заполнения полей
	if ((name != '') && (phone != '') && (email.indexOf('@') > -1) && (email.indexOf('.', email.indexOf('@')) > -1)) {

		if ($('#form_amo_error').lengh > 0) {
			// информация о просьбе подождать
			$('#form_amo_error').text('Пожалуйста, подождите')
		}

		//формируем ajax-запрос
	    ajaxRequest= $.ajax({
	        url: 'https://itproblem.net/dev/marketingtyumbitru/integrations/contacts.php',
	        data: {'settings':{'name':name,'phone':phone,'email':email}},
	        type: 'POST',
	        dataType: 'json',
	        async: false,
	        success: function(response) {
	        			// id контакта из ajax-запроса
	        			contactId = response['contact_id']

	        			if (contactId == 'error') {
	        				if ($('#form_amo_error').lengh > 0) {
		        				$('#form_amo_error').text('Пожалуйста, проверьте правильность заполнения данных, затем повторно кликните по кнопке, если ошибка повторяется, то обратитесь в тех.поддержку')
		        				console.log(contactId)
	        				}
	        			} else {
	        				console.log(contactId)
	        			}
		        	}
	    });

	    phone = phone.replace(/ /ig, '+')

	    // добавляем get-параметры в url отправки формы
		$('#form83623268').attr('data-success-url', '/pay?contact_id=' + contactId + '&email=' + email + '&phone=' + phone)

	}

}, true);




// клик формы "партнеры"
document.addEventListener('click', function(e){

	// отсеиваем ненужные клики
    if ($(e.target).parents('#form83642340').length == 0) return
    if ($(e.target).parents('.tn-form__submit').length == 0) return

	console.log('click')

	if ($('#form_amo_error_parnters').length == 0) $('#form83642340').append('<div id="form_amo_error_parnters"></div>')

	// берем название тарифа
	let name  = $('#form83642340 input[name="Name"]').val()

	// берем цену из кнопки оплаты картой онлайн
	let phone = $('#form83642340 input[name="Phone"]').val()

	// берем email
	let email = $('#form83642340 input[name="Email"]').val()

	// id контакта amocrm, возмется из ajax-запроса
	let contactId = ''

	// проверка заполнения полей
	if ((name != '') && (phone != '') && (email.indexOf('@') > -1) && (email.indexOf('.', email.indexOf('@')) > -1)) {

		if ($('#form_amo_error_parnters').lengh > 0) {
			// информация о просьбе подождать
			$('#form_amo_error_parnters').text('Пожалуйста, подождите')
		}

		//формируем ajax-запрос
	    ajaxRequest= $.ajax({
	        url: 'https://itproblem.net/dev/marketingtyumbitru/integrations/partners.php',
	        data: {'settings':{'name':name,'phone':phone,'email':email}},
	        type: 'POST',
	        dataType: 'json',
	        async: false,
	        success: function(response) {
	        			// id контакта из ajax-запроса
	        			contactId = response['contact_id']

	        			if (contactId == 'error') {
	        				if ($('#form_amo_error_parnters').lengh > 0) {
		        				$('#form_amo_error_parnters').text('Пожалуйста, проверьте правильность заполнения данных, затем повторно кликните по кнопке, если ошибка повторяется, то обратитесь в тех.поддержку')
		        				console.log(contactId)
	        				}
	        			} else {
	        				console.log(contactId)
	        			}
		        	}
	    });

	}

}, true);
