<?php
/*
* ===================================================================================================================
* Implementation of K-Nearest Neighbor (K-NN) Algorithm and Term Frequency - Inverse Document Frequency (TF-IDF)
* ===================================================================================================================
*/

require_once ('nbc-database-api.php');

function nbc_tf($words){
	foreach($words as $word){
		if (isset($counterWord[$word])){
			$counterWord[$word]++;
		}
		else{
			$counterWord[$word] = 1;
		}
		
	}
	return $counterWord;
}

function nbc_remove_stopword_from_current_db($words, $stopwords){
	$result = [];
	$result = array_diff($words, $stopwords);
	return $result;
}

function nbc_process_word_from_new_text(array $words){
	$idWordFromDb = nbc_select_all_word();
	$idRootFromDb = nbc_select_all_root();
	$counterWord = [];
	$accessWebApiWord = [];
	
	$stopwords = nbc_get_stopword_from_db();
	$words = nbc_remove_stopword_from_current_db($words, $stopwords);
	
	if(isset($idWordFromDb)){
		$onlyWords = array_keys($idWordFromDb);
		$onlyRootWords = array_keys($idRootFromDb);
		$needToSearch = array_diff($words, $onlyWords);
		$alreadySearchBefore = array_intersect($words, $onlyWords);
		$detailWordsFromDb = nbc_select_all_word_detail();
		$countWordNeedToSearch = count($needToSearch);
		$countWordAlreadySearchBefore = count($alreadySearchBefore);
		
		if($countWordNeedToSearch > 0){
			$counterWord = nbc_tf($needToSearch);
			$base = 'http://kateglo.com/api.php?format=json&phrase=';
			foreach($counterWord as $word=>$counter){
				$link = $base.$word;
				@$jsonString = file_get_contents($link);
				$object = json_decode($jsonString);
				$baseWord = $word;
				
				if (!isset($object)) {
					$accessWebApiWord[$word] = 1;
					for($x=0;$x<$counter;$x++){
						$roots[] = $baseWord;
					}
				} else {
					
					$lexClass = $object->kateglo->lex_class_ref;
					if($lexClass == "promina" || $lexClass == "konjungsi" || 
						$lexClass == "interjeksi" || $lexClass == "numeralia" || 
						$lexClass == "preposisi" || $lexClass == "partikel"){
							$newStopword[] = "('$word')";
					}
					else{
						$accessWebApiWord[$word] = 2;
					
						if(isset($object->kateglo->root[0])){
							$baseWord = $object->kateglo->root[0]->root_phrase;
							
						}
						
						for($x=0;$x<$counter;$x++){
							$roots[] = $baseWord;
						}
						
						
						$wordDetailList[$word] = $baseWord;
					}
					
				}
				
				sleep(4);
			}
			
			if(isset($newStopword)){
				$valueStringStopwords = implode("," , $newStopword);
				$result = nbc_insert("wp_nbc_stopword_lists", "detail", $valueStringStopwords);
				if(!isset($result)) echo "insert stopwords failed";
			}
			
			
			
			foreach($accessWebApiWord as $word=>$wordTypeId){
				$insertNewWords[] = "('$word', $wordTypeId)";
			}
			
			$insertIntoWordsTable = implode("," , $insertNewWords);
			$result = nbc_insert("wp_nbc_words", "name, word_type_id", $insertIntoWordsTable);
			if(!isset($result)) echo "insert words failed </br></br>";
			
			if(isset($roots)){
				$counterRootWordNew = nbc_tf($roots);
				foreach($counterRootWordNew as $word=>$counter){
					if(!isset($idRootFromDb[$word])){
						$insertValueIntoRootsTable[$word] = "('$word')";
						$oldRoots[] = $word;
					}
					else{
						$oldRoots[] = $word;
					}
				}
				
				if(isset($insertValueIntoRootsTable)){
					$countNewRoot = count($insertValueIntoRootsTable);
					if($countNewRoot > 0){
						$insertIntoRootsTable = implode("," , $insertValueIntoRootsTable);
						$result = nbc_insert("wp_nbc_root_words", "word", $insertIntoRootsTable);
						if(!isset($result)) echo "insert root words failed";
						
					}
				}
			}
			
			
			$idWordFromDbUpdated = nbc_select_all_word();
			$idRootFromDbUpdated = nbc_select_all_root();
			
		
			foreach($wordDetailList as $word=>$root){
				$insertDetailWord[] = "($idWordFromDbUpdated[$word], $idRootFromDbUpdated[$root])";
			}
			$insertValueString = implode(",", $insertDetailWord);
			
			$result = nbc_insert("wp_nbc_word_details", "word_id, root_id", $insertValueString);
			if(!isset($result)) echo "insert word details failed </br></br>";
			
			
			
		}
		
		if($countWordAlreadySearchBefore > 0){
			foreach($alreadySearchBefore as $word){
				if(isset($detailWordsFromDb[$word])){
					$oldRoots[] = $detailWordsFromDb[$word];
				}
			}
			$counterRootWord = nbc_tf($oldRoots);
			
		}
	}
	return $counterRootWord;
	
}

function nbc_get_clean_content($data){
	$content = strtolower(preg_replace('/[^A-Za-z\- ]/', "", $data));
	return $content;
}

function nbc_get_words_from_content($content){
	$words = explode(" ", $content);
	$count = count($words);
	for($x=0;$x<$count;$x++){
		if($words[$x]=="") unset($words[$x]);
	}
	return $words;
}

function nbc_sum_tfidf_from_text($N, $words){
	$sum = 0;
	foreach($words as $word=>$tf){
		$Df = nbc_get_df($word);
		if($Df == 0) $tfidf = 0;
		else{
			$tfidf = $tf*log($N/$Df,2);
		}
		
		$sum += $tfidf;
		$weight[$word] = $tfidf;
	}
	
	foreach($weight as $word=>$tfidf){
		$normalizeWeight[$word] = $tfidf/$sum;
	}
	return $normalizeWeight;
}

function nbc_tfidf($N, $documentId){
	$words = nbc_get_all_word($documentId);
	$sum = 0;
	if(isset($words)){
		foreach($words as $word=>$tf){
			$Df = nbc_get_df($word);
			if($Df != 0)
				$tfidf = $tf*log(($N/$Df),2);
			else $tfidf = 0;
			$sum += $tfidf;
			
			$weight[$word] = $tfidf;
		}
		
		foreach($weight as $word=>$tfidf){
			$normalizeWeight[$word] = $tfidf/$sum;
		}
	}
	else $normalizeWeight = null;
	
	return $normalizeWeight;
}


function nbc_distance_knn_process(array $vector1, array $vector2){
	$result = 0;
	$keys = array_unique (array_merge (array_keys($vector1), array_keys($vector2)));
	
	foreach($keys as $key){
		if (isset($vector2[$key]) && isset($vector1[$key])){
			$result += pow($vector1[$key] - $vector2[$key],2);
		}
		elseif(isset($vector1[$key])) $result += pow($vector1[$key],2);
		else $result += pow($vector2[$key],2);
	}
	$result = sqrt($result);
	return $result;
}

function nbc_dot_product(array $vec1, array $vec2) {
	$result = 0;
	
	foreach (array_keys($vec1) as $key1) {
		foreach (array_keys($vec2) as $key2) {
			if ($key1 === $key2) $result += $vec1[$key1] * $vec2[$key2];
		}
	}
	
	return $result;
}

function nbc_abs_vector(array $vec) {
	$result = 0;
	
	foreach (array_values($vec) as $value) {
	  $result += $value * $value;
	}
	$sqrt = sqrt($result);
	
	return $sqrt;
}

function nbc_cosine_similarity(array $vec1, array $vec2) {
	return nbc_dot_product($vec1, $vec2) / (nbc_abs_vector($vec1)*nbc_abs_vector($vec2));
}

/*
* ===================================================================================================================
* Main function
* ===================================================================================================================
*/
function nbc_main_define_category_from_nbc_plugin($textContent){
	//remove special character from text content
	$textContent = nbc_get_clean_content($textContent);
	//split text content to words
	$words = nbc_get_words_from_content($textContent);
	//get number of document collections
	$numberOfDocuments = nbc_get_N_from_db();
	//proccess words from text content to get tf-idf for each words
	$countTfFromText = nbc_process_word_from_new_text($words);

	//define vector 1 as vector space model from text content
	$vector1 = nbc_sum_tfidf_from_text($numberOfDocuments, $countTfFromText);

	//similarity checks
	for($x=1; $x<=$numberOfDocuments; $x++){
		$x = $x+2;
		//define vector 2 as vector space model from document collections
		$vector2 = nbc_tfidf($numberOfDocuments, $x);
		if(isset($vector2)){
			$vec2[$x] = $vector2;
			//cosine similarity check
			$similarity[$x] = nbc_cosine_similarity($vector1, $vector2);
			
		}
	}
	//sort similarity descending
	arsort($similarity);
	
	//return 5 first data based on similarity
	$result = array_slice($similarity, 0, 5, true);
	
	foreach($result as $doc=>$similarity)
	{	
		if(isset($vec2[$doc])){
			//check euclidean distance
			$distance[$doc] = nbc_distance_knn_process($vector1, $vec2[$doc]);
		}
	}
	
	//sort distance ascending
	asort($distance);

	foreach($distance as $doc=>$value){
		$tempDistance[] = $doc;
	}

	//if first and second distance is same
	if($tempDistance[0] === $tempDistance[1]){
		//count frequency of category
		foreach($distance as $doc=>$distance){
			$catName = nbc_get_name_category($doc);
			
			if (isset($categoryCounterName[$catName])){
				$categoryCounterName[$catName]++;
			}
			else{
				$categoryCounterName[$catName] = 1;
			}
		}
		//sort frequency category descending
		arsort($categoryCounterName);
		$first = 0;
		//get the first based on frequency of category
		foreach($categoryCounterName as $categoryCountName=>$counter){
			if($first == 0){
				$categoryName = $categoryCountName;
				$first++;
			}
			else break;
		}
	}
	else{
		//all distance is unique
		$first = 0;
		foreach($distance as $doc=>$distance){
			//get the first document
			if($first == 0) {
				$documentChoose = $doc;
				$first++;
			}
			else break;
		}
		//get category name from database
		$categoryName = nbc_get_name_category($documentChoose);
	}
	return $categoryName;
}

?>