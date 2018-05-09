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


define('ZBX_PAGE_NO_HEADER', 1);
define('ZBX_PAGE_NO_FOOTER', 1);

require_once dirname(__FILE__).'/../page_header.php';

global $ZBX_SERVER_NAME;

$sessionid = getRequest('sessionid');
$sig_request = getRequest('sig_request','');
$name = getRequest('name','');

$config = select_config();
$server = $config['2fa_duo_api_hostname'];

(new CDiv([
	(isset($ZBX_SERVER_NAME) && $ZBX_SERVER_NAME !== '')
		? (new CDiv($ZBX_SERVER_NAME))->addClass(ZBX_STYLE_SERVER_NAME)
		: null,
	(new CDiv([
		(new CDiv())->addClass(ZBX_STYLE_SIGNIN_LOGO),
		(new CForm())
			->setId('duo_form')
			->cleanItems()
			->addVar('name', $name, 'name')
			->addVar('sessionid', $sessionid, 'sessionid'),
                (new CTag('script', true))
			->setAttribute('type', 'text/javascript')
			->setAttribute('src', 'js/Duo-Web-v2.js'),
                (new CTag('link', true))
			->setAttribute('href', 'styles/Duo-Frame.css')
			->setAttribute('rel', 'stylesheet')
			->setAttribute('type', 'text/css'),
		(new CTag('iframe', true))
			->setAttribute('id', 'duo_iframe')
			->setAttribute('data-host', $server)
			->setAttribute('data-sig-request', $sig_request)
	]))->addClass(ZBX_STYLE_SIGNIN_CONTAINER)
		->setWidth(420),
	(new CDiv([
		(new CLink(_('Help'), 'http://www.zabbix.com/documentation/3.4/'))
			->setTarget('_blank')
			->addClass(ZBX_STYLE_GREY)
			->addClass(ZBX_STYLE_LINK_ALT),
		'&nbsp;&nbsp;•&nbsp;&nbsp;',
		(new CLink(_('Support'), 'http://www.zabbix.com/support.php'))
			->setTarget('_blank')
			->addClass(ZBX_STYLE_GREY)
			->addClass(ZBX_STYLE_LINK_ALT)
	]))->addClass(ZBX_STYLE_SIGNIN_LINKS)
]))
	->addClass(ZBX_STYLE_ARTICLE)
	->show();
makePageFooter(false)->show();
?>
</body>
