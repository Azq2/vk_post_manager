<h1 class="center">Котопубликовалка 9000</h1>

<div id="status" class="pad_b center"></div>

<div id="content" style="display: none">
	<table class="table">
		<tr>
			<td>
				Авторизация приложения
			</td>
			<td>
				<?php if ($auth_status == 'not_set' || $auth_status == 'error'): ?>
					<span class="red">Не установлена</span>
				<?php elseif ($auth_status == 'expired'): ?>
					<span class="red">Нужно обновить</span>
				<?php elseif ($auth_status == 'success'): ?>
					<span class="green">OK</span>
				<?php endif; ?>
			</td>
			<td>
				<button class="btn js-auth">
					Авторизировать
				</button>
			</td>
		</tr>
		
		<tr>
			<td>
				Виджет сообщества
			</td>
			<td>
				
			</td>
			<td>
				<button class="btn js-install_widget"<?= $auth_status == 'success' ? '' : ' disabled="disabled"' ?>>
					Установить
				</button>
			</td>
		</tr>
	</table>
</div>

<script type="text/javascript">
$(function () {
	var VK_REQUEST_ACCESS 	= <?= json_encode($vk_request_access) ?>, 
		USER_ID				= <?= json_encode($user_id) ?>, 
		USER_ID_SIGN		= <?= json_encode($user_id_sign) ?>, 
		GROUP_ID			= <?= json_encode($group_id) ?>, 
		GROUP_WIDGET		= <?= json_encode($group_widget) ?>;
	
	function setStatus(type, text) {
		switch (type) {
			case "load":
				$('#status').html('<img src="/i/img/spinner2.gif" alt="" class="m" /> <span class="m grey">' + text + '</span>').show();
			break;
			
			case "error":
				$('#status').html('<span class="m red">' + text + '</span>').show();
			break;
			
			case "load":
				$('#status').html('<span class="m green">' + text + '</span>').show();
			break;
			
			default:
				$('#status').html('').hide();
			break;
		}
	}
	
	setStatus("load", "Инициализация VK API...");
	
	VK.init(function () {
		setStatus(false);
		$('#content').show();
		
		$('.js-auth').click(function (e) {
			e.preventDefault();
			
			var el = $(this);
			
			if (el.attr("disabled"))
				return;
			
			el.attr("disabled", "disabled");
			
			setStatus("load", "Обновление авторизации...");
			
			VK.callMethod("showGroupSettingsBox", VK_REQUEST_ACCESS);
		});
		
		$('.js-install_widget').click(function (e) {
			e.preventDefault();
			
			var el = $(this);
			
			if (el.attr("disabled"))
				return;
			
			if (!GROUP_WIDGET) {
				setStatus("error", "Виджет сообщества не включен в настройках <b>Котопубликовалки</b>.");
				return;
			}
			
			el.attr("disabled", "disabled");
			
			setStatus("load", "Установка виджета...");
			
			VK.callMethod("showAppWidgetPreviewBox", GROUP_WIDGET.type, GROUP_WIDGET.code);
		});
		
		VK.addCallback("onGroupSettingsChanged", function (new_settings, access_token) {
			$.ajax({
				url:		"/?a=vk_mini_apps/update_token", 
				data:		{
					access_token:		access_token, 
					user_id:			USER_ID, 
					user_id_sign:		USER_ID_SIGN, 
					group_id:			GROUP_ID
				}, 
				type:		"POST", 
				dataType:	"json"
			})
				.success(function (res) {
					if (res.error) {
						$('.js-auth').removeAttr("disabled");
						setStatus("error", res.error);
					} else {
						setStatus("load", "Проверка авторизации...");
						location.reload();
					}
				})
				.fail(function (err) {
					$('.js-auth').removeAttr("disabled");
					setStatus("error", "Ошибка обновления Access Token. Проверьте интернет.");
				});
		});
		
		VK.addCallback("onGroupSettingsCancel", function () {
			setStatus("load", "Отмена авторизации...");
			
			setTimeout(function () {
				setStatus(false);
				$('.js-auth').removeAttr("disabled");
			}, 3000);
		});
		
		VK.addCallback("onAppWidgetPreviewSuccess", function () {
			$('.js-install_widget').removeAttr("disabled");
			setStatus("success", "Виджет успешно установлен.");
		});
		
		VK.addCallback("onAppWidgetPreviewFail", function () {
			$('.js-install_widget').removeAttr("disabled");
			setStatus("error", "Ошибка установки виджета.");
		});
		
		VK.addCallback("onAppWidgetPreviewCancel", function () {
			$('.js-install_widget').removeAttr("disabled");
			setStatus(false);
		});
	}, function () {
		$('#spinner').html('<span class="red">Ошибка инициализации API. Обновите страницу.</span>');
	}, '5.101');
});
</script>
