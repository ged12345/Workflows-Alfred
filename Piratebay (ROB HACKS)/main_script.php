<?php
	//user settings
	$pirate_url = "http://thepiratebay.org"; //replace here with a proxy if the main address is blocked in your country
	$expiration = 2; //all searches will be kept in cache for 2 hours making every renewed search instant
	$split_symbol = " ➔ "; //this is the string that goes between the category and the rest of the query
	$history_symbol = "(✓) "; //this is the string that will be prefixed to the subtitle of torrent that has been downloaded (magnet or streaming)
	$enable_history = true; //boolean flag to enable or disable the history function.
	$min_query = 3; //this means that at 3 characters and lower, the query won't start. This makes the workflow faster.

	//other vars
	$table_id = "searchResult";
	$category = 0;
	$query = ($argv[1]?$argv[1]:$_POST["query"]);
	require_once('workflows.php');
	$w = new Workflows();
	$cache = $w->cache();
	$pirate_url = preg_replace("/\/$/", "", $pirate_url);
	$expiration = time() - $expiration * 3600;
	$categories = array(
		100 => "Audio",
		101 => "Music",
		102 => "Audio books",
		103 => "Sound clips",
		104 => "FLAC",
		199 => "Other",
		200 => "Video",
		201 => "Movies",
		202 => "Movies DVDR",
		203 => "Music videos",
		204 => "Movie clips",
		205 => "TV shows",
		206 => "Handheld",
		207 => "HD - Movies",
		208 => "HD - TV shows",
		209 => "3D",
		299 => "Other",
		300 => "Applications",
		301 => "Windows",
		302 => "Mac",
		303 => "UNIX",
		304 => "Handheld",
		305 => "IOS (iPad/iPhone)",
		306 => "Android",
		399 => "Other OS",
		400 => "Games",
		401 => "PC",
		402 => "Mac",
		403 => "PSx",
		404 => "XBOX360",
		405 => "Wii",
		406 => "Handheld",
		407 => "IOS (iPad/iPhone)",
		408 => "Android",
		499 => "Other",
		500 => "Porn",
		501 => "Movies",
		502 => "Movies DVDR",
		503 => "Pictures",
		504 => "Games",
		505 => "HD - Movies",
		506 => "Movie clips",
		599 => "Other",
		600 => "Other",
		601 => "E-books",
		602 => "Comics",
		603 => "Pictures",
		604 => "Covers",
		605 => "Physibles",
		699 => "Other"
	);

	//parse query
	$parts = explode($split_symbol, $query);

	if(count($parts)>1){
		//match main category
		$matched_category = false;
		$main_category = 0;
		reset($parts);
		foreach ($categories as $key => $name) {
			if($key%100==0 && strcmp(strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $name)), strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", current($parts)))) === 0){
				$matched_category = true;
				$main_category = $key;
				break;
			}
		}
		if(!$matched_category){
			// <------------------------------------------------------------------ END POINT 0.1: error in main category name
			$w->result( '', '', "The primary category \"".current($parts)."\" is invalid", "Try another category or remove the '$split_symbol' character to search normally", "", 'no', '' );
			echo $w->toxml();
			return;
		}
		$category = $main_category;
	}
	if(count($parts)>2){
		//match sub category
		$matched_category = false;
		$sub_category = 0;
		next($parts);
		foreach ($categories as $key => $name) {
			if(floor($key/100)==$main_category/100 && $key!=$main_category && strpos(strtolower($name), strtolower(current($parts))) === 0){
				$matched_category = true;
				$sub_category = $key;
				break;
			}
		}
		if(!$matched_category){
			// <------------------------------------------------------------------ END POINT 0.2: error in sub category name
			$w->result( '', '', "The subcategory \"".current($parts)."\" is invalid", "Try another category or remove the '$split_symbol' character to search normally", "", 'no', '' );
			echo $w->toxml();
			return;
		}
		$category = $sub_category;
	}

	if(!(strlen(end($parts))>$min_query) && trim(end($parts))!="top"){
		$matched_category = false;
		if(count($parts)==1){ // there is no main category
			if(strlen($query)==0){
				// output main categories (should never be reached)
				$matched_category = true;
				foreach ($categories as $key => $name) {
					if($key%100==0){
						$w->result( $key, $name, $name, "Tab to search for $name only", "", 'no', "$name$split_symbol" );
					}
				}
			} else {
				//match all categories
				foreach ($categories as $key => $name) {
					foreach (preg_split("/[^a-zA-Z0-9]+/", $name) as $particule) {
						if(strpos(strtolower($particule), strtolower($query)) === 0){
							$matched_category = true;
							if($key%100!=0){
								$name = $categories[100*floor($key/100)].$split_symbol.$name;
							}
							$w->result( $key, $name, $name, "Tab to search for $name only", "", 'no', "$name$split_symbol" );
							break;
						}
					}
				}
			}
		} elseif(count($parts)==2){ // there is a main category
			if(strlen(end($parts))==0){
				//output all relevant sub categories
				$matched_category = true;
				$w->result( "top$main_category", "Top ".$categories[$main_category], "Top ".$categories[$main_category], "Tab to browse top ".$categories[$main_category], "", 'no', $categories[$main_category].$split_symbol."top" );
				foreach ($categories as $key => $name) {
					if(floor($key/100)==$main_category/100 && $key!=$main_category){
						$name = $categories[$main_category].$split_symbol.$name;
						$w->result( $key, $name, $name, "Tab to search for $name only", "", 'no', "$name$split_symbol" );
					}
				}
			} else {
				//match relevant sub categories
				// if(strpos("top", strtolower(end($parts))) === 0)
				// 	$w->result( "top$main_category", "Top ".$categories[$main_category], "Top ".$categories[$main_category], "Tab to browse top ".$categories[$main_category], "", 'no', $categories[$main_category].$split_symbol."top" );
				foreach ($categories as $key => $name) {
					if(floor($key/100)==$main_category/100 && $key!=$main_category){
						foreach (preg_split("/[^a-zA-Z0-9]+/", $name) as $particule) {
							if(strpos(strtolower($particule), strtolower(end($parts))) === 0){
								$matched_category = true;
								$name = $categories[$main_category].$split_symbol.$name;
								$w->result( $key, $name, $name, "Tab to search for $name only", "", 'no', "$name$split_symbol" );
								break;
							}
						}
					}
				}
			}
		}
		if(!$matched_category){
			// <------------------------------------------------------------------ END POINT 1: error, query too short
			if(strlen(end($parts))==0)
				$subtitle = "Type a query to get the magic going :)";
			else
				$subtitle = "The query \"".end($parts)."\" is too short ; it requires ".($min_query+1-strlen(end($parts)))." more character".(strlen(end($parts))==$min_query?"":"s").".";
			$w->result( '', '', "...", $subtitle, "", 'no', '' );
			if(strlen(end($parts))==0 || strpos("top", strtolower(end($parts))) === 0)
				$w->result( "top$category", "Top ".$categories[$category].($category%100!=0?" (".$categories[$main_category].")":""), "Top ".$categories[$category].($category%100!=0?" (".$categories[$main_category].")":""), "Tab to browse top ".$categories[$category].($category%100!=0?" (".$categories[$main_category].")":""), "", 'no', $categories[$main_category].$split_symbol.$categories[$category].$split_symbol."top" );
			echo $w->toxml();
			return;
		}
		// <------------------------------------------------------------------ END POINT 2: output categories
		echo $w->toxml();
		return;
	}
	$search = trim(end($parts));



	$cachedPages = glob("$cache/cache/$search/$category/*.db");
	$results = false;
	$data_from = false;
	if (count($cachedPages) > 0 && explode("\.", basename($cachedPages[0]))[0] > $expiration) { //try retreiving from cache
		$data_from = "cache";
		$results = unserialize(file_get_contents($cachedPages[0]));
	} else { //defaults to curl if page not in cache
		$curltime=time();
		do{
			if($search=="top")
				$handle = curl_init("$pirate_url/top/$category");
			else
				$handle = curl_init("$pirate_url/search/".urlencode($search)."*/0/7/$category");
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($handle, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 20);
			curl_setopt($handle, CURLOPT_TIMEOUT, 20);
			curl_setopt($handle, CURLOPT_ENCODING, 'gzip,deflate,sdch');
			$newPage = curl_exec($handle);
			$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		} while ((empty($newPage) || $httpCode != 200) && time()-$curltime<2);
		curl_close($handle);

		if (!empty($newPage) && $httpCode == 200) { // turn page into array
			$data_from = "curl";
			$doc = new DOMDocument('1.0', 'UTF-8');
			libxml_use_internal_errors(true);
			$doc->loadHTML($newPage);
			$results = array();
			$daddyNode = $doc->getElementById($table_id);
			if($daddyNode){
				foreach ($daddyNode->childNodes as $node){
					if(strcmp($node->nodeName, "tr") === 0){
						$items = $node->getElementsByTagName('td');
						array_push($results, [
							"main_type" => $categories[end(explode("/", $items->item(0)->getElementsByTagName('a')->item(0)->getAttribute('href')))],
							"sub_type" => $categories[end(explode("/", $items->item(0)->getElementsByTagName('a')->item(1)->getAttribute('href')))],
							"link" => $items->item(1)->getElementsByTagName('a')->item(0)->getAttribute('href'),
							"id" => explode("/", $items->item(1)->getElementsByTagName('a')->item(0)->getAttribute('href'))[2],
							"title" => $items->item(1)->getElementsByTagName('a')->item(0)->nodeValue,
							"magnet" => $items->item(1)->getElementsByTagName('a')->item(1)->getAttribute('href'),
							"size" => explode(", ",  explode(", Size ", $items->item(1)->getElementsByTagName('font')->item(0)->nodeValue)[1])[0],
							"seed" => $items->item(2)->nodeValue,
							"leech" => $items->item(3)->nodeValue
						]);
					}
				}
			}
		} elseif (count($cachedPages) > 0) { //try retreiving from archive (= cache but w/o time limit)
			$data_from = "archive";
			$results = unserialize(file_get_contents($cachedPages[0]));
		} else {
			if($search=="top")
				$w->result( '', '', "The query for top ".$categories[$category]." couldn't reach piratebay.", "We do not have the top ".$categories[$category]." in cache nor in the archives and piratebay can't be reached...", "", 'no', '' );
			else
				$w->result( '', '', "The query \"$search\" couldn't reach piratebay.", "We do not have \"$search\" in cache nor in the archives and piratebay can't be reached...", "", 'no', '' );
			echo $w->toxml();
			// <------------------------------------------------------------------ END POINT 3: error, piratebay unavailable
			return;
		}
	}

	if(count($results)==0){
		if($search=="top")
			$w->result( '', '', "No top for ".$categories[$category], "There doesn't seem to be a top ".$categories[$category]." on piratebay.".($main_category>0?" Maybe try another category.":""), "", 'no', '' );
		else
			$w->result( '', '', "No result for \"$search\"", "No hits, even though we included an asterisk (*) to your search. ".($main_category>0?"Maybe try another category.":"Check orthography."), "", 'no', '' );
		echo $w->toxml();
		write_out_array();
		// <-------------------------------------------------------------------------- END POINT 4: error, no result
		return;
	}

	//loading history
	$history = array();
	if(file_exists("$cache/history.db") && $enable_history){
		$history = unserialize(file_get_contents("$cache/history.db"));
	}

	foreach ($results as $result){
		$argument = serialize(array(
			"id" => $result["id"],
			"title" => $result["title"],
			"magnet" => $result["magnet"],
			"link" => $pirate_url.$result["link"],
			"search" => "$pirate_url/search/".urlencode($search)."/0/7/$category")
		);

		$checkmark = (in_array($result["id"], $history) && $enable_history)?$history_symbol:"";
		$subtitle = $checkmark.$result["main_type"]." (".$result["sub_type"]."), Size: ".$result["size"].", Seeders: ".$result["seed"].", Leechers: ".$result["leech"].", for \"$search\"".($data_from!="curl"?" (from $data_from)":"");
		$autocomplete = ($main_category>0?($categories[$main_category].$split_symbol.($sub_category>0?$categories[$sub_category].$split_symbol:"")):"").$result["title"];

		$w->result(
		           $result["id"],
		           $argument,
		           $result["title"],
		           $subtitle,
		           "",
		           'yes',
		           $autocomplete
		        );
	}
	// <-------------------------------------------------------------------------- END POINT 5: output results
	echo $w->toxml();

	// write serialized array to file if new
	write_out_array();
	function write_out_array(){
		global $data_from, $cachedPages, $cache, $search, $category, $results;
		if($data_from=="curl"){
			if (count($cachedPages) > 0) unlink($cachedPages[0]);
			elseif (!file_exists("$cache/cache/$search/$category/")) {
				mkdir("$cache/cache/$search/$category/", 0777, true);
			}
			file_put_contents("$cache/cache/$search/$category/" . time() . ".db", serialize($results));
		}
	}

	return;
?>
