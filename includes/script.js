jQuery(document).ready(function($)
{

	function InitCharts()
	{
		$('.chartJS canvas').each(function(i, e)
		{
			$(e).attr
			({
				'width': $(e).parent().width(),
				'height': $(e).parent().outerHeight()
			});
		});

		var myPie = new Chart($('#ChartAssetTypesCreated').get(0).getContext('2d')).Pie(ChartAssetTypesCreatedData);
		var myDonut = new Chart($('#ChartEditorialErrorFixes').get(0).getContext('2d')).Doughnut(ChartEditorialErrorFixesData);
	}

	InitCharts();
	$(window).on('resize', function(){ InitCharts(); });
//	$('a[href="#dashboard"]').click(function(e){ InitCharts(); });


	$('a[href="#myModal-1"], a[href="#myModal-myb"], a[href="#myModal-1c"], a[href="#myModal-1d"], a[href="#myModal-1f"]').click(function(e)
	{
		e.preventDefault();

		var ModalId = $(this).attr('href');
		var ModalAction = $(this).attr('data-action');
		var ModalBody = $(ModalId + ' .modal-body');

		$('#ModalLoading > div').clone().appendTo(ModalBody);

		$.ajax
		({
			type: 'POST',
			url: ajaxurl,
			data: {'action': ModalAction, 'Id': $(this).attr('data-id')},
			dataType: 'html'
		})
		.done(function(Data)
		{
			ModalBody.html(Data);
		})
		.fail(function(jqXHR, textStatus, errorThrown)
		{
			ModalBody.html('<div class="alert alert-danger" role="alert">Request Error: '+errorThrown+'</div>');
		});
	});


	$('#myModal-1, #myModal-1c, #myModal-1d, #myModal-myb').on('click', 'button.btn-danger', function(e)
	{
		$(this).closest('.modal-body').empty();
	});

	$('#myModal-1, #myModal-1c, #myModal-1d, #myModal-myb').on('click', 'button.close', function(e)
	{
		$(this).closest('.modal-content').find('.modal-body').empty();
	});


	$('#myModal-1f button.close').click(function(e)
	{
		document.location.reload();
	});


	if ($.fn.datepicker)
	{
		var Now = new Date();

		$.fn.datepicker.defaults.format = 'mm/dd/yyyy';
		$.fn.datepicker.defaults.weekStart = 1;
		$.fn.datepicker.defaults.autoclose = true;
		$.fn.datepicker.defaults.todayHighlight = true;
		$.fn.datepicker.defaults.startDate = Now;

		$('.default-date-picker').datepicker();

		$('.modal-dialog').on('focus', '.default-date-picker', function()
		{
			$(this).datepicker();
		});


		$('#myModal-1, #myModal-1a').on('change', 'select[name="Idea"]', function(e)
		{
			var Elm = $(this);
			var ElmMessage = Elm.closest('.modal-content').find('.help-block-date');

			if (Elm.val() != '')
			{
				ElmMessage.html('<i class="fa fa-spinner fa-spin"></i>');

				$.ajax
				({
					type: 'POST',
					url: ajaxurl,
					data: {'action': 'OCTMTaskEndDate', 'Id': Elm.val()},
					dataType: 'json'
				})
				.done(function(Data)
				{
					if (Data.Status == 'OK')
					{
						ElmMessage.html('Idea Due Date is '+Data.Data);
						Elm.closest('.modal-content').find('.default-date-picker').datepicker('setEndDate', Data.Data);
					}
					else if (Data.Status == 'Error')
					{
						ElmMessage.html(Data.Message);
					}
				})
				.fail(function(jqXHR, textStatus, errorThrown)
				{
					ElmMessage.html('Request Error: '+errorThrown);
				});
			}
		});
	}

});
