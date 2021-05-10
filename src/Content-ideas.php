<?php

namespace App;

function createMainContent(){
	global $scalarisd, $trafficCIn, $trafficCOut, $newPeersCount;

	date_default_timezone_set('UTC');

	$peers = getPeerData();
	$peerCount = count($peers);

	$content = [];
	$nodecounts = $scalarisd->servicenodecount();
	$content['totalNodes'] = $nodecounts["total"];
	$content['onlineNodes'] = $nodecounts["online"];

	$content['nextSuperblock'] = $scalarisd->nextsuperblock();
	$content['node'] = new Node();
	if(Config::PEERS_GEO){
		$content['map'] = createMapJs($peerCount);
	}
	$content['geo'] = Config::PEERS_GEO;
	$content['nPeers'] = $newPeersCount;
	$content['chartData'] = getTopClients($peers);

	// Current peers traffic
	$content['trafcin'] = round($trafficCIn/1000, 2);
	$content['trafcout'] = round($trafficCOut/1000, 2);

	// Current price info
	$content['priceInfo'] = getPriceInfo();
	$txoutset = $scalarisd->gettxoutsetinfo();
	$content['issued'] = floor($txoutset['total_amount']);
	$content['marketCap'] = round($txoutset['total_amount'] * $content['priceInfo']['SCA/USD'], 0);

	// aBLOCK info
	// Total Token Supply at a contract address:
	// API call to https://api.etherscan.io/api?module=stats&action=tokensupply&contractaddress=YourContractAddress&apikey=YourApiKeyToken;
	// eg: for aBLOCK total supply: https://etherscan.io/token/0xe692c8d72bd4ac7764090d54842a305546dd1de5
	// {"status":"1","message":"OK","result":"2820748083286"}
	// with 8DPs, total supply=28207.48083286 aBLOCK
	// eg: aBLOCK/BUSD-T total supply result:
	// {"status":"1","message":"OK","result":"34380897291898234538597"}
	// with 18DPs, total Cake-LP token supply=34380.897291898234538597
	// For BNB price: https://api.bscscan.com/api?module=stats&action=bnbprice&apikey=YourApiKeyToken
	// {"status":"1","message":"OK","result":{"ethbtc":"0.004776","ethbtc_timestamp":"1616434004","ethusd":"271.22","ethusd_timestamp":"1616434028"}}
    // For BUSD-T in the PancakeSwap LP: https://api.bscscan.com/api?module=account&action=tokenbalance&contractaddress=0x55d398326f99059ff775485246999027b3197955&address=0x613aef33ddb3363b49c861044dfa0eb0453e7aa2&tag=latest&apikey=YourApiKeyToken
	// {"status":"1","message":"OK","result":"15638364252616519034870"}
	// For aBLOCK in the PancakeSwap LP: https://api.bscscan.com/api?module=account&action=tokenbalance&contractaddress=0x4b04fd7060ee7e30d5a2b369ee542f9ad8ada571&address=0x613aef33ddb3363b49c861044dfa0eb0453e7aa2&tag=latest&apikey=YourApiKeyToken
	// {"status":"1","message":"OK","result":"94590561041016418051265"}

	// create curl resource
	$ch = curl_init();

	//return the transfer as a string
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	// get aBLOCK total supply
	curl_setopt($ch, CURLOPT_URL, "https://api.etherscan.io/api?module=stats&action=tokensupply&contractaddress=0xe692c8d72bd4ac7764090d54842a305546dd1de5&apikey=" . Config::ApiKey);
	$decoded = json_decode(curl_exec($ch),TRUE);
	$content['aBLOCKtotal'] = round($decoded['result']/100000000,2);

	// get ETH/aBLOCK LP total supply
	https://info.uniswap.org/pair/0x9c55c20092097e933f3a46220bcbc5f66c73857b
	curl_setopt($ch, CURLOPT_URL, "https://api.etherscan.com/api?module=stats&action=tokensupply&contractaddress=0x9c55c20092097e933f3a46220bcbc5f66c73857b&apikey=" . Config::ApiKey);
	$decoded = json_decode(curl_exec($ch),TRUE);
	$content['PancakeLPtotal'] = round($decoded['result']/1000000000000000000,2);
    // external call to scrape the $ value of the LP contract
	exec("python3 scrape.py", $out, $rc);
	$content["PancakeDollars"] = $out[0];
	$content["aBLOCKdollars"] = $out[1];

	// get USDT/aBLOCK LP total supply
	https://info.uniswap.org/pair/0xf3fd0bccf572c68ae2baeb87d950a87f0104e7e4
	curl_setopt($ch, CURLOPT_URL, "https://api.etherscan.com/api?module=account&action=tokenbalance&contractaddress=0xf3fd0bccf572c68ae2baeb87d950a87f0104e7e4&address=0x613aef33ddb3363b49c861044dfa0eb0453e7aa2&tag=latest&apikey=" . Config::ApiKey);
	$decoded = json_decode(curl_exec($ch),TRUE);
	$content['PancakeSwapaBLOCK'] = round($decoded['result']/1000000000000000000,2);

	// get BUSD-T in PancakeSwap LP
	curl_setopt($ch, CURLOPT_URL, "https://api.bscscan.com/api?module=account&action=tokenbalance&contractaddress=0x55d398326f99059ff775485246999027b3197955&address=0x613aef33ddb3363b49c861044dfa0eb0453e7aa2&tag=latest&apikey" . Config::ApiKey);
	$decoded = json_decode(curl_exec($ch),TRUE);
	$content['PancakeSwapBUSDT'] = round($decoded['result']/1000000000000000000,2);

	// close curl resource to free up system resources
	curl_close($ch);     

	// Open orders count
	$openorders = $scalarisd->dxGetOrders();
	$content['openOrders'] = count($openorders);

	// Completed orders
	$completedorders = $scalarisd->dxGetTradingData(1440);
	$content['recentOrders'] = count($completedorders);

	return $content;
	
}

function createPeerContent(){
	global $trafficC, $trafficCIn, $trafficCOut, $scalarisd, $newPeersCount;

	$peers = getPeerData();
	$netinfo = $scalarisd->getnettotals();

	$content = getMostPop($peers);
	$content['peers'] = $peers;
	$content['tPeers'] = count($peers);
	$content['nPeers'] = $newPeersCount;
	$content['segWitP'] = round($content['segWitC']/$content['tPeers'],2)*100;
	$content['cTraf'] = round($trafficC/1000,2);
	$content['trafcin'] = round($trafficCIn/1000,2);
	$content['trafcout'] = round($trafficCOut/1000,2);
	$content['tTraf'] = ($netinfo["totalbytesrecv"] + $netinfo["totalbytessent"])/1000000;
	$content['cTrafP'] = round($content['cTraf']/$content['tTraf'],2)*100;
	$content['geo'] = Config::PEERS_GEO;

	return $content;
}

function getPriceInfo(){
	date_default_timezone_set ('UTC');

	$bittrex = new \ccxt\bittrex (array (
    	'verbose' => false,
    	'timeout' => 30000,
    	'enablerateLimit' => true,
	));

	$result = array('BLOCK/BTC' => 'N/A', 'BTC/USD' => 'N/A', 'BLOCK/USD' => 'N/A');
	try {
    	$p1 = $bittrex->fetch_ticker("BLOCK/BTC")['last'];
    	$p2 = $bittrex->fetch_ticker("BTC/USD")['last'];
    } catch (\ccxt\NetworkError $e) {
		echo '[Network Error] ' . $e->getMessage () . "\n";
		return $result;
	} catch (\ccxt\ExchangeError $e) {
    	echo '[Exchange Error] ' . $e->getMessage () . "\n";
		return $result;
	} catch (Exception $e) {
    	echo '[Error] ' . $e->getMessage () . "\n";
		return $result;
	}
	$result['BLOCK/BTC'] = $p1 * 1E8;
	$result['BTC/USD'] = round($p2, 2);
	$result['BLOCK/USD'] = round($p1 * $p2, 2);
	return $result;
}

function createBlocksContent(){
	global $scalarisd;

	$content = [];
	$content["totalTx"] = 0;
	$content["totalFees"] = 0;
	$content["totalSize"] = 0;
	$content["segwitCount"] = 0;
	$blocktime = 60;

	$blockHash = $scalarisd->getbestblockhash();

	for($i = 0; $i < Config::DISPLAY_BLOCKS; $i++){
		$block = $scalarisd->getblock($blockHash);
		if($i==0){ 
			$content["latest"] = $block["height"];
		}
		$content["blocks"][$block["height"]]["hash"] = $block["hash"];
		$content["blocks"][$block["height"]]["size"] = round($block["size"]/1000,2);
		$content["totalSize"] += $block["size"];
		$content["blocks"][$block["height"]]["versionhex"] = "N/A";
		$content["blocks"][$block["height"]]["voting"] = "N/A";
		$content["blocks"][$block["height"]]["time"] = getDateTime($block["time"]);
		$content["blocks"][$block["height"]]["timeago"] = round((time() - $block["time"])/60);
		$content["blocks"][$block["height"]]["coinbasetx"] = $block["tx"][0];
		$content["blocks"][$block["height"]]["coinstaketx"] = $block["tx"][1];
		$coinbaseTx = $scalarisd->getrawtransaction($block["tx"][0], 1);
		$coinstakeTx = $scalarisd->getrawtransaction($block["tx"][1], 1);
		$coinbase = $coinbaseTx["vout"][1]["value"];
		$coinstake = $coinstakeTx["vout"][0]["value"];
		// $superblock = $block["height"] % $sbinterval == 0;
		// if($superblock){
		// 	$content["blocks"][$block["height"]]["fees"] = 0;
		// }else{
			$content["blocks"][$block["height"]]["fees"] = round($coinbase + $coinstake, 5);
		// }
		$content["blocks"][$block["height"]]["fees"] = $coinbase;
		$content["totalFees"] += $content["blocks"][$block["height"]]["fees"];
		$content["blocks"][$block["height"]]["txcount"] = count($block["tx"]);
		$content["totalTx"] += $content["blocks"][$block["height"]]["txcount"];
		$blockHash = $block["previousblockhash"];
	}
	$content["avgTxSize"] = round(($content["totalSize"]/($content["totalTx"]))/1000,2);
	$content["avgSize"] = round($content["totalSize"]/(Config::DISPLAY_BLOCKS*1000),2);
	$content["totalSize"] = round($content["totalSize"]/1000000,2);
	$content["avgFee"] = round($content["totalFees"]/Config::DISPLAY_BLOCKS,2);
	$content["totalFees"] = round($content["totalFees"],2);
	$content["numberOfBlocks"] = Config::DISPLAY_BLOCKS;
	$content["timeframe"] = round(end($content["blocks"])["timeago"]/$blocktime,0);

	return $content;
}

function createForksContent(){
	global $scalarisd;

	$content["recentForks"] = 0;	// Count forks in last 24h

	$forks = $scalarisd->getchaintips();
	$i = 0;
	$lastTime = 0;

	foreach($forks as $fork){
		if($i == Config::DISPLAY_FORKS){
			break;
		}

		$content["blocks"][$i]["height"] = $fork["height"];
		$content["blocks"][$i]["hash"] = $fork["hash"];
		$content["blocks"][$i]["forklength"] = $fork["branchlen"];
		$content["blocks"][$i]["status"] = $fork["status"];

		if($fork["status"] != "headers-only" AND $fork["status"] != "unknown"){
			$block = $scalarisd->getblock($fork["hash"]);
			$content["blocks"][$i]["size"] = round($block["size"]/1000,2);
			//$content["blocks"][$i]["versionhex"] = $block["versionHex"];
			//$content["blocks"][$i]["voting"] = getVoting($block["versionHex"]);
			$content["blocks"][$i]["time"] = getDateTime($block["time"]);
			$lastTime = $block["time"];
			$content["blocks"][$i]["timeago"] = round((time() - $block["time"])/3600);
			$content["blocks"][$i]["txcount"] = count($block["tx"]);

			if($content["blocks"][$i]["timeago"] <= 24){
				$content["recentForks"]++;
			}
		}
		$i++;
	}

	$content["timeframe"] = round((time()-$lastTime)/3600);
	$content["forkCount"] = Config::DISPLAY_FORKS - 1;	// Don't count most recent block as a fork
	$content["recentForks"]--;	// Don't count most recent block as a fork

	return $content;
}

function createMempoolContent(){
	global $scalarisd;

	$content['txs'] = $scalarisd->getrawmempool(TRUE);
	$content['txs'] = array_slice($content['txs'], 0, CONFIG::DISPLAY_TXS);
	$content['node'] = new Node();

	return $content;
}

function createNodesContent(){
	global $scalarisd, $newNodesCount;

	$nodes = getNodesData();
	$counts = $scalarisd->servicenodecount();

	$content['nodes'] = $nodes;
	$content['totalNodes'] = $counts["total"];
	$content['onlineNodes'] = $counts["online"];
	$content['nNodes'] = $newNodesCount;
	$content['geo'] = Config::NODES_GEO;

	return $content;
}

function createGovernanceContent(){
	global $scalarisd;
	$content["nextSuperblock"] = $scalarisd->nextsuperblock();
	$proposals = $scalarisd->listproposals($content["nextSuperblock"]-43200+1);
	$mnCount = $scalarisd->servicenodecount()["total"];
	$currentBlock = $scalarisd->getblockcount();
	$content["nextDate"] = "Estimated " . date("D j F Y H:iT", time()+($content["nextSuperblock"]-$currentBlock)*60);
	$content["pCutoff"] = "Estimated new proposals deadline: " . date("D j F Y H:iT", time()+($content["nextSuperblock"]-2880-$currentBlock)*60);
	$content["vCutoff"] = "Estimated voting deadline: " . date("D j F Y H:iT", time()+($content["nextSuperblock"]-60-$currentBlock)*60);
	if($currentBlock > $content["nextSuperblock"] - 1440 * 2){
		$content['pCutoffColour'] = "red";
		$content["pCutoff"] = "New proposals submission window for this superblock is closed.";
	}elseif($currentBlock > $content["nextSuperblock"] - 1440 * 4){
		$content['pCutoffColour'] = "orange";
	}else{$content['pCutoffColour'] = "green";}
	if($currentBlock > $content["nextSuperblock"] - 60){
		$content['vCutoffColour'] = "red";
		$content['vCutoff'] = "Voting window for this superblock is closed.";
	}elseif($currentBlock > $content["nextSuperblock"] - 1440 * 2 - 60){
		$content['vCutoffColour'] = "orange";
	}else{$content['vCutoffColour'] = "green";}
	$maxBudget = 40000;
	$content["budgetRequested"] = 0;
	$content["budgetPassing"] = 0;
	$content["budgetRemaining"] = $maxBudget;
	$content["pCount"] = 0;
	$content["passingCount"] = 0;
	$i = 0;
    foreach($proposals as $proposal){
		$blockStart = $proposal["superblock"];
		$content["proposal"][$i]["hash"] = $proposal["hash"];
		$content["proposal"][$i]["name"] = $proposal["name"];
		$content["proposal"][$i]["superblock"] = $proposal["superblock"];
		$content["proposal"][$i]["amount"] = $proposal["amount"];
		$content["proposal"][$i]["address"] = $proposal["address"];
		$content["proposal"][$i]["URL"] = $proposal["url"];
		$content["proposal"][$i]["description"] = $proposal["description"];
		$content["proposal"][$i]["yeas"] = $proposal["votes_yes"];
		$content["proposal"][$i]["nays"] = $proposal["votes_no"];
		$content["proposal"][$i]["abstains"] = $proposal["votes_abstain"];
		$content["proposal"][$i]["status"] = $proposal["status"];
		$content["budgetRequested"] += $proposal["amount"];
		$content["proposal"][$i]["passingMargin"] = ($proposal["votes_yes"]-$proposal["votes_no"]-$proposal["votes_abstain"]);
		if($content["proposal"][$i]["passingMargin"] > $mnCount / 10) {
			$content["proposal"][$i]["passing"] = "Yes";
			$content["budgetPassing"] += $proposal["amount"];
			$content["passingCount"] += 1;
		}else{
			$content["proposal"][$i]["passing"] = "No";
		}
		$i++;			
	}
	$content["pCount"] = $i;
	$content["budgetRemaining"] -= $content["budgetRequested"];
	if($content["budgetRequested"] > $maxBudget){
		$content["reqColour"] = "red";
	}elseif($content["budgetRequested"] > $maxBudget * 0.9){
		$content["reqColour"] = "orange";
	}else{
		$content["reqColour"] = "green";
	}
	if($content["budgetPassing"] > $maxBudget){
		$content["passingColour"] = "red";
	}elseif($content["budgetPassing"] > $maxBudget * 0.9){
		$content["passingColour"] = "orange";
	}else{
		$content["passingColour"] = "green";
	}
	if($content["budgetRemaining"] < 0){
		$content["remainingColour"] = "red";
	}elseif($content["budgetRemaining"] < $maxBudget * 0.1){
		$content["remainingColour"] = "orange";
	}else{
		$content["remainingColour"] = "green";
	}
	return $content;
}

function createOldGovernanceContent(){
	global $scalarisd;
	$content["nextSuperblock"] = $scalarisd->nextsuperblock();
	$proposals = $scalarisd->listproposals(1339200);
	$currentBlock = $scalarisd->getblockcount();
	$content["nextDate"] = "Estimated " . date("D j F Y H:iT", time()+($content["nextSuperblock"]-$currentBlock)*60);
	$content["budgetRequested"] = 0;
	$content["budgetPassing"] = 0;
	$content["pCount"] = 0;
	$content["passingCount"] = 0;
	$i = 0;
    foreach($proposals as $proposal){
		$superblock = $proposal["superblock"];
		if($superblock < $currentBlock){
			$content["proposal"][$i]["hash"] = $proposal["hash"];
			$content["proposal"][$i]["name"] = $proposal["name"];
			$content["proposal"][$i]["superblock"] = $proposal["superblock"];
			$content["proposal"][$i]["amount"] = $proposal["amount"];
			$content["proposal"][$i]["address"] = $proposal["address"];
			$content["proposal"][$i]["URL"] = $proposal["url"];
			$content["proposal"][$i]["description"] = $proposal["description"];
			$content["proposal"][$i]["yeas"] = $proposal["votes_yes"];
			$content["proposal"][$i]["nays"] = $proposal["votes_no"];
			$content["proposal"][$i]["abstains"] = $proposal["votes_abstain"];
			$content["proposal"][$i]["status"] = $proposal["status"];
			$content["budgetRequested"] += $proposal["amount"];
			$content["proposal"][$i]["passingMargin"] = ($proposal["votes_yes"]-$proposal["votes_no"]-$proposal["votes_abstain"]);
			if($proposal["status"] == "passed") {
				$content["budgetPassing"] += $proposal["amount"];
				$content["passingCount"] += 1;
			}
			$i++;			
		}
	}
	$content["pCount"] = $i;
	return $content;
}

function createOpenOrdersContent(){
	global $scalarisd;
	// Each order looks like
	//{
	//	"id": "19dce16f9c5058334c5897ac781eea73f9764d92ded55a685bf332cd852f84bb",
	//	"maker": "BLOCK",
	//	"maker_size": "136.576143",
	//	"taker": "LTC",
	//	"taker_size": "2.643747",
	//	"updated_at": "2021-03-29T20:04:08.341Z",
	//	"created_at": "2021-03-29T20:04:08.207Z",
	//	"order_type": "exact",
	//	"partial_minimum": "0.000000",
	//	"partial_orig_maker_size": "136.576143",
	//	"partial_orig_taker_size": "2.643747",
	//	"partial_repost": false,
	//	"partial_parent_id": "",
	//	"status": "open"
	//}
	
	$content = [];
	$content["openOrderCount"] = 0;
	$content["rolledBackCount"] = 0;
	$content["otherCount"] = 0;
	$content["totalCount"] = 0;

	$openorders = $scalarisd->dxGetOrders();
	$i = 0;
	foreach($openorders as $order){
		$content["order"][$i]["id"] = $order["id"];
		$content["order"][$i]["maker"] = $order["maker"];
		$content["order"][$i]["makerSize"] = $order["maker_size"];
		$content["order"][$i]["taker"] = $order["taker"];
		$content["order"][$i]["takerSize"] = $order["taker_size"];
		$content["order"][$i]["updatedAt"] = $order["updated_at"];
		$content["order"][$i]["createdAt"] = $order["created_at"];
		$content["order"][$i]["orderType"] = $order["order_type"];
		$content["order"][$i]["partialMinimum"] = $order["partial_minimum"];
		$content["order"][$i]["partialOMS"] = $order["partial_orig_maker_size"];
		$content["order"][$i]["partialOTS"] = $order["partial_orig_taker_size"];
		$content["order"][$i]["partialRepost"] = $order["partial_repost"];
		$content["order"][$i]["partialParentId"] = $order["partial_parent_id"];
		$content["order"][$i]["status"] = $order["status"];
		if($order["status"] == "open"){
			$content["openOrderCount"]++;
		}elseif($order["status"] == "rolled_back"){
			$content["rolledBackCount"]++;
		}else{
			$content["otherCount"]++;
		}
		$i++;
	}
	$content["totalCount"] = $i;
	$content['scalarisd'] = $scalarisd;
    return $content;
}

function createPastOrdersContent($days = 30){
	global $scalarisd;
	// Get last order block from stats
	// Calculate how many blocks since then
	// Fetch trading data for that many blocks
	// Insert new trading data (if any) to past-orders table
	// Update last order block in stats
	// Fetch required rows from past-orders table

	$content = [];
	//if($days == ""){
	//	$days = 30;
	//}
	$content["days"] = $days;
	$content["pastOrderCount"] = 0;
	$blocks = $days * 1440;
	$content["blocks"] = $blocks;

	$pastorders = $scalarisd->dxGetTradingData($blocks);
	$i = 0;
	foreach($pastorders as $order){
		$content["order"][$i]["time"] = getDateTime($order["timestamp"]);
		$content["order"][$i]["txid"] = $order["fee_txid"];
		$content["order"][$i]["snodekey"] = $order["nodepubkey"];
		$content["order"][$i]["xid"] = $order["id"];
		$content["order"][$i]["taker"] = $order["taker"];
		$content["order"][$i]["takerAmount"] = $order["taker_size"];
		$content["order"][$i]["maker"] = $order["maker"];
		$content["order"][$i]["makerAmount"] = $order["maker_size"];
		$i++;
	}
	$content["pastOrderCount"] = $i;
	return $content;
}

?>
