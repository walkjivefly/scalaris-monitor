<?php

namespace App;

function createMainContent(){
	global $scalarisd, $coinApi, $trafficCIn, $trafficCOut, $newPeersCount;

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
	$content['priceInfo'] = getPriceInfo($coinApi);
	$txoutset = $scalarisd->gettxoutsetinfo();
	$content['issued'] = floor($txoutset['total_amount']);
	if($content['priceInfo']['SCA/USD'] != "N/A"){
		$content['marketCap'] = round($txoutset['total_amount'] * $content['priceInfo']['SCA/USD'], 0);
	}else{$content['marketCap'] = "N/A";}

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

function getPriceInfo($coinApi){

	$result = array('SCA/BTC' => 'N/A', 'BTC/USD' => 'N/A', 'SCA/USD' => 'N/A');
	try {
    	$p1 = $coinApi->getTickerByCoinId("btc-bitcoin")->getPriceUSD();
    	$p2 = $coinApi->getTickerByCoinId("sca-scalaris")->getPriceBTC();
    } catch (\Exception $e) {
		echo '[CoinPaprika] ' . $e->getMessage () . "\n";
		return $result;
	}
	$result['SCA/BTC'] = $p2 * 1E8;
	$result['BTC/USD'] = round($p1, 2);
	$result['SCA/USD'] = round($p2 * $p1, 2);
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
		$content["blocks"][$block["height"]]["fees"] = round($coinbase + $coinstake, 5);
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
	$proposals = $scalarisd->listproposals($content["nextSuperblock"]-42000+1);
	$mnCount = $scalarisd->servicenodecount()["total"];
	$currentBlock = $scalarisd->getblockcount();
	$content["nextDate"] = "Estimated " . date("D j F Y H:iT", time()+($content["nextSuperblock"]-$currentBlock)*60);
	$content["pCutoff"] = "Estimated new proposals deadline: " . date("D j F Y H:iT", time()+($content["nextSuperblock"]-2000-$currentBlock)*60);
	$content["vCutoff"] = "Estimated voting deadline: " . date("D j F Y H:iT", time()+($content["nextSuperblock"]-106-$currentBlock)*60);
	if($currentBlock > $content["nextSuperblock"] - 2000){
		$content['pCutoffColour'] = "red";
		$content["pCutoff"] = "New proposals submission window for this superblock is closed.";
	}elseif($currentBlock > $content["nextSuperblock"] - 2000 * 2){
		$content['pCutoffColour'] = "orange";
	}else{$content['pCutoffColour'] = "green";}
	if($currentBlock > $content["nextSuperblock"] - 106){
		$content['vCutoffColour'] = "red";
		$content['vCutoff'] = "Voting window for this superblock is closed.";
	}elseif($currentBlock > $content["nextSuperblock"] - 2000 - 106){
		$content['vCutoffColour'] = "orange";
	}else{$content['vCutoffColour'] = "green";}
	$maxBudget = 30000;
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
	$proposals = $scalarisd->listproposals(1);
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

	$content = [];
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
