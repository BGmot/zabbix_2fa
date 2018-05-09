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


$widget = (new CWidget())->setTitle(_('Two factor authentication'));

// create form
$twofaForm = (new CForm())->setName('twofaForm');

// create form list
$twofaFormList = new CFormList('twofaList');

// append config radio buttons to form list
$twofaFormList->addRow(_('Two factor authentication'),
	(new CRadioButtonList('config', (int) $this->data['config']['2fa_type']))
		->addValue(_('None'), ZBX_AUTH_2FA_NONE, null, 'submit()')
		->addValue(_('DUO'), ZBX_AUTH_2FA_DUO, null, 'submit()')
		->setModern(true)
);

// append DUO fields to form list
if ($this->data['config']['2fa_type'] == ZBX_AUTH_2FA_DUO) {
	$twofaFormList->addRow(
		_('API hostname'),
		(new CTextBox('2fa_duo_api_hostname', $this->data['config']['2fa_duo_api_hostname']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$twofaFormList->addRow(
		_('Integration key'),
		(new CTextBox('2fa_duo_integration_key', $this->data['config']['2fa_duo_integration_key']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$twofaFormList->addRow(
		_('Secret key'),
		(new CPassBox('2fa_duo_secret_key', $this->data['config']['2fa_duo_secret_key']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
	$twofaFormList->addRow(
		_('40 characters long custom key'),
		(new CPassBox('2fa_duo_a_key', $this->data['config']['2fa_duo_a_key'], 40))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	);
}

// append form list to tab
$twofaTab = new CTabView();
$twofaTab->addTab('twofaTab', $this->data['title'], $twofaFormList);

// create save button
$saveButton = new CSubmit('update', _('Update'));
if ($this->data['is_2fa_type_changed']) {
	$saveButton->onClick('javascript: if (confirm('.
		CJs::encodeJson(_('Switching two factor authentication method will reset all except this session! Continue?')).')) {'.
		'jQuery("#twofaForm").submit(); return true; } else { return false; }'
	);
}
elseif ($this->data['config']['2fa_type'] != ZBX_AUTH_2FA_DUO) {
	$saveButton->setAttribute('disabled', 'true');
}

$twofaTab->setFooter(makeFormFooter($saveButton));

// append tab to form
$twofaForm->addItem($twofaTab);

// append form to widget
$widget->addItem($twofaForm);

return $widget;
