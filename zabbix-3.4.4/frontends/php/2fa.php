<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';

$page['title'] = _('Configuration of two factor authentication');
$page['file'] = '2fa.php';

require_once dirname(__FILE__).'/include/page_header.php';

//	VAR						TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'config' =>			[T_ZBX_INT, O_OPT, null, IN(ZBX_AUTH_2FA_NONE.','.ZBX_AUTH_2FA_DUO), null],
	'form_refresh' =>	[T_ZBX_INT, O_OPT, null,			null, null],
	// actions
	'update' =>			[T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null, null],
	// DUO 2FA
	'2fa_duo_api_hostname' => [T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'2fa_duo_integration_key' => [T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'2fa_duo_secret_key' => [T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null],
	'2fa_duo_a_key' => [T_ZBX_STR, O_OPT, null, NOT_EMPTY,	null]
];
check_fields($fields);


$config = select_config();

if (hasRequest('config')) {
	$is2faTypeChanged = ($config['2fa_type'] != getRequest('config'));
	$config['2fa_type'] = getRequest('config');
}
else {
	$is2faTypeChanged = false;
}

$fields = [
	'2fa_type' => true,
	'2fa_duo_api_hostname' => true,
	'2fa_duo_integration_key' => true,
	'2fa_duo_secret_key' => true,
	'2fa_duo_a_key' => true
];

foreach ($config as $field => $value) {
	if (array_key_exists($field, $fields)) {
		$config[$field] = getRequest($field, $config[$field]);
	}
	else {
		unset($config[$field]);
	}
}

/*
 * Actions
 */
if ($config['2fa_type'] == ZBX_AUTH_2FA_NONE) {
	if (hasRequest('update')) {
		$messageSuccess = _('Two factor authentication turned off');
		$messageFailed = _('Cannot turn off two factor authentication');

		DBstart();

		$result = update_config($config);

		if ($result) {
			// reset all sessions
			if ($is2faTypeChanged) {
				$result &= DBexecute(
					'UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.
					' WHERE sessionid<>'.zbx_dbstr(CWebUser::$data['sessionid'])
				);
			}

			$is2faTypeChanged = false;

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, $messageSuccess);
		}

		$result = DBend($result);
		show_messages($result, $messageSuccess, $messageFailed);
	}
}
elseif ($config['2fa_type'] == ZBX_AUTH_2FA_DUO) {
	$login = false;
	if (hasRequest('update')) {
		$messageSuccess = $is2faTypeChanged
			? _('Two factore authentication method changed to DUO')
			: _('Two factor authentication DUO changed');
		$messageFailed = $is2faTypeChanged
			? _('Cannot change two factor authentication method to DUO')
			: _('Cannot change two factor authentication DUO');

		DBstart();

		$result = update_config($config);

		if ($result) {
			unset($_REQUEST['change_bind_password']);

			// reset all sessions
			if ($is2faTypeChanged) {
				$result &= DBexecute(
					'UPDATE sessions SET status='.ZBX_SESSION_PASSIVE.
					' WHERE sessionid<>'.zbx_dbstr(CWebUser::$data['sessionid'])
				);
			}

			$is2faTypeChanged = false;

			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ZABBIX_CONFIG, $messageSuccess);
		}

		$result = DBend($result);
		show_messages($result, $messageSuccess, $messageFailed);
	}
}

show_messages();

/*
 * Display
 */
$data = [
	'form_refresh' => getRequest('form_refresh'),
	'config' => $config,
	'is_2fa_type_changed' => $is2faTypeChanged,
	'user' => getRequest('user', CWebUser::$data['alias']),
	'user_password' => getRequest('user_password', ''),
	'user_list' => null
];

// get tab title
$data['title'] = twofa2str($config['2fa_type']);

// render view
$view = new CView('administration.2fa.edit', $data);
$view->render();
$view->show();

require_once dirname(__FILE__).'/include/page_footer.php';
