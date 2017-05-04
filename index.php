<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require 'Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->get('/', 'frontPage');
$app->get('/records/:num1/:num2(/)'.
		'(search/:search/?)(/)'.
		'(search_field/:search_field/?)(/)'.
		'(type/:type/?)(/)'.
		'(category/:category/?)(/)'.
		'(year_from/:year_from/?)(/)'.
		'(year_to/:year_to/?)(/)'.
		'(person_relation/:person_relation/?)(/)'.
		'(gender/:gender/?)(/)'.

		'(person_landskap/:person_landskap/?)'.
		'(person_county/:person_county/?)'.
		'(person_harad/:person_harad/?)'.
		'(person_socken/:person_socken/?)'.
		'(person_place/:person_place/?)'.
		
		'(record_landskap/:record_landskap/?)'.
		'(record_county/:record_county/?)'.
		'(record_harad/:record_harad/?)'.
		'(record_socken/:record_socken/?)'.
		'(record_place/:record_place/?)'.
		'(person/:person/?)'.

		'(only_categories/:only_categories/?)',
	'getRecords');

$app->get('/record/:record', 'getRecord');

$app->get('/homes(/)'.
		'(category/:category/?)(/)'.
		'(category_type/:category_type/?)(/)'.
		'(category_level/:category_level/?)(/)'.
		'(year_from/:year_from/?)(/)'.
		'(year_to/:year_to/?)(/)'.
		'(person_relation/:person_relation/?)(/)'.
		'(gender/:gender/?)(/)',
	'getHomes');

$app->get('/persons(/)'.
		'(relation/:relation/?)(/)'.
		'(gender/:gender/?)(/)'.
		'(category/:category/?)(/)'.
		'(category_type/:category_type/?)(/)'.
		'(category_level/:category_level/?)', 
	'getPersons');

$app->get('/person/:id', 'getPerson');

$app->get('/locations(/)'.
		'(search/:search/?)(/)'.
		'(search_field/:search_field/?)(/)'.
		'(type/:type/?)(/)'.
		'(category/:category/?)(/)'.
		'(year_from/:year_from/?)(/)'.
		'(year_to/:year_to/?)(/)'.
		'(person_relation/:person_relation/?)(/)'.
		'(gender/:gender/?)(/)'.

		'(person_landskap/:person_landskap/?)'.
		'(person_county/:person_county/?)'.
		'(person_harad/:person_harad/?)'.
		'(person_socken/:person_socken/?)'.
		'(person_place/:person_place/?)'.

		'(person_name/:person_name/?)'.

		'(only_categories/:only_categories/?)',
	'getLocations');

$app->get('/place/:place_id(/)(type/:type/?)(only_categories/:only_categories/?)', 'getPlace');

$app->get('/landskap', 'getLandskap');
$app->get('/county', 'getCounty');
$app->get('/harad', 'getHarad');
$app->get('/socken', 'getSocken');

$app->get('/json_export/:num1/:num2', 'getJsonExport');

$app->post('/feedback', 'sendFeedbackMail');

$app->get('/lm_proxy/:x/:y/:z', 'lantmaterietProxy');

$app->contentType('application/json;charset=utf-8');
$app->response()->header('Access-Control-Allow-Origin', '*');
$app->run();

function processItem(&$item, $key) {
	if (is_null($item)) {
		unset($item);
	}
	else if (is_string($item)) {
		$item = mb_encode_numericentity($item, array (0x80, 0xffff, 0, 0xffff), 'UTF-8');
	}
}

function array_unset_recursive(&$array, $remove) {
    if (!is_array($remove)) $remove = array($remove);
    foreach ($array as $key => &$value) {
        if (in_array($value, $remove)) unset($array[$key]);
        else if (is_array($value)) {
            array_unset_recursive($value, $remove);
        }
    }
}

function json_encode_is($arr) {
	array_walk_recursive($arr, 'processItem');
	array_unset_recursive($arr, null);
	return mb_decode_numericentity(json_encode($arr), array (0x80, 0xffff, 0, 0xffff), 'UTF-8');
}

function frontPage() {
	global $app;
	$app->contentType('text/html;charset=utf-8');
	readfile('front.html');
}

function getRecord($id) {
	$db = getConnection();

	$sql = 'SELECT records.id, '.
		'records.title, '.
		'records.text, '.
		'records.comment, '.
		'records.category, '.
		'categories.name categoryname, '.
		'records.type, '.
		'records.year, '.
		'records.archive, '.
		'records.archive_id, '.
		'records.archive_page, '.
		'records.informant_name, '.
		'records.source FROM records LEFT JOIN categories ON categories.id = records.category WHERE records.id = '.$id;

	$res = $db->query($sql);

	$row = $res->fetch_assoc();

	echo json_encode_is(getRecordObj($row));
}

function getRecordObj($row) {
	$db = getConnection();

	$placesSql = 'SELECT DISTINCT '.
		'socken.id, '.
		'socken.name, '.
		'socken.lat, '.
		'socken.lng, '.
		'socken.harad harad_id, '.
		'harad.name harad, '.
		'harad.landskap, '.
		'harad.lan county '.
		'FROM '.
		'socken '.
		'INNER JOIN harad ON socken.harad = harad.id '.
		'INNER JOIN records_places ON socken.id = records_places.place '.
		'WHERE '.
		'records_places.record = '.$row['id'];
	$placesRes = $db->query($placesSql);

	$places = [];

	while ($placeRow = $placesRes->fetch_assoc()) {
		array_push($places, array(
			'id' => $placeRow['id'], 
			'name' => $placeRow['name'],
			'harad_id' => $placeRow['harad_id'],
			'harad' => $placeRow['harad'],
			'landskap' => $placeRow['landskap'],
			'county' => $placeRow['county'],
			'lat' => $placeRow['lat'],
			'lng' => $placeRow['lng']
		));
	}

	$personsSql = 'SELECT '.
		'persons.id, '.
		'persons.name personname, '.
		'persons.gender, '.
		'persons.birth_year, '.
		'records_persons.relation, '.
		'socken.id sockenid, '.
		'socken.name, '.
		'socken.lat, '.
		'socken.lng, '.
		'harad.id harad_id, '.
		'harad.name harad, '.
		'harad.landskap, '.
		'harad.lan county '.
		'FROM '.
		'persons '.
		'INNER JOIN records_persons ON records_persons.person = persons.id '.
		'LEFT JOIN persons_places ON persons_places.person = persons.id '.
		'LEFT JOIN socken ON socken.id = persons_places.place '.
		'LEFT JOIN harad ON harad.id = socken.harad '.
		'WHERE '.
		'records_persons.record = '.$row['id'];

//	echo $personsSql;

	$personsRes = $db->query($personsSql);

	$persons = [];

	while ($personsRow = $personsRes->fetch_assoc()) {
		array_push($persons, array(
			'id' => $personsRow['id'], 
			'name' => $personsRow['personname'],
			'birth_year' => $personsRow['birth_year'],
			'gender' => $personsRow['gender'],
			'relation' => (
				$personsRow['relation'] == 'c' ? 'collector' :
				($personsRow['relation'] == 'i' ? 'informant' : '')
			),
			'home' => array(
				'id' => $personsRow['sockenid'],
				'name' => $personsRow['name'],
				'harad' => $personsRow['harad'],
				'harad_id' => $personsRow['harad_id'],
				'landskap' => $personsRow['landskap'],
				'county' => $personsRow['county'],
				'lat' => $personsRow['lat'],
				'lng' => $personsRow['lng']
			)
		));
	}

	$mediaRes = $db->query('SELECT '.
		'media.source, '.
		'media.type, '.
		'media.title '.
		'FROM '.
		'media '.
		'INNER JOIN records_media ON records_media.media = media.id '.
		'WHERE '.
		'records_media.record = '.$row['id']
	);

	$media = [];

	while ($mediaRow = $mediaRes->fetch_assoc()) {
		array_push($media, array(
			'source' => $mediaRow['source'], 
			'type' => $mediaRow['type'],
			'title' => $mediaRow['title']
		));
	}
		
/*
	$categoryRes = $db->query('SELECT '.
		'category, '.
		'level, '.
		'type '.
		'FROM '.
		'records_category '.
		'WHERE '.
		'record = '.$row['id']
	);

	$categories = [];

	while ($categoryRow = $categoryRes->fetch_assoc()) {

		$categoryMeta = array();

		if ($categoryRow['type'] == 'klintberg') {
			$catMetaRes = $db->query('SELECT name, name_en FROM categories_klintberg WHERE id = "'.$categoryRow['category'].'" AND level = '.$categoryRow['level']);
			$catMetaRow = $catMetaRes->fetch_assoc();

			$categoryMeta = array(
				'name' => $catMetaRow['name'],
				'name_en' => $catMetaRow['name_en']
			);
		}
		array_push($categories, array(
			'category' => $categoryRow['category'], 
			'level' => $categoryRow['level'], 
			'type' => $categoryRow['type'],
			'meta' => $categoryMeta
		));
	}
*/
	return array(
		'id' => $row['id'], 
		'title' => $row['title'],
		'text' => nl2br($row['text']),
		'comment' => nl2br($row['comment']),
		'taxonomy' => array(
			'category' => $row['category'],
			'name' => $row['categoryname']
		),
		'type' => $row['type'],
		'year' => $row['year'],
//		'taxonomy' => $categories,
		'archive' => array(
			'archive' => $row['archive'],
			'archive_id' => $row['archive_id'],
			'page' => $row['archive_page']
		),
		'printed_source' => $row['source'],
		'places' => $places,
		'informant_name' => $row['informant_name'],
		'persons' => $persons,
		'media' => $media
	);
}

function getRecords(
		$num1 = null, 
		$num2 = null, 
		$search = null, 
		$searchField = null, 
		$type = null, 
		$category = null, 
		$yearFrom = null, 
		$yearTo = null, 
		$personRelation = null, 
		$gender = null, 

		$person_landskap = null,
		$person_county = null,
		$person_harad = null,
		$person_socken = null,
		$person_place = null,

		$record_landskap = null,
		$record_county = null,
		$record_harad = null,
		$record_socken = null,
		$record_place = null,

		$person = null,

		$only_categories = null) {
	$data = getRecordsArray($num1, 
		$num2, 
		$search, 
		$searchField, 
		$type, 
		$category, 
		$yearFrom, 
		$yearTo, 
		$personRelation, 
		$gender, 
		$person_landskap, 
		$person_county, 
		$person_harad, 
		$person_socken, 
		$person_place, 
		$record_landskap, 
		$record_county, 
		$record_harad, 
		$record_socken, 
		$record_place,
		null,
		null,
		$person,
		$only_categories);

	echo json_encode_is($data);
}

function getRecordsArray(
		$num1 = null, 
		$num2 = null, 
		$search = null, 
		$searchField = 'records', 
		$type = null, 
		$category = null, 
		$yearFrom = null, 
		$yearTo = null, 
		$relation = null, 
		$gender = null, 

		$person_landskap = null,
		$person_county = null,
		$person_harad = null,
		$person_socken = null,
		$person_place = null,

		$record_landskap = null,
		$record_county = null,
		$record_harad = null,
		$record_socken = null,
		$record_place = null,

		$collector = null,
		$informant = null,
		$person = null,

		$onlyCategories = null) {
	$where = array();
	$join = array();

	if (!is_null($type) && $type != '') {
		if (strpos($type, ';')) {
			$types = explode(';', $type);
			$typeCriteria = '(LOWER(records.type) = "'.implode('" OR LOWER(records.type) = "', $types).'")';
			array_push($where, $typeCriteria);
		}
		else {
			array_push($where, 'LOWER(records.type) = "'.strtolower($type).'"');
		}
	}

	if (!is_null($category) && $category != '') {
		if (strpos($category, ';')) {
			$categories = explode(';', $category);
			$categoryCriteria = '(LOWER(records.category) = "'.implode('" OR LOWER(records.category) = "', $categories).'")';
			array_push($where, $categoryCriteria);
		}
		else {
			array_push($where, 'LOWER(records.category) = "'.strtolower($category).'"');
		}
	}

	if (!is_null($search) && $search != '') {
		if ($searchField == 'record') {
			if (strpos($search, ';') !== false) {
				array_push($where, 'MATCH(text) AGAINST("'.str_replace(';', '+', $search).'")');
			}
			else {			
				array_push($where, '('.
					'LOWER(records.title) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
					'LOWER(records.text) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
					'LOWER(records.archive_id) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%"'.
					')');
			}
		}
		else if ($searchField == 'person') {
			array_push($where, '('.
				'LOWER(persons.name) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%"'.
				')');
		}
		else if ($searchField == 'place') {
			array_push($where, '('.
				'LOWER(rsh.name) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
				'LOWER(rsh.landskap) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
				'LOWER(rsh.lan) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
				'LOWER(rs.name) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%"'.
				')');
		}
		else {
			array_push($where, '('.
				'LOWER(records.title) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
				'LOWER(records.text) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
				'LOWER(records.archive_id) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%"'.
				')');
		}
	}

	if (!is_null($yearFrom) && $yearFrom != '') {
		array_push($where, 'records.year >= '.$yearFrom);
	}

	if (!is_null($yearTo) && $yearTo != '') {
		array_push($where, 'records.year <= '.$yearTo);
	}

	if ((!is_null($gender) && $gender != '') || 
			(!is_null($person_county) && $person_county != '') || 
			(!is_null($person_harad) && $person_harad != '') || 
			(!is_null($person_landskap) && $person_landskap != '') || 
			(!is_null($person_socken) && $person_socken != '') || 
			(!is_null($person_place) && $person_place != '') || 
			(!is_null($search) && $search != '' && $searchField == 'person')) {
		array_push($join, 'LEFT JOIN records_persons ON records_persons.record = records.id'.(
			!is_null($relation) && $relation != '' ? ' AND records_persons.relation = "'.$relation.'"' : ''
		));
		array_push($join, 'LEFT JOIN persons ON persons.id = records_persons.person');
	}

	if ((!is_null($person_county) && $person_county != '') || 
			(!is_null($person_harad) && $person_harad != '') || 
			(!is_null($person_landskap) && $person_landskap != '') || 
			(!is_null($person_socken) && $person_socken != '') || 
			(!is_null($person_place) && $person_place != '')) {
		array_push($join, 'LEFT JOIN persons_places ON persons_places.person = persons.id');
		array_push($join, 'LEFT JOIN socken ps ON ps.id = persons_places.place');
		array_push($join, 'LEFT JOIN harad psh ON psh.id = ps.harad');
	}

	if (!is_null($person_county) && $person_county != '') {
		array_push($where, 'LOWER(psh.lan) LIKE "%'.mb_convert_case($person_county, MB_CASE_LOWER, "UTF-8").'%"');
	}

	if (!is_null($person_harad) && $person_harad != '') {
		array_push($where, 'LOWER(psh.name) LIKE "%'.mb_convert_case($person_harad, MB_CASE_LOWER, "UTF-8").'%"');
	}

	if (!is_null($person_landskap) && $person_landskap != '') {
		array_push($where, 'LOWER(psh.landskap) LIKE "%'.mb_convert_case($person_landskap, MB_CASE_LOWER, "UTF-8").'%"');
	}

	if (!is_null($person_socken) && $person_socken != '') {
		array_push($where, 'LOWER(ps.name) LIKE "%'.mb_convert_case($person_socken, MB_CASE_LOWER, "UTF-8").'%"');
	}

	if (!is_null($person_place) && $person_place != '') {
		array_push($where, 'ps.id = '.$person_place);
	}

	if ((!is_null($record_county) && $record_county != '') || 
			(!is_null($record_harad) && $record_harad != '') || 
			(!is_null($record_landskap) && $record_landskap != '') || 
			(!is_null($record_socken) && $record_socken != '') || 
			(!is_null($record_place) && $record_place != '') || 
			(!is_null($search) && $search != '' && $searchField == 'place')) {
		if ((!is_null($search) && $search != '' && $searchField == 'place')) {
			array_push($join, 'LEFT JOIN records_places ON records_places.record = records.id');

			array_push($join, 'INNER JOIN socken rs ON rs.id = records_places.place AND LOWER(rs. NAME) LIKE "%'.mb_convert_case($record_county, MB_CASE_LOWER, "UTF-8").'%"');
			array_push($join, 'INNER JOIN harad rsh ON rsh.id = rs.harad AND (LOWER(rsh. NAME) LIKE "%'.mb_convert_case($record_county, MB_CASE_LOWER, "UTF-8").'%" OR LOWER(rsh.landskap) LIKE "%'.mb_convert_case($record_county, MB_CASE_LOWER, "UTF-8").'%" OR LOWER(rsh.lan) LIKE "%'.mb_convert_case($record_county, MB_CASE_LOWER, "UTF-8").'%")');
		}
		else {		
			array_push($join, 'LEFT JOIN records_places ON records_places.record = records.id');
			array_push($join, 'LEFT JOIN socken rs ON rs.id = records_places.place');
			array_push($join, 'LEFT JOIN harad rsh ON rsh.id = rs.harad');
		}
	}

	if (!is_null($record_county) && $record_county != '') {
		array_push($where, 'LOWER(rsh.lan) LIKE "%'.mb_convert_case($record_county, MB_CASE_LOWER, "UTF-8").'%"');
	}

	if (!is_null($record_harad) && $record_harad != '') {
		array_push($where, 'LOWER(rsh.name) LIKE "%'.mb_convert_case($record_harad, MB_CASE_LOWER, "UTF-8").'%"');
	}

	if (!is_null($record_landskap) && $record_landskap != '') {
		array_push($where, 'LOWER(rsh.landskap) LIKE "%'.mb_convert_case($record_landskap, MB_CASE_LOWER, "UTF-8").'%"');
	}

	if (!is_null($record_socken) && $record_socken != '') {
		array_push($where, 'LOWER(rs.name) LIKE "%'.mb_convert_case($record_socken, MB_CASE_LOWER, "UTF-8").'%"');
	}

	if (!is_null($record_place) && $record_place != '') {
		array_push($where, 'rs.id = '.$record_place);
	}

	if (!is_null($gender) && $gender != '') {
		array_push($where, 'LOWER(persons.gender) = "'.strtolower($gender).'"');
	}

	if ((!is_null($collector) && $collector != '') || (!is_null($informant) && $informant != '') || (!is_null($person) && $person != '')) {
		array_push($join, 'LEFT JOIN records_persons ON records_persons.record = records.id');
		array_push($join, 'LEFT JOIN persons ON persons.id = records_persons.person');

		if (!is_null($collector)) {
			array_push($where, 'persons.id = '.$collector);
			array_push($where, 'records_persons.relation = "c"');
		}

		if (!is_null($informant)) {
			array_push($where, 'persons.id = '.$informant);
			array_push($where, 'records_persons.relation = "i"');
		}

		if (!is_null($person) && $person != '') {
			array_push($where, 'persons.id = '.$person);
		}
	}

	if ($onlyCategories) {
		array_push($where, 'records.category != ""');
	}

	array_push($join, 'LEFT JOIN categories ON categories.id = records.category');

	$sql = 'SELECT DISTINCT SQL_CALC_FOUND_ROWS  records.id, '.
		'records.title, '.
		'records.text, '.
		'records.category, '.
		'categories.name categoryname, '.
		'records.type, '.
		'records.year, '.
		'records.archive, '.
		'records.archive_id, '.
		'records.archive_page, '.
		'records.informant_name, '.
		(
			(!is_null($person) && $person != '') ? 'records_persons.relation, ' : ''
		).
		'records.source FROM records'.
		(
			count($join) > 0 ? ' '.implode(' ', $join) : ''
		).
		(
			count($where) > 0 ? ' WHERE '.implode(' AND ', $where) : ''
		).
		(
			' ORDER BY FIELD(records.type, "arkiv", "tryckt", "register"), records.year'
		).
		(
			!is_null($num1) && is_null($num2) ? ' LIMIT 0, '.$num1 :
			!is_null($num1) && !is_null($num2) ? ' LIMIT '.$num1.', '.$num2 :
			''
		)
	;

	$db = getConnection();

	$res = $db->query($sql);

	$totalRes = $db->query('SELECT FOUND_ROWS() total');
	$totalRow = $totalRes->fetch_assoc();
	
	$data = array();
	while ($row = $res->fetch_assoc()) {

		$placesSql = 'SELECT DISTINCT '.
			'socken.id, '.
			'socken.name, '.
			'socken.lat, '.
			'socken.lng, '.
			'harad.name harad, '.
			'harad.landskap, '.
			'harad.lan county '.
			'FROM '.
			'socken '.
			'INNER JOIN harad ON socken.harad = harad.id '.
			'INNER JOIN records_places ON socken.id = records_places.place '.
			'WHERE '.
			'records_places.record = '.$row['id'];
		$placesRes = $db->query($placesSql);

		$places = [];

		while ($placeRow = $placesRes->fetch_assoc()) {
			array_push($places, array(
				'id' => $placeRow['id'], 
				'name' => $placeRow['name'],
				'harad' => $placeRow['harad'],
				'landskap' => $placeRow['landskap'],
				'county' => $placeRow['county'],
				'lat' => $placeRow['lat'],
				'lng' => $placeRow['lng']
			));
		}

		$recordObj = array(
			'id' => $row['id'], 
			'title' => $row['title'],
			'taxonomy' => array(
				'category' => $row['category'],
				'name' => $row['categoryname']
			),
			'type' => $row['type'],
			'year' => $row['year'],
			'archive' => array(
				'archive' => $row['archive'],
				'archive_id' => $row['archive_id'],
				'page' => $row['archive_page']
			),
			'places' => $places
		);

		if (isset($row['relation'])) {
			$recordObj['relation'] = $row['relation'];
		}
		array_push($data, $recordObj);
	}

	return array(
		'metadata' => array(
			'sql' => $sql,
			'total' => $totalRow['total'],
		),
		'data' => $data
	);
}

function getPerson($id) {
	$db = getConnection();

	$res = $db->query('SELECT * FROM persons WHERE id = '.$id);

	$row = $res->fetch_assoc();

	$relatedRecords = getRecordsArray(0, 100, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, $id);

	$homesSql = 'SELECT '.
		'socken.id, '.
		'socken.name, '.
		'socken.lat, '.
		'socken.lng, '.
		'harad.name harad, '.
		'harad.landskap, '.
		'harad.lan county '.
		'FROM '.
		'socken '.
		'INNER JOIN harad ON socken.harad = harad.id '.
		'LEFT JOIN persons_places ON persons_places.place = socken.id '.
		'WHERE '.
		'persons_places.person = '.$id;

	$homesRes = $db->query($homesSql);

//	echo $homesSql;

	$homes = [];

	while ($homesRow = $homesRes->fetch_assoc()) {
		array_push($homes, array(
			'id' => $homesRow['id'], 
			'name' => $homesRow['name'],
			'harad' => $homesRow['harad'],
			'landskap' => $homesRow['landskap'],
			'county' => $homesRow['county'],
			'lat' => $homesRow['lat'],
			'lng' => $homesRow['lng']
		));
	}

	$data = array(
		'id' => $row['id'], 
		'name' => $row['name'],
		'birth_year' => $row['birth_year'],
		'gender' => $row['gender'],
		'address' => $row['address'],
		'biography' => nl2br($row['biography']),
		'image' => $row['image'],
		'records' => $relatedRecords['data'],
		'home' => $homes
	);

	echo json_encode_is($data);
}

function getPersons($relation = null, $gender = null, $category = null, $categoryType = 'klintberg', $categoryLevel = 0) {
	$join = array();
	$where = array();

	if ((!is_null($category) && $category != '') || (!is_null($relation) && $relation != '')) {
		array_push($join, 'INNER JOIN records_persons ON records_persons.person = persons.id');
	}

	if (!is_null($category) && $category != '') {
		array_push($where, 'LOWER(records_category.category) = "'.strtolower($category).'"');
		array_push($where, 'LOWER(records_category.type) = "'.strtolower($categoryType).'"');
		array_push($where, 'records_category.level = '.$categoryLevel);

		array_push($join, 'INNER JOIN records ON records.id = records_persons.record');
		array_push($join, 'INNER JOIN records_category ON records_category.record = records.id');
	}

	if (!is_null($relation) && $relation != '') {
		array_push($where, 'LOWER(records_persons.relation) = "'.strtolower($relation).'"');
	}

	if (!is_null($gender) && $gender != '') {
		array_push($where, 'LOWER(gender) = "'.strtolower($gender).'"');
	}

	$sql = 'SELECT persons.id, persons.name, persons.gender, persons.birth_year, '.
		'(SELECT COUNT(*) FROM records_persons WHERE records_persons.person = persons.id) recordscount '.
		'FROM persons '.
		(
			count($join) > 0 ? ' '.implode(' ', $join) : ''
		).
		(
			count($where) > 0 ? ' WHERE '.implode(' AND ', $where) : ''
		)
	;

	$db = getConnection();

	$res = $db->query($sql);
	
	$data = array();
	while ($row = $res->fetch_assoc()) {
		$homesRes = $db->query('SELECT DISTINCT '.
			'socken.id, '.
			'socken.name, '.
			'socken.lat, '.
			'socken.lng, '.
			'harad.name harad, '.
			'harad.landskap, '.
			'harad.lan county '.
			'FROM '.
			'socken '.
			'INNER JOIN harad ON socken.harad = harad.id '.
			'INNER JOIN records_places ON socken.id = records_places.place '.
			'LEFT JOIN persons_places ON persons_places.place = socken.id '.
			'WHERE '.
			'persons_places.person = '.$row['id']
		);

		$homes = [];

		while ($homesRow = $homesRes->fetch_assoc()) {
			array_push($homes, array(
				'id' => $homesRow['id'], 
				'name' => $homesRow['name'],
				'harad' => $homesRow['harad'],
				'landskap' => $homesRow['landskap'],
				'county' => $homesRow['county'],
				'lat' => $homesRow['lat'],
				'lng' => $homesRow['lng']
			));
		}

		array_push($data, array(
			'id' => $row['id'], 
			'name' => $row['name'],
			'birth_year' => $row['birth_year'],
			'gender' => $row['gender'],
			'records' => $row['recordscount'],
			'home' => $homes
		));
	}
	
	echo json_encode_is(array(
		'sql' => $sql,
		'data' => $data
	));
}

function getLocations(
		$search = null,
		$searchField = 'record',
		$type = null, 
		$category = null, 
		$yearFrom = null, 
		$yearTo = null, 
		$relation = null, 
		$gender = null, 
		$person_county = null,
		$person_landskap = null,
		$person_harad = null,
		$person_socken = null,
		$person_place = null,
		$person_name = null,
		$only_categories = null
	) {
	$join = array();
	$where = array();

	$data = array();

	if ($type == 'random') {
		$max = is_null($category) ? 1000 : $category;

		for ($i = 0; $i<$max; $i++) {
			$lat = rand(56000, 68000)/1000;		
			$lng = rand(11000, 24000)/1000;		

				array_push($data, array(
				'id' => $i+1, 
				'name' => 'random-'.($i+1),
				'lat' => $lat,
				'lng' => $lng
			));
		}
	}
	else {
		if (!is_null($gender) || 
			!is_null($person_name) || 
			!is_null($search) || 
			!is_null($type) || 
			!is_null($category) || 
			!is_null($relation) || 
			!is_null($yearFrom) || 
			!is_null($yearTo) || 
			!is_null($only_categories)
		) {
			array_push($join, 'LEFT JOIN records_places ON records_places.place = socken.id');
			array_push($join, 'LEFT JOIN records ON records.id = records_places.record');
		}

		if (!is_null($search) && $search != '') {
			if ($searchField == 'record') {
				if (strpos($search, ';') !== false) {
					array_push($where, 'MATCH(records.text) AGAINST("'.str_replace(';', '+', $search).'")');
				}
				else {
					array_push($where, '('.
						'LOWER(records.title) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
						'LOWER(records.text) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
						'LOWER(records.archive_id) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%"'.
						')');
				}
			}
			else if ($searchField == 'person') {
				array_push($where, '('.
					'LOWER(persons.name) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%"'.
					')');
			}
			else if ($searchField == 'place') {
				array_push($where, '('.
					'LOWER(harad.name) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
					'LOWER(harad.landskap) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
					'LOWER(harad.lan) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%" OR '.
					'LOWER(socken.name) LIKE "%'.mb_convert_case($search, MB_CASE_LOWER, "UTF-8").'%"'.
					')');
				array_push($join, 'INNER JOIN harad ON harad.id = socken.harad');
			}
			else {

			}
		}

		if (!is_null($type) && $type != '') {
			if (strpos($type, ';')) {
				$types = explode(';', $type);
				$typeCriteria = '(LOWER(records.type) = "'.implode('" OR LOWER(records.type) = "', $types).'")';
				array_push($where, $typeCriteria);
			}
			else {
				array_push($where, 'LOWER(records.type) = "'.strtolower($type).'"');
			}
		}

		if (!is_null($category) && $category != '') {
			if (strpos($category, ';')) {
				$categories = explode(';', $category);
				$categoryCriteria = '(LOWER(records.category) = "'.implode('" OR LOWER(records.category) = "', $categories).'")';
				array_push($where, $categoryCriteria);
			}
			else {
				array_push($where, 'LOWER(records.category) = "'.strtolower($category).'"');
			}
		}

		if (
			(!is_null($gender) && $gender != '') || 
			(!is_null($person_name) && $person_name != '') || 
			(!is_null($person_county) && $person_county != '') || 
			(!is_null($person_harad) && $person_harad != '') || 
			(!is_null($person_landskap) && $person_landskap != '') || 
			(!is_null($person_socken) && $person_socken != '') || 
			(!is_null($search) && $search != '' && $searchField == 'person')
		) {
			array_push($join, 'LEFT JOIN records_persons ON records_persons.record = records.id');
			array_push($join, 'LEFT JOIN persons ON records_persons.person = persons.id');
		}

		if (
			(!is_null($person_county) && $person_county != '') || 
			(!is_null($person_harad) && $person_harad != '') || 
			(!is_null($person_landskap) && $person_landskap != '') || 
			(!is_null($person_socken) && $person_socken != '') || 
			(!is_null($person_place) && $person_place != '')
		) {
			array_push($join, 'LEFT JOIN persons_places ON persons_places.person = persons.id');
			array_push($join, 'LEFT JOIN socken ps ON ps.id = persons_places.place');
			array_push($join, 'LEFT JOIN harad psh ON psh.id = ps.harad');
		}

		if (!is_null($yearFrom) && $yearFrom != '') {
			array_push($where, 'records.year >= '.$yearFrom);
		}

		if (!is_null($yearTo) && $yearTo != '') {
			array_push($where, 'records.year <= '.$yearTo);
		}

		if (!is_null($relation) && $relation != '') {
			array_push($where, 'LOWER(records_persons.relation) = "'.strtolower($relation).'"');
		}

		if (!is_null($gender) && $gender != '') {
			array_push($where, 'LOWER(persons.gender) = "'.strtolower($gender).'"');
		}

		if (!is_null($person_name) && $person_name != '') {
			array_push($where, 'LOWER(persons.name) = "'.strtolower($person_name).'"');
		}

		if (!is_null($person_county) && $person_county != '') {
			array_push($where, 'LOWER(psh.lan) LIKE "%'.mb_convert_case($person_county, MB_CASE_LOWER, "UTF-8").'%"');
		}

		if (!is_null($person_harad) && $person_harad != '') {
			array_push($where, 'LOWER(psh.name) LIKE "%'.mb_convert_case($person_harad, MB_CASE_LOWER, "UTF-8").'%"');
		}

		if (!is_null($person_landskap) && $person_landskap != '') {
			array_push($where, 'LOWER(psh.landskap) LIKE "%'.mb_convert_case($person_landskap, MB_CASE_LOWER, "UTF-8").'%"');
		}

		if (!is_null($person_socken) && $person_socken != '') {
			array_push($where, 'LOWER(ps.name) LIKE "%'.mb_convert_case($person_socken, MB_CASE_LOWER, "UTF-8").'%"');
		}

		if (!is_null($person_place) && $person_place != '') {
			array_push($where, 'ps.id = '.$person_place);
		}

		if (!is_null($only_categories) && $only_categories != '') {
			array_push($where, 'records.category != ""');
		}

		array_push($where, 'socken.lat IS NOT NULL');
		array_push($where, 'socken.lng IS NOT NULL');

	//	array_push($join, 'INNER JOIN harad ON harad.id = socken.harad');

		$sql = 'SELECT DISTINCT socken.id, socken.name, socken.lat, socken.lng, COUNT(records.id) c /*, harad.name harad, harad.landskap, harad.lan county */ '.
			'FROM socken '.
			(
				count($join) > 0 ? ' '.implode(' ', $join) : ''
			).
			(
				count($where) > 0 ? ' WHERE '.implode(' AND ', $where) : ''
			).
			' GROUP BY socken.id'
		;

		
		$db = getConnection();

		$res = $db->query($sql);

		while ($row = $res->fetch_assoc()) {
			array_push($data, array(
				'id' => $row['id'], 
				'name' => $row['name'],
	//			'harad' => $row['harad'],
	//			'landskap' => $row['landskap'],
	//			'county' => $row['county'],
				'lat' => $row['lat'],
				'lng' => $row['lng'],
				'c' => $row['c']
			));
		}
	}
	
	echo json_encode_is(array(
		'sql' => isset($sql) ? $sql : 'random generated',
		'data' => $data
	));
}

function getPlace($id, $type = null, $only_categories = null) {
	$sql = 'SELECT socken.id, socken.name, socken.lat, socken.lng, harad.name harad, harad.landskap, harad.lan county '.
		'FROM socken INNER JOIN harad ON harad.id = socken.harad WHERE socken.id = '.$id;

	$db = getConnection();

	$res = $db->query($sql);

	$row = $res->fetch_assoc();

	//	$num1, $num2, $search, $searchField, $type, $category, $yearFrom, $yearTo, $personRelation, $gender, $person_landskap, $person_county, $person_harad, $person_socken, $person_place, $record_landskap, $record_county, $record_harad, $record_socken, $record_place,null,null,$person,$only_categories

	$records = getRecordsArray(0, 200, null, null, $type, null, null, null, null, null, null, null, null, null, null, null, null, null, null, $id, null, null, null, $only_categories);

	$persons = array();

	$personsRes = $db->query('SELECT '.
		'persons.name, '.
		'persons.gender, '.
		'persons.birth_year, '.
		'persons.id '.
		'FROM '.
		'persons '.
		'INNER JOIN persons_places ON persons.id = persons_places.person '.
		'WHERE '.
		'persons_places.place = '.$id);

	while ($personRow = $personsRes->fetch_assoc()) {
		array_push($persons, array(
			'id' => $personRow['id'], 
			'name' => $personRow['name'],
			'birth_year' => $personRow['birth_year'],
			'gender' => $personRow['gender']
		));
	}

	$informants = array();

	$informantsRes = $db->query('SELECT DISTINCT '.
		'persons.name, '.
		'persons.gender, '.
		'persons.birth_year, '.
		'persons.id '.
		'FROM '.
		'records '.
		'INNER JOIN records_places ON records_places.record = records.id '.
		'INNER JOIN records_persons ON records_persons.record = records.id '.
		'INNER JOIN persons ON records_persons.person = persons.id '.
		'WHERE '.
		'records_persons.relation = "i" AND '.
		'records_places.place = '.$id);

	while ($informantsRow = $informantsRes->fetch_assoc()) {
		array_push($informants, array(
			'id' => $informantsRow['id'], 
			'name' => $informantsRow['name'],
			'birth_year' => $informantsRow['birth_year'],
			'gender' => $informantsRow['gender']
		));
	}

	$data = array(
		'id' => $row['id'], 
		'name' => $row['name'],
		'harad' => $row['harad'],
		'landskap' => $row['landskap'],
		'county' => $row['county'],
		'lat' => $row['lat'],
		'lng' => $row['lng'],
		'records' => $records['data'],
		'persons' => $persons,
		'informants' => $informants
	);
	
	echo json_encode_is($data);
}

function getHomes(
		$category = null,
		$categoryType = 'klintberg',
		$categoryLevel = 0,
		$yearFrom = null,
		$yearTo = null,
		$relation = null,
		$gender = null
	) {
	$join = array();
	$where = array();

	array_push($join, 'INNER JOIN persons_places ON persons_places.person = persons.id');
	array_push($join, 'INNER JOIN socken ON socken.id = persons_places.id');
	array_push($join, 'INNER JOIN harad ON harad.id = socken.harad');
	array_push($join, 'INNER JOIN records_persons ON records_persons.person = persons.id');
	array_push($join, 'INNER JOIN records ON records.id = records_persons.record');

	if (!is_null($category) && $category != '') {
		array_push($where, 'LOWER(records_category.category) = "'.strtolower($category).'"');
		array_push($where, 'LOWER(records_category.type) = "'.($categoryType == '' ? 'klintberg' : strtolower($categoryType)).'"');
		array_push($where, 'records_category.level = '.($categoryLevel == '' ? 0 : $categoryLevel));

		array_push($join, 'INNER JOIN records_category ON records_category.record = records.id');
	}

	if (!is_null($gender)) {
		array_push($where, 'persons.gender = "'.$gender.'"');
	}

	if (!is_null($yearFrom) && $yearFrom != '') {
		array_push($where, 'records.year >= '.$yearFrom);
	}

	if (!is_null($yearTo) && $yearTo != '') {
		array_push($where, 'records.year <= '.$yearTo);
	}

	if (!is_null($relation) && $relation != '') {
		array_push($where, 'LOWER(records_persons.relation) = "'.strtolower($relation).'"');
	}

	array_push($where, 'socken.lat IS NOT NULL');
	array_push($where, 'socken.lng IS NOT NULL');

	$sql = 'SELECT DISTINCT socken.id, socken.name, socken.lat, socken.lng, harad.name harad, harad.landskap, harad.lan county '.
		'FROM persons '.
		(
			count($join) > 0 ? ' '.implode(' ', $join) : ''
		).
		(
			count($where) > 0 ? ' WHERE '.implode(' AND ', $where) : ''
		)
	;

	$db = getConnection();

	$res = $db->query($sql);

	$data = array();
	while ($row = $res->fetch_assoc()) {
		array_push($data, array(
			'id' => $row['id'], 
			'name' => $row['name'],
			'harad' => $row['harad'],
			'landskap' => $row['landskap'],
			'county' => $row['county'],
			'lat' => $row['lat'],
			'lng' => $row['lng']
		));
	}
	
	echo json_encode_is(array(
		'sql' => $sql,
		'data' => $data
	));

}

function getJsonExport($num1, $num2) {
	$db = getConnection();

	$sql = 'SELECT records.id, '.
		'records.title, '.
		'records.text, '.
		'records.comment, '.
		'records.category, '.
		'categories.name categoryname, '.
		'records.type, '.
		'records.year, '.
		'records.archive, '.
		'records.archive_id, '.
		'records.archive_page, '.
		'records.informant_name, '.
		'records.source FROM records LEFT JOIN categories ON categories.id = records.category '.
		'LIMIT '.$num1.', '.$num2;

	$res = $db->query($sql);

	$data = array();
	while ($row = $res->fetch_assoc()) {
		array_push($data, getRecordObj($row));
	}

	echo json_encode_is(array(
		'data' => $data
	));	
}

function getSocken() {
	$db = getConnection();

	$res = $db->query('SELECT '.
		'socken.id, '.
		'socken.name, '.
		'socken.lat, '.
		'socken.lng, '.
		'harad.name harad, '.
		'harad.landskap, '.
		'harad.lan county '.
		'FROM '.
		'socken '.
		'INNER JOIN harad ON harad.id = socken.harad');

	$data = array();
	while ($row = $res->fetch_assoc()) {
		array_push($data, array(
			'id' => $row['id'], 
			'name' => $row['name'],
			'harad' => $row['harad'],
			'landskap' => $row['landskap'],
			'county' => $row['county'],
			'lat' => $row['lat'],
			'lng' => $row['lng']
		));
	}
	
	echo json_encode_is($data);
}

function getHarad() {
	$db = getConnection();

	$res = $db->query('SELECT '.
		'harad.name, '.
		'harad.landskap, '.
		'harad.lan county '.
		'FROM '.
		'harad');

	$data = array();
	while ($row = $res->fetch_assoc()) {
		array_push($data, array(
			'name' => $row['name'],
			'landskap' => $row['landskap'],
			'county' => $row['county']
		));
	}
	
	echo json_encode_is($data);
}

function getLandskap() {
	$db = getConnection();

	$res = $db->query('SELECT DISTINCT '.
		'harad.landskap, '.
		'harad.lan county '.
		'FROM '.
		'harad');

	$data = array();
	while ($row = $res->fetch_assoc()) {
		array_push($data, array(
			'name' => $row['landskap'],
			'county' => $row['county']
		));
	}
	
	echo json_encode_is($data);
}

function getCounty() {
	$db = getConnection();

	$res = $db->query('SELECT DISTINCT '.
		'harad.lan name '.
		'FROM '.
		'harad');

	$data = array();
	while ($row = $res->fetch_assoc()) {
		array_push($data, array(
			'name' => $row['name']
		));
	}
	
	echo json_encode_is($data);
}

function sendFeedbackMail() {
	$app = \Slim\Slim::getInstance();
	$request = $app->request();
	$requestBody = $request->getBody();

	$requestData = json_decode($requestBody, true);

	$headers = 'From: sagenkarta@sprakochfolkminnen.se'."\r\n".
		'Reply-To: '.$requestData['from_email']."\r\n".
		'X-Mailer: PHP/'.phpversion().
		'Content-Type: text/html; charset=UTF-8';

	if (mail('trausti.dagsson@sprakochfolkminnen.se', utf8_decode($requestData['subject']), utf8_decode($requestData['message']), $headers)) {
		echo json_encode_is(array(
			'success' => 'mail sent from '.$requestData['from_email']
		));
	}
	else {
		echo json_encode_is(array(
			'error' => 'mail sending failed'
		));
	}
}

function lantmaterietProxy($x, $y, $z) {
// http://maps.lantmateriet.se/topowebb/v1/wmts/1.0.0/topowebb/default/3006/{x}/{y}/{z}.png

	global $app;

	$app->contentType('image/png');

	echo file_get_contents('http://ifsf0001:k7r9ZjQh4SN77N6p@maps.lantmateriet.se/topowebb/v1/wmts/1.0.0/topowebb/default/3006/'.$x.'/'.$y.'/'.$z.'.png');
}

function getConnection($dbName = null) {
	include 'config.php';
	
	$db = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	$db->set_charset('utf8');

	return $db;
}

?>