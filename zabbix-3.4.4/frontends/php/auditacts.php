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
require_once dirname(__FILE__).'/include/audit.inc.php';
require_once dirname(__FILE__).'/include/actions.inc.php';
require_once dirname(__FILE__).'/include/users.inc.php';

$page['title'] = _('Action log');
$page['file'] = 'auditacts.php';
$page['scripts'] = ['class.calendar.js', 'gtlc.js'];
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	// filter
	'filter_rst' =>	[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'filter_set' =>	[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'alias' =>		[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'period' =>		[T_ZBX_INT, O_OPT, null,	null,	null],
	'stime' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	'isNow' =>		[T_ZBX_INT, O_OPT, null,	IN('0,1'),	null]
];
check_fields($fields);

if ($page['type'] == PAGE_TYPE_JS || $page['type'] == PAGE_TYPE_HTML_BLOCK) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::update('web.auditacts.filter.alias', getRequest('alias', ''), PROFILE_TYPE_STR);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.auditacts.filter.alias');
	DBend();
}

/*
 * Display
 */
$data = [
	'alias' => CProfile::get('web.auditacts.filter.alias', ''),
	'users' => [],
	'alerts' => [],
	'paging' => null,
	'timeline' => calculateTime([
		'profileIdx' => 'web.auditacts.timeline',
		'profileIdx2' => 0,
		'updateProfile' => (hasRequest('period') || hasRequest('stime') || hasRequest('isNow')),
		'period' => getRequest('period'),
		'stime' => getRequest('stime'),
		'isNow' => getRequest('isNow')
	])
];

$userid = null;

if ($data['alias']) {
	$data['users'] = API::User()->get([
		'output' => ['userid', 'alias', 'name', 'surname'],
		'filter' => ['alias' => $data['alias']],
		'preservekeys' => true
	]);

	if ($data['users']) {
		$user = reset($data['users']);

		$userid = $user['userid'];
	}
}

if (!$data['alias'] || $data['users']) {
	$config = select_config();

	// fetch alerts for different objects and sources and combine them in a single stream
	foreach (eventSourceObjects() as $eventSource) {
		$data['alerts'] = array_merge($data['alerts'], API::Alert()->get([
			'output' => ['alertid', 'actionid', 'userid', 'clock', 'sendto', 'subject', 'message', 'status',
				'retries', 'error', 'alerttype'
			],
			'selectMediatypes' => ['mediatypeid', 'description', 'maxattempts'],
			'userids' => $userid,
			'time_from' => zbxDateToTime($data['timeline']['stime']),
			'time_till' => zbxDateToTime($data['timeline']['usertime']),
			'eventsource' => $eventSource['source'],
			'eventobject' => $eventSource['object'],
			'sortfield' => 'alertid',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => $config['search_limit'] + 1
		]));
	}

	CArrayHelper::sort($data['alerts'], [
		['field' => 'alertid', 'order' => ZBX_SORT_DOWN]
	]);

	$data['alerts'] = array_slice($data['alerts'], 0, $config['search_limit'] + 1);

	// paging
	$data['paging'] = getPagingLine($data['alerts'], ZBX_SORT_DOWN, new CUrl('auditacts.php'));

	// get users
	if (!$data['alias']) {
		$data['users'] = API::User()->get([
			'output' => ['userid', 'alias', 'name', 'surname'],
			'userids' => zbx_objectValues($data['alerts'], 'userid'),
			'preservekeys' => true
		]);
	}
}

// get first alert clock
$first_alert = null;
if ($userid) {
	$first_alert = DBfetch(DBselect(
		'SELECT MIN(a.clock) AS clock'.
		' FROM alerts a'.
		' WHERE a.userid='.zbx_dbstr($userid)
	));
}
elseif ($data['alias'] === '') {
	$first_alert = DBfetch(DBselect('SELECT MIN(a.clock) AS clock FROM alerts a'));
}
$min_start_time = ($first_alert) ? $first_alert['clock'] - 1 : null;

// get actions names
if ($data['alerts']) {
	$data['actions'] = API::Action()->get([
		'output' => ['actionid', 'name'],
		'actionids' => array_unique(zbx_objectValues($data['alerts'], 'actionid')),
		'preservekeys' => true
	]);
}

// Show shorter timeline.
if ($min_start_time !== null && $min_start_time > 0) {
	$data['timeline']['starttime'] = date(TIMESTAMP_FORMAT, $min_start_time);
}

// render view
$auditView = new CView('administration.auditacts.list', $data);
$auditView->render();
$auditView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
