// переменная, в которой хранится открытая вкладка, через нее будет открываться в новой вкладке pdf-счет
let win;

// переменная, которой после ajax запроса будет присвоен адрес pdf-счета
let pdf_global = 'about:blank';

// переменная для проверки правильности выполнения ajax-запроса, если он будет правильно выполнен, то по дефолту ей присвоится true
let permission = false;

// после формирования dom-структуры
$(document).on("ready", function() {

	let getParams = getUrlVar()

	// берем email из гет-параметров и вставляем в поле email
	$('.t702__wrapper input[name="Email"]').val(getParams['email']);

	// берем номер телефона из гет-параметров и вставляем в поле phone, заменив плюсы на пробелы
	let phoneFromGetParams = getParams['phone']
	phoneFromGetParams = phoneFromGetParams.replace(/\+/ig, ' ')
	$('.t706 input[data-tilda-rule="phone"]').val(phoneFromGetParams);

	// берем id контакта amocrm из гет-параметров
	let contactId = getParams['contact_id'];

	//ф-я отправки ajax-запроса для формирования pdf-счета
	function inn_request_and_pdf_billing() {

		// берем количество билетов из инпута формы
		let count = $('.t702__wrapper input[name="quantity"]').val();

		// берем email
		let email = $('.t702__wrapper').find($('input[name="Email"]')).val();

		// берем название тарифа из кнопки оплаты картой онлайн
		//let name = $(('a')[0]).attr('href');
		//name = name.substr(name.indexOf(':')+1,name.indexOf('=')-name.indexOf(':')-1);

		// название товара
		let name = 'Участие в форуме'

		// берем цену из кнопки оплаты картой онлайн
		let price = $(('a')[0]).attr('href');
		price = price.substr(price.indexOf('=')+1);

		// формируем массив продаж, на данный момент в нем только одна позиция
		let leads = {'0' : {'name': name, 'price': price, 'unit':'усл.ед.', 'nds': '0', 'count': count}};

		// берем ИНН
		let inn = $('.t702__wrapper input[data-tilda-rule="phone"]').val();

		// создаем переменную для хранения url pdf-счета из ответа ajax-Запроса
		let pdfUrl = '';

		//формируем ajax-запрос
	    let ajax_request= $.ajax({
	        url: 'https://itproblem.net/dev/marketingtyumbitru/integrations/convert_schet.php',
	        data: {'settings':{'inn':inn,'leads':leads,'contact_id':contactId,'email':email}},
	        type: 'POST',
	        dataType: 'json',
	        async: false,
	        success: function(response) {

	        		console.log(response)
					
					// url-адрес сгенерированного pdf-счета
		        	pdfUrl = response['url'];
		        	if (pdfUrl != '') {
		        		pdf_global = pdfUrl;
		        	}
	        	} 
	    });

	    // проверка url из ajax-запроса и открытие url сгенерированного pdf-счета в новой вкладке
		switch (pdfUrl) {
			// если неверный ИНН
		    case 'no-inn':
		    	$('.t702__wrapper').find('.t-input-group_in').find('.t-input-error').text('Неверный ИНН');
		    	$('.t702__wrapper').find('.t-input-group_in').find('.t-input-error').show();
		    	console.log('no inn')
		    	break;

			// если отсутствует email в amocrm
		    case 'no-email':
		    	$('.t702__wrapper').find('.t-input-group_in').find('.t-input-error').text('Забронированных билетов с текущей почтой не обнаружено');
		    	$('.t702__wrapper').find('.t-input-group_in').find('.t-input-error').show();
		    	break;

		    // если ошибка запроса в дадату
		    case 'no-url':
		    	$('.t702__wrapper').find('.t-input-group_in').find('.t-input-error').text('Ошибка запроса, повторите позднее');
		    	$('.t702__wrapper').find('.t-input-group_in').find('.t-input-error').show();
		    	console.log('no url')
		    	break;

		    // если вернулся пустой url
		    case '':
		    	$('.t702__wrapper').find('.t-input-group_in').find('.t-input-error').text('Неизвестная ошибка, обратитесь в тех.поддержку сайта');
		    	$('.t702__wrapper').find('.t-input-group_in').find('.t-input-error').show();
		    	console.log('xz')
		    	break;

		    // если url pdf-счета существует
		    default:
		    	permission = true;
		}

	}
	


	// убираем все события с кнопки формы
	$('.t702__wrapper').find('button').unbind();
	$('.t702__wrapper').find('button').off();


	// устанавливаем на button событие onclick, в котором сначала вызываем ф-ю inn_request_and_pdf_billing() и после вызываем метод submit()
	// сделать все это сразу в событии onsubmit формы не получилось, т.к. tilda обходит блокировку отправки формы по нажатия на кнопку 
	$('.t702__wrapper').find('button').on('click', function(event){

			// отменяем действия по умолчанию
			event.preventDefault;

			// проверка заполнения email и инн
			if (($('.t702__wrapper').find($('input[name="Email"]')).val() != '') && ($('.t702__wrapper').find($('input[data-tilda-rule="phone"]')).val() != '')) {

				// открываем новую вкладку для последующего открытия в нем pdf счета, адрес которого вернется после ajax-запроса
				win = window.open('about:blank', target='_blank'); 

				//ф-я отправки ajax-запроса для формирования pdf-счета
				inn_request_and_pdf_billing();

					// проверка что url pdf-счета вернулась корректно
					if (permission == true) {
						permission == false;

						console.log('true')

						// проверяем url pdf-счета, если верный, то в ранее открытой вкладке открываем pdf-счет
						if (pdf_global != 'about:blank') {
							win.document.location = pdf_global;

							// сбрасываем значение переменной со значением url pdf-счета
							pdf_global = 'about:blank';

							// выполняем отправку формы
							$('.t702__wrapper').find('form').submit();
						}
					}				

				// выполняем отправку формы
				//$('.t702__wrapper').find('form').submit();
			}
			return false;
	});



	// при отправке формы блокируем нажатие кнопки, т.к. в tilda при отправке формы вызывается нажатие кнопки, от чего формируется 2 pdf-счета
	$('.t702__wrapper').find('form').on('submit', function(e){

		$('.t702__wrapper').find('button').unbind();
		$('.t702__wrapper').find('button').off();
			
	});








	// убираем все события с кнопки формы
	$('.t706__cartwin').find('button').unbind();
	$('.t706__cartwin').find('button').off();


	// устанавливаем на button событие onclick и вызываем метод submit()
	$('.t706__cartwin').find('button').on('click', function(event){

			// отменяем действия по умолчанию
			event.preventDefault;

			// проверка заполнения поля phone
			if ($('.t706__cartwin').find($('input[data-tilda-rule="phone"]')).val() != '') {

			// берем количество билетов из инпута формы
			let count = $('.t706__cartwin .t706__product-quantity').text();

			// берем цену из кнопки оплаты картой онлайн
			let price = $(('a')[0]).attr('href');
			price = price.substr(price.indexOf('=')+1);

			// формируем массив продаж, на данный момент в нем только одна позиция
			let leads = {'0' : {'price': price, 'count': count}};

			// создаем переменную для хранения url pdf-счета из ответа ajax-Запроса
			let responseUrl = '';

			//формируем ajax-запрос
		    let ajax_request= $.ajax({
		        url: 'https://itproblem.net/dev/marketingtyumbitru/integrations/karta_yandex.php',
		        data: {'settings':{'leads':leads,'contact_id':contactId}},
		        type: 'POST',
		        dataType: 'json',
		        async: false,
		        success: function(response) {

		        		console.log(response)
						
						// url-адрес сгенерированного pdf-счета
			        	responseUrl = response['url'];

					    // проверка url из ajax-запроса и открытие url сгенерированного pdf-счета в новой вкладке
						switch (responseUrl) {

						    // если ошибка запроса в дадату
						    case 'no-url':
						    	$('.t706__cartwin').find('.t-input-error').text('Ошибка запроса, повторите позднее');
						    	$('.t706__cartwin').find('.t-input-error').show();
						    	console.log('no url')
						    	break;

						    // если вернулся пустой url
						    case '':
						    	$('.t706__cartwin').find('.t-input-error').text('Неизвестная ошибка, обратитесь в тех.поддержку сайта');
						    	$('.t706__cartwin').find('.t-input-error').show();
						    	console.log('xz')
						    	break;

						    // если url pdf-счета существует
						    default:
						    	$('.t706__cartwin').find('form').submit();
						}	

		        	} 
		    	});

			}
			return false;
	});



	// при отправке формы блокируем нажатие кнопки, т.к. в tilda при отправке формы вызывается нажатие кнопки, от чего формируется 2 pdf-счета
	$('.t706__cartwin').find('form').on('submit', function(e){

		$('.t706__cartwin').find('button').unbind();
		$('.t706__cartwin').find('button').off();
			
	});









});

// получение гет-параметров из url
function getUrlVar(){
    var urlVar = window.location.search; // получаем параметры из урла
    var arrayVar = []; // массив для хранения переменных
    var valueAndKey = []; // массив для временного хранения значения и имени переменной
    var resultArray = {}; // объект для хранения переменных
    arrayVar = (urlVar.substr(1)).split('&'); // разбираем урл на параметры
    if(arrayVar[0]=="") return false; // если нет переменных в урле
    for (i = 0; i < arrayVar.length; i ++) { // перебираем все переменные из урла
        valueAndKey = arrayVar[i].split('='); // пишем в массив имя переменной и ее значение
        resultArray[valueAndKey[0]] = valueAndKey[1]; // добавляем в итоговый объект имя переменной и ее значение
    }
    return resultArray; // возвращаем результат
}