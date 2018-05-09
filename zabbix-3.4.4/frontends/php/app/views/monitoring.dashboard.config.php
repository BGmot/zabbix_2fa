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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


$form = (new CForm('post'))
	->cleanItems()
	->setId('widget_dialogue_form')
	->setName('widget_dialogue_form');
$jq_templates = [];
$js_scripts = [];

$form_list = new CFormList();

// common fields
$form_list->addRow(_('Type'),
	new CComboBox('type', $data['dialogue']['type'], 'updateWidgetConfigDialogue()', $data['known_widget_types'])
);

$form_list->addRow(_('Name'),
	(new CTextBox('name', $data['dialogue']['name']))
		->setAttribute('placeholder', _('default'))
		->setAttribute('autofocus', 'autofocus')
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
);

// widget specific fields
foreach ($data['dialogue']['fields'] as $field) {
	if (!$data['config']['event_ack_enable'] && ($field->getFlags() & CWidgetField::FLAG_ACKNOWLEDGES)) {
		$form->addVar($field->getName(), $field->getValue());
		continue;
	}

	if ($field instanceof CWidgetFieldComboBox) {
		$form_list->addRow($field->getLabel(),
			new CComboBox($field->getName(), $field->getValue(), $field->getAction(), $field->getValues())
		);
	}
	elseif ($field instanceof CWidgetFieldTextBox || $field instanceof CWidgetFieldUrl) {
		$form_list->addRow($field->getLabel(),
			(new CTextBox($field->getName(), $field->getValue()))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		);
	}
	elseif ($field instanceof CWidgetFieldCheckBox) {
		$form_list->addRow($field->getLabel(), [
			new CVar($field->getName(), '0'),
			(new CCheckBox($field->getName()))->setChecked((bool) $field->getValue())
		]);
	}
	elseif ($field instanceof CWidgetFieldGroup) {
		// multiselect.js must be preloaded in parent view.

		$field_groupids = (new CMultiSelect([
			'name' => $field->getName().'[]',
			'objectName' => 'hostGroup',
			'data' => $data['captions']['ms']['groups'][$field->getName()],
			'popup' => [
				'parameters' => 'srctbl=host_groups&dstfrm='.$form->getName().'&dstfld1='.$field->getName().'_'.
					'&srcfld1=groupid&multiselect=1'
			],
			'add_post_js' => false
		]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

		$form_list->addRow($field->getLabel(), $field_groupids);

		$js_scripts[] = $field_groupids->getPostJS();
	}
	elseif ($field instanceof CWidgetFieldHost) {
		// multiselect.js must be preloaded in parent view.

		$field_hostids = (new CMultiSelect([
			'name' => $field->getName().'[]',
			'objectName' => 'hosts',
			'data' => $data['captions']['ms']['hosts'][$field->getName()],
			'popup' => [
				'parameters' => 'srctbl=hosts&dstfrm='.$form->getName().'&dstfld1='.$field->getName().'_'.
					'&srcfld1=hostid&multiselect=1'
			],
			'add_post_js' => false
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

		$form_list->addRow($field->getLabel(), $field_hostids);

		$js_scripts[] = $field_hostids->getPostJS();
	}
	elseif ($field instanceof CWidgetFieldReference) {
		$form->addVar($field->getName(), $field->getValue() ? $field->getValue() : '');

		if (!$field->getValue()) {
			$javascript = $field->getJavascript('#'.$form->getAttribute('id'));
			$form->addItem(new CJsScript(get_js($javascript, true)));
		}
	}
	elseif ($field instanceof CWidgetFieldHidden) {
		$form->addVar($field->getName(), $field->getValue());
	}
	elseif ($field instanceof CWidgetFieldSelectResource) {
		$caption = ($field->getValue() != 0)
			? $data['captions']['simple'][$field->getResourceType()][$field->getValue()]
			: '';

		// Needed for popup script.
		$form->addVar($field->getName(), $field->getValue());
		$form_list->addRow($field->getLabel(), [
			(new CTextBox($field->getName().'_caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('javascript: return PopUp("'.$field->getPopupUrl().'&dstfrm='.$form->getName().'");')
		]);
	}
	elseif ($field instanceof CWidgetFieldWidgetListComboBox) {
		$form_list->addRow($field->getLabel(),
			(new CComboBox($field->getName(), [], $field->getAction(), []))
				->setAttribute('style', 'width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px')
		);

		$form->addItem(new CJsScript(get_js($field->getJavascript(), true)));
	}
	elseif ($field instanceof CWidgetFieldNumericBox) {
		$form_list->addRow($field->getLabel(),
			(new CNumericBox($field->getName(), $field->getValue(), $field->getMaxLength()))
				->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
		);
	}
	elseif ($field instanceof CWidgetFieldRadioButtonList) {
		$radio_button_list = (new CRadioButtonList($field->getName(), $field->getValue()))
			->setModern($field->getModern());
		foreach ($field->getValues() as $key => $value) {
			$radio_button_list->addValue($value, $key, null, $field->getAction());
		}
		$form_list->addRow($field->getLabel(), $radio_button_list);
	}
	elseif ($field instanceof CWidgetFieldSeverities) {
		$severities = (new CList())->addClass(ZBX_STYLE_LIST_CHECK_RADIO);

		for ($severity = TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity < TRIGGER_SEVERITY_COUNT; $severity++) {
			$severities->addItem(
				(new CCheckBox($field->getName().'[]', $severity))
					->setLabel(getSeverityName($severity, $data['config']))
					->setId($field->getName().'_'.$severity)
					->setChecked(in_array($severity, $field->getValue()))
			);
		}

		$form_list->addRow($field->getLabel(), $severities);
	}
	elseif ($field instanceof CWidgetFieldTags) {
		$tags = $field->getValue();

		if (!$tags) {
			$tags = [['tag' => '', 'value' => '']];
		}

		$tags_table = (new CTable())->setId('tags_table');
		$i = 0;

		foreach ($tags as $tag) {
			$tags_table->addRow([
				(new CTextBox($field->getName().'['.$i.'][tag]', $tag['tag']))
					->setAttribute('placeholder', _('tag'))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
				(new CTextBox($field->getName().'['.$i.'][value]', $tag['value']))
					->setAttribute('placeholder', _('value'))
					->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
				(new CCol(
					(new CButton($field->getName().'['.$i.'][remove]', _('Remove')))
						->addClass(ZBX_STYLE_BTN_LINK)
						->addClass('element-table-remove')
				))->addClass(ZBX_STYLE_NOWRAP)
			], 'form_row');

			$i++;
		}

		$tags_table->addRow(
			(new CCol(
				(new CButton('tags_add', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-add')
			))->setColSpan(3)
		);

		$form_list->addRow($field->getLabel(), $tags_table);

		$jq_templates['tag-row'] = (new CRow([
			(new CTextBox($field->getName().'[#{rowNum}][tag]'))
				->setAttribute('placeholder', _('tag'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CTextBox($field->getName().'[#{rowNum}][value]'))
				->setAttribute('placeholder', _('value'))
				->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH),
			(new CCol(
				(new CButton($field->getName().'[#{rowNum}][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
			))->addClass(ZBX_STYLE_NOWRAP)
		]))
			->addClass('form_row')
			->toString();

		$js_scripts[] = 'jQuery("#tags_table").dynamicRows({template: "#tag-row"});';
	}
}

$form->addItem($form_list);

// Submit button is needed to enable submit event on Enter on inputs.
$form->addItem((new CInput('submit', 'dashboard_widget_config_submit'))->addStyle('display: none;'));

$output = [
	'body' => $form->toString()
];

foreach ($jq_templates as $id => $jq_template) {
	$output['body'] .= '<script type="text/x-jquery-tmpl" id="'.$id.'">'.$jq_template.'</script>';
}
if ($js_scripts) {
	$output['body'] .= get_js(implode("\n", $js_scripts));
}

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
