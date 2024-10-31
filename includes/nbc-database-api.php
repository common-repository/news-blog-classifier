<?php
function nbc_get_df($word){
	global $wpdb;
	
	$sql = $wpdb->prepare("SELECT count(*) AS count FROM wp_nbc_word_lists WHERE word_id = (SELECT id FROM wp_nbc_root_words WHERE word=%s);", $word);
	
	$result = $wpdb->get_row($sql);
	
	if (isset($result)) {
		//result
		$numberOfDocumentHasTerm = $result->count;
	}
	else{
		$numberOfDocumentHasTerm = 1;
	}
	
	return $numberOfDocumentHasTerm;
}

function nbc_select_all_root(){
	global $wpdb;
	
	$sql = "SELECT id, word FROM wp_nbc_root_words;";

	$result = $wpdb->get_results($sql);
	
	if (isset($result)) {
		//result
		foreach($result as $row) {
			 $rootWordsFromDb[trim($row->word)] = $row->id;
		}			
	}
	else{
		$rootWordsFromDb = null;
	}
	
	return $rootWordsFromDb;
}

function nbc_select_all_word(){
	global $wpdb;
	
	$sql = "SELECT id, name FROM wp_nbc_words;";
	$result = $wpdb->get_results($sql);
	
	if (isset($result)) {
		foreach($result as $row) {
			 $rootWordsFromDb[trim($row->name)] = $row->id;
		}			
	}
	else{
		$rootWordsFromDb = null;
	}
	
	return $rootWordsFromDb;
}

function nbc_get_stopword_from_db(){
	global $wpdb;
	
	$sql = "
			SELECT detail FROM wp_nbc_stopword_lists;
		";
	$result = $wpdb->get_results($sql);
	if (isset($result)) {
		//result
		foreach($result as $row) {
			 $stopwords[] = trim($row->detail);
		}
	}
	return $stopwords;
}

function nbc_select_all_word_detail(){
	global $wpdb;
	
	$sql = "SELECT w.name, r.word FROM wp_nbc_words w, wp_nbc_root_words r, wp_nbc_word_details wd WHERE wd.word_id = w.id && r.id =wd.root_id";

	$result = $wpdb->get_results($sql);
	
	if (isset($result)) {
		//result
		foreach($result as $row) {
			 $detailWordsFromDb[trim($row->name)] = $row->word;
		}			
	}
	else{
		$detailWordsFromDb = null;
	}
	
	return $detailWordsFromDb;
}

function nbc_insert($tableName, $tableFields, $values){
	global $wpdb;
	
	$sql = "INSERT INTO $tableName($tableFields) VALUES ".$values.";";

	$result = $wpdb->query($sql);
	if($result){
		$response = 1;
	}
	else{
		$response = null;
	}
	
	return $response;
}

function nbc_get_N_from_db(){
	global $wpdb;
	
	$sql = "SELECT count(*) AS count FROM wp_nbc_document_collections;";
	$result = $wpdb->get_row($sql);
	
	if (isset($result)) {
		//result
		$numberOfDocumentCollections = $result->count;			
	}
	else{
		$numberOfDocumentCollections = null;
	}
	
	return $numberOfDocumentCollections;
}

function nbc_get_all_word($documentId){
	global $wpdb;
	
	$sql = $wpdb->prepare("SELECT wl.tf, r.word FROM wp_nbc_word_lists wl, wp_nbc_root_words r WHERE wl.document_id = %d AND r.id = wl.word_id;", $documentId);

	$result = $wpdb->get_results($sql);
	
	if (isset($result)) {
		//result
		foreach($result as $row) {
			 $words[$row->word] = $row->tf;
		}			
	}
	else{
		$words = null;
	}
	
	return $words;
}

function nbc_get_name_category($documentId){
	global $wpdb;
	
	$sql = $wpdb->prepare("SELECT c.name FROM wp_nbc_document_collections d, wp_nbc_categories c WHERE d.id = %d AND d.category_id = c.id;", $documentId);
	$result = $wpdb->get_row($sql);
	
	if (isset($result)) {
		//result
		$doc = $result->name;
	}
	else{
		$doc = null;
	}
	
	return $doc;
}

?>