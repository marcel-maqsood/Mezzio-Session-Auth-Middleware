$(document).ready(function () {

	$('.save-pw').click(
		function (e) {
			e.preventDefault();

			var formData = $("#savePwForm").serialize();

			$.ajax({
				type: 'POST',
				url: window.location.href,
				data: formData,
				success: function (response) {
					$('.set-sent').show();
				},
				error: function (xhr, status, error) {
					$('.set-fail').show();
				}
			});

		}
	);

	$('.reset-password').click(
		function (e) {
			e.preventDefault();

			var formData = $("#resetPwForm").serialize();

			var actionUrl = $(this).data('target');

			var additionalData = {
				'targetReset': actionUrl
			};

			formData += '&' + $.param(additionalData);

			$.ajax({
				type: 'POST',
				url: actionUrl,
				data: formData,
				success: function (response) {
					$('.reset-sent').show();
				},
				error: function (xhr, status, error) {
					$('.reset-fail').show();
				}
			});
		}
	);

	$('.to-login').click(
		function (e) {
			e.preventDefault();
			$('.reset-sent').hide();
			$('.reset-fail').hide();

			$('.enable-login').show();
			$('.enable-reset').hide();
		}
	);

	$('.forgotpassword').click(
		function (e) {
			e.preventDefault();
			$('.reset-sent').hide();
			$('.reset-fail').hide();

			$('.enable-login').hide();
			$('.enable-reset').show();
		}
	);

});