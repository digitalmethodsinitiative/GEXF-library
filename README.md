GEXF-library
============

PHP class to help with building GEXF networks.

Example
=======

	// create new graph
	$gexf = new Gexf();
	$gexf->setTitle("Hashtag - user " . $filename);
	$gexf->setEdgeType(GEXF_EDGE_UNDIRECTED);
	$gexf->setCreator("tools.digitalmethods.net");
	
	// fill bi-partite graph
	foreach ($userHashtags as $user => $hashtags) {
		foreach ($hashtags as $hashtag => $frequency) {
		
			// make node 1
			$node1 = new GexfNode($user);
			$node1->addNodeAttribute("type", 'user', $type = "string");
			$gexf->addNode($node1);
	
			// make node 2
			$node2 = new GexfNode($hashtag);
			$node2->addNodeAttribute("type", 'hashtag', $type = "string");
			$gexf->addNode($node2);
		
			// create edge	
			$edge_id = $gexf->addEdge($node1, $node2, $frequency);
		}
	}
	
	// render the file
	$gexf->render();

	// write out the file
	file_put_contents('file.gexf', $gexf->gexfFile);