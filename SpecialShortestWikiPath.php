<?php
	
	/*
	 * SpecialShortestWikiPath
	 * 
	 * author		komed3
	 * version		1.0.0
	 * date			2018-08-02
	 * modified		2018-08-03
	 * license		MIT
	 * 
	 */
	
	$GLOBALS['operations'] = 0;
	
	class SpecialShortestWikiPath extends SpecialPage {
		
		private $pages;
		
		protected function getGroupName() { 
			return 'wiki';
		}
		
		public function __construct() {
			parent::__construct('ShortestWikiPath');
		}
		
		# executive function at page calling
		public function execute($args) {
			
			$out = $this->getOutput();
			
			$out->setPageTitle($this->msg('shortestwikipath'));
			
			$out->addHelpLink('https://labs.komed3.de/shortestwikipath/', true);
			
			$out->addHeadItem("ShortestWikiPath Scripts",
				'<link href="https://' . $_SERVER['SERVER_NAME'] . '/extensions/ShortestWikiPath/modules/ShortestWikiPath.css" rel="stylesheet" type="text/css" />'
			);
			
			$out->addWikiMsg('shortestwikipath-summary', wfMessage('shortestwikipath-form-submit'), wfMessage('shortestwikipath-form-backward'));
			
			$formFields = [
				'from' => [
					'type'					=> 'title',
					'namespace'				=> NS_MAIN,
					'placeholder-message'	=> 'shortestwikipath-form-from',
					'required'				=> true
				],
				'to' => [
					'type'					=> 'title',
					'namespace'				=> NS_MAIN,
					'placeholder-message'	=> 'shortestwikipath-form-to',
					'required'				=> true
				],
				'backward' => [
					'type'					=> 'check',
					'label-message'			=> 'shortestwikipath-form-backward',
					'default'				=> false
				]
			];
			
			$htmlForm = HTMLForm::factory('ooui', $formFields, $this->getContext());
			$htmlForm->setMethod('get')
					 ->setWrapperLegendMsg('shortestwikipath-form')
					 ->setSubmitTextMsg('shortestwikipath-form-submit')
					 ->prepareForm()
					 ->displayForm(false);
			
			if(isset($_GET['wpfrom']) &&
			   isset($_GET['wpto'])) {
				
				# measure execution time
				$durTime = microtime(true);
				
				# conn to database
				$dbr = wfGetDB(DB_REPLICA);
				
				# reading all pages
				$respages = $dbr->select(
					'page',
					['page_id', 'page_namespace', 'page_title'],
					'page_namespace LIKE 0'
				)->result;
				
				$this->pages = array();
				$nodeCount = 0;
				
				$graph = new Graph();
				
				foreach($respages as $page) {
					
					$this->pages[$page['page_id']] = $page['page_title'];
					
					$graph->addNode($page['page_id']);
					
					$nodeCount++;
					
				}
				
				# check, if search nodes exists
				if(!in_array(str_replace(' ', '_', $_GET['wpfrom']), $this->pages) &&
				   !in_array(str_replace(' ', '_', $_GET['wpto']), $this->pages)) {
					
					$out->addWikiMsg('shortestwikipath-error-both', $_GET['wpfrom'], $_GET['wpto']);
					
				} else if(!in_array(str_replace(' ', '_', $_GET['wpfrom']), $this->pages)) {
					
					$out->addWikiMsg('shortestwikipath-error-from', $_GET['wpfrom']);
					
				} else if(!in_array(str_replace(' ', '_', $_GET['wpto']), $this->pages)) {
					
					$out->addWikiMsg('shortestwikipath-error-to', $_GET['wpto']);
					
				} else {
					
					$_GET['wpfrom'] = trim($_GET['wpfrom']);
					$_GET['wpto'] = trim($_GET['wpto']);
					
					# reading all pagelinks
					$respagelinks = $dbr->select(
						'pagelinks',
						['*']
					)->result;
					
					$edgeCount = 0;
					
					foreach($respagelinks as $link) {
						
						if(array_key_exists($link['pl_from'], $this->pages) &&
						   in_array($link['pl_title'], $this->pages)) {
							
							$graph->addEdge($link['pl_from'], array_search($link['pl_title'], $this->pages), 1);
							
							$edgeCount++;
							
						}
						
					}
					
					# forward searching
					
					# search for shortest path
					$shortestPath = $graph->dijkstra(array_search(str_replace(' ', '_', $_GET['wpfrom']), $this->pages))
										  ->getShortestPathTo(array_search(str_replace(' ', '_', $_GET['wpto']), $this->pages));
					
					$shortestPathCount = count($shortestPath);
					
					# create table for searching results
					$this->createOutput($out, $shortestPath, $shortestPathCount);
					
					# backward searching
					if(isset($_GET['wpbackward']) &&
					   $_GET['wpbackward'] == 1) {
						
						# swap start and target page
						list($_GET['wpto'], $_GET['wpfrom']) = array($_GET['wpfrom'], $_GET['wpto']);
						
						# search for shortest path
						$bwShortestPath = $graph->dijkstra(array_search(str_replace(' ', '_', $_GET['wpfrom']), $this->pages))
												  ->getShortestPathTo(array_search(str_replace(' ', '_', $_GET['wpto']), $this->pages));
						
						$bwShortestPathCount = count($bwShortestPath);
						
						# create table for searching results
						$this->createOutput($out, $bwShortestPath, $bwShortestPathCount, true);
						
					}
					
					$durTime = microtime(true) - $durTime;
					
					# output execution time
					$out->addWikiMsg('shortestwikipath-duration', number_format($durTime, 1), $GLOBALS['operations'], $nodeCount, $edgeCount);
					
				}
				
			}
			
		}
		
		# create table for searching results
		private function createOutput($out, $shortestPath, $shortestPathCount, $bw = false) {
			
			$out->addHTML('<div class="shortestwikipath-result-wrapper">' .
				'<div class="headline">' . (($bw) ?
						  	wfMessage('shortestwikipath-result-backward') :
						  	wfMessage('shortestwikipath-result')) . '</div>');
			
			if(!isset($shortestPath) ||
			   !is_array($shortestPath) ||
			   $shortestPathCount == 0) {
				
				# no way found
				$out->addWikiMsg('shortestwikipath-no-way', $_GET['wpfrom'], $_GET['wpto']);
				
			} else if($shortestPathCount == 1) {
				
				# self linking
				$out->addWikiMsg('shortestwikipath-self-linking', $_GET['wpfrom'], $_GET['wpto']);
				
			} else {
				
				# output results
				$out->addWikiMsg('shortestwikipath-forward', $_GET['wpfrom'], $_GET['wpto'], $shortestPathCount, ($shortestPathCount - 2), ($shortestPathCount - 1));
				
				$out->addHTML('<table cellspacing="0">');
				
				$i = 1;
				
				foreach($shortestPath as $path) {
					
					# type of the node
					$type = (($i == 1) ? 'start' :
								(($i == $shortestPathCount) ? 'target' :
									'hop'));
					
					# type of the next node
					$ntype = ((($i + 1) == $shortestPathCount) ? 'ntarget' :
								'nhop');
					
					$out->addHTML('<tr class="' . $type . ' ' . $ntype . '">' .
								  	'<td class="node" colspan="2">&nbsp;</td>' .
								  	'<td class="label label-type">' .
								  		wfMessage('shortestwikipath-result-' . $type, $i) .
								  	'</td>' .
								  '</tr>' .
								  '<tr class="' . $type . ' ' . $ntype . '">' .
								  	'<td class="edge">&nbsp;</td>' .
								  	'<td>&nbsp;</td>' .
								  	'<td class="label label-node">' .
								  		Linker::link(Title::makeTitleSafe(NS_MAIN, $this->pages[$path->label]), str_replace('_', ' ', $this->pages[$path->label])) .
								  	'</td>' .
								  '</tr>');
					
					$i++;
					
				}
				
				$out->addHTML('</table>');
				
			}
			
			$out->addHTML('</div>');
			
			return true;
			
		}
		
	}
	
	/*
	 * Gewichtete Kante für Dijkstra
	 * Container
	 * 
	 */
	
	class Edge {
		
		static $destinationNode;
		
		static $cost;
		
		/*
		 * @param Node $destinationNode Ziel der gerichteten Kante.
		 * @param number $cost Kosten (Gewicht)
		 * 
		 */
		 
		function __construct(Node $destinationNode, $cost) {
			
			$this->destinationNode = $destinationNode;
			$this->cost = $cost;
			
		}
		
		/*
		 * @return Node
		 * 
		 */
		
		function getDestinationNode() {
			
			return $this->destinationNode;
			
		}
		
		/*
		 * @return number
		 * 
		 */
		
		function getCost() {
			
			return $this->cost;
			
		}
		
	}
	
	/**
     * Knoten für Dijkstra
     * Container
     * 
     */
    
    class Node {
		
		static $label;
		
		private $outgoingEdges = []; // Ausgehende Kanten auf Nachbarsknoten
		
		/*
		 * @param string $label eindeutige Knoten-ID
		 *
		 */
		
		function __construct($label) {
			
			$this->label = $label;
			
		}
		
		/*
		 * @return string
		 * 
		 */
		
		function getLabel() {
			
			return $this->label;
			
		}
		
		/*
		 * Ausgehende Kante auf Nachbarsknoten.
		 * @param Edge $edge
		 * 
		 */
		
		function addOutgoingEdge(Edge $edge) {
			
			$this->outgoingEdges[] = $edge;
			
		}
		
		/*
		 * @return array
		 * 
		 */
		
		function getOutgoingEdges() {
			
			return $this->outgoingEdges;
			
		}
		
	}
	
	/*
	 * Dijkstra
	 * kürzesten Weg zwischen Start- und Endknoten finden
	 * 
	 */
	
	class Dijkstra {
		
		static $startLabel;
		
		static $items;
		
		/*
		 * @param string $startLabel Eindeutige ID jenes Knotens, zu dem die $items ermittelt wurden.
		 * @param array $items
		 * 
		 */
		
		function __construct($startLabel, array $items) {
			
			$this->startLabel = $startLabel;
			$this->items = $items;
			
		}
		
		/*
		 * @param string $destinationLabel Eindeutige ID jenes Knotens, zu dem der küzeste Pfad gefunden werden soll.
		 * @return null|array Nodes
		 *         null, falls der Zielknoten vom Startknoten aus nicht erreichbar ist.
		 * 
		 */
		
		function getShortestPathTo($label) {
			
			// was passiert wenn label nicht existiert?
			$node = $this->items[$label]['node']; // Zielknoten
			$nodes = [$node];
			
			while($label = $this->items[$label]['predLabel']) {
				
				$node = $this->items[$label]['node'];
				array_unshift($nodes, $node); // Knoten vorne einfügen
				
			}
			
			// Kontrolle, ob Startknoten erreicht
			if($node->getLabel() != $this->startLabel) {
				
				return null;
				
			}
			
			return $nodes;
			
		}
		
	}
	
	/*
	 * Graph für Dijkstra
	 * Eintrittspunkt, Definition und Ausgabe
	 * 
	 */
	
	class Graph {
		
		static $nodes = []; // Liste aller Graphen-Knoten, assoziatives Array, der key ist das label (eindeutige ID) des Knotens
		
		/*
		 * Neuen Knoten anlegen
		 * @param string $label eindeutige ID des Knotens
		 * 
		 */
		
		function addNode($label) {
			
			$this->nodes[$label] = new Node($label);
			
		}
		
		/*
		 * Neue Kante anlegen
		 * @param string $startLabel eindeutige ID des Startknotens
		 * @param string $destinationLabel eindeutige ID des Zielknotens
		 * @param number $cost >= 0 Kosten (Gewicht)
		 * @throws überspringe, wenn Start- oder Zielknoten nicht zuvor mittels addNode definiert wurden.
		 * 
		 */
		
		function addEdge($startLabel, $destinationLabel, $cost) {
			
			if(array_key_exists($startLabel, $this->nodes) &&
			   array_key_exists($destinationLabel, $this->nodes)) {
				
				$this->nodes[$startLabel]->addOutgoingEdge(new Edge($this->nodes[$destinationLabel], $cost));
				
			}
			 
		}
		
		/*
		 * @param string $startLabel Eindeutige ID jenes Knotens, zu dem die Knoten-d-Werte ermittelt werden sollen.
		 * @throws überspringe, wenn der Startknoten nicht zuvor mittels addNode definiert wurden.
		 * 
		 */
		
		function dijkstra($startLabel) {
			
			// Initialisierung:
			
			/* 
			 * Man könnte die Parameter d und pred auch als
			 * Eigenschaften in der Klasse Node aufnehmen.
			 * 
			 * Das Problem ist jedoch, dass jeder
			 * Graph-Algorithmus andere Parameter benötigt.
			 * 
			 * Node müsste also mit jedem neuen Algorithmus
			 * verändert werden (verstößt gegen das
			 * never-touch-a-running-system-Prinzip) und
			 * würde immer größer werden (Gottes-Klasse).
			 * 
			 * Darum gibt es in den Algorithmen statt
			 * dessen einen Warpper um node-Objekte.
			 * 
			 */
			
			$items = [];
			
			foreach($this->nodes as $label => $node) {
				
				// Operations Counter
				$GLOBALS['operations']++;
				//
				
				$items[$label] = [
					'node' => $node,
					'd' => INF,
					'predLabel' => null
				];
				
			}
			
			$labels = array_keys($this->nodes);
			
			// 1. Schritt:
			
			/*if(!array_key_exists($startLabel, $items)) {
				
				throw;
				
			}*/
			
			$items[$startLabel]['d'] = 0;
			
			// alle weiteren Schritte:
			
			while(count($labels) > 0) {
				
				// den Knoten mit dem kleinsten D aus $labels extrahieren:
				$minDItem = null;
				
				foreach($labels as $label) {
					
					// Operations Counter
					$GLOBALS['operations']++;
					//
					
					if($minDItem === null ||
					   $items[$label]['d'] < $minDItem['d']) {
						
						$minDItem = $items[$label];
						
					}
					
				}
				
				$minDItemLabel = $minDItem['node']->getLabel();
				
				$labels = array_diff($labels, [$minDItemLabel]);
				
				// Nachbarsknoten von $minDItem:
				
				foreach($minDItem['node']->getOutgoingEdges() as $edge) {
					
					// Operations Counter
					$GLOBALS['operations']++;
					//
					
					// Nachbarsknoten von $minDItem, die sich noch in $labels befinden:
					$minDNeighbourLabel = $edge->getDestinationNode()->getLabel();
					
					if(in_array($minDNeighbourLabel, $labels)) {
						
						$minDNeighbourItem = &$items[$minDNeighbourLabel];
						$d = $minDItem['d'] + $edge->getCost();
						
						/* 
						 * Knotenkosten + Kantenkosten zum Nachbarsknoten
						 * 
						 * Bei extrem hohen Werten kann d auch INF werden. Auch
						 * in diesem Fall gibt es eine Verbindung zwischen den
						 * Knoten. Da INF < INF jedoch false liefert, muss hier
						 * extra noch auf is_infinite kontrolliert werden.
						 * 
						 */
						
						if(is_infinite($minDNeighbourItem['d']) ||
						   $d < $minDNeighbourItem['d']) {
							
							$minDNeighbourItem['d'] = $d;
							$minDNeighbourItem['predLabel'] = $minDItemLabel;
							
						}
						
					}
					
				}
				
			}
			
			/* 
			 * Macht man mehrere Abfragen mit unterschiedlichen Zielknoten, jedoch immer mit
			 * gleichem Startknoten, muss man diese Methode nicht immer wieder neu aufrufen.
			 * Dann reicht es, von den Knoten einen Snapshot zu machen und diesen einem 
			 * eigenen Objekt zu übergeben:
			 * 
			 */
			
			return new Dijkstra($startLabel, $items);
			
		}
		
	}
	
?>
