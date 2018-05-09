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


require_once dirname(__FILE__).'/include/classes/duo/CDuoWeb.php';

require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('ZABBIX');
$page['file'] = 'duo.php';


if (isset($_POST['sig_response'])) {
	/*
	* Verify sig response and log in user. Make sure that verifyResponse
	* returns the username we logged in with. You can then set any
	* cookies/session data for that username and complete the login process.
	*/
	$resp = CDuoWeb::verifyResponse($_POST['sig_response'], $_POST['name']);
	if ($resp === true) {
		// 2FA successfull
		CWebUser::setSessionCookie($_POST['sessionid']);
		if (!zbx_empty($request)) {
			$url = $request;
		}
		elseif (!zbx_empty(CWebUser::$data['url'])) {
			$url = CWebUser::$data['url'];
		}
		else {
			$url = ZBX_DEFAULT_URL;
		}
		redirect($url);
		exit;
	}
	// login failed, fall back to a guest account
	else {
		CWebUser:logout();
		redirect(index.php);
		exit;
	}
}

$name = CWebUser::$data['alias'];
if (!$name || $name == ZBX_GUEST_USER) {
  // User is not authenticated
  redirect('index.php');
}
// Authentication is not complete yet so reset cookie
// to make it impossible to visit other pages until 2FA complete
$_REQUEST['sessionid'] = CWebUser::getSessionCookie();
zbx_unsetcookie('zbx_sessionid');

// Perform 2FA via DUO
$sig_request = CDuoWeb::signRequest($name);
$_REQUEST['sig_request'] = $sig_request;
$_REQUEST['name'] = $name;

$enrollView = new CView('general.duo');
$enrollView->render();
?>
